<?php

declare(strict_types=1);

use App\Enums\AttendanceStatus;
use App\Enums\GateScanResult;
use App\Enums\GuardianRelation;
use App\Enums\Role as RoleEnum;
use App\Models\AttendanceDay;
use App\Models\GateScan;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\StudentCheckedInNotification;
use App\Notifications\StudentCheckedOutNotification;
use App\Services\Attendance\GateScanService;
use App\Services\Attendance\StudentCardService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function createGateOfficer(): User
{
    $officer = User::factory()->create(['is_active' => true]);
    $officer->assignRole(RoleEnum::GateOfficer->value);

    return $officer;
}

function createStudentWithCardAndGuardian(): array
{
    $unit = Unit::factory()->create([
        'attendance_settings' => [
            'check_in_opens_at' => '05:30',
            'late_after' => '07:00',
            'absent_after' => '09:00',
            'check_out_opens_at' => '11:00',
        ],
    ]);

    $student = Student::factory()->create(['unit_id' => $unit->id]);

    $guardianUser = User::factory()->create(['is_active' => true]);
    $guardianUser->assignRole(RoleEnum::Guardian->value);
    $guardian = Guardian::factory()->create(['user_id' => $guardianUser->id]);
    $student->guardians()->attach($guardian->id, [
        'relation' => GuardianRelation::Father->value,
        'is_primary' => true,
    ]);

    $admin = User::factory()->create(['is_active' => true]);
    $issued = app(StudentCardService::class)->issueQr($student, $admin);

    return [$student, $issued['token'], $guardianUser];
}

test('scan masuk tepat waktu, scan duplikat, dan scan pulang mencatat gate_scans, attendance_days, serta mengirim notifikasi ke wali', function (): void {
    Notification::fake();

    $officer = createGateOfficer();
    [$student, $token, $guardianUser] = createStudentWithCardAndGuardian();
    $service = app(GateScanService::class);

    // 1. Tap masuk tepat waktu pukul 06.45 WITA
    $checkInTime = CarbonImmutable::parse('2026-11-27 06:45:00', 'Asia/Makassar');
    $inOutcome = $service->scan($token, $officer, 'Gerbang Utama', $checkInTime);

    expect($inOutcome->accepted)->toBeTrue()
        ->and($inOutcome->result)->toBe(GateScanResult::OnTime)
        ->and($inOutcome->attendanceDay?->status)->toBe(AttendanceStatus::Present);

    Notification::assertSentTo($guardianUser, StudentCheckedInNotification::class);

    // 2. Scan ulang dalam 45 detik → duplikat, tidak mengubah attendance_days
    $dupOutcome = $service->scan($token, $officer, 'Gerbang Utama', $checkInTime->addSeconds(45));
    expect($dupOutcome->accepted)->toBeFalse()
        ->and($dupOutcome->result)->toBe(GateScanResult::Duplicate);

    // 3. Tap pulang pukul 11.15 WITA
    $checkOutTime = CarbonImmutable::parse('2026-11-27 11:15:00', 'Asia/Makassar');
    $outOutcome = $service->scan($token, $officer, 'Gerbang Utama', $checkOutTime);

    expect($outOutcome->accepted)->toBeTrue()
        ->and($outOutcome->result)->toBe(GateScanResult::CheckOut);

    Notification::assertSentTo($guardianUser, StudentCheckedOutNotification::class);

    expect(GateScan::query()->where('student_id', $student->id)->count())->toBe(3)
        ->and(AttendanceDay::query()->where('student_id', $student->id)->count())->toBe(1);
});

test('kartu dicabut atau token tidak dikenal ditolak tanpa menulis gate_scans', function (): void {
    $officer = createGateOfficer();
    [$student, $token] = createStudentWithCardAndGuardian();

    app(StudentCardService::class)->revokeActive($student, $officer);

    $outcome = app(GateScanService::class)->scan($token, $officer);
    expect($outcome->accepted)->toBeFalse()
        ->and($outcome->code)->toBe('invalid_card')
        ->and(GateScan::query()->count())->toBe(0);
});

test('scan ganda berturut-turut untuk siswa dan tanggal yang sama hanya menghasilkan satu attendance_days', function (): void {
    $officer = createGateOfficer();
    [$student, $token] = createStudentWithCardAndGuardian();
    $service = app(GateScanService::class);
    $at = CarbonImmutable::parse('2026-11-27 07:10:00', 'Asia/Makassar');

    $first = $service->scan($token, $officer, 'Gerbang A', $at);
    $second = $service->scan($token, $officer, 'Gerbang B', $at->addSeconds(5));

    expect($first->result)->toBe(GateScanResult::Late)
        ->and($second->result)->toBe(GateScanResult::Duplicate)
        ->and(AttendanceDay::query()->where('student_id', $student->id)->whereDate('school_date', '2026-11-27')->count())->toBe(1);
})->group('mysql');
