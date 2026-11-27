<?php

declare(strict_types=1);

use App\Enums\PointCategory;
use App\Enums\Role as RoleEnum;
use App\Models\AcademicYear;
use App\Models\BehaviorRecord;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('profil siswa memuat identitas, wali, rombel aktif, dan poin dari catatan aktif', function (): void {
    $unit = Unit::factory()->create(['code' => 'MI']);
    $year = AcademicYear::factory()->active()->create();
    $classroom = Classroom::factory()->for($unit)->for($year)->create(['name' => '2-A']);
    $student = Student::factory()->for($unit)->create(['name' => 'Dais']);
    Enrollment::factory()->create(['student_id' => $student->id, 'classroom_id' => $classroom->id, 'academic_year_id' => $year->id]);
    Guardian::factory()->withGuardianAccount()->linkedTo($student)->create();
    BehaviorRecord::factory()->create(['student_id' => $student->id, 'academic_year_id' => $year->id, 'category' => PointCategory::GoodDeed, 'points' => 10]);
    BehaviorRecord::factory()->create(['student_id' => $student->id, 'academic_year_id' => $year->id, 'category' => PointCategory::Violation, 'points' => 5, 'status' => 'voided']);

    $teacher = User::factory()->create();
    $teacher->assignRole(RoleEnum::Teacher->value);

    $this->actingAs($teacher)->get(route('admin.students.show', $student))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/students/show')
            ->where('student.name', 'Dais')
            ->where('student.classroom', '2-A')
            ->has('guardians', 1)
            ->where('behavior.merit', 10)
            ->where('behavior.violation', 0)
            ->has('behavior.records', 2)
            ->where('can.manage', false)
        );
});

test('profil siswa ditolak untuk peran tanpa izin lihat siswa', function (): void {
    $student = Student::factory()->create();
    $guard = User::factory()->create();
    $guard->assignRole(RoleEnum::GateOfficer->value);

    $this->get(route('admin.students.show', $student))->assertRedirect(route('login'));
    $this->actingAs($guard)->get(route('admin.students.show', $student))->assertForbidden();
});
