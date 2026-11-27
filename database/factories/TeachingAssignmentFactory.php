<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeachingAssignment>
 */
class TeachingAssignmentFactory extends Factory
{
    protected $model = TeachingAssignment::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'subject_id' => Subject::factory(),
            'classroom_id' => Classroom::factory(),
            'academic_year_id' => AcademicYear::factory(),
        ];
    }
}
