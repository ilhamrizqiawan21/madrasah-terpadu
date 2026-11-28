<?php

declare(strict_types=1);

use App\Enums\Role as RoleEnum;
use App\Models\AcademicYear;
use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('guru dapat membuat, mengubah, dan menghapus tugasnya sendiri serta ditolak mengedit tugas guru lain', function (): void {
    $mi = Unit::factory()->create(['code' => 'MI']);
    $year = AcademicYear::factory()->active()->create();
    $classroom = Classroom::factory()->for($mi)->for($year)->create(['name' => '4-A']);
    $subject = Subject::factory()->for($mi)->create(['name' => 'Matematika']);

    $teacher1 = User::factory()->create();
    $teacher1->assignRole(RoleEnum::Teacher->value);

    $teacher2 = User::factory()->create();
    $teacher2->assignRole(RoleEnum::Teacher->value);

    $this->actingAs($teacher1)
        ->get(route('admin.assignments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/elearning/assignments'));

    $this->actingAs($teacher1)
        ->post(route('admin.assignments.store'), [
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'title' => 'PR Pecahan',
            'description' => 'Kerjakan halaman 12.',
            'due_date' => '2026-12-05',
            'due_time' => '20:00',
        ])
        ->assertRedirect();

    $assignment = Assignment::query()->where('title', 'PR Pecahan')->firstOrFail();
    expect(AuditLog::query()->where('action', 'assignment.created')->exists())->toBeTrue();

    // Guru lain tidak boleh mengubah atau menghapus
    $this->actingAs($teacher2)
        ->put(route('admin.assignments.update', $assignment), [
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'title' => 'Diubah Guru Lain',
            'due_date' => '2026-12-06',
            'due_time' => '20:00',
        ])
        ->assertForbidden();

    $this->actingAs($teacher1)
        ->delete(route('admin.assignments.destroy', $assignment))
        ->assertRedirect();

    expect(Assignment::query()->find($assignment->id))->toBeNull();
});

test('wali hanya dapat melihat tugas untuk rombel anaknya sendiri', function (): void {
    $mi = Unit::factory()->create(['code' => 'MI']);
    $year = AcademicYear::factory()->active()->create();
    $classA = Classroom::factory()->for($mi)->for($year)->create(['name' => '4-A']);
    $classB = Classroom::factory()->for($mi)->for($year)->create(['name' => '4-B']);
    $subject = Subject::factory()->for($mi)->create();

    $teacher = User::factory()->create();
    $teacher->assignRole(RoleEnum::Teacher->value);

    Assignment::query()->create([
        'subject_id' => $subject->id,
        'classroom_id' => $classA->id,
        'teacher_id' => $teacher->id,
        'title' => 'Tugas Kelas A',
        'due_at' => now()->addDays(2),
    ]);
    Assignment::query()->create([
        'subject_id' => $subject->id,
        'classroom_id' => $classB->id,
        'teacher_id' => $teacher->id,
        'title' => 'Tugas Kelas B',
        'due_at' => now()->addDays(2),
    ]);

    $ownChild = Student::factory()->for($mi)->create();
    Enrollment::factory()->create(['student_id' => $ownChild->id, 'classroom_id' => $classA->id, 'academic_year_id' => $year->id]);

    $otherChild = Student::factory()->for($mi)->create();
    Enrollment::factory()->create(['student_id' => $otherChild->id, 'classroom_id' => $classB->id, 'academic_year_id' => $year->id]);

    $guardianUser = User::factory()->create();
    $guardianUser->assignRole(RoleEnum::Guardian->value);
    $guardian = Guardian::factory()->for($guardianUser)->create();
    $guardian->students()->attach($ownChild->id, ['relation' => 'ibu', 'is_primary' => true]);

    $this->actingAs($guardianUser)
        ->get(route('guardian.students.assignments.index', $ownChild))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('guardian/assignments')
            ->has('assignments', 1)
            ->where('assignments.0.title', 'Tugas Kelas A')
        );

    $this->actingAs($guardianUser)
        ->get(route('guardian.students.assignments.index', $otherChild))
        ->assertNotFound();
});
