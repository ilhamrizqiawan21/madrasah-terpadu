<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Sifat perulangan jenis tagihan (T-601).
 */
enum FeeRecurrence: string
{
    case Monthly = 'monthly';
    case Once = 'once';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Bulanan',
            self::Once => 'Sekali',
        };
    }
}
