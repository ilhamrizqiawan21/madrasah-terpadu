<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\PointCategory;
use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\FeatureFlag;
use App\Models\Guardian;
use App\Models\PointRule;
use App\Models\RewardOffer;
use App\Models\RewardOfferNotice;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\RewardOfferAvailableNotification;
use App\Services\Behavior\BehaviorRecordService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    FeatureFlag::query()->updateOrCreate(
        ['key' => Feature::Behavior->value],
        ['enabled' => true]
    );
});

it('notifies guardian once when merit points reach an active reward offer cost', function (): void {
    Notification::fake();

    $bk = User::factory()->create();
    $bk->assignRole(Role::Counselor->value);

    $unit = Unit::factory()->create();
    AcademicYear::factory()->create(['is_active' => true]);
    $student = Student::factory()->create(['unit_id' => $unit->id]);

    $guardianUser = User::factory()->create(['is_active' => true]);
    $guardianUser->assignRole(Role::Guardian->value);
    $guardian = Guardian::factory()->create(['user_id' => $guardianUser->id]);
    $student->guardians()->attach($guardian->id, ['relation' => 'ibu', 'is_primary' => true]);

    $offer = RewardOffer::factory()->create([
        'unit_id' => $unit->id,
        'name' => 'Voucher Kantin Rp 5.000',
        'cost_points' => 20,
        'stock' => 5,
    ]);

    $rule = PointRule::factory()->create([
        'unit_id' => $unit->id,
        'category' => PointCategory::GoodDeed,
        'points' => 15,
    ]);

    $service = app(BehaviorRecordService::class);

    // 15 points: below 20 -> no notice yet
    $service->recordMany($bk, [$student->id], $rule, '2026-11-27');
    Notification::assertNotSentTo($guardianUser, RewardOfferAvailableNotification::class);
    expect(RewardOfferNotice::query()->count())->toBe(0);

    // 30 points: reaches 20 -> notifies once
    $service->recordMany($bk, [$student->id], $rule, '2026-11-27');
    Notification::assertSentToTimes($guardianUser, RewardOfferAvailableNotification::class, 1);
    expect(RewardOfferNotice::query()->where('reward_offer_id', $offer->id)->count())->toBe(1);

    // 45 points: already notified -> does NOT repeat
    $service->recordMany($bk, [$student->id], $rule, '2026-11-27');
    Notification::assertSentToTimes($guardianUser, RewardOfferAvailableNotification::class, 1);
});
