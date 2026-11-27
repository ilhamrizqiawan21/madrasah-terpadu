<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Profil kurikulum penilaian karakter per unit (ADR-029). Merdeka = bawaan.
 * Nama dimensi dan label skala Merdeka wajib dicocokkan dengan panduan kokurikuler KMA 1503
 * sebelum dipakai; semuanya dapat diubah admin.
 */
enum Curriculum: string
{
    case Merdeka = 'merdeka';
    case K13 = 'k13';

    public function label(): string
    {
        return match ($this) {
            self::Merdeka => 'Kurikulum Merdeka',
            self::K13 => 'Kurikulum 2013',
        };
    }

    /**
     * @return list<array{code: string, label: string, min: int}>
     */
    public function defaultScale(): array
    {
        return match ($this) {
            self::Merdeka => [
                ['code' => 'SAB', 'label' => 'Sangat Berkembang', 'min' => 90],
                ['code' => 'BSH', 'label' => 'Berkembang Sesuai Harapan', 'min' => 75],
                ['code' => 'SB', 'label' => 'Sedang Berkembang', 'min' => 60],
                ['code' => 'MB', 'label' => 'Mulai Berkembang', 'min' => 0],
            ],
            self::K13 => [
                ['code' => 'A', 'label' => 'Sangat Baik', 'min' => 90],
                ['code' => 'B', 'label' => 'Baik', 'min' => 80],
                ['code' => 'C', 'label' => 'Cukup', 'min' => 70],
                ['code' => 'D', 'label' => 'Perlu Bimbingan', 'min' => 0],
            ],
        };
    }

    /**
     * @return list<array{code: string, name: string, components: list<array{source: CharacterSource, weight: int, params: array<string, mixed>}>}>
     */
    public function defaultDimensions(): array
    {
        $violation = ['tiers' => CharacterSource::ViolationPoints->defaultTiers()];
        $merit = ['tiers' => CharacterSource::MeritPoints->defaultTiers()];

        return match ($this) {
            self::Merdeka => [
                [
                    'code' => 'keimanan',
                    'name' => 'Keimanan & ketakwaan',
                    'components' => [
                        ['source' => CharacterSource::HabitsAspect, 'weight' => 100, 'params' => ['aspect' => 'spiritual']],
                    ],
                ],
                [
                    'code' => 'kedisiplinan',
                    'name' => 'Kedisiplinan & kemandirian',
                    'components' => [
                        ['source' => CharacterSource::AttendanceOnTime, 'weight' => 50, 'params' => []],
                        ['source' => CharacterSource::ViolationPoints, 'weight' => 50, 'params' => $violation],
                    ],
                ],
                [
                    'code' => 'akhlak-sosial',
                    'name' => 'Akhlak sosial & kepedulian',
                    'components' => [
                        ['source' => CharacterSource::HabitsAspect, 'weight' => 50, 'params' => ['aspect' => 'social']],
                        ['source' => CharacterSource::MeritPoints, 'weight' => 25, 'params' => $merit],
                        ['source' => CharacterSource::ViolationPoints, 'weight' => 25, 'params' => $violation],
                    ],
                ],
            ],
            self::K13 => [
                [
                    'code' => 'spiritual',
                    'name' => 'Sikap spiritual',
                    'components' => [
                        ['source' => CharacterSource::HabitsAspect, 'weight' => 100, 'params' => ['aspect' => 'spiritual']],
                    ],
                ],
                [
                    'code' => 'sosial',
                    'name' => 'Sikap sosial',
                    'components' => [
                        ['source' => CharacterSource::HabitsAspect, 'weight' => 50, 'params' => ['aspect' => 'social']],
                        ['source' => CharacterSource::MeritPoints, 'weight' => 20, 'params' => $merit],
                        ['source' => CharacterSource::ViolationPoints, 'weight' => 30, 'params' => $violation],
                    ],
                ],
            ],
        };
    }

    /**
     * Templat deskripsi bawaan per kode skala. Penanda: {nama}, {dimensi}, {kuat}, {lemah}.
     *
     * @return array<string, string>
     */
    public function defaultTemplates(): array
    {
        $codes = array_column($this->defaultScale(), 'code');

        return [
            $codes[0] => '{nama} sangat baik dalam {dimensi}, terutama {kuat}.',
            $codes[1] => '{nama} baik dalam {dimensi}, terutama {kuat}; perlu dipertahankan dalam {lemah}.',
            $codes[2] => '{nama} cukup dalam {dimensi}; perlu pendampingan dalam {lemah}.',
            $codes[3] => '{nama} perlu bimbingan dalam {dimensi}, khususnya {lemah}.',
        ];
    }
}
