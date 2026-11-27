<?php

declare(strict_types=1);

use App\Enums\ExamDeviceMode;
use App\Enums\ExamStatus;
use App\Enums\Feature;
use App\Enums\Role as RoleEnum;
use App\Enums\Semester;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\Unit;
use App\Models\User;
use App\Services\Features\FeatureFlags;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function examAdmin(): User
{
    $admin = User::factory()->withTwoFactor()->create();
    $admin->assignRole(RoleEnum::Admin->value);

    return $admin;
}

function examTeacher(string $name = 'Guru Ujian'): User
{
    $teacher = User::factory()->create(['name' => $name]);
    $teacher->assignRole(RoleEnum::Teacher->value);

    return $teacher;
}

/**
 * @param  list<int>  $classroomIds
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function examPayload(Subject $subject, array $classroomIds, array $overrides = []): array
{
    return [
        'subject_id' => $subject->id,
        'title' => 'PAS Ganjil Matematika',
        'grade' => '5',
        'classroom_ids' => $classroomIds,
        'opens_at' => '2026-12-01 07:30',
        'closes_at' => '2026-12-01 10:00',
        'duration_minutes' => 90,
        'shuffle_questions' => true,
        'shuffle_options' => true,
        'weights' => ['mcq' => 2, 'matching' => 2, 'essay_paper' => 4],
        'access_token' => 'PAS5A',
        'violation_limit' => 3,
        'device_modes' => [ExamDeviceMode::Lab->value, ExamDeviceMode::Tablet->value],
        'show_result_to_guardian' => false,
        ...$overrides,
    ];
}

test('tamu diarahkan ke halaman login saat membuka daftar ujian', function (): void {
    $this->get(route('admin.cbt.exams.index'))->assertRedirect(route('login'));
});

test('peran tanpa izin ujian ditolak dan kepala unit hanya bisa melihat', function (): void {
    $treasurer = User::factory()->withTwoFactor()->create();
    $treasurer->assignRole(RoleEnum::Treasurer->value);
    $unitHead = User::factory()->create();
    $unitHead->assignRole(RoleEnum::UnitHead->value);

    $unit = Unit::factory()->create();
    $year = AcademicYear::factory()->active()->create();
    $subject = Subject::factory()->for($unit)->create();
    $classroom = Classroom::factory()->for($unit)->for($year)->create();

    $this->actingAs($treasurer)->get(route('admin.cbt.exams.index'))->assertForbidden();
    $this->actingAs($unitHead)->get(route('admin.cbt.exams.index'))->assertOk();
    $this->actingAs($unitHead)->post(route('admin.cbt.exams.store'), examPayload($subject, [$classroom->id]))->assertForbidden();
});

test('kunci fitur cbt nonaktif mengembalikan 404 pada rute ujian', function (): void {
    $admin = examAdmin();
    app(FeatureFlags::class)->update([Feature::Cbt->value => false], $admin);

    $this->actingAs($admin)->get(route('admin.cbt.exams.index'))->assertNotFound();
});

test('guru hanya bisa membuat dan mengubah ujian untuk mapel dan rombel yang ditugaskan', function (): void {
    $unit = Unit::factory()->create(['code' => 'MI']);
    $year = AcademicYear::factory()->active()->create(['name' => '2026/2027', 'semester' => Semester::Ganjil]);

    $subjectA = Subject::factory()->for($unit)->create(['code' => 'MTK']);
    $subjectB = Subject::factory()->for($unit)->create(['code' => 'IPA']);
    $classroomA = Classroom::factory()->for($unit)->for($year)->create(['name' => '5-A']);
    $classroomB = Classroom::factory()->for($unit)->for($year)->create(['name' => '5-B']);

    $teacherA = examTeacher('Guru A');
    $teacherB = examTeacher('Guru B');

    TeachingAssignment::factory()->for($teacherA, 'teacher')->for($subjectA)->for($classroomA)->for($year)->create();
    TeachingAssignment::factory()->for($teacherB, 'teacher')->for($subjectB)->for($classroomB)->for($year)->create();

    // Guru A ditolak membuat ujian untuk mapel/rombel yang bukan penugasannya.
    $this->actingAs($teacherA)
        ->post(route('admin.cbt.exams.store'), examPayload($subjectB, [$classroomB->id]))
        ->assertForbidden();

    $this->actingAs($teacherA)
        ->post(route('admin.cbt.exams.store'), examPayload($subjectA, [$classroomA->id, $classroomB->id]))
        ->assertForbidden();

    // Guru A sukses membuat ujian untuk penugasannya.
    $this->actingAs($teacherA)
        ->post(route('admin.cbt.exams.store'), examPayload($subjectA, [$classroomA->id]))
        ->assertRedirect();

    $exam = Exam::query()->firstOrFail();
    expect($exam->status)->toBe(ExamStatus::Draft)
        ->and($exam->created_by)->toBe($teacherA->id)
        ->and($exam->device_modes)->toBe([ExamDeviceMode::Lab->value, ExamDeviceMode::Tablet->value]);

    // Guru B tidak melihat ujian milik Guru A, dan ditolak mengubahnya.
    $this->actingAs($teacherB)
        ->get(route('admin.cbt.exams.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('exams.data', 0));

    $this->actingAs($teacherB)
        ->put(route('admin.cbt.exams.update', $exam), examPayload($subjectA, [$classroomA->id], ['title' => 'Ubah']))
        ->assertForbidden();
});

test('validasi tanggal durasi mode perangkat dan bobot ujian bekerja dengan benar', function (): void {
    $unit = Unit::factory()->create();
    $year = AcademicYear::factory()->active()->create();
    $subject = Subject::factory()->for($unit)->create();
    $classroom = Classroom::factory()->for($unit)->for($year)->create();
    $admin = examAdmin();

    // closes_at <= opens_at ditolak.
    $this->actingAs($admin)
        ->post(route('admin.cbt.exams.store'), examPayload($subject, [$classroom->id], [
            'opens_at' => '2026-12-01 10:00',
            'closes_at' => '2026-12-01 09:00',
        ]))
        ->assertSessionHasErrors('closes_at');

    // duration_minutes < 5 atau > 300 ditolak.
    $this->actingAs($admin)
        ->post(route('admin.cbt.exams.store'), examPayload($subject, [$classroom->id], [
            'duration_minutes' => 2,
        ]))
        ->assertSessionHasErrors('duration_minutes');

    // device_modes kosong atau tidak valid ditolak.
    $this->actingAs($admin)
        ->post(route('admin.cbt.exams.store'), examPayload($subject, [$classroom->id], [
            'device_modes' => [],
        ]))
        ->assertSessionHasErrors('device_modes');

    // Rombel beda unit dengan mapel ditolak.
    $otherUnit = Unit::factory()->create(['code' => 'RA']);
    $otherClassroom = Classroom::factory()->for($otherUnit)->for($year)->create();
    $this->actingAs($admin)
        ->post(route('admin.cbt.exams.store'), examPayload($subject, [$otherClassroom->id]))
        ->assertSessionHasErrors('classroom_ids');
});

test('admin dapat membuat mengubah dan menjadwalkan ujian beserta audit log', function (): void {
    $unit = Unit::factory()->create();
    $year = AcademicYear::factory()->active()->create();
    $subject = Subject::factory()->for($unit)->create();
    $classroom = Classroom::factory()->for($unit)->for($year)->create();
    $admin = examAdmin();

    $this->actingAs($admin)
        ->post(route('admin.cbt.exams.store'), examPayload($subject, [$classroom->id]))
        ->assertRedirect();

    $exam = Exam::query()->firstOrFail();

    $this->actingAs($admin)
        ->put(route('admin.cbt.exams.update', $exam), examPayload($subject, [$classroom->id], [
            'title' => 'PAS Ganjil Matematika Revisi',
            'duration_minutes' => 120,
            'show_result_to_guardian' => true,
        ]))
        ->assertRedirect();

    expect($exam->fresh()->title)->toBe('PAS Ganjil Matematika Revisi')
        ->and($exam->fresh()->duration_minutes)->toBe(120)
        ->and($exam->fresh()->show_result_to_guardian)->toBeTrue();

    $this->actingAs($admin)
        ->patch(route('admin.cbt.exams.status.update', $exam), [
            'status' => ExamStatus::Scheduled->value,
        ])
        ->assertRedirect();

    expect($exam->fresh()->status)->toBe(ExamStatus::Scheduled)
        ->and(AuditLog::query()->where('subject_type', Exam::class)->where('subject_id', $exam->id)->pluck('action')->all())
        ->toEqualCanonicalizing(['created', 'updated', 'status_updated']);
});
