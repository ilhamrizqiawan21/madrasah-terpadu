<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Semester;
use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicYear>
 */
class AcademicYearFactory extends Factory
{
    protected $model = AcademicYear::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->unique()->numberBetween(2000, 2090);

        return [
            'name' => $start.'/'.($start + 1),
            'semester' => Semester::Ganjil,
            'starts_on' => "{$start}-07-13",
            'ends_on' => "{$start}-12-19",
            'is_active' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(['is_active' => true]);
    }
}
