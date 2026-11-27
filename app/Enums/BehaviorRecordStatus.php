<?php

declare(strict_types=1);

namespace App\Enums;

enum BehaviorRecordStatus: string
{
    case Active = 'active';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Voided => 'Dibatalkan',
        };
    }
}
