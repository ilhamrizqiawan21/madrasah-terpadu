<?php

declare(strict_types=1);

namespace App\Enums;

enum HabitAspect: string
{
    case Spiritual = 'spiritual';
    case Social = 'social';

    public function label(): string
    {
        return match ($this) {
            self::Spiritual => 'Spiritual',
            self::Social => 'Sosial',
        };
    }
}
