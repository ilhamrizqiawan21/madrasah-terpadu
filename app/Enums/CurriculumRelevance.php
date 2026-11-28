<?php

declare(strict_types=1);

namespace App\Enums;

enum CurriculumRelevance: string
{
    case Very = 'very';
    case Fair = 'fair';
    case Low = 'low';

    public function label(): string
    {
        return match ($this) {
            self::Very => 'Sangat sesuai',
            self::Fair => 'Sesuai / cukup membantu',
            self::Low => 'Kurang sesuai',
        };
    }
}
