<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Jenis entri ledger dompet kantin (T-801, ADR-006).
 */
enum WalletEntryType: string
{
    case Topup = 'topup';
    case Purchase = 'purchase';
    case Reversal = 'reversal';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Topup => 'Isi saldo',
            self::Purchase => 'Belanja kantin',
            self::Reversal => 'Pembalikan',
            self::Adjustment => 'Penyesuaian',
        };
    }
}
