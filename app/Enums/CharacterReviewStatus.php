<?php

declare(strict_types=1);

namespace App\Enums;

enum CharacterReviewStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Submitted => 'Diajukan',
            self::Approved => 'Disetujui kepala unit',
        };
    }
}
