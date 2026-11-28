<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\Role as RoleEnum;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletEntry;
use App\Notifications\WalletTopupNotification;
use App\Services\Features\FeatureFlags;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('isi saldo tunai hanya untuk bendahara dan admin serta idempoten per client_uuid', function (): void {
    Notification::fake();

    $unit = Unit::factory()->create();
    $student = Student::factory()->for($unit)->create();

    $guardianUser = User::factory()->create();
    $guardianUser->assignRole(RoleEnum::Guardian->value);
    $guardian = Guardian::factory()->for($guardianUser)->create();
    $guardian->students()->attach($student->id, ['relation' => 'ibu', 'is_primary' => true]);

    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    app(FeatureFlags::class)->update([Feature::Wallet->value => true], $admin);

    $treasurer = User::factory()->create();
    $treasurer->assignRole(RoleEnum::Treasurer->value);

    $teacher = User::factory()->create();
    $teacher->assignRole(RoleEnum::Teacher->value);

    $cashier = User::factory()->create();
    $cashier->assignRole(RoleEnum::CanteenCashier->value);

    $uuid = (string) Str::uuid();

    $this->actingAs($teacher)
        ->post(route('admin.wallets.topup'), [
            'student_id' => $student->id,
            'amount' => 50_000,
            'client_uuid' => $uuid,
        ])
        ->assertForbidden();

    $this->actingAs($cashier)
        ->post(route('admin.wallets.topup'), [
            'student_id' => $student->id,
            'amount' => 50_000,
            'client_uuid' => $uuid,
        ])
        ->assertForbidden();

    $this->actingAs($treasurer)
        ->post(route('admin.wallets.topup'), [
            'student_id' => $student->id,
            'amount' => 50_000,
            'client_uuid' => $uuid,
        ])
        ->assertRedirect();

    // Kirim ulang client_uuid sama -> idempoten
    $this->actingAs($treasurer)
        ->post(route('admin.wallets.topup'), [
            'student_id' => $student->id,
            'amount' => 50_000,
            'client_uuid' => $uuid,
        ])
        ->assertRedirect();

    $wallet = Wallet::query()->where('student_id', $student->id)->firstOrFail();
    $entry = WalletEntry::query()->where('wallet_id', $wallet->id)->firstOrFail();

    expect($wallet->balance)->toBe(50_000)
        ->and(WalletEntry::query()->where('wallet_id', $wallet->id)->count())->toBe(1);

    Notification::assertSentToTimes($guardianUser, WalletTopupNotification::class, 1);

    $pdf = $this->actingAs($treasurer)->get(route('admin.wallets.receipt', $entry));
    $pdf->assertOk();
    expect((string) $pdf->getContent())->toStartWith('%PDF');
});
