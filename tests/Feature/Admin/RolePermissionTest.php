<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\Role as RoleEnum;
use App\Models\User;
use App\Support\PermissionCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleEnum::Admin->value);
});

test('katalog hak akses memuat setiap izin tepat satu kali', function (): void {
    $listed = collect(PermissionCatalog::groups())
        ->flatMap(fn (array $g) => $g['rows'])
        ->flatMap(fn (array $r) => array_filter([$r['view'], $r['manage']]))
        ->map(fn (Permission $p): string => $p->value);

    expect($listed->duplicates())->toBeEmpty()
        ->and($listed->sort()->values()->all())
        ->toBe(collect(Permission::cases())->map->value->sort()->values()->all());
});

test('hanya pemegang izin kelola pengguna yang membuka halaman hak akses', function (): void {
    $this->get(route('admin.roles.index'))->assertRedirect(route('login'));

    $treasurer = User::factory()->create();
    $treasurer->assignRole(RoleEnum::Treasurer->value);
    $this->actingAs($treasurer)->get(route('admin.roles.index'))->assertForbidden();
    $this->actingAs($treasurer)->put(route('admin.roles.update', RoleEnum::Teacher->value), ['permissions' => []])->assertForbidden();

    $this->actingAs($this->admin)->get(route('admin.roles.index', ['peran' => 'guru']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/roles')
            ->where('selectedRole', 'guru')
            ->where('roles', fn ($roles): bool => ! collect($roles)->pluck('value')->contains('admin')
                && ! collect($roles)->pluck('value')->contains('wali'))
            ->where('granted', fn ($granted): bool => in_array(Permission::CbtTeach->value, $granted->all(), true)));
});

test('admin mengubah izin guru; kelola otomatis menyertakan lihat dan tercatat di audit', function (): void {
    $this->actingAs($this->admin)
        ->put(route('admin.roles.update', 'guru'), ['permissions' => [Permission::InvoicesIssue->value, Permission::ReportsView->value]])
        ->assertRedirect(route('admin.roles.index', ['peran' => 'guru']));

    $names = Role::findByName('guru', 'web')->permissions()->pluck('name')->sort()->values()->all();
    expect($names)->toBe(collect([Permission::InvoicesIssue->value, Permission::InvoicesView->value, Permission::ReportsView->value])->sort()->values()->all());

    $teacher = User::factory()->create();
    $teacher->assignRole('guru');
    expect($teacher->fresh()->can(Permission::StudentsView->value))->toBeFalse()
        ->and($teacher->fresh()->can(Permission::InvoicesView->value))->toBeTrue();

    $this->assertDatabaseHas('audit_logs', ['action' => 'role.permissions_updated', 'user_id' => $this->admin->id]);
});

test('peran admin dan wali tidak dapat diubah serta izin tidak dikenal ditolak', function (): void {
    $this->actingAs($this->admin)
        ->put(route('admin.roles.update', 'admin'), ['permissions' => []])
        ->assertSessionHasErrors('role');
    $this->actingAs($this->admin)
        ->put(route('admin.roles.update', 'wali'), ['permissions' => [Permission::StudentsView->value]])
        ->assertSessionHasErrors('role');
    $this->actingAs($this->admin)
        ->put(route('admin.roles.update', 'guru'), ['permissions' => ['izin.palsu']])
        ->assertSessionHasErrors('permissions.0');
    $this->actingAs($this->admin)
        ->put(route('admin.roles.update', 'peran-palsu'), ['permissions' => []])
        ->assertNotFound();

    expect(Role::findByName('admin', 'web')->permissions()->count())->toBe(count(Permission::cases()));
});

test('menjalankan ulang seeder saat rilis tidak menimpa izin yang diatur admin', function (): void {
    $this->actingAs($this->admin)
        ->put(route('admin.roles.update', 'guru'), ['permissions' => [Permission::ReportsView->value]]);

    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Role::findByName('guru', 'web')->permissions()->pluck('name')->all())->toBe([Permission::ReportsView->value])
        ->and(Role::findByName('admin', 'web')->permissions()->count())->toBe(count(Permission::cases()));
});
