<?php

declare(strict_types=1);

namespace App\Domain\Character;

/**
 * Hasil hitung satu dimensi. `score`/`level` null = data kurang (wali kelas menetapkan manual).
 */
final readonly class DimensionResult
{
    /**
     * @param  list<array{source: string, phrase: string, weight: int, effective_weight: float, value: float|null, coverage: float|null, score: float|null, used: bool, reason: string|null}>  $components
     */
    public function __construct(
        public ?float $score,
        public ?string $level,
        public float $usedWeight,
        public array $components,
        public ?string $strongest,
        public ?string $weakest,
    ) {}

    public function insufficient(): bool
    {
        return $this->score === null;
    }
}
