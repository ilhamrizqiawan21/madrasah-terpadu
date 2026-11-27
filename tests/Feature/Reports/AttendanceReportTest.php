<?php

declare(strict_types=1);

use App\Enums\AttendanceDecider;
use App\Enums\AttendanceStatus;
use App\Enums\Feature;
use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\AttendanceDay;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\FeatureFlag;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    FeatureFlag::query()->updateOrCreate(
        ['key' => Feature::Attendance->value],
        ['enabled' => true],
    );
});

it('authorizes reports.view and matches daily, monthly, student counts plus PDF and Excel export', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(Role::Admin->value);

    $teacher = User::factory()->create();
    $teacher->assignRole(Role::Teacher->value);

    $unit = Unit::factory()->create();
    $year = AcademicYear::factory()->create(['is_active' => true]);
    $classroom = Classroom::factory()->create([
        'unit_id' => $unit->id,
        'academic_year_id' => $year->id,
        'homeroom_teacher_id' => $teacher->id,
    ]);

    $s1 = Student::factory()->create(['unit_id' => $unit->id, 'status' => StudentStatus::Active]);
    $s2 = Student::factory()->create(['unit_id' => $unit->id, 'status' => StudentStatus::Active]);

    Enrollment::factory()->create(['student_id' => $s1->id, 'classroom_id' => $classroom->id, 'academic_year_id' => $year->id]);
    Enrollment::factory()->create(['student_id' => $s2->id, 'classroom_id' => $classroom->id, 'academic_year_id' => $year->id]);

    AttendanceDay::query()->create([
        'student_id' => $s1->id,
        'school_date' => '2026-11-27',
        'status' => AttendanceStatus::Present,
        'decided_by' => AttendanceDecider::Manual,
    ]);
    AttendanceDay::query()->create([
        'student_id' => $s2->id,
        'school_date' => '2026-11-27',
        'status' => AttendanceStatus::Late,
        'decided_by' => AttendanceDecider::Manual,
    ]);

    // Unauthorized role (teacher does not have reports.view; gate officer does not have admin area)
    $this->actingAs($teacher)
        ->get(route('admin.reports.index'))
        ->assertForbidden();

    // Daily mode
    $this->actingAs($admin)
        ->get(route('admin.reports.index', ['mode' => 'daily', 'date' => '2026-11-27']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/attendance')
            ->where('summary.present', 1)
            ->where('summary.late', 1)
            ->has('rows.data', 2)
        );

    // Monthly mode
    $this->actingAs($admin)
        ->get(route('admin.reports.index', ['mode' => 'monthly', 'month' => '2026-11']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('rows.data.0.present', 1)
            ->where('rows.data.0.late', 1)
            ->where('rows.data.0.attendance_pct', '100%')
        );

    // Student mode
    $this->actingAs($admin)
        ->get(route('admin.reports.index', ['mode' => 'student', 'month' => '2026-11']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rows.data', 2)
        );

    // Exports
    $this->actingAs($admin)
        ->get(route('admin.reports.attendance.pdf', ['mode' => 'daily', 'date' => '2026-11-27']))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $this->actingAs($admin)
        ->get(route('admin.reports.attendance.excel', ['mode' => 'daily', 'date' => '2026-11-27']))
        ->assertOk();
});
