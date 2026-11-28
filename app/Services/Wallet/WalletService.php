<?php

declare(strict_types=1);

namespace App\Services\Wallet;

use App\Enums\StudentStatus;
use App\Enums\WalletEntryType;
use App\Exceptions\Wallet\DailyLimitExceededException;
use App\Exceptions\Wallet\FrozenWalletException;
use App\Exceptions\Wallet\InsufficientBalanceException;
use App\Models\Student;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletEntry;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ledger append-only dompet kantin siswa (T-801, ADR-006, ARCHITECTURE §7).
 */
final class WalletService
{
    private const BUSINESS_TIMEZONE = 'Asia/Makassar';

    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function ensureWallet(Student $student): Wallet
    {
        $existing = Wallet::query()->where('student_id', $student->id)->first();
        if ($existing instanceof Wallet) {
            return $existing;
        }

        if ($student->status !== StudentStatus::Active) {
            throw ValidationException::withMessages([
                'student_id' => 'Dompet hanya dapat dibuat otomatis untuk siswa aktif.',
            ]);
        }

        try {
            return Wallet::query()->create([
                'student_id' => $student->id,
                'balance' => 0,
                'daily_limit' => (int) config('sekolah.wallet.default_daily_limit', 20_000),
                'is_frozen' => false,
            ]);
        } catch (UniqueConstraintViolationException) {
            return Wallet::query()->where('student_id', $student->id)->firstOrFail();
        }
    }

