<?php

declare(strict_types=1);

use App\Enums\ExamQuestionType;
use App\Enums\ExamResultStatus;
use App\Enums\ExamSessionStatus;
use App\Enums\ExamStatus;
use App\Jobs\CloseExpiredExamSessions;
use App\Jobs\ScoreExamSession;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamQuestion;
use App\Models\ExamResult;
use App\Models\ExamSession;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Unit;
use App\Services\Cbt\ExamScoringService;
use Illuminate\Support\Facades\Queue;

test('job penutupan otomatis menutup sesi kedaluwarsa dan sesi terputus lalu menilai lewat antrean', function (): void {
    $unit = Unit::factory()->create();
    $subject = Subject::factory()->for($unit)->create();
    $exam = Exam::factory()->for($subject)->create([
        'status' => ExamStatus::Running,
        'opens_at' => now()->subHours(2),
        'closes_at' => now()->addHour(),
        'weights' => ['mcq' => 2, 'matching' => 2, 'essay_paper' => 4],
    ]);
    $q1 = ExamQuestion::factory()->for($exam)->create([
        'type' => ExamQuestionType::Mcq,
        'answer_key' => ['correct' => 'A'],
    ]);

    $expiredInProgress = ExamSession::factory()->for($exam)->for(Student::factory()->for($unit))->create([
        'status' => ExamSessionStatus::InProgress,
        'deadline_at' => now()->subMinutes(5),
    ]);
    $disconnectedLocked = ExamSession::factory()->for($exam)->for(Student::factory()->for($unit))->create([
        'status' => ExamSessionStatus::Locked,
        'deadline_at' => now()->subMinutes(5),
    ]);
    $forceClosed = ExamSession::factory()->for($exam)->for(Student::factory()->for($unit))->create([
        'status' => ExamSessionStatus::ForceClosed,
        'deadline_at' => now()->addMinutes(20),
    ]);
    $stillActive = ExamSession::factory()->for($exam)->for(Student::factory()->for($unit))->create([
        'status' => ExamSessionStatus::InProgress,
        'deadline_at' => now()->addMinutes(20),
    ]);

    ExamAnswer::query()->create([
        'exam_session_id' => $disconnectedLocked->id,
        'exam_question_id' => $q1->id,
        'answer' => ['selected' => 'A'],
        'is_flagged' => false,
        'client_ts' => now()->subMinutes(10),
        'received_at' => now()->subMinutes(10),
    ]);

    Queue::fake();

    (new CloseExpiredExamSessions)->handle();

    expect($expiredInProgress->fresh()->status)->toBe(ExamSessionStatus::AutoClosed)
        ->and($disconnectedLocked->fresh()->status)->toBe(ExamSessionStatus::AutoClosed)
        ->and($forceClosed->fresh()->status)->toBe(ExamSessionStatus::ForceClosed)
        ->and($stillActive->fresh()->status)->toBe(ExamSessionStatus::InProgress);

    Queue::assertPushed(ScoreExamSession::class, 3);

    // Jalankan ScoreExamSession secara langsung untuk sesi yang terputus.
    (new ScoreExamSession($disconnectedLocked->id))->handle(app(ExamScoringService::class));

    $result = ExamResult::query()->where('exam_session_id', $disconnectedLocked->id)->firstOrFail();
    expect($result->score)->toBe(100)
        ->and($result->status)->toBe(ExamResultStatus::Final);
})->group('mysql');
