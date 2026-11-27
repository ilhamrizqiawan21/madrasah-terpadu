<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\MemorizationTarget;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemorizationTarget>
 */
class MemorizationTargetFactory extends Factory
{
    protected $model = MemorizationTarget::class;

    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'grade' => '1',
            'academic_year_id' => AcademicYear::factory(),
            'kind' => 'quran',
            'title' => 'Target Hafalan',
            'ranges' => [['surah' => 114, 'ayah_from' => 1, 'ayah_to' => 6]],
            'material_ids' => null,
            'is_active' => true,
        ];
    }
}
