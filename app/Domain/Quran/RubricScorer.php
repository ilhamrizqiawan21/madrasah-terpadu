<?php

declare(strict_types=1);

namespace App\Domain\Quran;

use InvalidArgumentException;

final class RubricScorer
{
    /**
     * @param  list<array{id: int, mode: string, weight: float, max_score: int, deduction_per_error: float, pass_threshold: float}>  $criteria
     * @param  array<int, float|int>  $inputs
     * @return array{final_score: float, predicate: string, passed: bool, criteria_scores: array<int, float>}
     */
    public function score(array $criteria, array $inputs, float $passThreshold = 75.0): array
    {
        if ($criteria === []) {
            throw new InvalidArgumentException('Rubrik harus memiliki minimal satu kriteria.');
        }

        $totalWeight = 0.0;
        $weightedSum = 0.0;
        $criteriaScores = [];

        foreach ($criteria as $criterion) {
            $id = $criterion['id'];
            $maxScore = $criterion['max_score'];
            $weight = $criterion['weight'];

            if ($maxScore <= 0 || $weight <= 0) {
                throw new InvalidArgumentException('Bobot dan skor maksimal kriteria harus > 0.');
            }

            if (! array_key_exists($id, $inputs)) {
                throw new InvalidArgumentException("Nilai kriteria #{$id} belum diisi.");
            }

            $rawInput = (float) $inputs[$id];
            if ($rawInput < 0) {
                throw new InvalidArgumentException('Nilai atau jumlah kesalahan tidak boleh negatif.');
            }

            if ($criterion['mode'] === 'direct') {
                if ($rawInput > $maxScore) {
                    throw new InvalidArgumentException("Nilai langsung tidak boleh melebihi {$maxScore}.");
                }
                $rawScore = $rawInput;
            } else {
                $rawScore = max(0.0, $maxScore - ($rawInput * $criterion['deduction_per_error']));
            }

            $normalized = ($rawScore / $maxScore) * 100.0;
            $criteriaScores[$id] = round($normalized, 2);
            $weightedSum += $normalized * $weight;
            $totalWeight += $weight;
        }

        $finalScore = round($weightedSum / $totalWeight, 2);

        return [
            'final_score' => $finalScore,
            'predicate' => $this->predicate($finalScore),
            'passed' => $finalScore >= $passThreshold,
            'criteria_scores' => $criteriaScores,
        ];
    }

    public function predicate(float $score): string
    {
        if ($score >= 90.0) {
            return 'A';
        }
        if ($score >= 80.0) {
            return 'B';
        }
        if ($score >= 70.0) {
            return 'C';
        }

        return 'D';
    }
}
