<?php

declare(strict_types=1);

use App\Domain\Quran\RubricScorer;

it('computes weighted final score, predicate, and pass status for direct and deduction criteria', function (): void {
    $scorer = new RubricScorer;

    $criteria = [
        [
            'id' => 1,
            'mode' => 'deduction',
            'weight' => 40.0,
            'max_score' => 100,
            'deduction_per_error' => 5.0,
            'pass_threshold' => 75.0,
        ],
        [
            'id' => 2,
            'mode' => 'direct',
            'weight' => 60.0,
            'max_score' => 100,
            'deduction_per_error' => 0.0,
            'pass_threshold' => 75.0,
        ],
    ];

    // Criterion 1: 2 mistakes * 5 = 10 -> 90; Criterion 2: 90 direct -> weighted = 90.0 (A, passed)
    $res = $scorer->score($criteria, [1 => 2, 2 => 90], 75.0);
    expect($res['final_score'])->toBe(90.0)
        ->and($res['predicate'])->toBe('A')
        ->and($res['passed'])->toBeTrue();

    // Criterion 1: 25 mistakes * 5 -> clamped to 0; Criterion 2: 80 -> 0*0.4 + 80*0.6 = 48.0 (D, failed)
    $fail = $scorer->score($criteria, [1 => 25, 2 => 80], 75.0);
    expect($fail['final_score'])->toBe(48.0)
        ->and($fail['predicate'])->toBe('D')
        ->and($fail['passed'])->toBeFalse();
});

it('rejects out of range direct scores or negative deduction mistakes', function (): void {
    $scorer = new RubricScorer;
    $criteria = [
        ['id' => 1, 'mode' => 'direct', 'weight' => 100.0, 'max_score' => 100, 'deduction_per_error' => 0.0, 'pass_threshold' => 75.0],
    ];

    expect(fn () => $scorer->score($criteria, [1 => 105], 75.0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $scorer->score($criteria, [1 => -1], 75.0))->toThrow(InvalidArgumentException::class);
});
