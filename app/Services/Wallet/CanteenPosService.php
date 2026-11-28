<?php

declare(strict_types=1);

namespace App\Services\Wallet;

use App\Enums\CanteenTransactionStatus;
use App\Enums\Permission;
use App\Enums\StudentStatus;
use App\Exceptions\Wallet\FrozenWalletException;
use App\Models\AcademicYear;
use App\Models\CanteenProduct;
use App\Models\CanteenTransaction;
use App\Models\CanteenVoucher;
use App\Models\Student;
use App\Models\User;
use App\Models\VoucherRedemption;
use App\Models\WalletEntry;
use App\Notifications\CanteenPurchaseMadeNotification;
use App\Services\Attendance\StudentCardService;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\GuardianNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Layanan POS kantin: identifikasi kartu, belanja (voucher dulu lalu saldo),
 * notifikasi ke wali, dan koreksi/pembalikan (T-803, PRD §4.6).
 */
final class CanteenPosService
{
    private const BUSINESS_TIMEZONE = 'Asia/Makassar';

    public function __construct(
        private readonly StudentCardService $cards,
        private readonly WalletService $walletService,
        private readonly GuardianNotifier $notifier,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return array{
     *     student: array{id: int, nis: string, name: string, unit: string, classroom: string},
     *     wallet: array{balance: int, daily_limit: int, spent_today: int, remaining_limit: int, is_frozen: bool},
     *     vouchers: array{total_remaining: int, count: int},
     * }|null
     */
    public function lookupByToken(string $rawToken): ?array
    {
        $card = $this->cards->findActiveByRawToken($rawToken);
        if ($card === null) {
            return null;
        }

        $student = $card->student;
        if ($student->status !== StudentStatus::Active) {
            return null;
        }

        $activeYearId = AcademicYear::query()->where('is_active', true)->value('id');
        $student->load([
            'unit:id,code,name',
            'enrollments' => fn ($q) => $activeYearId !== null
                ? $q->where('academic_year_id', $activeYearId)->with('classroom:id,name')
                : $q->whereRaw('1 = 0'),
        ]);

        $wallet = $this->walletService->ensureWallet($student);
        $spentToday = $this->walletService->spentTodayWita($wallet);
        $todayDate = CarbonImmutable::now(self::BUSINESS_TIMEZONE)->toDateString();

        $voucherTotal = (int) CanteenVoucher::query()
            ->where('student_id', $student->id)
            ->where('remaining', '>', 0)
            ->whereDate('expires_on', '>=', $todayDate)
            ->sum('remaining');

        $voucherCount = CanteenVoucher::query()
            ->where('student_id', $student->id)
            ->where('remaining', '>', 0)
            ->whereDate('expires_on', '>=', $todayDate)
            ->count();

        $enrollment = $student->enrollments->first();

        return [
            'student' => [
                'id' => $student->id,
                'nis' => $student->nis,
                'name' => $student->name,
                'unit' => $student->unit->code,
                'classroom' => $enrollment?->classroom->name ?? '-',
            ],
            'wallet' => [
                'balance' => $wallet->balance,
                'daily_limit' => $wallet->daily_limit,
                'spent_today' => $spentToday,
                'remaining_limit' => max(0, $wallet->daily_limit - $spentToday),
                'is_frozen' => $wallet->is_frozen,
            ],
            'vouchers' => [
                'total_remaining' => $voucherTotal,
                'count' => $voucherCount,
            ],
        ];
    }

    /**
     * Belanja dari keranjang menu. Harga dan total dihitung ulang dari database, stok menu dikunci
     * dan dikurangi dalam transaksi yang sama dengan pemotongan voucher/saldo. Bila pembayaran
     * ditolak (saldo kurang, limit, dompet beku), seluruhnya dibatalkan termasuk stok.
     *
     * @param  list<array{product_id: int, qty: int}>  $lines
     */
    public function purchaseCart(
        User $cashier,
        Student $student,
        array $lines,
        int $manualAmount,
        ?string $manualNote,
        string $clientUuid,
    ): CanteenTransaction {
        $existing = CanteenTransaction::query()->where('client_uuid', $clientUuid)->first();
        if ($existing instanceof CanteenTransaction) {
            return $existing;
        }

        return DB::transaction(function () use ($cashier, $student, $lines, $manualAmount, $manualNote, $clientUuid): CanteenTransaction {
            $products = CanteenProduct::query()
                ->whereIn('id', array_map(static fn (array $l): int => $l['product_id'], $lines))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // Diperiksa lagi setelah kunci: permintaan ganda yang menunggu kunci tidak boleh memotong stok dua kali.
            $again = CanteenTransaction::query()->where('client_uuid', $clientUuid)->first();
            if ($again instanceof CanteenTransaction) {
                return $again;
            }

            $items = [];
            $total = 0;
            foreach ($lines as $line) {
                $product = $products->get($line['product_id']);
                if (! $product instanceof CanteenProduct || ! $product->is_active) {
                    throw ValidationException::withMessages(['lines' => 'Ada menu yang sudah tidak dijual. Muat ulang daftar menu.']);
                }
                if ($product->stock !== null && $product->stock < $line['qty']) {
                    throw ValidationException::withMessages([
                        'lines' => "Stok {$product->name} tinggal {$product->stock}.",
                    ]);
                }

                $amount = $product->price * $line['qty'];
                $total += $amount;
                $items[] = [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'price' => $product->price,
                    'qty' => $line['qty'],
                    'amount' => $amount,
                ];

                if ($product->stock !== null) {
                    $product->forceFill(['stock' => $product->stock - $line['qty']])->save();
                }
            }

            if ($manualAmount > 0) {
                $note = trim((string) $manualNote);
                $items[] = ['product_id' => null, 'name' => $note === '' ? 'Lain-lain' : $note, 'price' => $manualAmount, 'qty' => 1, 'amount' => $manualAmount];
                $total += $manualAmount;
            }

            if ($total < 100) {
                throw ValidationException::withMessages(['lines' => 'Pesanan masih kosong.']);
            }

            return $this->purchase($cashier, $student, $total, $clientUuid, $items);
        });
    }

    /**
     * @param  list<array{name: string, amount: int, product_id?: int|null, price?: int, qty?: int}>|null  $items
     */
    public function purchase(
        User $cashier,
        Student $student,
        int $total,
        string $clientUuid,
        ?array $items = null,
    ): CanteenTransaction {
        if ($total <= 0) {
            throw ValidationException::withMessages([
                'total' => 'Nominal belanja harus lebih dari nol.',
            ]);
        }

        $existing = CanteenTransaction::query()->where('client_uuid', $clientUuid)->first();
        if ($existing instanceof CanteenTransaction) {
            return $existing;
        }

        $todayDate = CarbonImmutable::now(self::BUSINESS_TIMEZONE)->toDateString();

        [$transaction, $balanceAfter] = DB::transaction(function () use ($cashier, $student, $total, $clientUuid, $items, $todayDate): array {
            $again = CanteenTransaction::query()->where('client_uuid', $clientUuid)->first();
            if ($again instanceof CanteenTransaction) {
                $wallet = $this->walletService->ensureWallet($student);

                return [$again, $wallet->balance];
            }

            $wallet = $this->walletService->ensureWallet($student);
            if ($wallet->is_frozen) {
                throw new FrozenWalletException;
            }

            $vouchers = CanteenVoucher::query()
                ->where('student_id', $student->id)
                ->where('remaining', '>', 0)
                ->whereDate('expires_on', '>=', $todayDate)
                ->orderBy('expires_on')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $remainingToPay = $total;
            $paidFromVouchers = 0;
            $redemptions = [];

            foreach ($vouchers as $voucher) {
                if ($remainingToPay <= 0) {
                    break;
                }

                $use = min($remainingToPay, $voucher->remaining);
                $voucher->forceFill(['remaining' => $voucher->remaining - $use])->save();
                $paidFromVouchers += $use;
                $remainingToPay -= $use;
                $redemptions[] = ['voucher_id' => $voucher->id, 'amount' => $use];
            }

            $paidFromWallet = $remainingToPay;

            $tx = CanteenTransaction::query()->create([
                'student_id' => $student->id,
                'cashier_id' => $cashier->id,
                'client_uuid' => $clientUuid,
                'total' => $total,
                'paid_from_vouchers' => $paidFromVouchers,
                'paid_from_wallet' => $paidFromWallet,
                'items' => $items,
                'status' => CanteenTransactionStatus::Completed,
                'created_at' => now(),
            ]);

            foreach ($redemptions as $red) {
                VoucherRedemption::query()->create([
                    'canteen_voucher_id' => $red['voucher_id'],
                    'canteen_transaction_id' => $tx->id,
                    'amount' => $red['amount'],
                    'created_at' => now(),
                ]);
            }

            $balanceAfter = $wallet->balance;
            if ($paidFromWallet > 0) {
                $entry = $this->walletService->purchase(
                    student: $student,
                    amount: $paidFromWallet,
                    idempotencyKey: "purchase:{$clientUuid}",
                    actor: $cashier,
                    description: 'Belanja kantin',
                    referenceType: CanteenTransaction::class,
                    referenceId: $tx->id,
                );
                $balanceAfter = $entry->balance_after;
            }

            $this->audit->record('canteen.purchase', $tx, null, [
                'student_id' => $student->id,
                'total' => $total,
                'paid_from_vouchers' => $paidFromVouchers,
                'paid_from_wallet' => $paidFromWallet,
                'client_uuid' => $clientUuid,
            ]);

            return [$tx, $balanceAfter];
        });

        $this->notifier->notifyStudentGuardians(
            $student,
            new CanteenPurchaseMadeNotification(
                transactionId: $transaction->id,
                studentName: $student->nickname ?? $student->name,
                total: $transaction->total,
                balanceAfter: $balanceAfter,
                itemsSummary: self::summarizeItems($transaction->items),
            ),
        );

        return $transaction;
    }

    public function reverse(User $actor, CanteenTransaction $transaction, string $reason): CanteenTransaction
    {
        $canManageAny = $actor->can(Permission::WalletManage->value);
        $windowMinutes = (int) config('sekolah.wallet.cashier_reversal_window_minutes', 15);

        if (! $canManageAny) {
            $isOwn = $actor->id === $transaction->cashier_id;
            $withinWindow = now()->lessThanOrEqualTo($transaction->created_at->addMinutes($windowMinutes));

            if (! $isOwn || ! $withinWindow) {
                throw new AuthorizationException('Anda tidak berwenang membalikkan transaksi ini.');
            }
        }

        $cleanReason = trim($reason);
        if ($cleanReason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Alasan koreksi wajib diisi.',
            ]);
        }

