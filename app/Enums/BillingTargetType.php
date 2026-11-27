<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Sasaran penerbitan tagihan massal (T-602).
 */
enum BillingTargetType: string
{
    case Unit = 'unit';
    case Grade = 'grade';
    case Classroom = 'classroom';
    case Students = 'students';

    public function label(): string
    {
        return match ($this) {
            self::Unit => 'Satu unit',
            self::Grade => 'Satu tingkat',
            self::Classroom => 'Satu rombel',
            self::Students => 'Siswa terpilih',
        };
    }
}
