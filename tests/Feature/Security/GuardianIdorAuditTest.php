<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\LeaveRequestStatus;
use App\Enums\LeaveType;
use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\FeatureFlag;
use App\Models\Guardian;
use App\Models\LeaveRequest;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    foreach (Feature::cases() as $feature) {
        FeatureFlag::query()->updateOrCreate(
            ['key' => $feature->value],
            ['enabled' => true],
        );
    }
});

it('rejects unauthenticated access and blocks cross-guardian IDOR on all guardian student routes', function (): void {
    $unit = Unit::factory()->create();
    AcademicYear::factory()->create(['is_active' => true]);

    $guardianA = User::factory()->create();
    $guardianA->assignRole(Role::Guardian->value);
    $guardianProfileA = Guardian::factory()->create(['user_id' => $guardianA->id]);

    $guardianB = User::factory()->create();
    $guardianB->assignRole(Role::Guardian->value);
    $guardianProfileB = Guardian::factory()->create(['user_id' => $guardianB->id]);

    $studentA = Student::factory()->create(['unit_id' => $unit->id]);
    $studentB = Student::factory()->create(['unit_id' => $unit->id]);

    $studentA->guardians()->attach($guardianProfileA->id, ['relation' => 'ayah', 'is_primary' => true]);
    $studentB->guardians()->attach($guardianProfileB->id, ['relation' => 'ibu', 'is_primary' => true]);

    $leaveB = LeaveRequest::query()->create([
        'student_id' => $studentB->id,
        'requested_by' => $guardianB->id,
        'type' => LeaveType::Sick,
        'starts_on' => '2026-11-27',
        'ends_on' => '2026-11-27',
        'reason' => 'Sakit demam',
        'status' => LeaveRequestStatus::Pending,
    ]);

    $notifB = DatabaseNotification::query()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\SystemNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $guardianB->id,
        'data' => ['title' => 'Rahasia B', 'body' => 'Isi rahasia'],
    ]);

    $getRoutes = [
        route('guardian.students.photo', $studentB),
        route('guardian.students.quran.index', $studentB),
        route('guardian.students.leave.create', $studentB),
        route('guardian.students.leave.attachment', [$studentB, $leaveB]),
        route('guardian.students.habits.index', $studentB),
        route('guardian.students.behavior.index', $studentB),
        route('guardian.students.attendance.index', $studentB),
        route('guardian.students.exams.index', $studentB),
    ];

    foreach ($getRoutes as $url) {
        $this->get($url)->assertRedirect(route('login'));
    }

    $this->actingAs($guardianA);

    foreach ($getRoutes as $url) {
        $this->get($url)->assertNotFound();
    }

    $this->post(route('guardian.child.update'), ['student_id' => $studentB->id])->assertNotFound();
    $this->post(route('guardian.students.habits.toggle', $studentB), [])->assertNotFound();
    $this->post(route('guardian.students.behavior.redeem', $studentB), [])->assertNotFound();
    $this->post(route('guardian.students.leave.store', $studentB), [])->assertNotFound();
    $this->patch(route('guardian.notifications.read', $notifB->id))->assertNotFound();

    // Non-guardian role cannot access guardian portal
    $teacher = User::factory()->create();
    $teacher->assignRole(Role::Teacher->value);
    $this->actingAs($teacher)->get(route('guardian.home'))->assertForbidden();
});
