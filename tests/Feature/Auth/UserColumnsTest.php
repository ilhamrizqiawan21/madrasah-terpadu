<?php

declare(strict_types=1);

use App\Enums\Role as RoleEnum;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

test('factory user menghasilkan kolom tambahan yang valid', function (): void {
    $user = User::factory()->create();

    expect($user->username)->not->toBeNull()
        ->and($user->phone)->toStartWith('08')
        ->and($user->is_active)->toBeTrue()
        ->and($user->last_login_at)->toBeNull();
});

test('email user bersifat opsional dan tanpa kolom must_change_password', function (): void {
    $user = User::factory()->create(['email' => null]);

    expect($user->email)->toBeNull()
        ->and(Schema::hasColumn('users', 'must_change_password'))->toBeFalse();
});

test('nomor HP user otomatis dinormalisasi saat disimpan', function (): void {
    $user = User::factory()->create(['phone' => '+62 811-2233-4455']);

    expect($user->fresh()->phone)->toBe('081122334455');
});

test('username dan nomor HP user harus unik', function (): void {
    User::factory()->create([
        'username' => 'wali.andi',
        'phone' => '081299998888',
    ]);

    expect(fn () => User::factory()->create(['username' => 'wali.andi']))
        ->toThrow(QueryException::class);

    expect(fn () => User::factory()->create(['phone' => '+62 812-9999-8888']))
        ->toThrow(QueryException::class);
});

test('user mendukung soft delete dan spatie roles', function (): void {
    Role::findOrCreate(RoleEnum::Guardian->value, 'web');

    $user = User::factory()->create();
    $user->assignRole(RoleEnum::Guardian->value);

    expect($user->hasRole(RoleEnum::Guardian->value))->toBeTrue();

    $user->delete();

    expect(User::find($user->id))->toBeNull()
        ->and(User::withTrashed()->find($user->id))->not->toBeNull();
});
