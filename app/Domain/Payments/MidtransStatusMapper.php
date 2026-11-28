<?php

declare(strict_types=1);

namespace App\Domain\Payments;

/**
 * Memetakan `transaction_status` + `fraud_status` Midtrans ke arti bagi order (PAYMENTS §4 tabel).
 * Murni tanpa I/O agar seluruh kombinasi bisa diuji unit.
 */
final class MidtransStatusMapper
{
    public static function map(?string $transactionStatus, ?string $fraudStatus = null): MidtransOutcome
    {
        $status = strtolower(trim((string) $transactionStatus));
        $fraud = strtolower(trim((string) $fraudStatus));

        return match ($status) {
            'settlement' => MidtransOutcome::Paid,
            'capture' => match ($fraud) {
                'accept' => MidtransOutcome::Paid,
                'challenge' => MidtransOutcome::Challenge,
                // capture tanpa fraud_status yang jelas tidak dianggap lunas.
                'deny' => MidtransOutcome::Failed,
                default => MidtransOutcome::Challenge,
            },
            'pending', 'authorize' => MidtransOutcome::Pending,
            'expire' => MidtransOutcome::Expired,
            'cancel', 'deny', 'failure' => MidtransOutcome::Failed,
            'refund', 'partial_refund', 'chargeback', 'partial_chargeback' => MidtransOutcome::NeedsReview,
            default => MidtransOutcome::Unknown,
        };
    }

    /**
     * Midtrans mengirim nominal sebagai string desimal ("150000.00"). Hanya rupiah utuh yang diterima;
     * selain itu null agar diperlakukan sebagai nominal tidak cocok.
     */
    public static function grossAmountToInt(mixed $grossAmount): ?int
    {
        if (is_int($grossAmount)) {
            return $grossAmount;
        }

        if (! is_string($grossAmount) || preg_match('/^(\d+)(?:\.0{1,2})?$/', $grossAmount, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }
}
