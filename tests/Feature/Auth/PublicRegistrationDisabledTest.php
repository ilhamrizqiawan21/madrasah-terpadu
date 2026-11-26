<?php

declare(strict_types=1);

use App\Models\User;

test('rute registrasi publik mengembalikan 404', function (): void {
    $this->get('/register')->assertNotFound();
    $this->post('/register', [
        'name' => 'Pengguna Baru',
        'email' => 'baru@example.com',
        'password' => 'password1234',
        'password_confirmation' => 'password1234',
    ])->assertNotFound();
});

test('pengguna yang belum verifikasi email tetap dapat mengakses aplikasi', function (): void {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('appearance.edit'))
        ->assertOk();
});
