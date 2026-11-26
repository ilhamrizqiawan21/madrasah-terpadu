<?php

declare(strict_types=1);

use App\Enums\Role as RoleEnum;
use App\Models\User;
use Database\Seeders\ProductionSeeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

test('perintah app:create-admin membuat akun admin baru secara non-interaktif', function (): void {
    $this->artisan('app:create-admin', [
        '--name' => 'Admin Yayasan',
        '--username' => 'admin.yayasan',
        '--password' => 'RahasiaYayasan123!',
    ])
        ->assertSuccessful();

    $user = User::query()->where('username', 'admin.yayasan')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Admin Yayasan')
        ->and($user->is_active)->toBeTrue()
        ->and(Hash::check('RahasiaYayasan123!', $user->password))->toBeTrue()
        ->and($user->hasRole(RoleEnum::Admin->value))->toBeTrue();
});

test('perintah app:create-admin mendukung mode interaktif', function (): void {
    $this->artisan('app:create-admin')
        ->expectsQuestion('Nama lengkap', 'Operator Utama')
        ->expectsQuestion('Username', 'operator.utama')
        ->expectsQuestion('Kata sandi (minimal 12 karakter)', 'SandiOperator123!')
        ->assertSuccessful();

    $user = User::query()->where('username', 'operator.utama')->first();

    expect($user)->not->toBeNull()
        ->and($user->hasRole(RoleEnum::Admin->value))->toBeTrue();
});

test('perintah app:create-admin menolak password kurang dari 12 karakter', function (): void {
    $this->artisan('app:create-admin', [
        '--name' => 'Admin Pendek',
        '--username' => 'admin.pendek',
        '--password' => 'pendek123',
    ])
        ->assertFailed();

    expect(User::query()->where('username', 'admin.pendek')->exists())->toBeFalse();
});

test('perintah app:create-admin menolak username duplikat', function (): void {
    User::factory()->create(['username' => 'admin.duplikat']);

    $this->artisan('app:create-admin', [
        '--name' => 'Admin Kedua',
        '--username' => 'admin.duplikat',
        '--password' => 'RahasiaYayasan123!',
    ])
        ->assertFailed();

    expect(User::query()->where('username', 'admin.duplikat')->count())->toBe(1);
});

test('ProductionSeeder membuat peran dan izin tanpa membuat akun pengguna', function (): void {
    $this->seed(ProductionSeeder::class);
    $this->seed(ProductionSeeder::class);

    expect(Role::query()->count())->toBe(count(RoleEnum::cases()))
        ->and(User::query()->count())->toBe(0);
});
