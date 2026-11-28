<?php

declare(strict_types=1);

use App\Enums\MeetingAudience;
use App\Enums\Role as RoleEnum;
use App\Models\Meeting;
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

test('hanya pengelola pengguna yang membuka undangan rapat', function (): void {
    $this->get(route('admin.meetings.index'))->assertRedirect(route('login'));

    $teacher = User::factory()->create();
    $teacher->assignRole('guru');
    $this->actingAs($teacher)->get(route('admin.meetings.index'))->assertForbidden();
    $this->actingAs($teacher)->post(route('admin.meetings.store'), [])->assertForbidden();
});

test('admin menjadwalkan, menulis notulen, menyaring, lalu menghapus agenda rapat dengan audit', function (): void {
    $this->actingAs($this->admin)->post(route('admin.meetings.store'), [
        'title' => 'Rapat evaluasi kurikulum',
        'held_on' => '2026-12-01',
        'unit_id' => $this->unit->id,
        'audience' => 'teachers',
    ])->assertSessionHasNoErrors();

    $meeting = Meeting::query()->sole();
    expect($meeting->audience)->toBe(MeetingAudience::Teachers)
        ->and($meeting->minutes)->toBeNull()
        ->and($meeting->created_by)->toBe($this->admin->id);

    $this->actingAs($this->admin)->put(route('admin.meetings.update', $meeting), [
        'title' => 'Rapat evaluasi kurikulum',
        'held_on' => '2026-12-01',
        'unit_id' => $this->unit->id,
        'audience' => 'teachers',
        'minutes' => "1. Jadwal PTS disepakati\n2. Modul ajar dikumpulkan",
    ])->assertSessionHasNoErrors();
    expect($meeting->fresh()->minutes)->toContain('Jadwal PTS');

    Meeting::query()->create(['title' => 'Rapat yayasan', 'held_on' => '2026-12-05', 'audience' => 'all_staff']);

    $this->actingAs($this->admin)->get(route('admin.meetings.index', ['unit' => $this->unit->id]))
        ->assertInertia(fn (Assert $page) => $page->component('admin/users/meetings')->has('meetings.data', 1)
            ->where('meetings.data.0.audience_label', 'Seluruh guru pengampu'));
    $this->actingAs($this->admin)->get(route('admin.meetings.index', ['search' => 'yayasan']))
        ->assertInertia(fn (Assert $page) => $page->has('meetings.data', 1)->where('meetings.data.0.title', 'Rapat yayasan'));

    $this->actingAs($this->admin)->delete(route('admin.meetings.destroy', $meeting))->assertRedirect();
    expect(Meeting::query()->find($meeting->id))->toBeNull();
    $this->assertDatabaseHas('audit_logs', ['action' => 'meeting.deleted', 'subject_id' => $meeting->id]);
});

test('validasi agenda rapat', function (): void {
    $this->actingAs($this->admin)->post(route('admin.meetings.store'), [
        'title' => '', 'held_on' => '01-12-2026', 'audience' => 'semua',
    ])->assertSessionHasErrors(['title', 'held_on', 'audience']);
});
