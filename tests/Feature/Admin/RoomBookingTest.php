<?php

declare(strict_types=1);

use App\Enums\Role as RoleEnum;
use App\Models\Building;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $unit = Unit::factory()->create(['code' => 'MI']);
    $building = Building::query()->create(['unit_id' => $unit->id, 'code' => 'G1', 'name' => 'Gedung A']);
    $this->room = Room::query()->create(['building_id' => $building->id, 'code' => 'LAB', 'name' => 'Lab IPA', 'capacity' => 30]);
    $this->teacher = User::factory()->create(['name' => 'Bu Sari']);
    $this->teacher->assignRole(RoleEnum::Teacher->value);
    $this->other = User::factory()->create();
    $this->other->assignRole(RoleEnum::Teacher->value);
    $this->date = now()->addDay()->toDateString();
});

test('booking ditolak bila jam beririsan, boleh bila bersebelahan', function (): void {
    $book = fn (User $u, string $start, string $end) => $this->actingAs($u)->post(route('admin.room-bookings.store'), [
        'room_id' => $this->room->id, 'used_on' => $this->date, 'starts_at' => $start, 'ends_at' => $end,
    ]);

    $book($this->teacher, '08:00', '09:30')->assertSessionHasNoErrors();
    $book($this->other, '09:00', '10:00')->assertSessionHasErrors('starts_at');
    $book($this->other, '09:30', '10:30')->assertSessionHasNoErrors();
    $book($this->other, '11:00', '10:00')->assertSessionHasErrors('ends_at');

    expect(RoomBooking::query()->count())->toBe(2);

    $this->actingAs($this->teacher)->get(route('admin.room-bookings.index'))
        ->assertInertia(fn (Assert $page) => $page->component('admin/academic/room-bookings')
            ->has('rows.data', 2)->where('rows.data.0.user', 'Bu Sari')->where('rows.data.0.can_cancel', true)
            ->where('rows.data.1.can_cancel', false));
});

test('booking tanggal lampau ditolak dan hanya pemesan yang bisa membatalkan', function (): void {
    $this->actingAs($this->teacher)->post(route('admin.room-bookings.store'), [
        'room_id' => $this->room->id, 'used_on' => now()->subDay()->toDateString(), 'starts_at' => '08:00', 'ends_at' => '09:00',
    ])->assertSessionHasErrors('used_on');

    $booking = RoomBooking::query()->create([
        'room_id' => $this->room->id, 'user_id' => $this->teacher->id, 'used_on' => $this->date, 'starts_at' => '08:00', 'ends_at' => '09:00',
    ]);
    $this->actingAs($this->other)->delete(route('admin.room-bookings.destroy', $booking))->assertNotFound();
    $this->actingAs($this->teacher)->delete(route('admin.room-bookings.destroy', $booking))->assertSessionHasNoErrors();
    expect(RoomBooking::query()->count())->toBe(0);
});