    public function topUp(
        Student $student,
        int $amount,
        string $idempotencyKey,
        ?User $actor = null,
        ?string $description = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): WalletEntry {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Nominal isi saldo harus lebih dari nol.',
            ]);
        }

        return $this->recordEntry(
            student: $student,
            type: WalletEntryType::Topup,
            signedAmount: $amount,
            idempotencyKey: $idempotencyKey,
            actor: $actor,
            description: $description ?? 'Isi saldo dompet kantin',
            referenceType: $referenceType,
            referenceId: $referenceId,
            enforcePurchaseGuards: false,
        );
    }

    public function purchase(
        Student $student,
        int $amount,
        string $idempotencyKey,
        ?User $actor = null,
        ?string $description = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): WalletEntry {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Nominal belanja harus lebih dari nol.',
            ]);
        }

        return $this->recordEntry(
            student: $student,
            type: WalletEntryType::Purchase,
            signedAmount: -$amount,
            idempotencyKey: $idempotencyKey,
            actor: $actor,
            description: $description ?? 'Belanja kantin',
            referenceType: $referenceType,
            referenceId: $referenceId,
            enforcePurchaseGuards: true,
        );
    }

    public function reverse(
        WalletEntry $originalEntry,
        string $idempotencyKey,
        User $actor,
        string $reason,
    ): WalletEntry {
        $cleanReason = trim($reason);
        if ($cleanReason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Alasan pembalikan wajib diisi.',
            ]);
        }

        $wallet = Wallet::query()->findOrFail($originalEntry->wallet_id);
        $student = Student::query()->findOrFail($wallet->student_id);

        return $this->recordEntry(
            student: $student,
            type: WalletEntryType::Reversal,
            signedAmount: -$originalEntry->amount,
            idempotencyKey: $idempotencyKey,
            actor: $actor,
            description: "Pembalikan: {$cleanReason}",
            referenceType: WalletEntry::class,
            referenceId: $originalEntry->id,
            enforcePurchaseGuards: false,
        );
    }

    public function adjust(
        Student $student,
        int $signedAmount,
        string $idempotencyKey,
        User $actor,
        string $reason,
    ): WalletEntry {
        if ($signedAmount === 0) {
            throw ValidationException::withMessages([
                'amount' => 'Nominal penyesuaian tidak boleh nol.',
            ]);
        }

        $cleanReason = trim($reason);
        if ($cleanReason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Alasan penyesuaian wajib diisi.',
            ]);
        }

        return $this->recordEntry(
            student: $student,
            type: WalletEntryType::Adjustment,
            signedAmount: $signedAmount,
            idempotencyKey: $idempotencyKey,
            actor: $actor,
            description: "Penyesuaian: {$cleanReason}",
            referenceType: null,
            referenceId: null,
            enforcePurchaseGuards: false,
            preventNegativeBalance: $signedAmount < 0,
        );
    }

    public function spentTodayWita(Wallet $wallet): int
    {
        $nowWita = CarbonImmutable::now(self::BUSINESS_TIMEZONE);
        $startUtc = $nowWita->startOfDay()->utc();
        $endUtc = $nowWita->endOfDay()->utc();

        $purchases = (int) WalletEntry::query()
            ->where('wallet_id', $wallet->id)
            ->where('type', WalletEntryType::Purchase->value)
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->sum('amount');

        $reversals = (int) WalletEntry::query()
            ->where('wallet_id', $wallet->id)
            ->where('type', WalletEntryType::Reversal->value)
            ->where('amount', '>', 0)
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->sum('amount');

        return max(0, abs($purchases) - $reversals);
    }

    public function updateDailyLimit(Student $student, int $dailyLimit, User $actor): Wallet
    {
        $maxLimit = (int) config('sekolah.wallet.max_daily_limit', 100_000);
        if ($dailyLimit < 0 || $dailyLimit > $maxLimit) {
            throw ValidationException::withMessages([
                'daily_limit' => 'Limit harian harus antara Rp 0 dan Rp '.number_format($maxLimit, 0, ',', '.').'.',
            ]);
        }

        $wallet = $this->ensureWallet($student);

        return DB::transaction(function () use ($wallet, $dailyLimit, $actor): Wallet {
            /** @var Wallet $locked */
            $locked = Wallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            $before = ['daily_limit' => $locked->daily_limit];

            $locked->forceFill(['daily_limit' => $dailyLimit])->save();

            $this->audit->record('wallet.limit_updated', $locked, $before, [
                'daily_limit' => $dailyLimit,
                'updated_by' => $actor->id,
            ]);

            return $locked;
        });
    }

    public function setFrozen(Student $student, bool $frozen, User $actor): Wallet
    {
        $wallet = $this->ensureWallet($student);

        return DB::transaction(function () use ($wallet, $frozen, $actor): Wallet {
            /** @var Wallet $locked */
            $locked = Wallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            $before = ['is_frozen' => $locked->is_frozen];

            $locked->forceFill(['is_frozen' => $frozen])->save();

            $this->audit->record($frozen ? 'wallet.frozen' : 'wallet.unfrozen', $locked, $before, [
                'is_frozen' => $frozen,
                'updated_by' => $actor->id,
            ]);

            return $locked;
        });
    }

    private function recordEntry(
        Student $student,
        WalletEntryType $type,
        int $signedAmount,
        string $idempotencyKey,
        ?User $actor,
        string $description,
        ?string $referenceType,
        ?int $referenceId,
        bool $enforcePurchaseGuards,
        bool $preventNegativeBalance = false,
    ): WalletEntry {
        $existing = WalletEntry::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing instanceof WalletEntry) {
            return $existing;
        }

        $wallet = $this->ensureWallet($student);

        return DB::transaction(function () use (
            $wallet,
            $type,
            $signedAmount,
            $idempotencyKey,
            $actor,
            $description,
            $referenceType,
            $referenceId,
            $enforcePurchaseGuards,
            $preventNegativeBalance,
        ): WalletEntry {
            $again = WalletEntry::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($again instanceof WalletEntry) {
                return $again;
            }

            /** @var Wallet $locked */
            $locked = Wallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();

            if ($enforcePurchaseGuards) {
                if ($locked->is_frozen) {
                    throw new FrozenWalletException;
                }

                $spendAmount = abs($signedAmount);
                if ($locked->balance < $spendAmount) {
                    throw new InsufficientBalanceException;
                }

                $spentToday = $this->spentTodayWita($locked);
                if (($spentToday + $spendAmount) > $locked->daily_limit) {
                    throw new DailyLimitExceededException;
                }
            } elseif ($preventNegativeBalance && ($locked->balance + $signedAmount) < 0) {
                throw new InsufficientBalanceException;
            }

            $newBalance = $locked->balance + $signedAmount;
            $locked->forceFill(['balance' => $newBalance])->save();

            $entry = WalletEntry::query()->create([
                'wallet_id' => $locked->id,
                'type' => $type,
                'amount' => $signedAmount,
                'balance_after' => $newBalance,
                'idempotency_key' => $idempotencyKey,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => $description,
                'created_by' => $actor?->id,
                'created_at' => now(),
            ]);

            $this->audit->record("wallet.{$type->value}", $locked, [
                'balance' => $locked->balance - $signedAmount,
            ], [
                'balance' => $newBalance,
                'amount' => $signedAmount,
                'entry_id' => $entry->id,
                'idempotency_key' => $idempotencyKey,
            ]);

            return $entry;
        });
    }
}
