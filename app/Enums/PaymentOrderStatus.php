<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status order Midtrans (PAYMENTS §4). Hanya berubah lewat MidtransNotificationProcessor,
 * kecuali `failed` saat Snap gagal dibuat dan `cancelled` saat wali membatalkan sebelum memilih metode.
 */
enum PaymentOrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Expired = 'expired';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu bayar',
            self::Paid => 'Berhasil',
            self::Expired => 'Kedaluwarsa',
            self::Failed => 'Gagal',
            self::Cancelled => 'Dibatalkan',
        };
    }
}
