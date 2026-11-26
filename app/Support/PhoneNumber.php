<?php

declare(strict_types=1);

namespace App\Support;

final class PhoneNumber
{
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $cleaned = preg_replace('/[\s\-\.\(\)]+/', '', trim($phone));

        if ($cleaned === null || $cleaned === '') {
            return null;
        }

        if (str_starts_with($cleaned, '+62')) {
            return '0'.substr($cleaned, 3);
        }

        if (str_starts_with($cleaned, '62')) {
            return '0'.substr($cleaned, 2);
        }

        if (str_starts_with($cleaned, '8')) {
            return '0'.$cleaned;
        }

        return $cleaned;
    }
}
