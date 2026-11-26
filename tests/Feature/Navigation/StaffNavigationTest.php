<?php

declare(strict_types=1);

use App\Enums\Role as RoleEnum;
use App\Models\User;
use App\Support\Navigation;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('guru tidak menerima grup menu keuangan pada navigasi server', function (): void {
    $teacher = User::factory()->create();
    $teacher->assignRole(RoleEnum::Teacher->value);

    $this->actingAs($teacher)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(function (Assert $page): void {
            $navigation = $page->toArray()['props']['navigation'];
            $keys = array_column($navigation, 'key');

            expect($keys)->toContain('data-induk', 'presensi', 'kesiswaan', 'ibadah')
                ->not->toContain('keuangan', 'laporan', 'pengaturan');
        });
});

test('setiap item menu navigasi lolos otorisasi untuk peran tersebut', function (RoleEnum $role): void {
    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole($role->value);

    $groups = Navigation::forUser($user);

    foreach ($groups as $group) {
        foreach ($group['items'] as $item) {
            $this->actingAs($user)
                ->get($item['href'])
                ->assertOk();
        }
    }

    expect($groups)->toBeArray();
})->with(RoleEnum::cases());

test('rute modul staf menolak pengguna tanpa izin yang sesuai', function (): void {
    $teacher = User::factory()->create();
    $teacher->assignRole(RoleEnum::Teacher->value);

    $this->actingAs($teacher)
        ->get(route('admin.invoices.index'))
        ->assertForbidden();
});

test('pemilih unit dan tahun ajaran menyimpan konteks ke dalam sesi', function (): void {
    $admin = User::factory()->withTwoFactor()->create();
    $admin->assignRole(RoleEnum::Admin->value);

    $this->actingAs($admin)
        ->post(route('admin.context.update'), [
            'unit' => 'TPQ',
            'academic_year' => '2025/2026',
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('schoolContext.unit', 'TPQ')
            ->where('schoolContext.academicYear', '2025/2026'),
        );
});
