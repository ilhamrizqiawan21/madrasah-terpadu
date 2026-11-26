<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
    Schema::create('students', function (Blueprint $table): void {
        $table->id();
        $table->string('nis', 20)->unique();
        $table->timestamps();
    });

    Schema::create('guardians', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('user_id');
        $table->timestamps();
    });

    Schema::create('guardian_student', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('guardian_id');
        $table->foreignId('student_id');
        $table->boolean('is_primary')->default(false);
    });

    $primaryUser = User::factory()->create();
    $secondaryUser = User::factory()->create();

    $studentId = DB::table('students')->insertGetId(['nis' => '20260001']);
    $primaryGuardianId = DB::table('guardians')->insertGetId(['user_id' => $primaryUser->id]);
    $secondaryGuardianId = DB::table('guardians')->insertGetId(['user_id' => $secondaryUser->id]);

    DB::table('guardian_student')->insert([
        ['guardian_id' => $secondaryGuardianId, 'student_id' => $studentId, 'is_primary' => false],
        ['guardian_id' => $primaryGuardianId, 'student_id' => $studentId, 'is_primary' => true],
    ]);

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
