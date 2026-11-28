<?php

declare(strict_types=1);

use App\Enums\Role as RoleEnum;
use App\Models\AuditLog;
use App\Models\StaffAttendance;
use App\Models\StaffLeaveRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('admin dapat mencatat presensi harian pegawai secara manual dan guru ditolak', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);

    $teacher = User::factory()->create();
    $teacher->assignRole(RoleEnum::Teacher->value);

    $this->actingAs($teacher)
        ->get(route('admin.staff-attendance.index'))
        ->assertForbidden();

    $this->actingAs($admin)
        ->get(route('admin.staff-attendance.index', ['work_date' => '2026-11-28']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/attendance/staff'));

    $this->actingAs($admin)
        ->post(route('admin.staff-attendance.store'), [
            'user_id' => $teacher->id,
            'work_date' => '2026-11-28',
            'status' => 'present',
            'check_in_time' => '07:05',
            'check_out_time' => '14:15',
            'note' => 'Tepat waktu',
        ])
        ->assertRedirect();

    $record = StaffAttendance::query()->where('user_id', $teacher->id)->firstOrFail();
    expect($record->status)->toBe('present')
        ->and($record->source)->toBe('manual')
        ->and(AuditLog::query()->where('action', 'staff_attendance.created')->exists())->toBeTrue();
});

test('pegawai dapat mengajukan izin dan hanya admin atau kepala unit yang dapat memutuskan', function (): void {
    $teacher = User::factory()->create();
    $teacher->assignRole(RoleEnum::Teacher->value);

    $principal = User::factory()->create();
    $principal->assignRole(RoleEnum::UnitHead->value);

    $this->actingAs($teacher)
        ->post(route('admin.staff-leaves.store'), [
            'type' => 'leave',
            'starts_on' => '2026-11-29',
            'ends_on' => '2026-11-30',
            'reason' => 'Keperluan keluarga',
        ])
        ->assertRedirect();

    $leave = StaffLeaveRequest::query()->firstOrFail();
    expect($leave->status)->toBe('pending');

    $this->actingAs($teacher)
        ->patch(route('admin.staff-leaves.decide', $leave), [
            'decision' => 'approved',
        ])
        ->assertForbidden();

    $this->actingAs($principal)
        ->patch(route('admin.staff-leaves.decide', $leave), [
            'decision' => 'approved',
        ])
        ->assertRedirect();

    expect($leave->fresh()?->status)->toBe('approved')
        ->and(StaffAttendance::query()->where('user_id', $teacher->id)->count())->toBe(2)
        ->and(AuditLog::query()->where('action', 'staff_leave.decided')->exists())->toBeTrue();
});
