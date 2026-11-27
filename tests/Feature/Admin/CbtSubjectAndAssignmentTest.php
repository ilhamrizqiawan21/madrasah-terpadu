<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\Role as RoleEnum;
use App\Enums\Semester;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\Unit;
use App\Models\User;
use App\Services\Features\FeatureFlags;
use App\Support\SchoolContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function cbtAdmin(): User
{
    $admin = User::factory()->withTwoFactor()->create();
    $admin->assignRole(RoleEnum::Admin->value);

    return $admin;
}

function cbtTeacher(string $name = 'Guru Mapel'): User
{
    $teacher = User::factory()->create(['name' => $name]);
    $teacher->assignRole(RoleEnum::Teacher->value);

    return $teacher;
}

test('tamu diarahkan ke halaman login saat membuka mapel dan penugasan', function (): void {
    $this->get(route('admin.cbt.subjects.index'))->assertRedirect(route('login'));
});

test('peran tanpa izin ujian ditolak mengakses mapel dan penugasan', function (RoleEnum $role): void {
    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole($role->value);

    $this->actingAs($user)->get(route('admin.cbt.subjects.index'))->assertForbidden();
})->with([
    RoleEnum::Treasurer,
    RoleEnum::Counselor,
    RoleEnum::GateOfficer,
    RoleEnum::CanteenCashier,
    RoleEnum::Guardian,
]);

test('guru dan kepala unit tidak bisa mengelola mapel maupun penugasan', function (RoleEnum $role): void {
    $user = User::factory()->create();
    $user->assignRole($role->value);
    $unit = Unit::factory()->create();
    $year = AcademicYear::factory()->active()->create();
    $classroom = Classroom::factory()->for($unit)->for($year)->create();
    $subject = Subject::factory()->for($unit)->create();
    $teacher = cbtTeacher();
    $assignment = TeachingAssignment::factory()
        ->for($teacher, 'teacher')
        ->for($subject)
        ->for($classroom)
        ->for($year)
        ->create();

    $this->actingAs($user)->get(route('admin.cbt.subjects.index'))->assertOk();
    $this->actingAs($user)->post(route('admin.cbt.subjects.store'), [
        'unit_id' => $unit->id,
        'code' => 'MTK',
        'name' => 'Matematika',
    ])->assertForbidden();
    $this->actingAs($user)->put(route('admin.cbt.subjects.update', $subject), [
        'code' => 'MTK',
        'name' => 'Matematika',
    ])->assertForbidden();
    $this->actingAs($user)->patch(route('admin.cbt.subjects.status.update', $subject), [
        'is_active' => false,
    ])->assertForbidden();
    $this->actingAs($user)->post(route('admin.cbt.assignments.store'), [
        'user_id' => $teacher->id,
        'subject_id' => $subject->id,
        'classroom_id' => $classroom->id,
        'academic_year_id' => $year->id,
    ])->assertForbidden();
    $this->actingAs($user)->delete(route('admin.cbt.assignments.destroy', $assignment))->assertForbidden();

    expect(TeachingAssignment::query()->count())->toBe(1)
        ->and($subject->fresh()->is_active)->toBeTrue();
})->with([
    RoleEnum::Teacher,
    RoleEnum::UnitHead,
]);

test('kunci fitur cbt nonaktif mengembalikan 404 dan menyembunyikan menu ujian', function (): void {
    $admin = cbtAdmin();
    app(FeatureFlags::class)->update([Feature::Cbt->value => false], $admin);

    $this->actingAs($admin)
        ->get(route('admin.cbt.subjects.index'))
        ->assertNotFound();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(function (Assert $page): void {
            $keys = array_column($page->toArray()['props']['navigation'], 'key');

            expect($keys)->not->toContain('ujian');
        });
});

