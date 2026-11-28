<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\CanteenTransactionStatus;
use App\Enums\WalletEntryType;
use App\Models\CanteenTransaction;
use App\Models\Unit;
use App\Models\Wallet;
use App\Models\WalletEntry;
use App\Support\AppTimezone;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Laporan kantin & dompet siswa: penjualan per kasir, top-up per metode,
 * saldo mengendap, dan subsidi voucher terpisah (T-807 / T-1304).
 */
final class CanteenReportService
{
    private const BUSINESS_TIMEZONE = 'Asia/Makassar';

    /**
     * @return array{
     *     summary: array{
     *         sales_total: int,
     *         sales_wallet: int,
     *         sales_voucher: int,
     *         tx_count: int,
     *         topup_cash: int,
     *         topup_online: int,
     *         floating_balance: int,
     *     },
     *     by_cashier: list<array{
     *         cashier_id: int,
     *         cashier_name: string,
     *         tx_count: int,
     *         total: int,
     *         paid_from_wallet: int,
     *         paid_from_vouchers: int,
     *     }>,
     * }
     */
    public function build(?Unit $unit, string $fromDate, string $toDate): array
    {
        $fromUtc = CarbonImmutable::parse($fromDate, self::BUSINESS_TIMEZONE)->startOfDay()->setTimezone(AppTimezone::name());
        $toUtc = CarbonImmutable::parse($toDate, self::BUSINESS_TIMEZONE)->endOfDay()->setTimezone(AppTimezone::name());

        $salesByCashier = CanteenTransaction::query()
            ->join('users', 'users.id', '=', 'canteen_transactions.cashier_id')
            ->join('students', 'students.id', '=', 'canteen_transactions.student_id')
            ->where('canteen_transactions.status', CanteenTransactionStatus::Completed->value)
            ->whereBetween('canteen_transactions.created_at', [$fromUtc, $toUtc])
            ->when($unit !== null, fn (Builder $q) => $q->where('students.unit_id', $unit->id))
            ->selectRaw('canteen_transactions.cashier_id, users.name as cashier_name, count(*) as tx_count')
            ->selectRaw('coalesce(sum(canteen_transactions.total), 0) as total_amount')
            ->selectRaw('coalesce(sum(canteen_transactions.paid_from_wallet), 0) as wallet_amount')
            ->selectRaw('coalesce(sum(canteen_transactions.paid_from_vouchers), 0) as voucher_amount')
            ->groupBy('canteen_transactions.cashier_id', 'users.name')
            ->orderByDesc('total_amount')
            ->orderBy('users.name')
            ->toBase()
            ->get();

        $byCashier = [];
        $salesTotal = 0;
        $salesWallet = 0;
        $salesVoucher = 0;
        $txCount = 0;

        foreach ($salesByCashier as $row) {
            $total = (int) $row->total_amount;
            $walletAmt = (int) $row->wallet_amount;
            $voucherAmt = (int) $row->voucher_amount;
            $count = (int) $row->tx_count;

            $byCashier[] = [
                'cashier_id' => (int) $row->cashier_id,
                'cashier_name' => (string) $row->cashier_name,
                'tx_count' => $count,
                'total' => $total,
                'paid_from_wallet' => $walletAmt,
                'paid_from_vouchers' => $voucherAmt,
            ];

            $salesTotal += $total;
            $salesWallet += $walletAmt;
            $salesVoucher += $voucherAmt;
            $txCount += $count;
        }

        /** @var object{topup_cash: int|string|null, topup_online: int|string|null}|null $topupRow */
        $topupRow = WalletEntry::query()
            ->join('wallets', 'wallets.id', '=', 'wallet_entries.wallet_id')
            ->join('students', 'students.id', '=', 'wallets.student_id')
            ->where('wallet_entries.type', WalletEntryType::Topup->value)
            ->whereBetween('wallet_entries.created_at', [$fromUtc, $toUtc])
            ->when($unit !== null, fn (Builder $q) => $q->where('students.unit_id', $unit->id))
            ->selectRaw("coalesce(sum(CASE WHEN wallet_entries.idempotency_key LIKE 'topup-cash:%' THEN wallet_entries.amount ELSE 0 END), 0) as topup_cash")
            ->selectRaw("coalesce(sum(CASE WHEN wallet_entries.idempotency_key NOT LIKE 'topup-cash:%' THEN wallet_entries.amount ELSE 0 END), 0) as topup_online")
            ->toBase()
            ->first();

        $floatingBalance = (int) Wallet::query()
            ->join('students', 'students.id', '=', 'wallets.student_id')
            ->when($unit !== null, fn (Builder $q) => $q->where('students.unit_id', $unit->id))
            ->sum(DB::raw('wallets.balance'));

        return [
            'summary' => [
                'sales_total' => $salesTotal,
                'sales_wallet' => $salesWallet,
                'sales_voucher' => $salesVoucher,
                'tx_count' => $txCount,
                'topup_cash' => (int) ($topupRow->topup_cash ?? 0),
                'topup_online' => (int) ($topupRow->topup_online ?? 0),
                'floating_balance' => $floatingBalance,
            ],
            'by_cashier' => $byCashier,
        ];
    }
}
