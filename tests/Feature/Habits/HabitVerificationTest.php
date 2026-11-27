<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\FeatureFlag;
use App\Models\Guardian;
use App\Models\HabitItem;
use App\Models\HabitLog;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\HabitRejectedNotification;
use Database\Seeders\HabitItemSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(HabitItemSeeder::class);

    FeatureFlag::query()->updateOrCreate(
        ['key' => Feature::Habits->value],
        ['enabled' => true]
    );
});

it('forbids a teacher from viewing or verifying another classrooms habit logs', function (): void {
    $teacherA = User::factory()->create();
    $teacherA->assignRole(Role::Teacher->value);
    $teacherB = User::factory()->create();
    $teacherB->assignRole(Role::Teacher->value);

    $unit = Unit::factory()->create();
    $year = AcademicYear::factory()->create(['is_active' => true]);
    $classA = Classroom::factory()->create([
        'unit_id' => $unit->id,
        'academic_year_id' => $year->id,
        'homeroom_teacher_id' => $teacherA->id,
    ]);
    $classB = Classroom::factory()->create([
        'unit_id' => $unit->id,
        'academic_year_id' => $year->id,
        'homeroom_teacher_id' => $teacherB->id,
    ]);

    $this->actingAs($teacherA)
        ->get(route('admin.habits.verify.index', ['classroom_id' => $classB->id]))
        ->assertForbidden();

    $this->actingAs($teacherA)
        ->post(route('admin.habits.verify.bulk'), [
            'classroom_id' => $classB->id,
            'log_date' => '2026-11-27',
            'log_ids' => [],
        ])
        ->assertForbidden();

    $this->actingAs($teacherA)
        ->get(route('admin.habits.verify.index', ['classroom_id' => $classA->id]))
        ->assertOk();
});

it('verifies habit logs in bulk and notifies guardian when a log is rejected', function (): void {
    Notification::fake();

    $teacher = User::factory()->create();
    $teacher->assignRole(Role::Teacher->value);

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

    $guardianUser = User::factory()->create(['is_active' => true]);
    $guardianUser->assignRole(Role::Guardian->value);
    $guardian = Guardian::factory()->create(['user_id' => $guardianUser->id]);
    $student->guardians()->attach($guardian->id, ['relation' => 'ayah', 'is_primary' => true]);

    $subuh = HabitItem::query()->where('name', 'Sholat Subuh')->firstOrFail();
    $dzuhur = HabitItem::query()->where('name', 'Sholat Dzuhur')->firstOrFail();

    $log1 = HabitLog::query()->create([
        'student_id' => $student->id,
        'habit_item_id' => $subuh->id,
        'log_date' => '2026-11-27',
        'done' => true,
        'submitted_by' => $guardianUser->id,
        'submitted_at' => now(),
        'verification' => 'pending',
    ]);

    $log2 = HabitLog::query()->create([
        'student_id' => $student->id,
        'habit_item_id' => $dzuhur->id,
        'log_date' => '2026-11-27',
        'done' => true,
        'submitted_by' => $guardianUser->id,
        'submitted_at' => now(),
        'verification' => 'pending',
    ]);

    $this->actingAs($teacher)
        ->post(route('admin.habits.verify.bulk'), [
            'classroom_id' => $classroom->id,
            'log_date' => '2026-11-27',
            'log_ids' => [$log1->id],
        ])
        ->assertRedirect();

    expect($log1->fresh()->verification)->toBe('verified')
        ->and($log1->fresh()->verified_by)->toBe($teacher->id);

    $this->actingAs($teacher)
        ->post(route('admin.habits.verify.reject', $log2), [
            'classroom_id' => $classroom->id,
            'note' => 'Mohon konfirmasi waktu sholat dengan ananda.',
        ])
        ->assertRedirect();

    expect($log2->fresh()->verification)->toBe('rejected')
        ->and($log2->fresh()->note)->toBe('Mohon konfirmasi waktu sholat dengan ananda.');

    Notification::assertSentTo($guardianUser, HabitRejectedNotification::class);
});
