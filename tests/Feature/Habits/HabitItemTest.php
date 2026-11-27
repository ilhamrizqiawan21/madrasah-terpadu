<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\Role;
use App\Models\FeatureFlag;
use App\Models\HabitItem;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\HabitItemSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(HabitItemSeeder::class);

    FeatureFlag::query()->updateOrCreate(
        ['key' => Feature::Habits->value],
        ['enabled' => true]
    );
});

it('seeds five locked prayer habit items and blocks modifying, deactivating, or deleting them via policy and service', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(Role::Admin->value);

    $lockedItems = HabitItem::query()->where('is_locked', true)->orderBy('sort_order')->get();
    expect($lockedItems)->toHaveCount(5)
        ->and($lockedItems->pluck('name')->all())->toBe([
            'Sholat Subuh',
            'Sholat Dzuhur',
            'Sholat Ashar',
            'Sholat Maghrib',
            'Sholat Isya',
        ]);

    $subuh = $lockedItems->firstOrFail();

    $this->actingAs($admin)
        ->put(route('admin.habits.items.update', $subuh), [
            'name' => 'Ganti Nama',
            'aspect' => 'social',
        ])
        ->assertForbidden();

    $this->actingAs($admin)
        ->patch(route('admin.habits.items.toggle', $subuh), ['is_active' => false])
        ->assertForbidden();

    $this->actingAs($admin)
        ->delete(route('admin.habits.items.destroy', $subuh))
        ->assertForbidden();

    expect($subuh->fresh()->name)->toBe('Sholat Subuh')
        ->and($subuh->fresh()->is_active)->toBeTrue();
});

it('allows admin to create, update, reorder, and deactivate custom habit items', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(Role::Admin->value);
    $guru = User::factory()->create();
    $guru->assignRole(Role::Teacher->value);
    $unit = Unit::factory()->create();

    $this->actingAs($guru)
        ->post(route('admin.habits.items.store'), [
            'unit_id' => $unit->id,
            'name' => 'Tilawah Harian',
            'aspect' => 'spiritual',
        ])
        ->assertForbidden();

    $this->actingAs($admin)
        ->post(route('admin.habits.items.store'), [
            'unit_id' => $unit->id,
            'name' => 'Tilawah Harian',
            'aspect' => 'spiritual',
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->post(route('admin.habits.items.store'), [
            'unit_id' => $unit->id,
            'name' => 'Membantu Orang Tua',
            'aspect' => 'social',
        ])
        ->assertRedirect();

    $tilawah = HabitItem::query()->where('name', 'Tilawah Harian')->firstOrFail();
    $membantu = HabitItem::query()->where('name', 'Membantu Orang Tua')->firstOrFail();
    expect($tilawah->sort_order)->toBeLessThan($membantu->sort_order);

    // Move Membantu up -> sort_order swaps cleanly
    $this->actingAs($admin)
        ->post(route('admin.habits.items.move', $membantu), ['direction' => 'up'])
        ->assertRedirect();

    expect($membantu->fresh()->sort_order)->toBeLessThan($tilawah->fresh()->sort_order);

    // Locked items CAN be reordered
    $subuh = HabitItem::query()->where('name', 'Sholat Subuh')->firstOrFail();
    $dzuhur = HabitItem::query()->where('name', 'Sholat Dzuhur')->firstOrFail();
    $this->actingAs($admin)
        ->post(route('admin.habits.items.move', $dzuhur), ['direction' => 'up'])
        ->assertRedirect();

    expect($dzuhur->fresh()->sort_order)->toBeLessThan($subuh->fresh()->sort_order);
});
