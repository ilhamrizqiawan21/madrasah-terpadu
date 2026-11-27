<?php

declare(strict_types=1);

namespace App\Support;

final class Money
{
    /**
     * Format nominal integer rupiah menjadi teks Indonesia (mis. "Rp 1.250.000", "-Rp 50.000").
     */
    public static function format(int $amount): string
    {
        $prefix = $amount < 0 ? '-Rp ' : 'Rp ';
        $formatted = number_format(abs($amount), 0, ',', '.');

        return $prefix.$formatted;
    }
}
