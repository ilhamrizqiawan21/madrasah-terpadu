<?php

declare(strict_types=1);

use App\Enums\ExamQuestionType;
use App\Enums\ExamStatus;
use App\Enums\Role as RoleEnum;
use App\Http\Resources\StudentExamQuestionResource;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');
});

function qBankAdmin(): User
{
    $admin = User::factory()->withTwoFactor()->create();
    $admin->assignRole(RoleEnum::Admin->value);

    return $admin;
}

function qBankTeacher(string $name = 'Guru Soal'): User
{
    $teacher = User::factory()->create(['name' => $name]);
    $teacher->assignRole(RoleEnum::Teacher->value);

    return $teacher;
}

test('idor antar guru pada bank soal mengembalikan 404', function (): void {
    $unit = Unit::factory()->create();
    $year = AcademicYear::factory()->active()->create();
    $subject = Subject::factory()->for($unit)->create();
    $classroom = Classroom::factory()->for($unit)->for($year)->create();

    $teacherA = qBankTeacher('Guru A');
    $teacherB = qBankTeacher('Guru B');

    TeachingAssignment::factory()->for($teacherA, 'teacher')->for($subject)->for($classroom)->for($year)->create();

    $exam = Exam::factory()->for($subject)->create([
        'classroom_ids' => [$classroom->id],
        'created_by' => $teacherA->id,
    ]);

    $this->actingAs($teacherA)->get(route('admin.cbt.questions.index', $exam))->assertOk();
    $this->actingAs($teacherB)->get(route('admin.cbt.questions.index', $exam))->assertNotFound();
    $this->actingAs($teacherB)->post(route('admin.cbt.questions.store', $exam), [
        'type' => ExamQuestionType::Mcq->value,
        'body' => 'Soal ilegal',
        'is_rtl' => false,
        'options' => [
            ['label' => 'A', 'body' => '1'],
            ['label' => 'B', 'body' => '2'],
        ],
        'answer_key' => ['correct' => 'A'],
    ])->assertNotFound();
});

test('guru dapat membuat soal pg menjodohkan dan essay kertas beserta teks arab dan gambar', function (): void {
    $unit = Unit::factory()->create();
    $year = AcademicYear::factory()->active()->create();
    $subject = Subject::factory()->for($unit)->create();
    $classroom = Classroom::factory()->for($unit)->for($year)->create();
    $teacher = qBankTeacher();
    TeachingAssignment::factory()->for($teacher, 'teacher')->for($subject)->for($classroom)->for($year)->create();

    $exam = Exam::factory()->for($subject)->create([
        'classroom_ids' => [$classroom->id],
        'created_by' => $teacher->id,
    ]);

    // PG dengan teks Arab RTL dan gambar PNG.
    $this->actingAs($teacher)->post(route('admin.cbt.questions.store', $exam), [
        'type' => ExamQuestionType::Mcq->value,
        'body' => 'ما معنى <script>alert(1)</script>؟',
        'is_rtl' => true,
        'image' => UploadedFile::fake()->image('soal.png', 200, 200),
        'options' => [
            ['label' => 'A', 'body' => 'Pilihan 1'],
            ['label' => 'B', 'body' => 'Pilihan 2'],
        ],
        'answer_key' => ['correct' => 'A'],
    ])->assertRedirect();

    // Menjodohkan.
    $this->actingAs($teacher)->post(route('admin.cbt.questions.store', $exam), [
        'type' => ExamQuestionType::Matching->value,
        'body' => 'Jodohkan istilah berikut',
        'is_rtl' => false,
        'options' => [
            ['label' => '1', 'body' => 'Wudhu'],
            ['label' => '2', 'body' => 'Tayammum'],
        ],
        'answer_key' => ['pairs' => ['1' => 'Air', '2' => 'Debu']],
    ])->assertRedirect();

    // Essay kertas.
    $this->actingAs($teacher)->post(route('admin.cbt.questions.store', $exam), [
        'type' => ExamQuestionType::EssayPaper->value,
        'body' => 'Jelaskan rukun wudhu di kertas lembar jawaban.',
        'is_rtl' => false,
    ])->assertRedirect();

    expect($exam->questions()->count())->toBe(3);

    $mcq = $exam->questions()->where('type', ExamQuestionType::Mcq->value)->firstOrFail();
    expect($mcq->is_rtl)->toBeTrue()
        ->and($mcq->image_path)->not->toBeNull()
        ->and($mcq->options()->count())->toBe(2);

    // Tolak SVG.
    $this->actingAs($teacher)->post(route('admin.cbt.questions.store', $exam), [
        'type' => ExamQuestionType::EssayPaper->value,
        'body' => 'Soal SVG',
        'is_rtl' => false,
        'image' => UploadedFile::fake()->create('xss.svg', 10, 'image/svg+xml'),
    ])->assertSessionHasErrors('image');

    // Resource siswa TIDAK memuat answer_key.
    $studentPayload = StudentExamQuestionResource::make($mcq->load('options'))->resolve();
    expect($studentPayload)->not->toHaveKey('answer_key');
});

