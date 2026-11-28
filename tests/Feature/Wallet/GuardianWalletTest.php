<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\Role as RoleEnum;
use App\Models\AuditLog;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Features\FeatureFlags;
use App\Services\Wallet\WalletService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('wali hanya dapat melihat dompet anaknya sendiri dan anak lain mengembalikan 404', function (): void {
    $unit = Unit::factory()->create();
    $ownChild = Student::factory()->for($unit)->create();
    $otherChild = Student::factory()->for($unit)->create();

    $guardianUser = User::factory()->create();
    $guardianUser->assignRole(RoleEnum::Guardian->value);
    $guardian = Guardian::factory()->for($guardianUser)->create();
    $guardian->students()->attach($ownChild->id, ['relation' => 'ibu', 'is_primary' => true]);

    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    app(FeatureFlags::class)->update([Feature::Wallet->value => true], $admin);

    app(WalletService::class)->topUp($ownChild, 40_000, 'topup-g-1', $admin);

    $this->actingAs($guardianUser)
        ->get(route('guardian.students.wallet.index', $ownChild))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('guardian/wallet')
            ->where('wallet.balance', 40_000)
        );

    $this->actingAs($guardianUser)
        ->get(route('guardian.students.wallet.index', $otherChild))
        ->assertNotFound();
});

test('wali dapat mengubah limit harian dalam batas sekolah dan membekukan dompet', function (): void {
    $unit = Unit::factory()->create();
    $student = Student::factory()->for($unit)->create();

    $guardianUser = User::factory()->create();
    $guardianUser->assignRole(RoleEnum::Guardian->value);
    $guardian = Guardian::factory()->for($guardianUser)->create();
    $guardian->students()->attach($student->id, ['relation' => 'ayah', 'is_primary' => true]);

    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    app(FeatureFlags::class)->update([Feature::Wallet->value => true], $admin);

    // Melebihi batas atas sekolah (100.000) -> ditolak
    $this->actingAs($guardianUser)
        ->patch(route('guardian.students.wallet.limit', $student), [
            'daily_limit' => 250_000,
        ])
        ->assertSessionHasErrors('daily_limit');

    $this->actingAs($guardianUser)
        ->patch(route('guardian.students.wallet.limit', $student), [
            'daily_limit' => 35_000,
        ])
        ->assertRedirect();

    $this->actingAs($guardianUser)
        ->patch(route('guardian.students.wallet.freeze', $student), [
            'is_frozen' => true,
        ])
        ->assertRedirect();

    $wallet = Wallet::query()->where('student_id', $student->id)->firstOrFail();

    expect($wallet->daily_limit)->toBe(35_000)
        ->and($wallet->is_frozen)->toBeTrue()
        ->and(AuditLog::query()->where('action', 'wallet.limit_updated')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'wallet.frozen')->exists())->toBeTrue();
});
