<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PointCategory;
use App\Models\PointRule;
use Illuminate\Database\Seeder;

class PointRuleSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            ['P-01', 'Tidak membawa alat sholat', PointCategory::Violation, null, 5],
            ['P-02', 'Tidak membawa Juz Amma', PointCategory::Violation, null, 5],
            ['P-03', 'Terlambat masuk sekolah', PointCategory::Violation, null, 3],
            ['P-04', 'Tidak memakai atribut lengkap', PointCategory::Violation, null, 3],
            ['P-05', 'Tidak menjaga kebersihan lingkungan', PointCategory::Violation, null, 2],
            ['PR-01', 'Juara lomba tingkat Sekolah', PointCategory::Achievement, 'Sekolah', 10],
            ['PR-02', 'Juara lomba tingkat Kecamatan', PointCategory::Achievement, 'Kecamatan', 15],
            ['PR-03', 'Juara lomba tingkat Kabupaten', PointCategory::Achievement, 'Kabupaten', 25],
            ['PR-04', 'Juara lomba tingkat Provinsi', PointCategory::Achievement, 'Provinsi', 40],
            ['PR-05', 'Juara lomba tingkat Nasional', PointCategory::Achievement, 'Nasional', 60],
            ['KB-01', 'Membantu guru dan teman', PointCategory::GoodDeed, null, 3],
            ['KB-02', 'Menjaga kebersihan kelas sukarela', PointCategory::GoodDeed, null, 5],
            ['KB-03', 'Menunjukkan sikap jujur dan amanah', PointCategory::GoodDeed, null, 5],
        ];

        foreach ($rules as [$code, $name, $category, $level, $points]) {
            PointRule::query()->updateOrCreate(
                ['unit_id' => null, 'code' => $code],
                [
                    'name' => $name,
                    'category' => $category->value,
                    'level' => $level,
                    'points' => $points,
                    'is_active' => true,
                ]
            );
        }
    }
}
