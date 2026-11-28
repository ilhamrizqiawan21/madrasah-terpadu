<?php

declare(strict_types=1);

namespace App\Exceptions\Payments;

/**
 * Get Status tidak mengenal order_id: wali membuka Snap tetapi belum memilih metode bayar.
 */
final class MidtransTransactionNotFoundException extends MidtransRequestException
{
    public function __construct(public readonly string $orderId)
    {
        parent::__construct(404, ["Transaksi {$orderId} tidak ditemukan di Midtrans."]);
    }
}
