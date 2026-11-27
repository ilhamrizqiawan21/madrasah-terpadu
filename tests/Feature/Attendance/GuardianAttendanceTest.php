<?php

declare(strict_types=1);

use App\Enums\AttendanceDecider;
use App\Enums\AttendanceStatus;
use App\Enums\GuardianRelation;
use App\Enums\Role as RoleEnum;
use App\Models\AttendanceDay;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function createGuardianWithChildForAttendance(): array
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

test('wali hanya dapat melihat presensi anaknya sendiri dan anak wali lain mendapat 404', function (): void {
    [$guardian1, $student1] = createGuardianWithChildForAttendance();
    [, $student2] = createGuardianWithChildForAttendance();

    $this->travelTo(CarbonImmutable::parse('2026-11-27 10:00:00', 'Asia/Makassar'));

    AttendanceDay::query()->create([
        'student_id' => $student1->id,
        'school_date' => '2026-11-27',
        'status' => AttendanceStatus::Present,
        'checked_in_at' => CarbonImmutable::parse('2026-11-27 06:45:00', 'Asia/Makassar'),
        'decided_by' => AttendanceDecider::Scan,
    ]);

    // Anak sendiri → 200 dengan data hari ini & riwayat bulanan
    $this->actingAs($guardian1)
        ->get(route('guardian.students.attendance.index', $student1))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('guardian/attendance')
            ->where('student.id', $student1->id)
            ->where('today.status', AttendanceStatus::Present->value)
            ->where('today.checked_in_at', '06.45')
            ->where('summary.present', 1));

    // Anak wali lain → 404
    $this->actingAs($guardian1)
        ->get(route('guardian.students.attendance.index', $student2))
        ->assertNotFound();

    // Beranda wali menampilkan kartu Presensi hari ini
    $this->actingAs($guardian1)
        ->get(route('guardian.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('home.cards.0.key', 'attendance_today'));
});
