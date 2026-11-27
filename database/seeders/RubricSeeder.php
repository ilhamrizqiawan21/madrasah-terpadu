<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Rubric;
use App\Models\RubricVersion;
use Illuminate\Database\Seeder;

class RubricSeeder extends Seeder
{
    public function run(): void
    {
        $rubric = Rubric::query()->firstOrCreate(
            ['name' => 'Rubrik Tahfidz Standar', 'unit_id' => null],
            ['is_active' => true]
        );

        $version = RubricVersion::query()->firstOrCreate(
            ['rubric_id' => $rubric->id, 'version_number' => 1],
            ['pass_threshold' => 75.00, 'published_at' => null]
        );

        if ($version->criteria()->count() === 0) {
            $criteria = [
                ['Kelancaran', 'deduction', 40, 100, 2, 75, 1],
                ['Tajwid', 'deduction', 35, 100, 2, 75, 2],
                ['Makhraj', 'deduction', 25, 100, 2, 75, 3],
            ];

            foreach ($criteria as [$name, $mode, $weight, $maxScore, $deduction, $threshold, $order]) {
                $version->criteria()->create([
                    'name' => $name,
                    'mode' => $mode,
                    'weight' => $weight,
                    'max_score' => $maxScore,
                    'deduction_per_error' => $deduction,
                    'pass_threshold' => $threshold,
                    'sort_order' => $order,
                ]);
            }
        }
    }
}
