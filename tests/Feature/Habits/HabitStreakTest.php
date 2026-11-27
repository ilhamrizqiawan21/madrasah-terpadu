<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\PointAccount;
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
use App\Services\Behavior\PointLedgerService;
use Carbon\CarbonImmutable;
use Database\Seeders\HabitItemSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(HabitItemSeeder::class);

    FeatureFlag::query()->updateOrCreate(
        ['key' => Feature::Habits->value],
        ['enabled' => true]
    );
});

it('allows admin to customize habit streak points and awards merit points once per streak when verified', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(Role::Admin->value);
    $teacher = User::factory()->create();
    $teacher->assignRole(Role::Teacher->value);

    // Admin customizes streak: enabled, 3 consecutive days = 8 merit points
    $this->actingAs($admin)
        ->put(route('admin.habits.streak.update'), [
            'enabled' => true,
            'days' => 3,
            'points' => 8,
        ])
        ->assertRedirect();

    $unit = Unit::factory()->create();
    $year = AcademicYear::factory()->create(['is_active' => true]);
    $classroom = Classroom::factory()->create([
        'unit_id' => $unit->id,
        'academic_year_id' => $year->id,
        'homeroom_teacher_id' => $teacher->id,
    ]);

    $student = Student::factory()->create(['unit_id' => $unit->id]);
    Enrollment::factory()->create([
        'student_id' => $student->id,
        'classroom_id' => $classroom->id,
        'academic_year_id' => $year->id,
    ]);

    $subuh = HabitItem::query()->where('name', 'Sholat Subuh')->firstOrFail();

    $logs = [];
    foreach (['2026-11-25', '2026-11-26', '2026-11-27'] as $date) {
        $logs[$date] = HabitLog::query()->create([
            'student_id' => $student->id,
            'habit_item_id' => $subuh->id,
            'log_date' => $date,
            'done' => true,
            'submitted_by' => $teacher->id,
            'submitted_at' => now(),
            'verification' => 'pending',
        ]);
    }

    // Verify day 1 and day 2 -> no streak points yet
    $this->actingAs($teacher)->post(route('admin.habits.verify.bulk'), [
        'classroom_id' => $classroom->id,
        'log_date' => '2026-11-25',
        'log_ids' => [$logs['2026-11-25']->id],
    ])->assertRedirect();

    $this->actingAs($teacher)->post(route('admin.habits.verify.bulk'), [
        'classroom_id' => $classroom->id,
        'log_date' => '2026-11-26',
        'log_ids' => [$logs['2026-11-26']->id],
    ])->assertRedirect();

    $ledger = app(PointLedgerService::class);
    expect($ledger->balanceFor($student->id, $year->id, PointAccount::Merit))->toBe(0);

    // Verify day 3 -> 3-day streak reached -> +8 merit points
    $this->actingAs($teacher)->post(route('admin.habits.verify.bulk'), [
        'classroom_id' => $classroom->id,
        'log_date' => '2026-11-27',
        'log_ids' => [$logs['2026-11-27']->id],
    ])->assertRedirect();

    expect($ledger->balanceFor($student->id, $year->id, PointAccount::Merit))->toBe(8);

    // Day 4 alone should NOT award another streak (cooldown until next 3 consecutive days)
    $day4 = HabitLog::query()->create([
        'student_id' => $student->id,
        'habit_item_id' => $subuh->id,
        'log_date' => CarbonImmutable::parse('2026-11-28')->toDateString(),
        'done' => true,
        'submitted_by' => $teacher->id,
        'submitted_at' => now(),
        'verification' => 'pending',
    ]);

    $this->actingAs($teacher)->post(route('admin.habits.verify.bulk'), [
        'classroom_id' => $classroom->id,
        'log_date' => '2026-11-28',
        'log_ids' => [$day4->id],
    ])->assertRedirect();

    expect($ledger->balanceFor($student->id, $year->id, PointAccount::Merit))->toBe(8);
});
