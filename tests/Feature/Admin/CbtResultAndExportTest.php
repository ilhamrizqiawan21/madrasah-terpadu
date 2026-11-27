<?php

declare(strict_types=1);

use App\Enums\ExamResultStatus;
use App\Enums\ExamSessionStatus;
use App\Enums\Role as RoleEnum;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\ExamSession;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use OpenSpout\Reader\XLSX\Reader;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('ekspor excel hasil ujian menetralkan formula injection dan memfilter rombel', function (): void {
    $unit = Unit::factory()->create();
    $year = AcademicYear::factory()->active()->create();
    $classA = Classroom::factory()->for($unit)->for($year)->create(['name' => '5-A']);
    $classB = Classroom::factory()->for($unit)->for($year)->create(['name' => '5-B']);
    $subject = Subject::factory()->for($unit)->create();

    $teacher = User::factory()->create();
    $teacher->assignRole(RoleEnum::Teacher->value);
    $otherTeacher = User::factory()->create();
    $otherTeacher->assignRole(RoleEnum::Teacher->value);
    TeachingAssignment::factory()->for($teacher, 'teacher')->for($subject)->for($classA)->for($year)->create();

    $exam = Exam::factory()->for($subject)->create([
        'classroom_ids' => [$classA->id, $classB->id],
        'created_by' => $teacher->id,
    ]);

    $studentA = Student::factory()->for($unit)->create(['nis' => '2026701', 'name' => '=CMD("calc")']);
    Enrollment::factory()->for($studentA)->for($classA)->for($year)->create();
    $sessionA = ExamSession::factory()->for($exam)->for($studentA)->create(['status' => ExamSessionStatus::Submitted]);
    ExamResult::query()->create([
        'exam_session_id' => $sessionA->id,
        'auto_points' => 8,
        'essay_points' => 0,
        'max_points' => 10,
        'score' => 80,
        'status' => ExamResultStatus::Final,
        'computed_at' => now(),
    ]);

    $studentB = Student::factory()->for($unit)->create(['nis' => '2026702', 'name' => 'Siswa B']);
    Enrollment::factory()->for($studentB)->for($classB)->for($year)->create();
    $sessionB = ExamSession::factory()->for($exam)->for($studentB)->create(['status' => ExamSessionStatus::Submitted]);
    ExamResult::query()->create([
        'exam_session_id' => $sessionB->id,
        'auto_points' => 9,
        'essay_points' => 0,
        'max_points' => 10,
        'score' => 90,
        'status' => ExamResultStatus::Final,
        'computed_at' => now(),
    ]);

    // Guru lain ditolak (404).
    $this->actingAs($otherTeacher)->get(route('admin.cbt.results.index', $exam))->assertNotFound();

    // Filter rombel A hanya menampilkan 1 siswa.
    $this->actingAs($teacher)
        ->get(route('admin.cbt.results.index', [$exam, 'classroom_id' => $classA->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/cbt/results/index')
            ->has('results', 1));

    // Ekspor Excel menetralkan awalan '='.
    $response = $this->actingAs($teacher)
        ->get(route('admin.cbt.results.export', [$exam, 'classroom_id' => $classA->id]));
    $response->assertOk();

    $file = $response->getFile()->getPathname();
    $reader = new Reader;
    $reader->open($file);
    $rows = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
        break;
    }
    $reader->close();

    expect(count($rows))->toBe(2)
        ->and($rows[1][1])->toBe("'=CMD(\"calc\")");
});

test('wali hanya melihat hasil ujian anaknya sendiri yang final dan diizinkan tampil', function (): void {
    $unit = Unit::factory()->create();
    $subject = Subject::factory()->for($unit)->create();

    $guardianUser = User::factory()->create();
    $guardianUser->assignRole(RoleEnum::Guardian->value);
    $guardian = Guardian::factory()->for($guardianUser)->create();

    $ownChild = Student::factory()->for($unit)->create(['nis' => '2026710']);
    $otherChild = Student::factory()->for($unit)->create(['nis' => '2026711']);
    $guardian->students()->attach($ownChild->id, ['relation' => 'ayah', 'is_primary' => true]);

    // Ujian 1: tampil ke wali + final -> MUNCUL.
    $examShown = Exam::factory()->for($subject)->create(['title' => 'Ujian Tampil', 'show_result_to_guardian' => true]);
    $s1 = ExamSession::factory()->for($examShown)->for($ownChild)->create(['status' => ExamSessionStatus::Submitted]);
    ExamResult::query()->create([
        'exam_session_id' => $s1->id,
        'auto_points' => 8,
        'essay_points' => 0,
        'max_points' => 10,
        'score' => 80,
        'status' => ExamResultStatus::Final,
        'computed_at' => now(),
    ]);

    // Ujian 2: tampil ke wali + awaiting_essay -> TIDAK MUNCUL.
    $examPending = Exam::factory()->for($subject)->create(['title' => 'Ujian Menunggu Essay', 'show_result_to_guardian' => true]);
    $s2 = ExamSession::factory()->for($examPending)->for($ownChild)->create(['status' => ExamSessionStatus::Submitted]);
    ExamResult::query()->create([
        'exam_session_id' => $s2->id,
        'auto_points' => 4,
        'essay_points' => null,
        'max_points' => 10,
        'score' => null,
        'status' => ExamResultStatus::AwaitingEssay,
        'computed_at' => now(),
    ]);

    // Ujian 3: tidak tampil ke wali + final -> TIDAK MUNCUL.
    $examHidden = Exam::factory()->for($subject)->create(['title' => 'Ujian Tersembunyi', 'show_result_to_guardian' => false]);
    $s3 = ExamSession::factory()->for($examHidden)->for($ownChild)->create(['status' => ExamSessionStatus::Submitted]);
    ExamResult::query()->create([
        'exam_session_id' => $s3->id,
        'auto_points' => 9,
        'essay_points' => 0,
        'max_points' => 10,
        'score' => 90,
        'status' => ExamResultStatus::Final,
        'computed_at' => now(),
    ]);

    // Anak wali lain -> 404.
    $this->actingAs($guardianUser)
        ->get(route('guardian.students.exams.index', $otherChild))
        ->assertNotFound();

    // Anak sendiri -> hanya melihat Ujian Tampil.
    $this->actingAs($guardianUser)
        ->get(route('guardian.students.exams.index', $ownChild))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('guardian/exams')
            ->has('results', 1)
            ->where('results.0.exam_title', 'Ujian Tampil')
            ->where('results.0.score', 80));
});
