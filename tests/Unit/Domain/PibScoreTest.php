<?php

declare(strict_types=1);

use App\Domain\Quran\PibScore;

it('calculates pib score as 90 minus mistakes and rejects out of range mistakes', function (): void {
    expect(PibScore::calculate(0))->toBe(90)
        ->and(PibScore::calculate(12))->toBe(78)
        ->and(PibScore::calculate(90))->toBe(0);

    expect(fn () => PibScore::calculate(-1))->toThrow(InvalidArgumentException::class)
        ->and(fn () => PibScore::calculate(91))->toThrow(InvalidArgumentException::class);
});
