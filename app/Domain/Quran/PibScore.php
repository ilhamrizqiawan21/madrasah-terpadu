<?php

declare(strict_types=1);

namespace App\Domain\Quran;

use InvalidArgumentException;

final class PibScore
{
    public const MAX_SCORE = 90;

    public const MIN_MISTAKES = 0;

    public const MAX_MISTAKES = 90;

    public static function calculate(int $mistakes): int
    {
        if ($mistakes < self::MIN_MISTAKES || $mistakes > self::MAX_MISTAKES) {
            throw new InvalidArgumentException('Jumlah kesalahan PIB harus antara 0 sampai 90.');
        }

        return self::MAX_SCORE - $mistakes;
    }
}
