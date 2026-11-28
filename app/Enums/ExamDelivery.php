<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Jenis pelaksanaan periode ujian (T-611, ADR-016).
 */
enum ExamDelivery: string
{
    case Cbt = 'cbt';
    case Paper = 'paper';

    public function label(): string
    {
        return match ($this) {
            self::Cbt => 'CBT',
            self::Paper => 'Kertas',
        };
    }
}
