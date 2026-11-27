<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\Role as RoleEnum;
use App\Models\User;
use App\Services\Features\FeatureFlags;
use App\Support\Navigation;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function soonUser(RoleEnum $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

test('menu segera membuka halaman sedang disiapkan, bukan beranda', function (): void {
    $this->actingAs(soonUser(RoleEnum::Admin))
        ->get(route('admin.soon', ['module' => 'ekstrakurikuler']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/coming-soon')
            ->where('module.label', 'Ekstrakurikuler')
            ->where('module.group', 'Akademik')
        );

    $this->actingAs(soonUser(RoleEnum::Admin))
        ->get(route('admin.quran.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/coming-soon')
            ->where('module.label', 'Setoran hafalan')
        );
});

test('modul segera yang tidak dikenal menghasilkan 404', function (): void {
    $this->actingAs(soonUser(RoleEnum::Admin))
        ->get(route('admin.soon', ['module' => 'tidak-ada']))
        ->assertNotFound();
});

test('modul segera tetap memeriksa izin peran', function (): void {
    $this->actingAs(soonUser(RoleEnum::Teacher))
        ->get(route('admin.soon', ['module' => 'inventaris']))
        ->assertForbidden();
});

test('modul segera yang kunci fiturnya mati menghasilkan 404', function (): void {
    $admin = soonUser(RoleEnum::Admin);
    app(FeatureFlags::class)->update([Feature::Wallet->value => false], $admin);

    $this->actingAs($admin)
        ->get(route('admin.soon', ['module' => 'saldo-kantin']))
        ->assertNotFound();
});

test('item menu menandai modul yang belum dibangun dan setiap grup punya ikon', function (): void {
    $groups = Navigation::forUser(soonUser(RoleEnum::Admin));
    $items = collect($groups)->flatMap(fn (array $group) => $group['items']);

    expect(collect($groups)->pluck('icon')->filter()->count())->toBe(count($groups))
        ->and($items->firstWhere('label', 'Siswa')['soon'])->toBeFalse()
        ->and($items->firstWhere('label', 'Ekstrakurikuler')['soon'])->toBeTrue()
        ->and($items->pluck('href')->duplicates())->toBeEmpty();
});

test('label menu tidak ganda dalam satu grup', function (): void {
    foreach (Navigation::forUser(soonUser(RoleEnum::Admin)) as $group) {
        $labels = array_column($group['items'], 'label');

        expect(array_unique($labels))->toHaveCount(count($labels));
    }
});