        return DB::transaction(function () use ($actor, $transaction, $cleanReason): CanteenTransaction {
            /** @var CanteenTransaction $locked */
            $locked = CanteenTransaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === CanteenTransactionStatus::Reversed) {
                throw ValidationException::withMessages([
                    'transaction' => 'Transaksi ini sudah dibalikkan sebelumnya.',
                ]);
            }

            $redemptions = VoucherRedemption::query()
                ->where('canteen_transaction_id', $locked->id)
                ->where('amount', '>', 0)
                ->get();

            foreach ($redemptions as $redemption) {
                $voucher = CanteenVoucher::query()->whereKey($redemption->canteen_voucher_id)->lockForUpdate()->firstOrFail();
                $voucher->forceFill(['remaining' => $voucher->remaining + $redemption->amount])->save();

                VoucherRedemption::query()->create([
                    'canteen_voucher_id' => $voucher->id,
                    'canteen_transaction_id' => $locked->id,
                    'amount' => -$redemption->amount,
                    'created_at' => now(),
                ]);
            }

            if ($locked->paid_from_wallet > 0) {
                $originalEntry = WalletEntry::query()
                    ->where('idempotency_key', "purchase:{$locked->client_uuid}")
                    ->firstOrFail();

                $this->walletService->reverse(
                    originalEntry: $originalEntry,
                    idempotencyKey: "reversal:{$locked->client_uuid}",
                    actor: $actor,
                    reason: $cleanReason,
                );
            }

            $this->restoreStock($locked);

            $locked->forceFill([
                'status' => CanteenTransactionStatus::Reversed,
                'reversed_at' => now(),
                'reversed_by' => $actor->id,
                'reversal_reason' => $cleanReason,
            ])->save();

            $this->audit->record('canteen.reversal', $locked, ['status' => CanteenTransactionStatus::Completed->value], [
                'status' => CanteenTransactionStatus::Reversed->value,
                'reason' => $cleanReason,
            ]);

            return $locked;
        });
    }

    /** Mengembalikan stok menu yang terjual pada transaksi yang dibatalkan. */
    private function restoreStock(CanteenTransaction $transaction): void
    {
        $quantities = [];
        foreach ($transaction->items ?? [] as $item) {
            $productId = $item['product_id'] ?? null;
            if ($productId !== null) {
                $quantities[$productId] = ($quantities[$productId] ?? 0) + ($item['qty'] ?? 1);
            }
        }
        if ($quantities === []) {
            return;
        }

        $products = CanteenProduct::query()->whereIn('id', array_keys($quantities))->whereNotNull('stock')
            ->orderBy('id')->lockForUpdate()->get();
        foreach ($products as $product) {
            $product->forceFill(['stock' => (int) $product->stock + $quantities[$product->id]])->save();
        }
    }

    /**
     * Ringkasan untuk notifikasi wali, mis. "Nasi goreng ×1, Es teh ×2". Maksimal tiga nama.
     *
     * @param  list<array{name: string, amount: int, product_id?: int|null, price?: int, qty?: int}>|null  $items
     */
    public static function summarizeItems(?array $items): ?string
    {
        if ($items === null || $items === []) {
            return null;
        }

        $parts = array_map(static fn (array $i): string => $i['name'].' ×'.($i['qty'] ?? 1), array_slice($items, 0, 3));
        $more = count($items) - 3;

        return implode(', ', $parts).($more > 0 ? " dan {$more} lainnya" : '');
    }
}
