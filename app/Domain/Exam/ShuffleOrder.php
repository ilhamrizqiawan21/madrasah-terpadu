<?php

declare(strict_types=1);

namespace App\Domain\Exam;

use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Pengacakan deterministik berdasarkan `shuffle_seed` agar sambung ulang mempertahankan urutan soal (ADR-020).
 */
final class ShuffleOrder
{
    /**
     * @template T
     *
     * @param  list<T>  $items
     * @return list<T>
     */
    public static function apply(array $items, int $seed): array
    {
        if (count($items) <= 1) {
            return $items;
        }

        $randomizer = new Randomizer(new Mt19937($seed));

        return $randomizer->shuffleArray($items);
    }
}
