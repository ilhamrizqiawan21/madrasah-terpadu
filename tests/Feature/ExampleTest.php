<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

test('tamu yang membuka halaman depan berakhir di halaman login', function (): void {
    $this->get(route('home'))->assertRedirect(route('dashboard'));

    $this->followingRedirects()
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/login'));
});

test('pengguna yang sudah masuk diarahkan dari halaman depan ke beranda sesuai peran', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $guardian = User::factory()->create();
    $guardian->assignRole(Role::Guardian->value);

    $this->actingAs($guardian)
        ->get(route('home'))
        ->assertRedirect(route('dashboard'));

    $this->actingAs($guardian)
        ->followingRedirects()
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('guardian/home'));
});
