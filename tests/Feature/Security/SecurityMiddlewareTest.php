<?php

declare(strict_types=1);

use App\Models\User;

test('pengguna yang dinonaktifkan otomatis logout pada request berikutnya', function (): void {
    $user = User::factory()->create(['is_active' => true]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();

    $user->update(['is_active' => false]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('respons web menyertakan header keamanan dan CSP yang sesuai', function (): void {
    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

    $csp = (string) $response->headers->get('Content-Security-Policy');
    $permissionsPolicy = (string) $response->headers->get('Permissions-Policy');

    expect($csp)
        ->toContain("default-src 'self'")
        ->toContain('https://app.midtrans.com')
        ->toContain('https://app.sandbox.midtrans.com')
        ->and($permissionsPolicy)->toContain('camera=()');
});
