<?php

declare(strict_types=1);

namespace App\Enums;

enum ExamResultStatus: string
{
    case AwaitingEssay = 'awaiting_essay';
    case Final = 'final';

    public function label(): string
    {
        return match ($this) {
            self::AwaitingEssay => 'Menunggu nilai essay',
            self::Final => 'Final',
        };
    }
}
