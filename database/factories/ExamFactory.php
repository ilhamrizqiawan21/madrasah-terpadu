<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ExamDeviceMode;
use App\Enums\ExamStatus;
use App\Models\Exam;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exam>
 */
class ExamFactory extends Factory
{
    protected $model = Exam::class;

    public function definition(): array
    {
        return [
            'exam_period_id' => null,
            'subject_id' => Subject::factory(),
            'title' => 'Ujian '.fake()->sentence(2),
            'grade' => '5',
            'classroom_ids' => [],
            'opens_at' => now()->addHour(),
            'closes_at' => now()->addHours(3),
            'duration_minutes' => 90,
            'shuffle_questions' => true,
            'shuffle_options' => true,
            'weights' => ['mcq' => 2, 'matching' => 2, 'essay_paper' => 4],
            'access_token' => null,
            'violation_limit' => 3,
            'device_modes' => [ExamDeviceMode::Lab->value, ExamDeviceMode::Tablet->value],
            'show_result_to_guardian' => false,
            'status' => ExamStatus::Draft,
            'locked_at' => null,
            'created_by' => User::factory(),
        ];
    }
}
