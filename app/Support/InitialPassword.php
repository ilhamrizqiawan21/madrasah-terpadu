<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Kata sandi awal/reset yang dibacakan atau dicetak untuk pengguna.
 * Tanpa karakter yang mudah tertukar (0/O, 1/l/I) agar tidak salah ketik.
 */
final class InitialPassword
{
    private const ALPHABET = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public static function generate(int $length = 12): string
    {
        $maxIndex = strlen(self::ALPHABET) - 1;
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= self::ALPHABET[random_int(0, $maxIndex)];
        }

        return $password;
    }
}
