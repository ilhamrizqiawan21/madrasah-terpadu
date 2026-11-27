<?php

declare(strict_types=1);

namespace App\Enums;

enum GateScanResult: string
{
    case OnTime = 'on_time';
    case Late = 'late';
    case CheckOut = 'check_out';
    case Duplicate = 'duplicate';
    case OutsideHours = 'outside_hours';
    case Holiday = 'holiday';

    public function label(): string
    {
        return match ($this) {
            self::OnTime => 'Masuk tepat waktu',
            self::Late => 'Masuk terlambat',
            self::CheckOut => 'Pulang',
            self::Duplicate => 'Sudah tercatat',
            self::OutsideHours => 'Di luar jam presensi',
            self::Holiday => 'Hari libur',
        };
    }
}
