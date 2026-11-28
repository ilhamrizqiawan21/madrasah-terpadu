<?php

declare(strict_types=1);

namespace App\Enums;

enum AlumniIncomeRange: string
{
    case None = 'none';
    case Below3m = 'below_3m';
    case From3To6m = '3m_6m';
    case Above6m = 'above_6m';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Belum berpenghasilan',
            self::Below3m => '< Rp3.000.000',
            self::From3To6m => 'Rp3.000.000–Rp6.000.000',
            self::Above6m => '> Rp6.000.000',
        };
    }
}
