<?php

declare(strict_types=1);

use App\Enums\Role as RoleEnum;
use App\Enums\SettingKey;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Settings\SettingsRepository;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function appearanceAdmin(RoleEnum $role = RoleEnum::Admin): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

test('admin dapat menyimpan warna utama yang memenuhi kontras AA', function (): void {
    $admin = appearanceAdmin();

    $this->actingAs($admin)
        ->post(route('admin.settings.appearance.update'), [
            'primary_color' => '#1e40af',
        ])
        ->assertRedirect();

    expect(app(SettingsRepository::class)->get(SettingKey::PrimaryColor))->toBe('#1E40AF')
        ->and(AuditLog::query()->where('action', 'settings_updated')->exists())->toBeTrue();

    $this->actingAs($admin)
        ->get(route('admin.settings.appearance.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('appearance.primary_color', '#1E40AF')
            ->where('branding.primaryColor', '#1E40AF')
        );
});

test('warna dengan kontras rendah ditolak', function (string $badColor): void {
    $admin = appearanceAdmin();

    $this->actingAs($admin)
        ->post(route('admin.settings.appearance.update'), [
            'primary_color' => $badColor,
        ])
        ->assertSessionHasErrors('primary_color');

    expect(app(SettingsRepository::class)->get(SettingKey::PrimaryColor))->toBeNull();
})->with(['#FDE047', '#D1D5DB', '#FFFFFF', '#93C5FD', 'bukan-hex']);

test('admin dapat mengembalikan warna utama ke bawaan', function (): void {
    $admin = appearanceAdmin();

    $this->actingAs($admin)
        ->post(route('admin.settings.appearance.update'), [
            'primary_color' => '#4C2C72',
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->post(route('admin.settings.appearance.update'), [
            'reset_color' => true,
        ])
        ->assertRedirect();

    expect(app(SettingsRepository::class)->get(SettingKey::PrimaryColor))->toBeNull();
});

test('non-admin ditolak mengubah tampilan', function (): void {
    $teacher = appearanceAdmin(RoleEnum::Teacher);

    $this->actingAs($teacher)
        ->post(route('admin.settings.appearance.update'), ['primary_color' => '#135B42'])
        ->assertForbidden();
});
