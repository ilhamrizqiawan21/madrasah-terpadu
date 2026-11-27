<?php

declare(strict_types=1);

namespace App\Enums;

enum PointAccount: string
{
    case Violation = 'violation';
    case Merit = 'merit';

    public function label(): string
    {
        return match ($this) {
            self::Violation => 'Pelanggaran',
            self::Merit => 'Kebaikan & Prestasi',
        };
    }
}
