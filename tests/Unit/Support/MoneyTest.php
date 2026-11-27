<?php

declare(strict_types=1);

use App\Support\Money;

test('memformat rupiah untuk nol, ribuan, jutaan, dan negatif', function (int $amount, string $expected): void {
    expect(Money::format($amount))->toBe($expected);
})->with([
    'nol' => [0, 'Rp 0'],
    'satuan' => [500, 'Rp 500'],
    'ribuan' => [8500, 'Rp 8.500'],
    'jutaan' => [1250000, 'Rp 1.250.000'],
    'negatif kecil' => [-1, '-Rp 1'],
    'negatif ribuan' => [-50000, '-Rp 50.000'],
    'negatif jutaan' => [-2500000, '-Rp 2.500.000'],
]);
