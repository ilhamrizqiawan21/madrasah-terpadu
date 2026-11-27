<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

/*
| Fitur bawaan starter kit yang dimatikan (ADR-024):
| - pengguna menghapus akunnya sendiri (akun dikelola sekolah, ADR-014);
| - login/konfirmasi dengan passkey (PRD: login dengan username/nomor HP/NIS).
*/

test('pengguna tidak dapat menghapus akunnya sendiri', function (): void {
    $user = User::factory()->create();

    expect(Route::has('profile.destroy'))->toBeFalse();

    $this->actingAs($user)
        ->delete('/settings/profile', ['password' => 'password'])
        ->assertMethodNotAllowed();

    expect($user->fresh()->trashed())->toBeFalse();
    $this->assertAuthenticatedAs($user);
});

test('fitur passkey nonaktif dan rutenya tidak terdaftar', function (): void {
    expect(Features::enabled(Features::passkeys()))->toBeFalse()
        ->and(Route::has('passkey.login'))->toBeFalse()
        ->and(Route::has('passkey.store'))->toBeFalse()
        ->and(Route::has('well-known.passkeys'))->toBeFalse();

    $this->post('/passkeys/login')->assertNotFound();
    $this->get('/.well-known/passkey-endpoints')->assertNotFound();
});

test('halaman keamanan tidak menawarkan pengelolaan passkey', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('canManagePasskeys', false));
});
