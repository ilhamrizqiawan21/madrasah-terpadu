<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use App\Enums\InvoiceStatus;

/**
 * Penentuan sisa dan status tagihan dari nominal, penyesuaian, dan pembayaran (T-602/T-604/T-605).
 */
final class InvoiceStatusResolver
{
    public static function remaining(int $amount, int $adjustmentTotal, int $paidTotal): int
    {
        return max(0, $amount - $adjustmentTotal - $paidTotal);
    }

    public static function resolve(
        int $amount,
        int $adjustmentTotal,
        int $paidTotal,
        bool $isCancelled = false,
    ): InvoiceStatus {
        if ($isCancelled) {
            return InvoiceStatus::Cancelled;
        }

        $remaining = self::remaining($amount, $adjustmentTotal, $paidTotal);

        if ($remaining === 0) {
            return InvoiceStatus::Paid;
        }

        if ($paidTotal > 0) {
            return InvoiceStatus::Partial;
        }

        return InvoiceStatus::Unpaid;
    }
}
