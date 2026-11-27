<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\PointCategory;
use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\CanteenVoucher;
use App\Models\DisciplineThreshold;
use App\Models\FeatureFlag;
use App\Models\Guardian;
use App\Models\PointRule;
use App\Models\RewardOffer;
use App\Models\RewardRedemption;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Services\Behavior\BehaviorRecordService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    FeatureFlag::query()->updateOrCreate(
        ['key' => Feature::Behavior->value],
        ['enabled' => true]
    );
    FeatureFlag::query()->updateOrCreate(
        ['key' => Feature::Rewards->value],
        ['enabled' => true]
    );
});

it('shows guardian their own child behavior summary, followups, offers, and vouchers without recorder name', function (): void {
    $bk = User::factory()->create(['name' => 'Rahasia BK']);
    $bk->assignRole(Role::Counselor->value);

    $unit = Unit::factory()->create();
    $year = AcademicYear::factory()->create(['is_active' => true]);
    $child = Student::factory()->create(['unit_id' => $unit->id, 'name' => 'Ahmad']);
    $otherChild = Student::factory()->create(['unit_id' => $unit->id]);

    $guardianUser = User::factory()->create(['is_active' => true]);
    $guardianUser->assignRole(Role::Guardian->value);
    $guardian = Guardian::factory()->create(['user_id' => $guardianUser->id]);
    $child->guardians()->attach($guardian->id, ['relation' => 'ayah', 'is_primary' => true]);

    DisciplineThreshold::factory()->create([
        'unit_id' => $unit->id,
        'points' => 5,
        'action_name' => 'Teguran wali kelas',
        'notify_guardian' => true,
    ]);
    DisciplineThreshold::factory()->create([
        'unit_id' => $unit->id,
        'points' => 6,
        'action_name' => 'Catatan internal BK',
        'notify_guardian' => false,
    ]);

    $vRule = PointRule::factory()->create([
        'unit_id' => $unit->id,
        'category' => PointCategory::Violation,
        'points' => 8,
    ]);
    $mRule = PointRule::factory()->create([
        'unit_id' => $unit->id,
        'category' => PointCategory::GoodDeed,
        'points' => 30,
    ]);

    $behaviorService = app(BehaviorRecordService::class);
    $behaviorService->recordMany($bk, [$child->id], $vRule, '2026-11-27');
    $behaviorService->recordMany($bk, [$child->id], $mRule, '2026-11-27');

    $offer = RewardOffer::factory()->create([
        'unit_id' => $unit->id,
        'name' => 'Voucher Kantin 10rb',
        'cost_points' => 25,
        'voucher_amount' => 10000,
    ]);

    $redemption = RewardRedemption::query()->create([
        'student_id' => $child->id,
        'academic_year_id' => $year->id,
        'reward_offer_id' => $offer->id,
        'cost_points' => 25,
        'voucher_amount' => 10000,
        'requested_by' => $guardianUser->id,
        'status' => 'approved',
    ]);

    CanteenVoucher::query()->create([
        'student_id' => $child->id,
        'reward_redemption_id' => $redemption->id,
        'amount' => 10000,
        'remaining' => 10000,
        'expires_on' => now()->addDays(7)->toDateString(),
        'created_at' => now(),
    ]);

    $this->actingAs($guardianUser)
        ->get(route('guardian.students.behavior.index', $child))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('guardian/behavior')
            ->where('summary.violation_points', 8)
            ->where('summary.merit_points', 30)
            ->has('records', 2)
            ->missing('records.0.recorded_by_name')
            ->has('followups', 1)
            ->where('followups.0.action_name', 'Teguran wali kelas')
            ->has('offers', 1)
            ->has('vouchers', 1)
        );

    // Guardian can request reward for their own child
    $this->actingAs($guardianUser)
        ->post(route('guardian.students.behavior.redeem', $child), [
            'reward_offer_id' => $offer->id,
        ])
        ->assertRedirect();

    expect(RewardRedemption::query()->where('student_id', $child->id)->where('status', 'pending')->count())->toBe(1);

    // Guardian gets 404 for another guardian's child
    $this->actingAs($guardianUser)
        ->get(route('guardian.students.behavior.index', $otherChild))
        ->assertNotFound();

    $this->actingAs($guardianUser)
        ->post(route('guardian.students.behavior.redeem', $otherChild), [
            'reward_offer_id' => $offer->id,
        ])
        ->assertNotFound();
});
