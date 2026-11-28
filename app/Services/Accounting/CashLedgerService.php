<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\CashAccount;
use App\Models\CashEntry;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\AppTimezone;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Satu-satunya penulis Buku Kas Umum (ADR-030). Entri tidak pernah diubah atau dihapus;
 * koreksi dibuat sebagai entri pembalik. Pengeluaran wajib punya sumber dana yang diizinkan
 * Alokasi Dana dan saldonya cukup; kunci baris pos sumber dana menjaga dua transaksi bersamaan.
 */
final class CashLedgerService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{unit_id: int, entry_date: string, cash_account_id: int, amount: int, cash_box: string, funding_account_id?: int|null, reference_no?: string|null, counterparty?: string|null, description: string, source_type?: string|null, source_id?: int|null}  $data
     */
    public function record(array $data, User $actor): CashEntry
    {
        if ($data['amount'] <= 0) {
            throw ValidationException::withMessages(['amount' => 'Nominal harus lebih dari nol.']);
        }
        if (! array_key_exists($data['cash_box'], CashEntry::BOXES)) {
            throw ValidationException::withMessages(['cash_box' => 'Pilih kas tunai atau rekening bank.']);
        }
        $date = CarbonImmutable::parse($data['entry_date'], AppTimezone::name())->startOfDay();
        if ($date->greaterThan(CarbonImmutable::now(AppTimezone::name())->startOfDay())) {
            throw ValidationException::withMessages(['entry_date' => 'Tanggal transaksi tidak boleh di masa depan.']);
        }

        return DB::transaction(function () use ($data, $date, $actor): CashEntry {
            $account = CashAccount::query()->whereKey($data['cash_account_id'])->lockForUpdate()->first();
            if (! $account instanceof CashAccount || $account->unit_id !== $data['unit_id'] || ! $account->is_active) {
                throw ValidationException::withMessages(['cash_account_id' => 'Pos akun tidak ditemukan, nonaktif, atau milik unit lain.']);
            }

            if ($account->direction === CashAccount::IN) {
                $funding = $account;
            } else {
                $funding = $this->lockFunding($account, (int) ($data['funding_account_id'] ?? 0));
                $available = $this->fundingBalance($funding->id);
                if ($data['amount'] > $available) {
                    throw ValidationException::withMessages([
                        'amount' => "Saldo sumber dana {$funding->name} tinggal ".Money::format($available).'.',
                    ]);
                }
            }

            $entry = CashEntry::query()->create([
                'unit_id' => $account->unit_id,
                'entry_date' => $date->toDateString(),
                'cash_account_id' => $account->id,
                'direction' => $account->direction,
                'amount' => $data['amount'],
                'cash_box' => $data['cash_box'],
                'funding_account_id' => $funding->id,
                'reference_no' => self::clean($data['reference_no'] ?? null),
                'counterparty' => self::clean($data['counterparty'] ?? null),
                'description' => trim($data['description']),
                'source_type' => $data['source_type'] ?? null,
                'source_id' => $data['source_id'] ?? null,
                'recorded_by' => $actor->id,
                'created_at' => now(),
            ]);

            $this->audit->record('cash_entry.recorded', $entry, null, [
                'direction' => $entry->direction, 'amount' => $entry->amount, 'account' => $account->name, 'funding' => $funding->name,
            ], $actor->id);

            return $entry;
        });
    }

    /**
     * Membatalkan entri dengan entri pembalik bertanggal hari ini. Pemasukan yang dananya sudah
     * dibelanjakan tidak bisa dibatalkan (saldo sumber dana tidak boleh minus).
     */
    public function reverse(CashEntry $entry, string $reason, User $actor): CashEntry
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Alasan koreksi wajib diisi.']);
        }

        return DB::transaction(function () use ($entry, $reason, $actor): CashEntry {
            $locked = CashEntry::query()->whereKey($entry->id)->lockForUpdate()->firstOrFail();
            if ($locked->reverses_entry_id !== null) {
                throw ValidationException::withMessages(['reason' => 'Entri koreksi tidak bisa dikoreksi lagi.']);
            }
            if (CashEntry::query()->where('reverses_entry_id', $locked->id)->exists()) {
                throw ValidationException::withMessages(['reason' => 'Entri ini sudah dikoreksi sebelumnya.']);
            }

            CashAccount::query()->whereKey($locked->funding_account_id)->lockForUpdate()->first();
            if ($locked->direction === CashAccount::IN) {
                $available = $this->fundingBalance($locked->funding_account_id);
                if ($locked->amount > $available) {
                    throw ValidationException::withMessages([
                        'reason' => 'Dana dari pemasukan ini sudah terpakai. Sisa sumber dana '.Money::format($available).'.',
                    ]);
                }
            }

            $reversal = CashEntry::query()->create([
                'unit_id' => $locked->unit_id,
                'entry_date' => CarbonImmutable::now(AppTimezone::name())->toDateString(),
                'cash_account_id' => $locked->cash_account_id,
                'direction' => $locked->direction === CashAccount::IN ? CashAccount::OUT : CashAccount::IN,
                'amount' => $locked->amount,
                'cash_box' => $locked->cash_box,
                'funding_account_id' => $locked->funding_account_id,
                'reference_no' => $locked->reference_no,
                'counterparty' => $locked->counterparty,
                'description' => mb_substr('Koreksi: '.$locked->description, 0, 255),
                'source_type' => $locked->source_type,
                'source_id' => $locked->source_id,
                'reverses_entry_id' => $locked->id,
                'reversal_reason' => mb_substr($reason, 0, 255),
                'recorded_by' => $actor->id,
                'created_at' => now(),
            ]);

            $this->audit->record('cash_entry.reversed', $locked, null, ['reversal_id' => $reversal->id, 'reason' => $reason], $actor->id);

            return $reversal;
        });
    }

    /** Saldo satu sumber dana: masuk − keluar yang dibiayainya (termasuk koreksi). */
    public function fundingBalance(int $fundingAccountId): int
    {
        $row = CashEntry::query()
            ->where('funding_account_id', $fundingAccountId)
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE 0 END), 0) AS total_in")
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'out' THEN amount ELSE 0 END), 0) AS total_out")
            ->first();

        return (int) ($row?->getAttribute('total_in') ?? 0) - (int) ($row?->getAttribute('total_out') ?? 0);
    }

    /**
     * Saldo setiap pos pendapatan (sumber dana) di satu unit.
     *
     * @return array<int, int> funding_account_id => saldo
     */
    public function fundingBalances(int $unitId): array
    {
        return CashEntry::query()
            ->where('unit_id', $unitId)
            ->groupBy('funding_account_id')
            ->selectRaw("funding_account_id, SUM(CASE WHEN direction = 'in' THEN amount ELSE -amount END) AS balance")
            ->pluck('balance', 'funding_account_id')
            ->map(fn (mixed $v): int => (int) $v)
            ->all();
    }

    /** Pos akun sistem (mis. Pencairan BOS) dibuat sekali per unit dan dipakai ulang. */
    public function systemAccount(int $unitId, string $key, string $name, string $direction): CashAccount
    {
        return CashAccount::query()->firstOrCreate(
            ['unit_id' => $unitId, 'system_key' => $key],
            ['name' => $name, 'direction' => $direction, 'is_active' => true],
        );
    }

    private function lockFunding(CashAccount $expense, int $fundingId): CashAccount
    {
        if ($fundingId === 0) {
            throw ValidationException::withMessages(['funding_account_id' => 'Pilih sumber dana yang membiayai pengeluaran ini.']);
        }

        $funding = CashAccount::query()->whereKey($fundingId)->lockForUpdate()->first();
        if (! $funding instanceof CashAccount || $funding->direction !== CashAccount::IN || $funding->unit_id !== $expense->unit_id) {
            throw ValidationException::withMessages(['funding_account_id' => 'Sumber dana harus pos pendapatan di unit yang sama.']);
        }

        $allowed = $expense->allowedFundingAccounts()->pluck('cash_accounts.id')->map(fn (mixed $id): int => (int) $id)->all();
        if ($allowed !== [] && ! in_array($funding->id, $allowed, true)) {
            throw ValidationException::withMessages([
                'funding_account_id' => "Pos {$expense->name} tidak boleh dibiayai dari {$funding->name} (lihat Alokasi Dana).",
            ]);
        }

        return $funding;
    }

    private static function clean(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
