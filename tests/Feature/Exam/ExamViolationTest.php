<?php

declare(strict_types=1);

use App\Enums\ExamDeviceMode;
use App\Enums\ExamSessionStatus;
use App\Enums\ExamStatus;
use App\Enums\ExamViolationKind;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\ExamViolation;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Unit;
use Illuminate\Support\Facades\Auth;

test('mode lab dan tablet menghitung pelanggaran dan mengunci sesi setelah batas tercapai', function (string $deviceType): void {
    $unit = Unit::factory()->create();
    $subject = Subject::factory()->for($unit)->create();
    $student = Student::factory()->for($unit)->create();
    $exam = Exam::factory()->for($subject)->create([
        'status' => ExamStatus::Running,
        'violation_limit' => 2,
        'device_modes' => [ExamDeviceMode::Lab->value, ExamDeviceMode::Tablet->value],
    ]);
    $session = ExamSession::factory()->for($exam)->for($student)->create([
        'device_type' => $deviceType,
        'status' => ExamSessionStatus::InProgress,
    ]);

    Auth::guard('student')->login($student);

    // Pelanggaran 1: belum terkunci.
    $this->postJson(route('exam.sessions.violations', $exam), [
        'device_token' => $session->device_token,
        'kind' => ExamViolationKind::TabHidden->value,
    ])->assertOk()
        ->assertJsonPath('counted_violations', 1)
        ->assertJsonPath('locked', false);

    expect($session->fresh()->status)->toBe(ExamSessionStatus::InProgress);

    // Pelanggaran 2: mencapai batas -> terkunci.
    $this->postJson(route('exam.sessions.violations', $exam), [
        'device_token' => $session->device_token,
        'kind' => ExamViolationKind::FullscreenExit->value,
    ])->assertOk()
        ->assertJsonPath('counted_violations', 2)
        ->assertJsonPath('locked', true);

    expect($session->fresh()->status)->toBe(ExamSessionStatus::Locked)
        ->and(ExamViolation::query()->where('exam_session_id', $session->id)->where('counted', true)->count())->toBe(2);
})->with([
    'lab' => ExamDeviceMode::Lab->value,
    'tablet' => ExamDeviceMode::Tablet->value,
]);

test('mode phone hanya mencatat pelanggaran dengan counted false tanpa mengunci sesi', function (): void {
    $unit = Unit::factory()->create();
    $subject = Subject::factory()->for($unit)->create();
    $student = Student::factory()->for($unit)->create();
    $exam = Exam::factory()->for($subject)->create([
        'status' => ExamStatus::Running,
        'violation_limit' => 1,
        'device_modes' => [ExamDeviceMode::Phone->value],
    ]);
    $session = ExamSession::factory()->for($exam)->for($student)->create([
        'device_type' => ExamDeviceMode::Phone->value,
        'status' => ExamSessionStatus::InProgress,
    ]);

    Auth::guard('student')->login($student);

    for ($i = 0; $i < 3; $i++) {
        $this->postJson(route('exam.sessions.violations', $exam), [
            'device_token' => $session->device_token,
            'kind' => ExamViolationKind::TabHidden->value,
        ])->assertOk()
            ->assertJsonPath('counted_violations', 0)
            ->assertJsonPath('locked', false);
    }

    expect($session->fresh()->status)->toBe(ExamSessionStatus::InProgress)
        ->and(ExamViolation::query()->where('exam_session_id', $session->id)->count())->toBe(3)
        ->and(ExamViolation::query()->where('exam_session_id', $session->id)->where('counted', false)->count())->toBe(3);
});
