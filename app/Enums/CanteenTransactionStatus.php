<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status transaksi POS kantin (T-803).
 */
enum CanteenTransactionStatus: string
{
    case Completed = 'completed';
    case Reversed = 'reversed';

    public function label(): string
    {
        return match ($this) {
            self::Completed => 'Selesai',
            self::Reversed => 'Dibalikkan',
        };
    }
}
