<?php

declare(strict_types=1);

use App\Enums\Role as RoleEnum;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('dashboard mengarahkan pengguna sesuai perannya', function (RoleEnum $role, string $expectedPath): void {
    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole($role->value);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect($expectedPath);
})->with([
    'admin' => [RoleEnum::Admin, '/admin'],
    'bendahara' => [RoleEnum::Treasurer, '/admin'],
    'guru' => [RoleEnum::Teacher, '/admin'],
    'bk' => [RoleEnum::Counselor, '/admin'],
    'kepala unit' => [RoleEnum::UnitHead, '/admin'],
    'petugas gerbang' => [RoleEnum::GateOfficer, '/gerbang'],
    'kasir kantin' => [RoleEnum::CanteenCashier, '/kantin'],
    'wali' => [RoleEnum::Guardian, '/wali'],
]);

test('pengguna tanpa peran melihat halaman tanpa akses di dashboard', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('no-access'));
});

test('area aplikasi menolak peran yang tidak berhak dengan 403', function (): void {
    $guardian = User::factory()->create();
    $guardian->assignRole(RoleEnum::Guardian->value);

    $this->actingAs($guardian)->get('/admin')->assertForbidden();
    $this->actingAs($guardian)->get('/gerbang')->assertForbidden();
    $this->actingAs($guardian)->get('/kantin')->assertForbidden();
    $this->actingAs($guardian)->get('/wali')->assertOk();
});
