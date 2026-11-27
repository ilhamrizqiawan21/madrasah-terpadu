<?php

declare(strict_types=1);

use App\Enums\Role as RoleEnum;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    foreach (RoleEnum::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    // Kewajiban 2FA bisa dimatikan sementara (ADR-025); tes T-104 menguji saat dinyalakan.
    config([
        'sekolah.security.two_factor_enabled' => true,
        'sekolah.security.require_two_factor' => true,
    ]);
});

test('saat kewajiban 2FA dimatikan, admin tanpa 2FA tetap bisa masuk area staf', function (): void {
    config(['sekolah.security.require_two_factor' => false]);

    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk();
});

test('kewajiban 2FA nonaktif secara bawaan selama masa pengembangan', function (): void {
    config()->offsetUnset('sekolah.security.require_two_factor');
    $config = require base_path('config/sekolah.php');

    expect($config['security']['require_two_factor'])->toBeFalse();
});

test('admin tanpa 2FA diarahkan ke pengaturan keamanan', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertRedirect(route('security.edit'));
});

test('bendahara tanpa 2FA diarahkan ke pengaturan keamanan', function (): void {
    $treasurer = User::factory()->create();
    $treasurer->assignRole(RoleEnum::Treasurer->value);

    $this->actingAs($treasurer)
        ->get(route('dashboard'))
        ->assertRedirect(route('security.edit'));
});

test('admin tanpa 2FA tetap dapat membuka halaman pengaturan keamanan tanpa redirect berulang', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);

    $this->actingAs($admin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk();
});

test('admin dengan 2FA aktif dapat mengakses area staf', function (): void {
    $admin = User::factory()->withTwoFactor()->create();
    $admin->assignRole(RoleEnum::Admin->value);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk();
});

test('peran non-istimewa tanpa 2FA tetap dapat mengakses area staf', function (): void {
    $teacher = User::factory()->create();
    $teacher->assignRole(RoleEnum::Teacher->value);

    $this->actingAs($teacher)
        ->get(route('admin.dashboard'))
        ->assertOk();
});
