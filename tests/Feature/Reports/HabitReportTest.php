<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\HabitAspect;
use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\FeatureFlag;
use App\Models\HabitItem;
use App\Models\HabitLog;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    FeatureFlag::query()->updateOrCreate(
        ['key' => Feature::Habits->value],
        ['enabled' => true],
    );
});

it('authorizes reports.view and matches habit report counts across modes and exports', function (): void {
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
    $student = Student::factory()->create(['unit_id' => $unit->id, 'status' => StudentStatus::Active]);
    Enrollment::factory()->create([
        'student_id' => $student->id,
        'classroom_id' => $classroom->id,
        'academic_year_id' => $year->id,
    ]);

    $item = HabitItem::query()->create([
        'unit_id' => $unit->id,
        'name' => 'Sholat Dhuha',
        'aspect' => HabitAspect::Spiritual,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    HabitLog::query()->create([
        'student_id' => $student->id,
        'habit_item_id' => $item->id,
        'log_date' => '2026-11-27',
        'done' => true,
        'submitted_by' => $admin->id,
        'submitted_at' => now(),
        'verification' => 'verified',
        'verified_by' => $teacher->id,
        'verified_at' => now(),
    ]);

    $this->actingAs($teacher)->get(route('admin.reports.habits.index'))->assertForbidden();

    $this->actingAs($admin)
        ->get(route('admin.reports.habits.index', ['mode' => 'item', 'date_from' => '2026-11-01', 'date_to' => '2026-11-30']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/habits')
            ->where('summary.total_logs', 1)
            ->where('summary.verified_logs', 1)
            ->where('rows.data.0.total_done', 1)
            ->where('rows.data.0.coverage_pct', '100%')
        );

    $this->actingAs($admin)
        ->get(route('admin.reports.habits.index', ['mode' => 'classroom', 'date_from' => '2026-11-01', 'date_to' => '2026-11-30']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('rows.data.0.total_done', 1)
            ->where('rows.data.0.verified', 1)
        );

    $this->actingAs($admin)
        ->get(route('admin.reports.habits.pdf', ['mode' => 'item', 'date_from' => '2026-11-01', 'date_to' => '2026-11-30']))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $this->actingAs($admin)
        ->get(route('admin.reports.habits.excel', ['mode' => 'item', 'date_from' => '2026-11-01', 'date_to' => '2026-11-30']))
        ->assertOk();
});