test('soal terkunci setelah ujian dimulai dan koreksi kunci hanya oleh admin dengan alasan dan audit', function (): void {
    $unit = Unit::factory()->create();
    $subject = Subject::factory()->for($unit)->create();
    $teacher = qBankTeacher();
    $admin = qBankAdmin();

    $exam = Exam::factory()->for($subject)->create([
        'status' => ExamStatus::Running,
        'locked_at' => now(),
        'created_by' => $teacher->id,
    ]);
    $question = ExamQuestion::factory()->for($exam)->create([
        'type' => ExamQuestionType::Mcq,
        'answer_key' => ['correct' => 'A'],
    ]);

    // Guru tidak bisa menambah/mengubah/menghapus soal saat terkunci.
    $this->actingAs($teacher)->post(route('admin.cbt.questions.store', $exam), [
        'type' => ExamQuestionType::EssayPaper->value,
        'body' => 'Baru',
        'is_rtl' => false,
    ])->assertForbidden();

    $this->actingAs($teacher)->delete(route('admin.cbt.questions.destroy', [$exam, $question]))->assertForbidden();

    // Guru tidak bisa koreksi kunci.
    $this->actingAs($teacher)->patch(route('admin.cbt.questions.correct-key', [$exam, $question]), [
        'answer_key' => ['correct' => 'B'],
        'reason' => 'Salah ketik',
    ])->assertForbidden();

    // Admin koreksi kunci wajib alasan.
    $this->actingAs($admin)->patch(route('admin.cbt.questions.correct-key', [$exam, $question]), [
        'answer_key' => ['correct' => 'B'],
        'reason' => '',
    ])->assertSessionHasErrors('reason');

    $this->actingAs($admin)->patch(route('admin.cbt.questions.correct-key', [$exam, $question]), [
        'answer_key' => ['correct' => 'B'],
        'reason' => 'Kunci jawaban B yang benar sesuai pembahasan.',
    ])->assertRedirect();

    expect($question->fresh()->answer_key)->toBe(['correct' => 'B'])
        ->and(AuditLog::query()->where('action', 'answer_key_corrected')->where('subject_id', $question->id)->exists())->toBeTrue();
});

test('impor soal dari excel memasukkan soal yang valid', function (): void {
    $unit = Unit::factory()->create();
    $subject = Subject::factory()->for($unit)->create();
    $admin = qBankAdmin();
    $exam = Exam::factory()->for($subject)->create(['created_by' => $admin->id]);

    $path = sys_get_temp_dir().'/cbt_questions_test.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues(['type', 'body', 'is_rtl', 'option_a', 'option_b', 'option_c', 'option_d', 'answer_key']));
    $writer->addRow(Row::fromValues(['mcq', 'Berapa 2 + 2?', '0', '3', '4', '5', '6', 'B']));
    $writer->addRow(Row::fromValues(['essay_paper', 'Jelaskan arti sholat.', '0', '', '', '', '', '']));
    $writer->close();

    $uploaded = new UploadedFile($path, 'soal.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    $this->actingAs($admin)
        ->post(route('admin.cbt.questions.import', $exam), ['file' => $uploaded])
        ->assertRedirect();

    @unlink($path);

    expect($exam->questions()->count())->toBe(2);

    $this->actingAs($admin)
        ->get(route('admin.cbt.questions.index', $exam))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/cbt/questions/index')
            ->has('questions', 2));
});
