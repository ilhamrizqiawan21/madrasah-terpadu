<?php

declare(strict_types=1);

use App\Enums\AttendanceDecider;
use App\Enums\AttendanceStatus;
use App\Enums\GuardianRelation;
use App\Enums\LeaveRequestStatus;
use App\Enums\LeaveType;
use App\Enums\Role as RoleEnum;
use App\Enums\StudentStatus;
use App\Jobs\MarkDailyAbsences;
use App\Models\AttendanceDay;
use App\Models\Guardian;
use App\Models\LeaveRequest;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\StudentMarkedAbsentNotification;
use App\Services\Attendance\SchoolCalendarService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function createStudentWithGuardian(Unit $unit, StudentStatus $status = StudentStatus::Active): array
{
    $student = Student::factory()->create([
        'unit_id' => $unit->id,
        'status' => $status,
    ]);

    $guardianUser = User::factory()->create(['is_active' => true]);
    $guardianUser->assignRole(RoleEnum::Guardian->value);
    $guardian = Guardian::factory()->create(['user_id' => $guardianUser->id]);
    $student->guardians()->attach($guardian->id, [
        'relation' => GuardianRelation::Father->value,
        'is_primary' => true,
    ]);

    return [$student, $guardianUser];
}

test('alpa otomatis menandai siswa aktif tanpa scan atau izin setelah jam batas unit dan idempoten saat dijalankan dua kali', function (): void {
    Notification::fake();

    $unit = Unit::factory()->create([
        'attendance_settings' => [
            'check_in_opens_at' => '05:30',
            'late_after' => '07:00',
            'absent_after' => '09:00',
            'check_out_opens_at' => '11:00',
        ],
    ]);

    [$absentStudent, $absentGuardian] = createStudentWithGuardian($unit);
    [$presentStudent, $presentGuardian] = createStudentWithGuardian($unit);
    [$leaveStudent, $leaveGuardian] = createStudentWithGuardian($unit);
    [$inactiveStudent, $inactiveGuardian] = createStudentWithGuardian($unit, StudentStatus::Inactive);

    $schoolDate = '2026-11-27'; // Jumat

    AttendanceDay::query()->create([
        'student_id' => $presentStudent->id,
        'school_date' => $schoolDate,
        'status' => AttendanceStatus::Present,
        'checked_in_at' => CarbonImmutable::parse("{$schoolDate} 06:45:00", 'Asia/Makassar'),
        'decided_by' => AttendanceDecider::Scan,
    ]);

    LeaveRequest::query()->create([
        'student_id' => $leaveStudent->id,
        'requested_by' => $leaveGuardian->id,
        'type' => LeaveType::Sick,
        'starts_on' => $schoolDate,
        'ends_on' => $schoolDate,
        'reason' => 'Demam',
        'status' => LeaveRequestStatus::Approved,
    ]);

    // Sebelum jam 09:00 WITA → tidak ada yang ditandai alpa.
    $beforeCount = app()->call([new MarkDailyAbsences("{$schoolDate} 08:55:00"), 'handle']);
    expect($beforeCount)->toBe(0);

    // Setelah jam 09:00 WITA → hanya absentStudent ditandai alpa.
    $firstRunCount = app()->call([new MarkDailyAbsences("{$schoolDate} 09:05:00"), 'handle']);
    expect($firstRunCount)->toBe(1);

    $day = AttendanceDay::query()->where('student_id', $absentStudent->id)->whereDate('school_date', $schoolDate)->first();
    expect($day?->status)->toBe(AttendanceStatus::Absent)
        ->and($day?->decided_by)->toBe(AttendanceDecider::System);

    Notification::assertSentToTimes($absentGuardian, StudentMarkedAbsentNotification::class, 1);
    Notification::assertNotSentTo($presentGuardian, StudentMarkedAbsentNotification::class);
    Notification::assertNotSentTo($leaveGuardian, StudentMarkedAbsentNotification::class);
    Notification::assertNotSentTo($inactiveGuardian, StudentMarkedAbsentNotification::class);

    // Dijalankan kedua kalinya → tetap 1 notifikasi & 0 tambahan baru (idempoten).
    $secondRunCount = app()->call([new MarkDailyAbsences("{$schoolDate} 09:20:00"), 'handle']);
    expect($secondRunCount)->toBe(0);
    Notification::assertSentToTimes($absentGuardian, StudentMarkedAbsentNotification::class, 1);
});

test('alpa otomatis tidak berjalan di hari libur', function (): void {
    Notification::fake();

    $admin = User::factory()->create(['is_active' => true]);
    $unit = Unit::factory()->create([
        'attendance_settings' => [
            'check_in_opens_at' => '05:30',
            'late_after' => '07:00',
            'absent_after' => '09:00',
            'check_out_opens_at' => '11:00',
        ],
    ]);

    [$student, $guardianUser] = createStudentWithGuardian($unit);

    app(SchoolCalendarService::class)->createHoliday([
        'unit_id' => $unit->id,
        'holiday_date' => '2026-11-27',
        'title' => 'Libur Sekolah',
        'is_day_off' => true,
    ], $admin);

    $count = app()->call([new MarkDailyAbsences('2026-11-27 10:00:00'), 'handle']);

    expect($count)->toBe(0)
        ->and(AttendanceDay::query()->where('student_id', $student->id)->exists())->toBeFalse();
    Notification::assertNotSentTo($guardianUser, StudentMarkedAbsentNotification::class);
});
