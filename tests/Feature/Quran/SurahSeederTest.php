<?php

declare(strict_types=1);

use App\Models\Surah;
use Database\Seeders\SurahSeeder;

it('seeds all 114 surahs with exactly 6236 total ayahs', function (): void {
    $this->seed(SurahSeeder::class);

    expect(Surah::query()->count())->toBe(114)
        ->and((int) Surah::query()->sum('ayah_count'))->toBe(6236)
        ->and(Surah::query()->where('number', 1)->firstOrFail()->name_latin)->toBe('Al-Fatihah')
        ->and(Surah::query()->where('number', 114)->firstOrFail()->name_latin)->toBe('An-Nas');
});
