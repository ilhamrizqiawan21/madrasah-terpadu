<?php

declare(strict_types=1);

namespace App\Enums;

enum PointCategory: string
{
    case Violation = 'violation';
    case Achievement = 'achievement';
    case GoodDeed = 'good_deed';

    public function label(): string
    {
        return match ($this) {
            self::Violation => 'Pelanggaran',
            self::Achievement => 'Prestasi',
            self::GoodDeed => 'Kebaikan',
        };
    }

    public function account(): string
    {
        return match ($this) {
            self::Violation => 'violation',
            self::Achievement, self::GoodDeed => 'merit',
        };
    }

    public static function fromInput(string $value): ?self
    {
        return match (mb_strtolower(trim($value))) {
            'violation', 'pelanggaran' => self::Violation,
            'achievement', 'prestasi' => self::Achievement,
            'good_deed', 'kebaikan' => self::GoodDeed,
            default => null,
        };
    }
}
