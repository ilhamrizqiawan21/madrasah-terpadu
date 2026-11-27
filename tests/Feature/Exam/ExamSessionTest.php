<?php

declare(strict_types=1);

use App\Enums\ExamDeviceMode;
use App\Enums\ExamQuestionType;
use App\Enums\ExamSessionStatus;
use App\Enums\ExamStatus;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamQuestion;
use App\Models\ExamSession;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Unit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function createExamStudent(Unit $unit, Classroom $classroom, AcademicYear $year, string $nis = '2026100'): Student
{
    $student = Student::factory()->for($unit)->create(['nis' => $nis, 'name' => 'Siswa '.$nis]);
    Enrollment::factory()->for($student)->for($classroom)->for($year)->create();

    return $student;
}

function createOpenExam(Subject $subject, Classroom $classroom, array $overrides = []): Exam
{
    return Exam::factory()->for($subject)->create([
        'classroom_ids' => [$classroom->id],
        'opens_at' => now()->subMinutes(10),
        'closes_at' => now()->addHours(2),
        'duration_minutes' => 60,
        'status' => ExamStatus::Running,
        'device_modes' => [ExamDeviceMode::Lab->value, ExamDeviceMode::Phone->value],
        'access_token' => null,
        ...$overrides,
    ]);
}

test('siswa dapat memulai menyambung ulang dan mengirim jawaban batch idempoten tanpa kunci jawaban', function (): void {
    $unit = Unit::factory()->create();
    $year = AcademicYear::factory()->active()->create();
    $classroom = Classroom::factory()->for($unit)->for($year)->create();
    $subject = Subject::factory()->for($unit)->create();
    $student = createExamStudent($unit, $classroom, $year);
    $exam = createOpenExam($subject, $classroom);

    $q1 = ExamQuestion::factory()->for($exam)->create([
        'type' => ExamQuestionType::Mcq,
        'sort_order' => 1,
        'answer_key' => ['correct' => 'A'],
    ]);
    $q2 = ExamQuestion::factory()->for($exam)->create([
        'type' => ExamQuestionType::Mcq,
        'sort_order' => 2,
        'answer_key' => ['correct' => 'B'],
    ]);

    Auth::guard('student')->login($student);

    // Mulai sesi.
    $this->post(route('exam.sessions.start', $exam), [
        'device_type' => ExamDeviceMode::Lab->value,
    ])->assertRedirect(route('exam.sessions.show', $exam));

    $session = ExamSession::query()->where('exam_id', $exam->id)->where('student_id', $student->id)->firstOrFail();
    $firstToken = $session->device_token;

    // Halaman kerjakan mengirim soal tanpa answer_key.
    $firstQuestionOrder = [];
    $this->get(route('exam.sessions.show', $exam))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$firstQuestionOrder): void {
            $page->component('exam/session')
                ->has('questions', 2);

            $questions = $page->toArray()['props']['questions'];
            foreach ($questions as $q) {
                expect($q)->not->toHaveKey('answer_key');
                $firstQuestionOrder[] = $q['id'];
            }
        });

    // Simpan jawaban batch (idempoten).
    $this->postJson(route('exam.sessions.answers', $exam), [
        'device_token' => $firstToken,
        'answers' => [
            [
                'exam_question_id' => $q1->id,
                'answer' => ['selected' => 'A'],
                'is_flagged' => true,
                'client_ts' => now()->toIso8601String(),
            ],
        ],
    ])->assertOk()->assertJsonPath('accepted', 1);

    // Kirim ulang jawaban yang sama → tetap 1 baris di exam_answers.
    $this->postJson(route('exam.sessions.answers', $exam), [
        'device_token' => $firstToken,
        'answers' => [
            [
                'exam_question_id' => $q1->id,
                'answer' => ['selected' => 'B'],
                'is_flagged' => false,
                'client_ts' => now()->toIso8601String(),
            ],
        ],
    ])->assertOk();

    expect(ExamAnswer::query()->where('exam_session_id', $session->id)->count())->toBe(1)
        ->and(ExamAnswer::query()->where('exam_session_id', $session->id)->firstOrFail()->answer)->toBe(['selected' => 'B']);

    // Sambung ulang (take-over) mempertahankan urutan soal dan menolak perangkat lama (409).
    $this->post(route('exam.sessions.start', $exam), [
        'device_type' => ExamDeviceMode::Lab->value,
    ])->assertRedirect(route('exam.sessions.show', $exam));

    $session->refresh();
    expect($session->takeover_count)->toBe(1)
        ->and($session->device_token)->not->toBe($firstToken);

    $this->postJson(route('exam.sessions.answers', $exam), [
        'device_token' => $firstToken,
        'answers' => [
            [
                'exam_question_id' => $q2->id,
                'answer' => ['selected' => 'B'],
                'is_flagged' => false,
                'client_ts' => now()->toIso8601String(),
            ],
        ],
    ])->assertStatus(409);

    $secondQuestionOrder = [];
    $this->get(route('exam.sessions.show', $exam))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$secondQuestionOrder): void {
            $questions = $page->toArray()['props']['questions'];
            foreach ($questions as $q) {
                $secondQuestionOrder[] = $q['id'];
            }
        });

    expect($secondQuestionOrder)->toBe($firstQuestionOrder);

    // Selesai ujian.
    $this->post(route('exam.sessions.submit', $exam), [
        'device_token' => $session->device_token,
    ])->assertRedirect(route('exam.index'));

    expect($session->fresh()->status)->toBe(ExamSessionStatus::Submitted);
});

