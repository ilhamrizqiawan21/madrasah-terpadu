<?php

declare(strict_types=1);

use App\Enums\Role as RoleEnum;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Models\Wallet;
use App\Notifications\WalletBalanceMismatchNotification;
use App\Services\Wallet\WalletService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('wallet:verify-balances mendeteksi selisih saldo tanpa koreksi otomatis dan mengirim notifikasi ke admin', function (): void {
    Notification::fake();

    $unit = Unit::factory()->create();
    $student = Student::factory()->for($unit)->create();
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole(RoleEnum::Admin->value);

    app(WalletService::class)->topUp($student, 25_000, 'verify-topup');

    // Cocok -> sukses tanpa notifikasi
    $this->artisan('wallet:verify-balances')->assertSuccessful();
    Notification::assertNothingSent();

    // Simulasi kerusakan cache saldo di DB
    $wallet = Wallet::query()->where('student_id', $student->id)->firstOrFail();
    DB::table('wallets')->where('id', $wallet->id)->update(['balance' => 99_000]);

    $this->artisan('wallet:verify-balances')->assertFailed();

    // Tidak ada koreksi otomatis
    expect($wallet->fresh()?->balance)->toBe(99_000);
    Notification::assertSentTo($admin, WalletBalanceMismatchNotification::class);
});
