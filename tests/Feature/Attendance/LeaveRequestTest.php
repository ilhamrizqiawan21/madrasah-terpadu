<?php

declare(strict_types=1);

use App\Enums\AttendanceDecider;
use App\Enums\AttendanceStatus;
use App\Enums\GuardianRelation;
use App\Enums\LeaveRequestStatus;
use App\Enums\LeaveType;
use App\Enums\Role as RoleEnum;
use App\Models\AttendanceDay;
use App\Models\Guardian;
use App\Models\LeaveRequest;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\LeaveRequestDecidedNotification;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');
});

function createGuardianAndStudent(): array
{
    $unit = Unit::factory()->create();
    $student = Student::factory()->create(['unit_id' => $unit->id]);

    $guardianUser = User::factory()->create(['is_active' => true]);
    $guardianUser->assignRole(RoleEnum::Guardian->value);
    $guardian = Guardian::factory()->create(['user_id' => $guardianUser->id]);
    $student->guardians()->attach($guardian->id, [
        'relation' => GuardianRelation::Father->value,
        'is_primary' => true,
    ]);

    return [$guardianUser, $student];
}

test('wali tidak bisa mengajukan izin untuk anak wali lain dan mendapat 404', function (): void {
    [$guardian1] = createGuardianAndStudent();
    [, $otherStudent] = createGuardianAndStudent();

    $this->travelTo(CarbonImmutable::parse('2026-11-27 08:00:00', 'Asia/Makassar'));

    $this->actingAs($guardian1)
        ->post(route('guardian.students.leave.store', $otherStudent), [
            'type' => LeaveType::Sick->value,
            'starts_on' => '2026-11-27',
            'ends_on' => '2026-11-27',
            'reason' => 'Demam tinggi',
        ])
        ->assertNotFound();
});

test('pengajuan izin lebih dari 3 hari ke belakang atau tanggal tumpang tindih ditolak', function (): void {
    [$guardianUser, $student] = createGuardianAndStudent();

    $this->travelTo(CarbonImmutable::parse('2026-11-27 08:00:00', 'Asia/Makassar'));

    // 4 hari ke belakang ditolak
    $this->actingAs($guardianUser)
        ->post(route('guardian.students.leave.store', $student), [
            'type' => LeaveType::Excused->value,
            'starts_on' => '2026-11-23',
            'ends_on' => '2026-11-23',
            'reason' => 'Acara keluarga di luar kota',
        ])
        ->assertSessionHasErrors('starts_on');

    // Pengajuan valid dengan lampiran gambar
    $this->actingAs($guardianUser)
        ->post(route('guardian.students.leave.store', $student), [
            'type' => LeaveType::Sick->value,
            'starts_on' => '2026-11-26',
            'ends_on' => '2026-11-27',
            'reason' => 'Demam dan batuk',
            'attachment' => UploadedFile::fake()->image('surat-dokter.jpg', 400, 400),
        ])
        ->assertSessionHasNoErrors();

    expect(LeaveRequest::query()->where('student_id', $student->id)->count())->toBe(1);

    // Tanggal tumpang tindih ditolak
    $this->actingAs($guardianUser)
        ->post(route('guardian.students.leave.store', $student), [
            'type' => LeaveType::Excused->value,
            'starts_on' => '2026-11-27',
            'ends_on' => '2026-11-27',
            'reason' => 'Keperluan mendadak',
        ])
        ->assertSessionHasErrors('starts_on');
});

test('persetujuan izin mengubah attendance_days termasuk menimpa alpa dan mengirim notifikasi keputusan ke wali', function (): void {
    Notification::fake();

    [$guardianUser, $student] = createGuardianAndStudent();
    $this->travelTo(CarbonImmutable::parse('2026-11-27 08:00:00', 'Asia/Makassar'));

    // Misal 2026-11-26 sempat ditandai alpa
    AttendanceDay::query()->create([
        'student_id' => $student->id,
        'school_date' => '2026-11-26',
        'status' => AttendanceStatus::Absent,
        'decided_by' => AttendanceDecider::System,
    ]);

    $this->actingAs($guardianUser)
        ->post(route('guardian.students.leave.store', $student), [
            'type' => LeaveType::Sick->value,
            'starts_on' => '2026-11-26',
            'ends_on' => '2026-11-27',
            'reason' => 'Sakit panas sesuai surat dokter',
        ])
        ->assertSessionHasNoErrors();

    $leave = LeaveRequest::query()->where('student_id', $student->id)->firstOrFail();

    $teacher = User::factory()->create(['is_active' => true]);
    $teacher->assignRole(RoleEnum::Teacher->value);

    $this->actingAs($teacher)
        ->post(route('admin.attendance.leaves.decide', $leave), [
            'approved' => true,
            'decision_note' => 'Semoga lekas sembuh.',
        ])
        ->assertSessionHasNoErrors();

    $leave->refresh();
    expect($leave->status)->toBe(LeaveRequestStatus::Approved);

    $day26 = AttendanceDay::query()->where('student_id', $student->id)->whereDate('school_date', '2026-11-26')->first();
    $day27 = AttendanceDay::query()->where('student_id', $student->id)->whereDate('school_date', '2026-11-27')->first();

    expect($day26?->status)->toBe(AttendanceStatus::Sick)
        ->and($day26?->decided_by)->toBe(AttendanceDecider::Leave)
        ->and($day27?->status)->toBe(AttendanceStatus::Sick);

    Notification::assertSentTo($guardianUser, LeaveRequestDecidedNotification::class);
});
