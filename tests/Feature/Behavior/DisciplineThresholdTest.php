<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\PointCategory;
use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\BehaviorRecord;
use App\Models\DisciplineFollowup;
use App\Models\DisciplineThreshold;
use App\Models\FeatureFlag;
use App\Models\Guardian;
use App\Models\PointRule;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\DisciplineThresholdTriggeredNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    FeatureFlag::query()->updateOrCreate(
        ['key' => Feature::Behavior->value],
        ['enabled' => true]
    );
});

it('creates followups for all crossed thresholds at once and notifies guardian when enabled', function (): void {
    Notification::fake();

    $bk = User::factory()->create();
    $bk->assignRole(Role::Counselor->value);

    $unit = Unit::factory()->create(['code' => 'MI']);
    AcademicYear::factory()->create(['is_active' => true]);
    $student = Student::factory()->create(['unit_id' => $unit->id]);

    $guardianUser = User::factory()->create(['is_active' => true]);
    $guardianUser->assignRole(Role::Guardian->value);
    $guardian = Guardian::factory()->create(['user_id' => $guardianUser->id]);
    $student->guardians()->attach($guardian->id, ['relation' => 'ibu', 'is_primary' => true]);

    $t1 = DisciplineThreshold::factory()->create([
        'unit_id' => $unit->id,
        'points' => 10,
        'action_name' => 'Teguran lisan',
        'notify_guardian' => false,
    ]);
    $t2 = DisciplineThreshold::factory()->create([
        'unit_id' => $unit->id,
        'points' => 25,
        'action_name' => 'Surat Peringatan 1',
        'notify_guardian' => true,
    ]);

    $rule = PointRule::factory()->create([
        'unit_id' => $unit->id,
        'category' => PointCategory::Violation,
        'points' => 30,
    ]);

    $this->actingAs($bk)
        ->post(route('admin.behavior.store'), [
            'student_ids' => [$student->id],
            'point_rule_id' => $rule->id,
            'occurred_on' => '2026-11-27',
        ])
        ->assertRedirect();

    expect(DisciplineFollowup::query()->where('student_id', $student->id)->count())->toBe(2)
        ->and(DisciplineFollowup::query()->where('discipline_threshold_id', $t1->id)->firstOrFail()->status)->toBe('open')
        ->and(DisciplineFollowup::query()->where('discipline_threshold_id', $t2->id)->firstOrFail()->status)->toBe('open');

    Notification::assertSentToTimes($guardianUser, DisciplineThresholdTriggeredNotification::class, 1);

    // Triggering another violation does NOT duplicate existing followups in the same year
    $this->actingAs($bk)
        ->post(route('admin.behavior.store'), [
            'student_ids' => [$student->id],
            'point_rule_id' => $rule->id,
            'occurred_on' => '2026-11-27',
        ])
        ->assertRedirect();

    expect(DisciplineFollowup::query()->where('student_id', $student->id)->count())->toBe(2);
});

it('keeps completed followups even if the triggering behavior record is later voided', function (): void {
    $bk = User::factory()->create();
    $bk->assignRole(Role::Counselor->value);

    $unit = Unit::factory()->create();
    AcademicYear::factory()->create(['is_active' => true]);
    $student = Student::factory()->create(['unit_id' => $unit->id]);

    DisciplineThreshold::factory()->create([
        'unit_id' => $unit->id,
        'points' => 10,
        'action_name' => 'Pembinaan BK',
        'notify_guardian' => false,
    ]);

    $rule = PointRule::factory()->create([
        'unit_id' => $unit->id,
        'category' => PointCategory::Violation,
        'points' => 15,
    ]);

    $this->actingAs($bk)
        ->post(route('admin.behavior.store'), [
            'student_ids' => [$student->id],
            'point_rule_id' => $rule->id,
            'occurred_on' => '2026-11-27',
        ])
        ->assertRedirect();

    $followup = DisciplineFollowup::query()->firstOrFail();

    $this->actingAs($bk)
        ->post(route('admin.behavior.followups.done', $followup), [
            'note' => 'Sudah dipanggil dan dibina bersama wali.',
        ])
        ->assertRedirect();

    expect($followup->fresh()->status)->toBe('done')
        ->and($followup->fresh()->done_by)->toBe($bk->id);

    $record = BehaviorRecord::query()->firstOrFail();
    $this->actingAs($bk)
        ->post(route('admin.behavior.void', $record), [
            'void_reason' => 'Salah input',
        ])
        ->assertRedirect();

    expect(DisciplineFollowup::query()->find($followup->id)?->status)->toBe('done');
});

it('allows managing discipline thresholds with unique points per unit', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(Role::Admin->value);
    $unit = Unit::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.behavior.thresholds.store'), [
            'unit_id' => $unit->id,
            'points' => 20,
            'action_name' => 'SP1',
            'notify_guardian' => true,
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->post(route('admin.behavior.thresholds.store'), [
            'unit_id' => $unit->id,
            'points' => 20,
            'action_name' => 'Duplikat',
            'notify_guardian' => false,
        ])
        ->assertSessionHasErrors('points');
});