test('jawaban setelah grace period 120 detik ditolak', function (): void {
    $unit = Unit::factory()->create();
    $year = AcademicYear::factory()->active()->create();
    $classroom = Classroom::factory()->for($unit)->for($year)->create();
    $subject = Subject::factory()->for($unit)->create();
    $student = createExamStudent($unit, $classroom, $year);
    $exam = createOpenExam($subject, $classroom);
    $q1 = ExamQuestion::factory()->for($exam)->create();

    $session = ExamSession::factory()->for($exam)->for($student)->create([
        'started_at' => now()->subMinutes(70),
        'deadline_at' => now()->subSeconds(125),
        'status' => ExamSessionStatus::InProgress,
    ]);

    Auth::guard('student')->login($student);

    $this->postJson(route('exam.sessions.answers', $exam), [
        'device_token' => $session->device_token,
        'answers' => [
            [
                'exam_question_id' => $q1->id,
                'answer' => ['selected' => 'A'],
                'is_flagged' => false,
                'client_ts' => now()->toIso8601String(),
            ],
        ],
    ])->assertStatus(422);
});

test('validasi jendela token dan mode perangkat saat mulai sesi', function (): void {
    $unit = Unit::factory()->create();
    $year = AcademicYear::factory()->active()->create();
    $classroom = Classroom::factory()->for($unit)->for($year)->create();
    $subject = Subject::factory()->for($unit)->create();
    $student = createExamStudent($unit, $classroom, $year);
    $exam = createOpenExam($subject, $classroom, [
        'access_token' => 'TOKEN123',
        'device_modes' => [ExamDeviceMode::Lab->value],
    ]);

    Auth::guard('student')->login($student);

    // Mode perangkat tidak diizinkan.
    $this->post(route('exam.sessions.start', $exam), [
        'device_type' => ExamDeviceMode::Phone->value,
        'access_token' => 'TOKEN123',
    ])->assertSessionHasErrors('device_type');

    // Token salah.
    $this->post(route('exam.sessions.start', $exam), [
        'device_type' => ExamDeviceMode::Lab->value,
        'access_token' => 'SALAH',
    ])->assertSessionHasErrors('access_token');
});

test('dua permintaan mulai bersamaan hanya menghasilkan satu sesi ujian', function (): void {
    $unit = Unit::factory()->create();
    $year = AcademicYear::factory()->active()->create();
    $classroom = Classroom::factory()->for($unit)->for($year)->create();
    $subject = Subject::factory()->for($unit)->create();
    $student = createExamStudent($unit, $classroom, $year);
    $exam = createOpenExam($subject, $classroom);

    Auth::guard('student')->login($student);

    DB::transaction(function () use ($exam): void {
        $this->post(route('exam.sessions.start', $exam), [
            'device_type' => ExamDeviceMode::Lab->value,
        ])->assertRedirect();

        $this->post(route('exam.sessions.start', $exam), [
            'device_type' => ExamDeviceMode::Lab->value,
        ])->assertRedirect();
    });

    expect(ExamSession::query()->where('exam_id', $exam->id)->where('student_id', $student->id)->count())->toBe(1);
})->group('mysql');
