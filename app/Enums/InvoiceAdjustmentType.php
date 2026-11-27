<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Jenis penyesuaian tagihan append-only (T-604).
 */
enum InvoiceAdjustmentType: string
{
    case Discount = 'discount';
    case Waiver = 'waiver';
    case Correction = 'correction';

    public function label(): string
    {
        return match ($this) {
            self::Discount => 'Diskon',
            self::Waiver => 'Pembebasan',
            self::Correction => 'Koreksi',
        };
    }
}
