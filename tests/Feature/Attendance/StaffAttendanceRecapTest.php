<?php

declare(strict_types=1);

use App\Enums\AttendanceDecider;
use App\Enums\AttendanceStatus;
use App\Enums\Role as RoleEnum;
use App\Models\AcademicYear;
use App\Models\AttendanceDay;
use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('tamu dan peran tanpa izin presensi ditolak', function (): void {
    $this->get(route('admin.attendance.index'))->assertRedirect(route('login'));

    $treasurer = User::factory()->create(['is_active' => true]);
    $treasurer->assignRole(RoleEnum::Treasurer->value);

    $this->actingAs($treasurer)->get(route('admin.attendance.index'))->assertForbidden();
    $this->actingAs($treasurer)->post(route('admin.attendance.correct'), [])->assertForbidden();
});

test('wali kelas dapat melihat rekap harian/bulanan dan mengoreksi status dengan alasan wajib serta tercatat di audit', function (): void {
    $unit = Unit::factory()->create(['code' => 'MI', 'is_active' => true]);
    $year = AcademicYear::factory()->create(['is_active' => true]);

    $homeroomTeacher = User::factory()->create(['is_active' => true]);
    $homeroomTeacher->assignRole(RoleEnum::Teacher->value);

    $otherTeacher = User::factory()->create(['is_active' => true]);
    $otherTeacher->assignRole(RoleEnum::Teacher->value);

    $classroom = Classroom::factory()->create([
        'unit_id' => $unit->id,
        'academic_year_id' => $year->id,
        'homeroom_teacher_id' => $homeroomTeacher->id,
        'is_active' => true,
    ]);

    $student = Student::factory()->create(['unit_id' => $unit->id]);
    Enrollment::factory()->create([
        'student_id' => $student->id,
        'classroom_id' => $classroom->id,
        'academic_year_id' => $year->id,
    ]);

    $this->actingAs($homeroomTeacher)
        ->get(route('admin.attendance.index', [
            'classroom_id' => $classroom->id,
            'date' => '2026-11-27',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/attendance/index')
            ->where('canCorrect', true)
            ->where('daily.counts.not_yet', 1));

    // Alasan kosong ditolak
    $this->actingAs($homeroomTeacher)
        ->post(route('admin.attendance.correct'), [
            'student_id' => $student->id,
            'school_date' => '2026-11-27',
            'status' => AttendanceStatus::Present->value,
            'reason' => '',
        ])
        ->assertSessionHasErrors('reason');

    // Guru bukan wali kelas rombel tersebut ditolak 403
    $this->actingAs($otherTeacher)
        ->post(route('admin.attendance.correct'), [
            'student_id' => $student->id,
            'school_date' => '2026-11-27',
            'status' => AttendanceStatus::Present->value,
            'reason' => 'Koreksi tidak sah',
        ])
        ->assertForbidden();

    // Wali kelas mengoreksi dengan alasan valid → tersimpan + audit
    $this->actingAs($homeroomTeacher)
        ->post(route('admin.attendance.correct'), [
            'student_id' => $student->id,
            'school_date' => '2026-11-27',
            'status' => AttendanceStatus::Present->value,
            'reason' => 'Siswa lupa membawa kartu QR saat masuk.',
        ])
        ->assertSessionHasNoErrors();

    $day = AttendanceDay::query()->where('student_id', $student->id)->whereDate('school_date', '2026-11-27')->first();
    expect($day?->status)->toBe(AttendanceStatus::Present)
        ->and($day?->decided_by)->toBe(AttendanceDecider::Manual);

    $audit = AuditLog::query()->where('action', 'attendance_day_corrected')->where('subject_id', $day?->id)->first();
    expect($audit)->not->toBeNull()
        ->and($audit?->user_id)->toBe($homeroomTeacher->id)
        ->and($audit?->after['reason'] ?? null)->toBe('Siswa lupa membawa kartu QR saat masuk.');

    // Rekap bulanan menampilkan 1 hadir
    $this->actingAs($homeroomTeacher)
        ->get(route('admin.attendance.index', [
            'mode' => 'monthly',
            'classroom_id' => $classroom->id,
            'month' => '2026-11',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('monthlyRows.0.present', 1));
});
