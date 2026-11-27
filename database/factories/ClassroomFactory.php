<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Classroom>
 */
class ClassroomFactory extends Factory
{
    protected $model = Classroom::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $grade = (string) fake()->numberBetween(1, 6);

        return [
            'unit_id' => Unit::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'grade' => $grade,
            'name' => $grade.'-'.fake()->unique()->bothify('??##'),
            'homeroom_teacher_id' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
