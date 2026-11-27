<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    protected $model = Enrollment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'classroom_id' => Classroom::factory(),
            // Tahun ajaran selalu mengikuti rombelnya.
            'academic_year_id' => fn (array $attributes): int => Classroom::query()
                ->whereKey($attributes['classroom_id'])->firstOrFail()->academic_year_id,
        ];
    }
}
