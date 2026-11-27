<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ExamDeviceMode;
use App\Enums\ExamSessionStatus;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamSession>
 */
class ExamSessionFactory extends Factory
{
    protected $model = ExamSession::class;

    public function definition(): array
    {
        return [
            'exam_id' => Exam::factory(),
            'student_id' => Student::factory(),
            'started_at' => now(),
            'deadline_at' => now()->addHour(),
            'extra_minutes' => 0,
            'status' => ExamSessionStatus::InProgress,
            'device_type' => ExamDeviceMode::Lab->value,
            'device_token' => bin2hex(random_bytes(32)),
            'shuffle_seed' => random_int(1, 2147483647),
            'last_seen_at' => now(),
            'takeover_count' => 0,
        ];
    }
}
