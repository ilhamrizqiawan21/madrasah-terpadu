<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BehaviorRecordStatus;
use App\Enums\PointCategory;
use App\Models\AcademicYear;
use App\Models\BehaviorRecord;
use App\Models\PointRule;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BehaviorRecord>
 */
class BehaviorRecordFactory extends Factory
{
    protected $model = BehaviorRecord::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'point_rule_id' => PointRule::factory(),
            'category' => PointCategory::Violation,
            'points' => 5,
            'occurred_on' => now()->toDateString(),
            'note' => null,
            'evidence_path' => null,
            'recorded_by' => User::factory(),
            'status' => BehaviorRecordStatus::Active,
            'voided_by' => null,
            'voided_at' => null,
            'void_reason' => null,
        ];
    }
}
