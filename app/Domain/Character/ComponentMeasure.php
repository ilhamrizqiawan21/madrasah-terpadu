<?php

declare(strict_types=1);

namespace App\Domain\Character;

use App\Enums\CharacterSource;

/**
 * Ukuran satu komponen untuk satu siswa, sebelum dinormalkan.
 *
 * `value`: persen 0–100 untuk sumber persentase, jumlah poin untuk sumber bertingkatan,
 * tingkat 1–4 untuk observasi guru; null = belum ada data.
 * `coverage`: persen hari efektif yang punya data (hanya sumber yang butuh cakupan).
 */
final readonly class ComponentMeasure
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function __construct(
        public CharacterSource $source,
        public int $weight,
        public array $params,
        public string $phrase,
        public ?float $value,
        public ?float $coverage = null,
    ) {}
}
