<?php

declare(strict_types=1);

use App\Enums\ExamQuestionType;
use App\Enums\ExamResultStatus;
use App\Enums\ExamSessionStatus;
use App\Enums\Role as RoleEnum;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamEssayScore;
use App\Models\ExamQuestion;
use App\Models\ExamResult;
use App\Models\ExamSession;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function essayTeacher(string $name = 'Guru Essay'): User
{
    $teacher = User::factory()->create(['name' => $name]);
    $teacher->assignRole(RoleEnum::Teacher->value);

    return $teacher;
}

test('hanya guru pengampu atau admin yang bisa menilai essay dan nilai di luar batas ditolak', function (): void {
    $unit = Unit::factory()->create();
    $year = AcademicYear::factory()->active()->create();
    $classroom = Classroom::factory()->for($unit)->for($year)->create();
    $subject = Subject::factory()->for($unit)->create();

    $owner = essayTeacher('Guru Pengampu');
    $otherTeacher = essayTeacher('Guru Lain');
    TeachingAssignment::factory()->for($owner, 'teacher')->for($subject)->for($classroom)->for($year)->create();

    $exam = Exam::factory()->for($subject)->create([
        'classroom_ids' => [$classroom->id],
        'weights' => ['mcq' => 2, 'matching' => 2, 'essay_paper' => 4],
        'created_by' => $owner->id,
    ]);
    $mcq = ExamQuestion::factory()->for($exam)->create([
        'type' => ExamQuestionType::Mcq,
        'answer_key' => ['correct' => 'A'],
    ]);
    $essay = ExamQuestion::factory()->for($exam)->create([
        'type' => ExamQuestionType::EssayPaper,
        'sort_order' => 2,
        'answer_key' => null,
    ]);

    $student = Student::factory()->for($unit)->create(['nis' => '2026501']);
    $session = ExamSession::factory()->for($exam)->for($student)->create([
        'status' => ExamSessionStatus::Submitted,
    ]);
    ExamAnswer::query()->create([
        'exam_session_id' => $session->id,
        'exam_question_id' => $mcq->id,
        'answer' => ['selected' => 'A'],
        'is_flagged' => false,
        'client_ts' => now(),
        'received_at' => now(),
    ]);

    // Guru lain ditolak (404).
    $this->actingAs($otherTeacher)->get(route('admin.cbt.essay.index', $exam))->assertNotFound();
    $this->actingAs($otherTeacher)->post(route('admin.cbt.essay.store', $exam), [
        'exam_session_id' => $session->id,
        'scores' => [['exam_question_id' => $essay->id, 'score' => 3]],
    ])->assertNotFound();

    // Nilai di luar 0..4 ditolak.
    $this->actingAs($owner)->post(route('admin.cbt.essay.store', $exam), [
        'exam_session_id' => $session->id,
        'scores' => [['exam_question_id' => $essay->id, 'score' => 5]],
    ])->assertSessionHasErrors('scores.0.score');

    $this->actingAs($owner)->post(route('admin.cbt.essay.store', $exam), [
        'exam_session_id' => $session->id,
        'scores' => [['exam_question_id' => $essay->id, 'score' => -1]],
    ])->assertSessionHasErrors('scores.0.score');

    // Nilai valid -> status result menjadi final.
    $this->actingAs($owner)->post(route('admin.cbt.essay.store', $exam), [
        'exam_session_id' => $session->id,
        'scores' => [['exam_question_id' => $essay->id, 'score' => 4]],
    ])->assertRedirect();

    $result = ExamResult::query()->where('exam_session_id', $session->id)->firstOrFail();
    expect($result->status)->toBe(ExamResultStatus::Final)
        ->and($result->essay_points)->toBe(4)
        ->and($result->score)->toBe(100)
        ->and(ExamEssayScore::query()->where('exam_session_id', $session->id)->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'essay_scored')->exists())->toBeTrue();
});

test('impor nilai essay dari excel memperbarui nilai dan status hasil menjadi final', function (): void {
    $unit = Unit::factory()->create();
    $subject = Subject::factory()->for($unit)->create();
    $owner = essayTeacher();

    $exam = Exam::factory()->for($subject)->create([
        'weights' => ['mcq' => 2, 'matching' => 2, 'essay_paper' => 4],
        'created_by' => $owner->id,
    ]);
    $essay = ExamQuestion::factory()->for($exam)->create([
        'type' => ExamQuestionType::EssayPaper,
        'sort_order' => 1,
        'answer_key' => null,
    ]);

    $student = Student::factory()->for($unit)->create(['nis' => '2026502']);
    $session = ExamSession::factory()->for($exam)->for($student)->create([
        'status' => ExamSessionStatus::Submitted,
    ]);

    $path = sys_get_temp_dir().'/cbt_essay_scores.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues(['nis', 'question_order', 'score']));
    $writer->addRow(Row::fromValues(['2026502', '1', '3']));
    $writer->close();

    $uploaded = new UploadedFile($path, 'essay.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    $this->actingAs($owner)
        ->post(route('admin.cbt.essay.import', $exam), ['file' => $uploaded])
        ->assertRedirect();

    @unlink($path);

    $result = ExamResult::query()->where('exam_session_id', $session->id)->firstOrFail();
    expect($result->status)->toBe(ExamResultStatus::Final)
        ->and($result->essay_points)->toBe(3)
        ->and($result->score)->toBe(75);
});
