<?php

declare(strict_types=1);

use App\Support\PhoneNumber;

test('menormalisasi berbagai format nomor HP Indonesia ke awalan 08', function (?string $input, ?string $expected): void {
    expect(PhoneNumber::normalize($input))->toBe($expected);
})->with([
    'awalan 08 dengan spasi dan tanda hubung' => ['0812-3456 7890', '081234567890'],
    'awalan +62' => ['+62 812-3456-7890', '081234567890'],
    'awalan 62 tanpa plus' => ['6281234567890', '081234567890'],
    'awalan 8 langsung' => ['81234567890', '081234567890'],
    'dengan tanda kurung dan titik' => ['(0812).3456.7890', '081234567890'],
    'null tetap null' => [null, null],
    'string kosong menjadi null' => ['   ', null],
]);
