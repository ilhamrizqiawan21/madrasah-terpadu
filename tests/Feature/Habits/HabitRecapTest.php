<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\HabitAspect;
use App\Enums\Role;
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
        ['enabled' => true]
    );
});

it('computes monthly habit recap percentages per aspect using only verified logs', function (): void {
    $teacher = User::factory()->create();
    $teacher->assignRole(Role::Teacher->value);

    $unit = Unit::factory()->create();
    $year = AcademicYear::factory()->create([
        'is_active' => true,
        'starts_on' => '2026-07-01',
        'ends_on' => '2027-06-30',
    ]);
    $classroom = Classroom::factory()->create([
        'unit_id' => $unit->id,
        'academic_year_id' => $year->id,
        'homeroom_teacher_id' => $teacher->id,
    ]);

    $student = Student::factory()->create(['unit_id' => $unit->id, 'name' => 'Ahmad Mutabaah']);
    Enrollment::factory()->create([
        'student_id' => $student->id,
        'classroom_id' => $classroom->id,
        'academic_year_id' => $year->id,
    ]);

    $spiritualItem = HabitItem::factory()->create([
        'unit_id' => $unit->id,
        'aspect' => HabitAspect::Spiritual,
        'is_active' => true,
    ]);
    $socialItem = HabitItem::factory()->create([
        'unit_id' => $unit->id,
        'aspect' => HabitAspect::Social,
        'is_active' => true,
    ]);

    // 1 verified spiritual log, 1 pending spiritual log (must NOT count), 1 rejected social log (must NOT count)
    HabitLog::query()->create([
        'student_id' => $student->id,
        'habit_item_id' => $spiritualItem->id,
        'log_date' => '2026-11-02',
        'done' => true,
        'submitted_by' => $teacher->id,
        'submitted_at' => now(),
        'verification' => 'verified',
        'verified_by' => $teacher->id,
        'verified_at' => now(),
    ]);
    HabitLog::query()->create([
        'student_id' => $student->id,
        'habit_item_id' => $spiritualItem->id,
        'log_date' => '2026-11-03',
        'done' => true,
        'submitted_by' => $teacher->id,
        'submitted_at' => now(),
        'verification' => 'pending',
    ]);
    HabitLog::query()->create([
        'student_id' => $student->id,
        'habit_item_id' => $socialItem->id,
        'log_date' => '2026-11-02',
        'done' => true,
        'submitted_by' => $teacher->id,
        'submitted_at' => now(),
        'verification' => 'rejected',
        'note' => 'Belum sesuai',
    ]);

    $this->actingAs($teacher)
        ->get(route('admin.habits.recap.index', [
            'classroom_id' => $classroom->id,
            'period' => 'month',
            'month' => '2026-11',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/habits/recap')
            ->has('rows', 1)
            ->where('rows.0.spiritual_verified', 1)
            ->where('rows.0.social_verified', 0)
            ->where('rows.0.social_pct', 0)
        );
});
