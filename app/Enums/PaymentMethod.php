<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Metode pembayaran tagihan (T-605).
 */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case Transfer = 'transfer';
    case Midtrans = 'midtrans';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Tunai',
            self::Transfer => 'Transfer',
            self::Midtrans => 'Midtrans',
        };
    }
}
