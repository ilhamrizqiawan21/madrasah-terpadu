<?php

declare(strict_types=1);

namespace App\Domain\Character;

use App\Enums\CharacterSource;

/**
 * Menghitung capaian satu dimensi karakter (ADR-029). Murni: tanpa DB, tanpa waktu.
 *
 * - Tiap komponen dinormalkan ke 0–100 (persen apa adanya, poin lewat tingkatan, observasi 1–4 → 40/60/80/100).
 * - Komponen tanpa data, atau berbasis harian dengan cakupan < batas, dibuang; bobot sisanya dinormalkan ulang.
 * - Bila bobot yang tersisa < 50, dimensi "data kurang": tanpa skor dan capaian.
 */
final class CharacterScoreCalculator
{
    public const MIN_USED_WEIGHT = 50;

    private const OBSERVATION_SCORES = [1 => 40.0, 2 => 60.0, 3 => 80.0, 4 => 100.0];

    /**
     * @param  list<ComponentMeasure>  $measures
     * @param  list<array{code: string, label: string, min: int}>  $scale  urut dari tingkat tertinggi
     */
    public function calculate(array $measures, array $scale, int $coverageMinPct): DimensionResult
    {
        $rows = [];
        $usedWeight = 0;
        $weighted = 0.0;

        foreach ($measures as $measure) {
            [$score, $reason] = $this->normalize($measure, $coverageMinPct);
            $used = $score !== null;

            if ($used) {
                $usedWeight += $measure->weight;
                $weighted += $score * $measure->weight;
            }

            $rows[] = [
                'source' => $measure->source->value,
                'phrase' => $measure->phrase,
                'weight' => $measure->weight,
                'effective_weight' => 0.0,
                'value' => $measure->value,
                'coverage' => $measure->coverage,
                'score' => $score,
                'used' => $used,
                'reason' => $reason,
            ];
        }

        foreach ($rows as $index => $row) {
            if ($row['used'] && $usedWeight > 0) {
                $rows[$index]['effective_weight'] = round($row['weight'] * 100 / $usedWeight, 2);
            }
        }

        $usedRows = array_values(array_filter($rows, fn (array $row): bool => $row['used']));
        [$strongest, $weakest] = $this->extremes($usedRows);

        if ($usedWeight < self::MIN_USED_WEIGHT) {
            return new DimensionResult(null, null, (float) $usedWeight, $rows, $strongest, $weakest);
        }

        $score = round($weighted / $usedWeight, 2);

        return new DimensionResult($score, $this->level($score, $scale), (float) $usedWeight, $rows, $strongest, $weakest);
    }

    /**
     * Isi templat deskripsi; penanda yang tidak ada nilainya diganti frasa umum.
     */
    public function describe(string $template, string $studentName, string $dimensionName, ?string $strongest, ?string $weakest): string
    {
        return strtr($template, [
            '{nama}' => $studentName,
            '{dimensi}' => mb_strtolower($dimensionName),
            '{kuat}' => $strongest ?? 'kegiatan sehari-hari',
            '{lemah}' => $weakest ?? 'kegiatan sehari-hari',
        ]);
    }

    /**
     * @param  list<array{code: string, label: string, min: int}>  $scale
     */
    public function level(float $score, array $scale): string
    {
        foreach ($scale as $level) {
            if ($score >= $level['min']) {
                return $level['code'];
            }
        }

        return $scale[count($scale) - 1]['code'];
    }

    /**
     * @return array{0: float|null, 1: string|null}
     */
    private function normalize(ComponentMeasure $measure, int $coverageMinPct): array
    {
        if ($measure->value === null) {
            return [null, 'Belum ada data'];
        }

        if ($measure->source->needsCoverage() && ($measure->coverage ?? 0.0) < $coverageMinPct) {
            return [null, 'Cakupan data di bawah batas'];
        }

        return match (true) {
            $measure->source->usesTiers() => [$this->tierScore($measure), null],
            $measure->source === CharacterSource::TeacherObservation => [self::OBSERVATION_SCORES[(int) $measure->value] ?? null, null],
            default => [max(0.0, min(100.0, $measure->value)), null],
        };
    }

    private function tierScore(ComponentMeasure $measure): float
    {
        /** @var list<array{max: int|null, score: int}> $tiers */
        $tiers = $measure->params['tiers'] ?? $measure->source->defaultTiers();
        $points = (int) round((float) $measure->value);

        foreach ($tiers as $tier) {
            if ($tier['max'] === null || $points <= $tier['max']) {
                return (float) $tier['score'];
            }
        }

        return (float) $tiers[count($tiers) - 1]['score'];
    }

    /**
     * Komponen terkuat & terlemah untuk deskripsi; bila sama atau hanya satu, lemah = null.
     *
     * @param  list<array{phrase: string, score: float|null}>  $rows
     * @return array{0: string|null, 1: string|null}
     */
    private function extremes(array $rows): array
    {
        if ($rows === []) {
            return [null, null];
        }

        usort($rows, fn (array $a, array $b): int => $b['score'] <=> $a['score']);
        $strongest = $rows[0];
        $weakest = $rows[count($rows) - 1];

        return [
            $strongest['phrase'],
            count($rows) > 1 && $weakest['score'] < $strongest['score'] ? $weakest['phrase'] : null,
        ];
    }
}
