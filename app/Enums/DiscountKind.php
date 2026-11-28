<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Jenis potongan diskon/beasiswa berulang per siswa (T-615).
 */
enum DiscountKind: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::Percent => 'Persen (%)',
            self::Fixed => 'Nominal (Rp)',
        };
    }
}
