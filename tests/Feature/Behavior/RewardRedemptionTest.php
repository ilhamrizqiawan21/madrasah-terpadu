<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\PointAccount;
use App\Enums\PointCategory;
use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\CanteenVoucher;
use App\Models\FeatureFlag;
use App\Models\Guardian;
use App\Models\PointRule;
use App\Models\RewardOffer;
use App\Models\RewardRedemption;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\RewardRedemptionDecidedNotification;
use App\Services\Behavior\BehaviorRecordService;
use App\Services\Behavior\PointLedgerService;
use App\Services\Behavior\RewardRedemptionService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

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

it('approves reward redemption, deducts merit points and stock, issues canteen voucher, and notifies guardian', function (): void {
    Notification::fake();

    $bk = User::factory()->create();
    $bk->assignRole(Role::Counselor->value);

    $unit = Unit::factory()->create();
    $year = AcademicYear::factory()->create(['is_active' => true]);
    $student = Student::factory()->create(['unit_id' => $unit->id]);

    $guardianUser = User::factory()->create(['is_active' => true]);
    $guardianUser->assignRole(Role::Guardian->value);
    $guardian = Guardian::factory()->create(['user_id' => $guardianUser->id]);
    $student->guardians()->attach($guardian->id, ['relation' => 'ayah', 'is_primary' => true]);

    $rule = PointRule::factory()->create([
        'unit_id' => $unit->id,
        'category' => PointCategory::GoodDeed,
        'points' => 50,
    ]);
    app(BehaviorRecordService::class)->recordMany($bk, [$student->id], $rule, '2026-11-27');

    $offer = RewardOffer::factory()->create([
        'unit_id' => $unit->id,
        'name' => 'Voucher Kantin Rp 10.000',
        'cost_points' => 30,
        'voucher_amount' => 10000,
        'voucher_valid_days' => 14,
        'stock' => 2,
    ]);

    $this->actingAs($bk)
        ->post(route('admin.behavior.rewards.redemptions.store'), [
            'student_id' => $student->id,
            'reward_offer_id' => $offer->id,
        ])
        ->assertRedirect();

    $redemption = RewardRedemption::query()->firstOrFail();
    expect($redemption->status)->toBe('pending');

    $this->actingAs($bk)
        ->post(route('admin.behavior.rewards.redemptions.approve', $redemption), [
            'decision_note' => 'Selamat atas poin kebaikannya.',
        ])
        ->assertRedirect();

    $redemption->refresh();
    $offer->refresh();

    expect($redemption->status)->toBe('approved')
        ->and($offer->stock)->toBe(1)
        ->and(app(PointLedgerService::class)->balanceFor($student->id, $year->id, PointAccount::Merit))->toBe(20);

    $voucher = CanteenVoucher::query()->where('reward_redemption_id', $redemption->id)->firstOrFail();
    expect($voucher->amount)->toBe(10000)
        ->and($voucher->remaining)->toBe(10000);

    Notification::assertSentTo($guardianUser, RewardRedemptionDecidedNotification::class);

    // Double approval of the same redemption must fail and only produce one voucher
    expect(fn () => app(RewardRedemptionService::class)->approve($bk, $redemption->fresh()))
        ->toThrow(ValidationException::class);

    expect(CanteenVoucher::query()->count())->toBe(1);
});

it('rejects redemption when points are insufficient or stock is exhausted', function (): void {
    $bk = User::factory()->create();
    $bk->assignRole(Role::Counselor->value);

    $unit = Unit::factory()->create();
    AcademicYear::factory()->create(['is_active' => true]);
    $student = Student::factory()->create(['unit_id' => $unit->id]);

    $offerOutOfStock = RewardOffer::factory()->create([
        'unit_id' => $unit->id,
        'cost_points' => 10,
        'stock' => 0,
    ]);

    $this->actingAs($bk)
        ->post(route('admin.behavior.rewards.redemptions.store'), [
            'student_id' => $student->id,
            'reward_offer_id' => $offerOutOfStock->id,
        ])
        ->assertSessionHasErrors('reward_offer_id');

    $offerExpensive = RewardOffer::factory()->create([
        'unit_id' => $unit->id,
        'cost_points' => 40,
        'stock' => 5,
    ]);

    $this->actingAs($bk)
        ->post(route('admin.behavior.rewards.redemptions.store'), [
            'student_id' => $student->id,
            'reward_offer_id' => $offerExpensive->id,
        ])
        ->assertSessionHasErrors('reward_offer_id');
});
