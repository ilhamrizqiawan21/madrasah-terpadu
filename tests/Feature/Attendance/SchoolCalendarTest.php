<?php

declare(strict_types=1);

use App\Enums\GateScanResult;
use App\Enums\Role as RoleEnum;
use App\Models\SchoolHoliday;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Services\Attendance\GateScanService;
use App\Services\Attendance\SchoolCalendarService;
use App\Services\Attendance\StudentCardService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function calendarAdmin(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole(RoleEnum::Admin->value);

    return $user;
}

test('tamu dan peran tanpa izin ditolak mengelola kalender sekolah', function (): void {
    $this->get(route('admin.attendance.calendar.index'))->assertRedirect(route('login'));

    $unitHead = User::factory()->create(['is_active' => true]);
    $unitHead->assignRole(RoleEnum::UnitHead->value);

    $this->actingAs($unitHead)->get(route('admin.attendance.calendar.index'))->assertOk();
    $this->actingAs($unitHead)->post(route('admin.attendance.calendar.store'), [
        'holiday_date' => '2026-12-01',
        'title' => 'Libur Uji',
        'is_day_off' => true,
    ])->assertForbidden();
});

test('admin dapat menambah dan menghapus hari libur serta mengatur akhir pekan per unit', function (): void {
    $admin = calendarAdmin();
    $unit = Unit::factory()->create(['code' => 'TPQ', 'name' => 'TPQ Darul Istiqamah']);

    $this->actingAs($admin)
        ->get(route('admin.attendance.calendar.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/attendance/calendar'));

    $this->actingAs($admin)
        ->post(route('admin.attendance.calendar.store'), [
            'unit_id' => null,
            'holiday_date' => '2026-12-25',
            'title' => 'Libur Nasional',
            'is_day_off' => true,
        ])
        ->assertSessionHasNoErrors();

    $holiday = SchoolHoliday::query()->whereDate('holiday_date', '2026-12-25')->firstOrFail();

    // Ubah akhir pekan unit TPQ hanya Minggu (7), sehingga Sabtu (6) menjadi hari masuk.
    $this->actingAs($admin)
        ->put(route('admin.attendance.calendar.weekend.update'), [
            'unit_id' => $unit->id,
            'weekend_days' => [7],
        ])
        ->assertSessionHasNoErrors();

    $calendar = app(SchoolCalendarService::class);
    $unit->refresh();

    expect($calendar->isHoliday($unit, '2026-12-25'))->toBeTrue() // libur semua unit
        ->and($calendar->isHoliday($unit, '2026-11-28'))->toBeFalse() // Sabtu: bukan libur di TPQ
        ->and($calendar->isHoliday($unit, '2026-11-29'))->toBeTrue(); // Minggu: akhir pekan

    $this->actingAs($admin)
        ->delete(route('admin.attendance.calendar.destroy', $holiday))
        ->assertSessionHasNoErrors();

    expect($calendar->isHoliday($unit, '2026-12-25'))->toBeFalse();
});

test('scan gerbang di hari libur ditolak dengan status holiday', function (): void {
    $admin = calendarAdmin();
    $unit = Unit::factory()->create([
        'attendance_settings' => [
            'check_in_opens_at' => '05:30',
            'late_after' => '07:00',
            'absent_after' => '09:00',
            'check_out_opens_at' => '11:00',
        ],
    ]);

    $student = Student::factory()->create(['unit_id' => $unit->id]);
    $issued = app(StudentCardService::class)->issueQr($student, $admin);

    app(SchoolCalendarService::class)->createHoliday([
        'unit_id' => $unit->id,
        'holiday_date' => '2026-11-27',
        'title' => 'Libur Khusus Unit',
        'is_day_off' => true,
    ], $admin);

    $outcome = app(GateScanService::class)->scan(
        $issued['token'],
        $admin,
        'Gerbang',
        CarbonImmutable::parse('2026-11-27 06:45:00', 'Asia/Makassar'),
    );

    expect($outcome->accepted)->toBeFalse()
        ->and($outcome->result)->toBe(GateScanResult::Holiday);
});
