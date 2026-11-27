<?php

declare(strict_types=1);

use App\Enums\ExamDeviceMode;
use App\Enums\ExamSessionStatus;
use App\Enums\ExamStatus;
use App\Enums\Role as RoleEnum;
use App\Jobs\ScoreExamSession;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamSession;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function monitorTeacher(string $name = 'Pengawas'): User
{
    $teacher = User::factory()->create(['name' => $name]);
    $teacher->assignRole(RoleEnum::Teacher->value);

    return $teacher;
}

test('monitor pengawas menolak guru lain dengan 404 dan berjalan tanpa n+1', function (): void {
    $unit = Unit::factory()->create();
    $year = AcademicYear::factory()->active()->create();
    $classroom = Classroom::factory()->for($unit)->for($year)->create();
    $subject = Subject::factory()->for($unit)->create();
    $owner = monitorTeacher('Guru Pengampu');
    $other = monitorTeacher('Guru Lain');
    TeachingAssignment::factory()->for($owner, 'teacher')->for($subject)->for($classroom)->for($year)->create();

    $exam = Exam::factory()->for($subject)->create([
        'classroom_ids' => [$classroom->id],
        'status' => ExamStatus::Running,
        'created_by' => $owner->id,
    ]);
    ExamQuestion::factory()->for($exam)->create();

    foreach (range(1, 5) as $idx) {
        $student = Student::factory()->for($unit)->create(['nis' => '202660'.$idx]);
        Enrollment::factory()->for($student)->for($classroom)->for($year)->create();
        if ($idx <= 3) {
            ExamSession::factory()->for($exam)->for($student)->create([
                'device_type' => ExamDeviceMode::Lab->value,
                'status' => ExamSessionStatus::InProgress,
            ]);
        }
    }

    // Guru lain ditolak (404).
    $this->actingAs($other)->get(route('admin.cbt.monitor.index', $exam))->assertNotFound();
    $this->actingAs($other)->getJson(route('admin.cbt.monitor.data', $exam))->assertNotFound();

    // Hitung jumlah kueri konstan (tanpa N+1).
    DB::enableQueryLog();
    $this->actingAs($owner)
        ->getJson(route('admin.cbt.monitor.data', $exam))
        ->assertOk()
        ->assertJsonPath('total_questions', 1)
        ->assertJsonCount(5, 'students');
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queryCount)->toBeLessThanOrEqual(10);
});

test('pengawas dapat membuka kunci menambah waktu dan memaksa selesai dengan audit', function (): void {
    $unit = Unit::factory()->create();
    $year = AcademicYear::factory()->active()->create();
    $classroom = Classroom::factory()->for($unit)->for($year)->create();
    $subject = Subject::factory()->for($unit)->create();
    $owner = monitorTeacher();
    TeachingAssignment::factory()->for($owner, 'teacher')->for($subject)->for($classroom)->for($year)->create();

    $exam = Exam::factory()->for($subject)->create([
        'classroom_ids' => [$classroom->id],
        'status' => ExamStatus::Running,
        'created_by' => $owner->id,
    ]);
    $student = Student::factory()->for($unit)->create();
    $session = ExamSession::factory()->for($exam)->for($student)->create([
        'status' => ExamSessionStatus::Locked,
        'extra_minutes' => 0,
    ]);

    // Buka kunci.
    $this->actingAs($owner)
        ->post(route('admin.cbt.monitor.unlock', [$exam, $session]))
        ->assertRedirect();
    expect($session->fresh()->status)->toBe(ExamSessionStatus::InProgress);

    // Tambah waktu.
    $this->actingAs($owner)
        ->post(route('admin.cbt.monitor.extra-time', [$exam, $session]), ['minutes' => 15])
        ->assertRedirect();
    expect($session->fresh()->extra_minutes)->toBe(15);

    // Paksa selesai.
    Queue::fake();
    $this->actingAs($owner)
        ->post(route('admin.cbt.monitor.force-close', [$exam, $session]))
        ->assertRedirect();

    expect($session->fresh()->status)->toBe(ExamSessionStatus::ForceClosed)
        ->and(AuditLog::query()->where('subject_type', ExamSession::class)->where('subject_id', $session->id)->pluck('action')->all())
        ->toEqualCanonicalizing(['exam_session_unlocked', 'exam_session_extra_time', 'exam_session_force_closed']);

    Queue::assertPushed(ScoreExamSession::class);
});
