<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Cakupan tagihan yang diwajibkan lunas pada syarat periode ujian (T-611).
 */
enum ExamRequirementScope: string
{
    case UpToPeriod = 'up_to_period';
    case AllOpen = 'all_open';

    public function label(): string
    {
        return match ($this) {
            self::UpToPeriod => 'Sampai bulan batas',
            self::AllOpen => 'Semua tagihan terbuka',
        };
    }
}
