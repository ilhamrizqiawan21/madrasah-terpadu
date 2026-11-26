<?php

declare(strict_types=1);

use App\Enums\Role;

it('memiliki label Indonesia untuk setiap peran', function (Role $role): void {
    expect($role->label())->not->toBeEmpty();
})->with(Role::cases());

it('tidak memasukkan wali murid ke daftar peran staf', function (): void {
    expect(Role::staff())
        ->not->toContain(Role::Guardian)
        ->toHaveCount(count(Role::cases()) - 1);
});

it('menyimpan nilai peran yang dipakai di tabel roles', function (): void {
    expect(array_map(fn (Role $role): string => $role->value, Role::cases()))->toBe([
        'admin', 'bendahara', 'guru', 'bk', 'kepala_unit', 'petugas_gerbang', 'kasir_kantin', 'wali',
    ]);
});
