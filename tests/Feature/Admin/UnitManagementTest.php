<?php

declare(strict_types=1);

use App\Enums\Role as RoleEnum;
use App\Models\AuditLog;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\ProductionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UnitSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function unitAdmin(): User
{
    $admin = User::factory()->withTwoFactor()->create();
    $admin->assignRole(RoleEnum::Admin->value);

    return $admin;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function unitPayload(array $overrides = []): array
{
    return [
        'code' => 'MI',
        'name' => 'Madrasah Ibtidaiyah',
        'check_in_opens_at' => '05:30',
        'late_after' => '07:00',
        'absent_after' => '09:00',
        'check_out_opens_at' => '11:00',
        ...$overrides,
    ];
}

test('tamu diarahkan ke halaman login saat membuka daftar unit', function (): void {
    $this->get(route('admin.settings.units.index'))->assertRedirect(route('login'));
});

test('peran selain admin ditolak mengelola unit', function (RoleEnum $role): void {
    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole($role->value);
    $unit = Unit::factory()->create();

    $this->actingAs($user)->get(route('admin.settings.units.index'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.settings.units.store'), unitPayload(['code' => 'RA']))->assertForbidden();
    $this->actingAs($user)->put(route('admin.settings.units.update', $unit), unitPayload())->assertForbidden();
    $this->actingAs($user)->patch(route('admin.settings.units.status.update', $unit), ['is_active' => false])->assertForbidden();

    expect(Unit::query()->count())->toBe(1);
})->with([
    RoleEnum::Treasurer,
    RoleEnum::Teacher,
    RoleEnum::UnitHead,
]);

test('admin melihat daftar unit berurutan kode beserta jam presensinya', function (): void {
    Unit::factory()->create(['code' => 'TPQ']);
    Unit::factory()->create(['code' => 'MI']);

    $this->actingAs(unitAdmin())
        ->get(route('admin.settings.units.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/settings/units')
            ->has('units', 2)
            ->where('units.0.code', 'MI')
            ->where('units.1.code', 'TPQ')
            ->has('units.0.attendance.late_after'));
});

test('admin menambah unit dengan jam presensi, kode dijadikan huruf besar, dan tercatat di audit', function (): void {
    $admin = unitAdmin();

    $this->actingAs($admin)
        ->post(route('admin.settings.units.store'), unitPayload(['code' => ' mi ']))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $unit = Unit::query()->sole();

    expect($unit->code)->toBe('MI')
        ->and($unit->is_active)->toBeTrue()
        ->and($unit->attendance_settings)->toBe([
            'check_in_opens_at' => '05:30',
            'late_after' => '07:00',
            'absent_after' => '09:00',
            'check_out_opens_at' => '11:00',
        ]);

    $audit = AuditLog::query()->where('action', 'created')->where('subject_type', Unit::class)->sole();
    expect($audit->user_id)->toBe($admin->id)
        ->and($audit->subject_id)->toBe($unit->id);
});

test('kode unit harus unik', function (): void {
    Unit::factory()->create(['code' => 'MI']);

    $this->actingAs(unitAdmin())
        ->post(route('admin.settings.units.store'), unitPayload(['code' => 'mi']))
        ->assertSessionHasErrors('code');

    expect(Unit::query()->count())->toBe(1);
});

test('urutan jam presensi yang tidak valid ditolak', function (array $hours, string $field): void {
    $this->actingAs(unitAdmin())
        ->post(route('admin.settings.units.store'), unitPayload($hours))
        ->assertSessionHasErrors($field);

    expect(Unit::query()->count())->toBe(0);
})->with([
    'terlambat sebelum jam masuk' => [['check_in_opens_at' => '07:00', 'late_after' => '06:30'], 'late_after'],
    'terlambat sama dengan jam masuk' => [['check_in_opens_at' => '07:00', 'late_after' => '07:00'], 'late_after'],
    'alpa sebelum batas terlambat' => [['late_after' => '07:00', 'absent_after' => '06:45'], 'absent_after'],
    'pulang sebelum batas terlambat' => [['late_after' => '07:00', 'check_out_opens_at' => '06:59'], 'check_out_opens_at'],
    'format jam salah' => [['check_in_opens_at' => '5.30'], 'check_in_opens_at'],
    'jam tidak ada' => [['late_after' => '25:00'], 'late_after'],
]);

test('admin mengubah jam presensi unit dan audit menyimpan nilai sebelum dan sesudah', function (): void {
    $unit = Unit::factory()->create(['code' => 'RA']);

    $this->actingAs(unitAdmin())
        ->put(route('admin.settings.units.update', $unit), unitPayload([
            'code' => 'RA',
            'name' => 'Raudhatul Athfal',
            'late_after' => '07:30',
        ]))
        ->assertSessionHasNoErrors();

    expect($unit->fresh()->attendance_settings['late_after'])->toBe('07:30');

    $audit = AuditLog::query()->where('action', 'updated')->where('subject_id', $unit->id)->sole();
    expect($audit->before['attendance_settings']['late_after'])->toBe('07:00')
        ->and($audit->after['attendance_settings']['late_after'])->toBe('07:30');
});

test('mengubah unit boleh mempertahankan kodenya sendiri', function (): void {
    $unit = Unit::factory()->create(['code' => 'TPQ']);

    $this->actingAs(unitAdmin())
        ->put(route('admin.settings.units.update', $unit), unitPayload(['code' => 'TPQ', 'name' => 'TPQ Baru']))
        ->assertSessionHasNoErrors();

    expect($unit->fresh()->name)->toBe('TPQ Baru');
});

test('admin menonaktifkan dan mengaktifkan kembali unit, bukan menghapusnya', function (): void {
    $unit = Unit::factory()->create();
    $admin = unitAdmin();

    $this->actingAs($admin)
        ->patch(route('admin.settings.units.status.update', $unit), ['is_active' => false])
        ->assertSessionHasNoErrors();
    expect($unit->fresh()->is_active)->toBeFalse();

    $this->actingAs($admin)
        ->patch(route('admin.settings.units.status.update', $unit), ['is_active' => true]);
    expect($unit->fresh()->is_active)->toBeTrue()
        ->and(AuditLog::query()->whereIn('action', ['activated', 'deactivated'])->count())->toBe(2);
});

test('UnitSeeder membuat MI, RA, dan TPQ dengan jam bawaan, idempoten, dan tidak menimpa jam yang sudah diubah', function (): void {
    $this->seed(UnitSeeder::class);

    expect(Unit::query()->orderBy('code')->pluck('code')->all())->toBe(['MI', 'RA', 'TPQ'])
        ->and(Unit::query()->where('code', 'MI')->sole()->attendance_settings['late_after'])
        ->toBe(config('sekolah.attendance.defaults.late_after'));

    $mi = Unit::query()->where('code', 'MI')->sole();
    $mi->update(['attendance_settings' => [...$mi->attendance_settings, 'late_after' => '07:15']]);

    $this->seed(UnitSeeder::class);

    expect(Unit::query()->count())->toBe(3)
        ->and($mi->fresh()->attendance_settings['late_after'])->toBe('07:15');
});

test('ProductionSeeder menyemai unit dasar tanpa membuat akun', function (): void {
    $this->seed(ProductionSeeder::class);

    expect(Unit::query()->count())->toBe(3)
        ->and(User::query()->count())->toBe(0);
});