test('admin dapat menambah mengubah dan menonaktifkan mapel per unit', function (): void {
    $mi = Unit::factory()->create(['code' => 'MI']);
    $ra = Unit::factory()->create(['code' => 'RA']);
    $admin = cbtAdmin();

    $this->actingAs($admin)
        ->post(route('admin.cbt.subjects.store'), [
            'unit_id' => $mi->id,
            'code' => 'mtk',
            'name' => 'Matematika',
        ])
        ->assertRedirect();

    $subject = Subject::query()->where('unit_id', $mi->id)->where('code', 'MTK')->firstOrFail();
    expect($subject->name)->toBe('Matematika')
        ->and($subject->is_active)->toBeTrue();

    // Kode sama boleh di unit berbeda, ditolak di unit sama.
    $this->actingAs($admin)
        ->post(route('admin.cbt.subjects.store'), [
            'unit_id' => $mi->id,
            'code' => 'MTK',
            'name' => 'Matematika Duplikat',
        ])
        ->assertSessionHasErrors('code');

    $this->actingAs($admin)
        ->post(route('admin.cbt.subjects.store'), [
            'unit_id' => $ra->id,
            'code' => 'MTK',
            'name' => 'Berhitung',
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->put(route('admin.cbt.subjects.update', $subject), [
            'code' => 'MTK-1',
            'name' => 'Matematika Wajib',
        ])
        ->assertRedirect();

    expect($subject->fresh()->code)->toBe('MTK-1')
        ->and($subject->fresh()->name)->toBe('Matematika Wajib');

    $this->actingAs($admin)
        ->patch(route('admin.cbt.subjects.status.update', $subject), ['is_active' => false])
        ->assertRedirect();

    expect($subject->fresh()->is_active)->toBeFalse()
        ->and(AuditLog::query()->where('subject_type', Subject::class)->where('subject_id', $subject->id)->pluck('action')->all())
        ->toEqualCanonicalizing(['created', 'updated', 'deactivated']);
});

test('admin dapat menambah dan menghapus penugasan guru dengan validasi lintas unit dan tahun', function (): void {
    $mi = Unit::factory()->create(['code' => 'MI']);
    $ra = Unit::factory()->create(['code' => 'RA']);
    $activeYear = AcademicYear::factory()->active()->create(['name' => '2026/2027', 'semester' => Semester::Ganjil]);
    $otherYear = AcademicYear::factory()->create(['name' => '2025/2026', 'semester' => Semester::Genap]);

    $subjectMi = Subject::factory()->for($mi)->create(['code' => 'IPA', 'name' => 'IPA']);
    $subjectRa = Subject::factory()->for($ra)->create(['code' => 'DOA', 'name' => 'Doa']);
    $classroomMi = Classroom::factory()->for($mi)->for($activeYear)->create(['name' => '5-A']);
    $classroomOld = Classroom::factory()->for($mi)->for($otherYear)->create(['name' => '5-A']);

    $teacher = cbtTeacher('Ustadz Ahmad');
    $inactiveTeacher = cbtTeacher('Guru Nonaktif');
    $inactiveTeacher->forceFill(['is_active' => false])->save();
    $nonTeacher = User::factory()->create();
    $nonTeacher->assignRole(RoleEnum::Treasurer->value);

    $admin = cbtAdmin();

    // Tolak non-guru & guru nonaktif.
    $this->actingAs($admin)->post(route('admin.cbt.assignments.store'), [
        'user_id' => $nonTeacher->id,
        'subject_id' => $subjectMi->id,
        'classroom_id' => $classroomMi->id,
        'academic_year_id' => $activeYear->id,
    ])->assertSessionHasErrors('user_id');

    $this->actingAs($admin)->post(route('admin.cbt.assignments.store'), [
        'user_id' => $inactiveTeacher->id,
        'subject_id' => $subjectMi->id,
        'classroom_id' => $classroomMi->id,
        'academic_year_id' => $activeYear->id,
    ])->assertSessionHasErrors('user_id');

    // Tolak mapel beda unit dengan rombel.
    $this->actingAs($admin)->post(route('admin.cbt.assignments.store'), [
        'user_id' => $teacher->id,
        'subject_id' => $subjectRa->id,
        'classroom_id' => $classroomMi->id,
        'academic_year_id' => $activeYear->id,
    ])->assertSessionHasErrors('subject_id');

    // Tolak rombel beda tahun ajaran.
    $this->actingAs($admin)->post(route('admin.cbt.assignments.store'), [
        'user_id' => $teacher->id,
        'subject_id' => $subjectMi->id,
        'classroom_id' => $classroomOld->id,
        'academic_year_id' => $activeYear->id,
    ])->assertSessionHasErrors('classroom_id');

    // Sukses.
    $this->actingAs($admin)->post(route('admin.cbt.assignments.store'), [
        'user_id' => $teacher->id,
        'subject_id' => $subjectMi->id,
        'classroom_id' => $classroomMi->id,
        'academic_year_id' => $activeYear->id,
    ])->assertRedirect();

    $assignment = TeachingAssignment::query()->firstOrFail();

    // Tolak duplikat kombinasi keempatnya.
    $this->actingAs($admin)->post(route('admin.cbt.assignments.store'), [
        'user_id' => $teacher->id,
        'subject_id' => $subjectMi->id,
        'classroom_id' => $classroomMi->id,
        'academic_year_id' => $activeYear->id,
    ])->assertSessionHasErrors('classroom_id');

    // Hapus penugasan.
    $this->actingAs($admin)
        ->delete(route('admin.cbt.assignments.destroy', $assignment))
        ->assertRedirect();

    expect(TeachingAssignment::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('subject_type', TeachingAssignment::class)->pluck('action')->all())
        ->toEqualCanonicalizing(['created', 'deleted']);
});

test('guru hanya melihat mapel dan rombel penugasan miliknya sendiri', function (): void {
    $mi = Unit::factory()->create(['code' => 'MI']);
    $year = AcademicYear::factory()->active()->create(['name' => '2026/2027', 'semester' => Semester::Ganjil]);

    $assignedSubject = Subject::factory()->for($mi)->create(['code' => 'FIQ', 'name' => 'Fiqih']);
    $otherSubject = Subject::factory()->for($mi)->create(['code' => 'BHS', 'name' => 'Bahasa Arab']);

    $assignedClassroom = Classroom::factory()->for($mi)->for($year)->create(['name' => '4-A']);
    $otherClassroom = Classroom::factory()->for($mi)->for($year)->create(['name' => '4-B']);

    $teacherA = cbtTeacher('Ustadzah Aisyah');
    $teacherB = cbtTeacher('Ustadz Bilal');

    TeachingAssignment::factory()->for($teacherA, 'teacher')->for($assignedSubject)->for($assignedClassroom)->for($year)->create();
    TeachingAssignment::factory()->for($teacherB, 'teacher')->for($otherSubject)->for($otherClassroom)->for($year)->create();

    $this->actingAs($teacherA)
        ->withSession([SchoolContext::SESSION_UNIT => 'MI'])
        ->get(route('admin.cbt.subjects.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/cbt/subjects/index')
            ->where('canManage', false)
            ->has('subjects.data', 1)
            ->where('subjects.data.0.code', 'FIQ')
            ->has('assignments.data', 1)
            ->where('assignments.data.0.subject.code', 'FIQ')
            ->where('assignments.data.0.classroom.name', '4-A'));
});
