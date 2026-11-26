<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Validator;

test('konfigurasi locale aplikasi menggunakan bahasa indonesia', function (): void {
    expect(app()->getLocale())->toBe('id');
});

test('login gagal menampilkan pesan kesalahan bahasa indonesia', function (): void {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors('email');

    $errors = session('errors')->get('email');

    expect($errors[0])
        ->not->toBe('These credentials do not match our records.')
        ->toBe(__('auth.failed'));
});

test('pesan validasi dan atribut umum menggunakan bahasa indonesia', function (): void {
    $validator = Validator::make(
        ['name' => '', 'phone' => '', 'password' => '123'],
        ['name' => 'required', 'phone' => 'required', 'password' => 'min:8']
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('name'))->toContain('nama')->toContain('wajib');
    expect($validator->errors()->first('phone'))->toContain('nomor HP')->toContain('wajib');
    expect($validator->errors()->first('password'))->toContain('kata sandi')->toContain('8');
});
