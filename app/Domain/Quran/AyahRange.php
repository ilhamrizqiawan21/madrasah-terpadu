<?php

declare(strict_types=1);

namespace App\Domain\Quran;

use InvalidArgumentException;

final class AyahRange
{
    public static function validate(int $surah, int $ayahFrom, int $ayahTo, int $surahAyahCount): void
    {
        if ($ayahFrom < 1) {
            throw new InvalidArgumentException("Ayat awal surah {$surah} minimal 1.");
        }

        if ($ayahTo < $ayahFrom) {
            throw new InvalidArgumentException("Ayat akhir surah {$surah} tidak boleh lebih kecil dari ayat awal.");
        }

        if ($ayahTo > $surahAyahCount) {
            throw new InvalidArgumentException("Surah {$surah} hanya memiliki {$surahAyahCount} ayat.");
        }
    }

    /**
     * @param  list<array{surah: int, ayah_from: int, ayah_to: int}>  $ranges
     */
    public static function countTotalAyahs(array $ranges): int
    {
        $seen = [];
        foreach ($ranges as $r) {
            for ($ayah = $r['ayah_from']; $ayah <= $r['ayah_to']; $ayah++) {
                $seen[$r['surah'].':'.$ayah] = true;
            }
        }

        return count($seen);
    }
}
