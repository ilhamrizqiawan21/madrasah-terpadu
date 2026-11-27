<?php

declare(strict_types=1);

use App\Domain\Character\CharacterScoreCalculator;
use App\Domain\Character\ComponentMeasure;
use App\Enums\CharacterSource;
use App\Enums\Curriculum;

function measure(CharacterSource $source, int $weight, ?float $value, ?float $coverage = 100.0, string $phrase = 'x'): ComponentMeasure
{
    $params = $source->usesTiers() ? ['tiers' => $source->defaultTiers()] : [];

    return new ComponentMeasure($source, $weight, $params, $phrase, $value, $coverage);
}

$merdeka = Curriculum::Merdeka->defaultScale();

test('batas skala capaian kurikulum merdeka', function (float $pct, string $level) use ($merdeka): void {
    $result = (new CharacterScoreCalculator)->calculate([measure(CharacterSource::HabitsAspect, 100, $pct)], $merdeka, 60);

    expect($result->level)->toBe($level);
})->with([
    [100.0, 'SAB'], [90.0, 'SAB'], [89.99, 'BSH'], [75.0, 'BSH'], [74.9, 'SB'], [60.0, 'SB'], [59.99, 'MB'], [0.0, 'MB'],
]);

test('batas skala capaian K-13', function (float $pct, string $level): void {
    $result = (new CharacterScoreCalculator)->calculate([measure(CharacterSource::HabitsAspect, 100, $pct)], Curriculum::K13->defaultScale(), 60);

    expect($result->level)->toBe($level);
})->with([[90.0, 'A'], [80.0, 'B'], [79.0, 'C'], [70.0, 'C'], [69.0, 'D']]);

test('tingkatan poin pelanggaran dan kebaikan', function (CharacterSource $source, int $points, float $score): void {
    $result = (new CharacterScoreCalculator)->calculate([measure($source, 100, (float) $points, null)], [['code' => 'A', 'label' => 'A', 'min' => 0]], 60);

    expect($result->score)->toBe($score);
})->with([
    [CharacterSource::ViolationPoints, 0, 100.0],
    [CharacterSource::ViolationPoints, 10, 90.0],
    [CharacterSource::ViolationPoints, 11, 75.0],
    [CharacterSource::ViolationPoints, 50, 60.0],
    [CharacterSource::ViolationPoints, 51, 40.0],
    [CharacterSource::MeritPoints, 0, 70.0],
    [CharacterSource::MeritPoints, 26, 100.0],
]);

test('rata-rata berbobot dari beberapa komponen', function () use ($merdeka): void {
    $result = (new CharacterScoreCalculator)->calculate([
        measure(CharacterSource::AttendanceOnTime, 50, 80.0),
        measure(CharacterSource::ViolationPoints, 50, 0.0, null),
    ], $merdeka, 60);

    expect($result->score)->toBe(90.0)->and($result->level)->toBe('SAB');
});

test('komponen dengan cakupan di bawah batas dibuang dan bobot dinormalkan ulang', function () use ($merdeka): void {
    $result = (new CharacterScoreCalculator)->calculate([
        measure(CharacterSource::HabitsAspect, 50, 20.0, 40.0, 'pembiasaan sosial'),
        measure(CharacterSource::MeritPoints, 25, 30.0, null, 'perbuatan baik'),
        measure(CharacterSource::ViolationPoints, 25, 0.0, null, 'ketaatan tata tertib'),
    ], $merdeka, 60);

    expect($result->usedWeight)->toBe(50.0)
        ->and($result->score)->toBe(100.0)
        ->and($result->components[0]['used'])->toBeFalse()
        ->and($result->components[0]['reason'])->toBe('Cakupan data di bawah batas')
        ->and($result->components[1]['effective_weight'])->toBe(50.0);
});

test('dimensi menjadi data kurang bila bobot yang tersisa kurang dari 50', function () use ($merdeka): void {
    $result = (new CharacterScoreCalculator)->calculate([
        measure(CharacterSource::HabitsAspect, 60, 95.0, 30.0),
        measure(CharacterSource::ViolationPoints, 40, 0.0, null),
    ], $merdeka, 60);

    expect($result->insufficient())->toBeTrue()
        ->and($result->level)->toBeNull()
        ->and($result->usedWeight)->toBe(40.0);
});

test('observasi guru yang belum diisi tidak dihitung', function () use ($merdeka): void {
    $result = (new CharacterScoreCalculator)->calculate([
        measure(CharacterSource::TeacherObservation, 30, null, null),
        measure(CharacterSource::HabitsAspect, 70, 80.0),
    ], $merdeka, 60);

    expect($result->score)->toBe(80.0)
        ->and($result->components[0]['reason'])->toBe('Belum ada data');

    $filled = (new CharacterScoreCalculator)->calculate([measure(CharacterSource::TeacherObservation, 100, 3.0, null)], $merdeka, 60);
    expect($filled->score)->toBe(80.0);
});

test('deskripsi memakai komponen terkuat dan terlemah', function () use ($merdeka): void {
    $calculator = new CharacterScoreCalculator;
    $result = $calculator->calculate([
        measure(CharacterSource::HabitsAspect, 50, 95.0, 100.0, 'pembiasaan sosial'),
        measure(CharacterSource::ViolationPoints, 50, 30.0, null, 'ketaatan tata tertib'),
    ], $merdeka, 60);

    expect($result->strongest)->toBe('pembiasaan sosial')
        ->and($result->weakest)->toBe('ketaatan tata tertib')
        ->and($calculator->describe('{nama} baik dalam {dimensi}, terutama {kuat}; perlu {lemah}.', 'Dais', 'Akhlak Sosial', $result->strongest, $result->weakest))
        ->toBe('Dais baik dalam akhlak sosial, terutama pembiasaan sosial; perlu ketaatan tata tertib.');
});
