<?php

declare(strict_types=1);

namespace App\Enums;

enum ExamStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Running = 'running';
    case Finished = 'finished';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Scheduled => 'Terjadwal',
            self::Running => 'Berlangsung',
            self::Finished => 'Selesai',
        };
    }
}
