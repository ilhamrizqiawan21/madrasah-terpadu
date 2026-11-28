<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status periode ujian untuk syarat kelayakan (T-611, ADR-016).
 */
enum ExamPeriodStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Active => 'Aktif',
            self::Closed => 'Selesai',
        };
    }
}
