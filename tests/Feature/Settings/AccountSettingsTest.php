<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

test('halaman login menampilkan nama yayasan dari konfigurasi', function (): void {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/login')
            ->where('branding.foundationName', config('sekolah.foundation_name'))
            ->where('branding.appName', config('app.name')),
        );
});

test('pengguna dapat memperbarui nama dan nomor HP pada profil tanpa email', function (): void {
    $user = User::factory()->create([
        'email' => null,
        'phone' => null,
    ]);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Ahmad Fauzi',
            'phone' => '0812-3456-7890',
            'email' => '',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Ahmad Fauzi')
        ->and($user->phone)->toBe('081234567890')
        ->and($user->email)->toBeNull();
});

test('nomor HP profil harus unik setelah dinormalisasi', function (): void {
    User::factory()->create(['phone' => '081234567890']);
    $user = User::factory()->create(['phone' => '081111111111']);

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'phone' => '+62 812-3456-7890',
        ])
        ->assertSessionHasErrors('phone')
        ->assertRedirect(route('profile.edit'));
});

test('ganti kata sandi wajib menyertakan kata sandi lama yang benar dan bisa login dengan kata sandi baru', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $user = User::factory()->create([
        'username' => 'wali_uji',
    ]);
    $user->assignRole(Role::Guardian->value);

    // Gagal bila kata sandi lama salah
    $this->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'kata-sandi-salah',
            'password' => 'KataSandiBaru123!',
            'password_confirmation' => 'KataSandiBaru123!',
        ])
        ->assertSessionHasErrors('current_password')
        ->assertRedirect(route('security.edit'));

    // Berhasil bila kata sandi lama benar
    $this->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'KataSandiBaru123!',
            'password_confirmation' => 'KataSandiBaru123!',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('security.edit'));

    expect(Hash::check('KataSandiBaru123!', $user->refresh()->password))->toBeTrue();

    // Keluar lalu masuk dengan kata sandi baru
    $this->post(route('logout'));

    $this->post(route('login.store'), [
        'email' => 'wali_uji',
        'password' => 'KataSandiBaru123!',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});
