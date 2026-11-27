<?php

declare(strict_types=1);

use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('pengguna dapat login menggunakan username dan last_login_at diperbarui serta sesi diregenerasi', function (): void {
    $user = User::factory()->create([
        'username' => 'ustadz.ahmad',
        'last_login_at' => null,
    ]);

    $this->get(route('login'));
    $initialSessionId = session()->getId();

    $response = $this->post(route('login.store'), [
        'email' => 'ustadz.ahmad',
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard', absolute: false));

    expect($user->fresh()->last_login_at)->not->toBeNull()
        ->and(session()->getId())->not->toBe($initialSessionId);
});

test('pengguna dapat login menggunakan nomor HP dengan berbagai format', function (): void {
    $user = User::factory()->create([
        'phone' => '081234567890',
    ]);

    $response = $this->post(route('login.store'), [
        'email' => '+62 812-3456-7890',
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('wali dapat login menggunakan NIS yang memetakan ke wali utama siswa', function (): void {
    $primaryUser = User::factory()->create();
    $secondaryUser = User::factory()->create();

    $student = Student::factory()->create(['nis' => '20260001']);
    Guardian::factory()->for($secondaryUser)->linkedTo($student, primary: false)->create();
    Guardian::factory()->for($primaryUser)->linkedTo($student, primary: true)->create();

    $response = $this->post(route('login.store'), [
        'email' => '20260001',
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($primaryUser);
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('akun nonaktif ditolak dengan pesan kesalahan generik', function (): void {
    $user = User::factory()->inactive()->create([
        'username' => 'staf.nonaktif',
    ]);

    $response = $this->post(route('login.store'), [
        'email' => 'staf.nonaktif',
        'password' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors([
        'email' => __('auth.failed'),
    ]);
});

test('login dibatasi maksimal 5 percobaan per menit per identitas dan IP', function (): void {
    User::factory()->create(['username' => 'budi.santoso']);

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('login.store'), [
            'email' => 'budi.santoso',
            'password' => 'salah-terus',
        ]);
    }

    $response = $this->post(route('login.store'), [
        'email' => 'budi.santoso',
        'password' => 'salah-terus',
    ]);

    $response->assertTooManyRequests();
});

test('percobaan login gagal dicatat di audit_logs tanpa password', function (): void {
    $user = User::factory()->create(['username' => 'guru.ani']);

    $this->post(route('login.store'), [
        'email' => 'guru.ani',
        'password' => 'rahasia-salah',
    ]);

    $log = DB::table('audit_logs')->where('action', 'login_failed')->first();

    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe($user->id)
        ->and($log->after)->toContain('guru.ani')
        ->and($log->after)->not->toContain('rahasia-salah');
});
