<?php

declare(strict_types=1);

use App\Enums\Role as RoleEnum;
use App\Models\Building;
use App\Models\Room;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleEnum::Admin->value);
    $this->unit = Unit::factory()->create(['code' => 'MI']);
});

test('hanya pengelola rombel yang membuka gedung dan ruangan', function (): void {
    $teacher = User::factory()->create();
    $teacher->assignRole('guru');
    $this->actingAs($teacher)->get(route('admin.buildings.index'))->assertForbidden();
    $this->actingAs($teacher)->post(route('admin.rooms.store'), [])->assertForbidden();
});

test('admin mengelola gedung dan ruangan; kode unik per induk; gedung berisi ruangan tidak bisa dihapus', function (): void {
    $this->actingAs($this->admin)->post(route('admin.buildings.store'), ['unit_id' => $this->unit->id, 'code' => 'gd-a', 'name' => 'Gedung Utama'])
        ->assertSessionHasNoErrors();
    $building = Building::query()->sole();
    expect($building->code)->toBe('GD-A');

    $this->actingAs($this->admin)->post(route('admin.buildings.store'), ['unit_id' => $this->unit->id, 'code' => 'GD-A', 'name' => 'Ganda'])
        ->assertSessionHasErrors('code');

    $this->actingAs($this->admin)->post(route('admin.rooms.store'), ['building_id' => $building->id, 'code' => 'R101', 'name' => 'Kelas 1-A', 'capacity' => 28])
        ->assertSessionHasNoErrors();
    $room = Room::query()->sole();

    $this->actingAs($this->admin)->get(route('admin.rooms.index', ['building' => $building->id]))
        ->assertInertia(fn (Assert $page) => $page->component('admin/academic/rooms')->where('rooms.data.0.capacity', 28));

    $this->actingAs($this->admin)->delete(route('admin.buildings.destroy', $building))->assertSessionHasErrors('building');

    $this->actingAs($this->admin)->put(route('admin.rooms.update', $room), ['building_id' => $building->id, 'code' => 'R101', 'name' => 'Lab IPA', 'capacity' => 20])
        ->assertSessionHasNoErrors();
    expect($room->fresh()->name)->toBe('Lab IPA');

    $this->actingAs($this->admin)->delete(route('admin.rooms.destroy', $room))->assertRedirect();
    $this->actingAs($this->admin)->delete(route('admin.buildings.destroy', $building))->assertSessionHasNoErrors();
    expect(Building::query()->count())->toBe(0);
    $this->assertDatabaseHas('audit_logs', ['action' => 'building.deleted']);
});
