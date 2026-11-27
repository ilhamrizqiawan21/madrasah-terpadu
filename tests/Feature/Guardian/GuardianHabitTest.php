<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\Role;
use App\Models\FeatureFlag;
use App\Models\Guardian;
use App\Models\HabitItem;
use App\Models\HabitLog;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\HabitItemSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(HabitItemSeeder::class);

    FeatureFlag::query()->updateOrCreate(
        ['key' => Feature::Habits->value],
        ['enabled' => true]
    );
});

it('allows guardian to toggle habit logs for today and up to 3 days back, but blocks older dates and verified logs', function (): void {
    $unit = Unit::factory()->create();
    $child = Student::factory()->create(['unit_id' => $unit->id]);
    $otherChild = Student::factory()->create(['unit_id' => $unit->id]);

    $guardianUser = User::factory()->create(['is_active' => true]);
    $guardianUser->assignRole(Role::Guardian->value);
    $guardian = Guardian::factory()->create(['user_id' => $guardianUser->id]);
    $child->guardians()->attach($guardian->id, ['relation' => 'ibu', 'is_primary' => true]);

    $subuh = HabitItem::query()->where('name', 'Sholat Subuh')->firstOrFail();
    $today = CarbonImmutable::now('Asia/Makassar')->toDateString();
    $threeDaysAgo = CarbonImmutable::now('Asia/Makassar')->subDays(3)->toDateString();
    $fourDaysAgo = CarbonImmutable::now('Asia/Makassar')->subDays(4)->toDateString();

    $this->actingAs($guardianUser)
        ->get(route('guardian.students.habits.index', $child))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('guardian/habits')
            ->has('items', 5)
        );

    // Toggle today -> success
    $this->actingAs($guardianUser)
        ->post(route('guardian.students.habits.toggle', $child), [
            'habit_item_id' => $subuh->id,
            'log_date' => $today,
            'done' => true,
        ])
        ->assertRedirect();

    $log = HabitLog::query()
        ->where('student_id', $child->id)
        ->where('habit_item_id', $subuh->id)
        ->whereDate('log_date', $today)
        ->firstOrFail();
    expect($log->done)->toBeTrue()
        ->and($log->verification)->toBe('pending');

    // Toggle 3 days ago -> allowed
    $this->actingAs($guardianUser)
        ->post(route('guardian.students.habits.toggle', $child), [
            'habit_item_id' => $subuh->id,
            'log_date' => $threeDaysAgo,
            'done' => true,
        ])
        ->assertSessionHasNoErrors();

    // Toggle 4 days ago -> rejected
    $this->actingAs($guardianUser)
        ->post(route('guardian.students.habits.toggle', $child), [
            'habit_item_id' => $subuh->id,
            'log_date' => $fourDaysAgo,
            'done' => true,
        ])
        ->assertSessionHasErrors('log_date');

    // Verified log cannot be changed by guardian
    $log->update([
        'verification' => 'verified',
        'verified_at' => now(),
    ]);

    $this->actingAs($guardianUser)
        ->post(route('guardian.students.habits.toggle', $child), [
            'habit_item_id' => $subuh->id,
            'log_date' => $today,
            'done' => false,
        ])
        ->assertSessionHasErrors('habit_item_id');

    expect($log->fresh()->done)->toBeTrue();

    // Other guardian's child -> 404
    $this->actingAs($guardianUser)
        ->post(route('guardian.students.habits.toggle', $otherChild), [
            'habit_item_id' => $subuh->id,
            'log_date' => $today,
            'done' => true,
        ])
        ->assertNotFound();
});
