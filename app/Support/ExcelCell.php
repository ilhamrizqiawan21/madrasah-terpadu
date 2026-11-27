<?php

declare(strict_types=1);

namespace App\Support;

final class ExcelCell
{
    /**
     * Netralkan awalan rumus Excel (`=`, `+`, `-`, `@`, tab, CR) untuk mencegah formula injection.
     */
    public static function neutralize(string $value): string
    {
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
