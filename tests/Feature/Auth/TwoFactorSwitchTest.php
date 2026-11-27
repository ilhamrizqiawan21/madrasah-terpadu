<?php

declare(strict_types=1);

use App\Enums\Role as RoleEnum;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/*
| 2FA dimatikan sementara atas keputusan pemilik (ADR-026): tanpa tantangan kode saat login
| dan tanpa menu 2FA di halaman Keamanan. Sakelar: sekolah.security.two_factor_enabled.
*/

test('2FA nonaktif secara bawaan', function (): void {
    $config = require base_path('config/sekolah.php');

    expect($config['security']['two_factor_enabled'])->toBeFalse();
});

test('saat 2FA nonaktif, akun yang punya 2FA langsung masuk tanpa tantangan kode', function (): void {
    config(['sekolah.security.two_factor_enabled' => false]);
    $user = User::factory()->withTwoFactor()->create();

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('saat 2FA aktif, akun yang punya 2FA diarahkan ke tantangan kode', function (): void {
    config(['sekolah.security.two_factor_enabled' => true]);
    $user = User::factory()->withTwoFactor()->create();

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('two-factor.login'));

    $this->assertGuest();
});

test('saat 2FA nonaktif, halaman keamanan tidak menawarkan pengaturan 2FA', function (): void {
    config(['sekolah.security.two_factor_enabled' => false]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canManageTwoFactor', false));
});

test('kewajiban 2FA tidak mengunci admin saat fitur 2FA nonaktif', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    config([
        'sekolah.security.two_factor_enabled' => false,
        'sekolah.security.require_two_factor' => true,
    ]);
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);

    $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
});
