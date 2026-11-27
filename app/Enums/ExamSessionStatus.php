<?php

declare(strict_types=1);

namespace App\Enums;

enum ExamSessionStatus: string
{
    case InProgress = 'in_progress';
    case Locked = 'locked';
    case Submitted = 'submitted';
    case AutoClosed = 'auto_closed';
    case ForceClosed = 'force_closed';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'Mengerjakan',
            self::Locked => 'Terkunci',
            self::Submitted => 'Selesai',
            self::AutoClosed => 'Ditutup otomatis',
            self::ForceClosed => 'Dihentikan pengawas',
        };
    }

    public function isFinished(): bool
    {
        return in_array($this, [self::Submitted, self::AutoClosed, self::ForceClosed], true);
    }
}
