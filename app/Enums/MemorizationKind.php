<?php

declare(strict_types=1);

namespace App\Enums;

enum MemorizationKind: string
{
    case Quran = 'quran';
    case Hadith = 'hadith';
    case Prayer = 'prayer';

    public function label(): string
    {
        return match ($this) {
            self::Quran => "Al-Qur'an",
            self::Hadith => 'Hadits',
            self::Prayer => "Do'a",
        };
    }
}
