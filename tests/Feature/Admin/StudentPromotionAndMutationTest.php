<?php

declare(strict_types=1);

use App\Enums\Gender;
use App\Enums\MutationType;
use App\Enums\Role as RoleEnum;
use App\Enums\Semester;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\StudentMutation;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function promotionAdmin(RoleEnum $role = RoleEnum::Admin): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

/**
 * @return array{unit: Unit, year1: AcademicYear, year2: AcademicYear, room1a: Classroom, room2a: Classroom}
 */
function promotionSetup(): array
{
    $unit = Unit::factory()->create(['code' => 'MI']);
    $year1 = AcademicYear::factory()->create([
        'name' => '2025/2026',
        'semester' => Semester::Ganjil,
        'starts_on' => '2025-07-01',
        'ends_on' => '2025-12-20',
        'is_active' => false,
    ]);
    $year2 = AcademicYear::factory()->active()->create([
        'name' => '2026/2027',
        'semester' => Semester::Ganjil,
        'starts_on' => '2026-07-01',
        'ends_on' => '2026-12-20',
    ]);
    $room1a = Classroom::factory()->for($unit)->for($year1)->create(['grade' => '1', 'name' => '1-A']);
    $room2a = Classroom::factory()->for($unit)->for($year2)->create(['grade' => '2', 'name' => '2-A']);

    return compact('unit', 'year1', 'year2', 'room1a', 'room2a');
}

test('tamu diarahkan ke login pada rute kenaikan kelas dan mutasi', function (): void {
    $this->get(route('admin.students.promotions.index'))->assertRedirect(route('login'));
    $this->post(route('admin.students.promotions.store'), [])->assertRedirect(route('login'));
    $this->get(route('admin.students.mutations.index'))->assertRedirect(route('login'));
    $this->post(route('admin.students.mutations.store-in'), [])->assertRedirect(route('login'));
    $this->post(route('admin.students.mutations.store-out'), [])->assertRedirect(route('login'));
});

test('peran selain admin tanpa izin kelola siswa ditolak 403', function (RoleEnum $role): void {
    $user = promotionAdmin($role);

    $this->actingAs($user)->get(route('admin.students.promotions.index'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.students.promotions.store'), [])->assertForbidden();
    $this->actingAs($user)->get(route('admin.students.mutations.index'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.students.mutations.store-in'), [])->assertForbidden();
    $this->actingAs($user)->post(route('admin.students.mutations.store-out'), [])->assertForbidden();
})->with([RoleEnum::Treasurer, RoleEnum::Teacher, RoleEnum::UnitHead, RoleEnum::Counselor]);

test('kenaikan kelas massal membuat enrollment baru, menjaga riwayat lama, mengecualikan siswa tinggal kelas, dan idempoten', function (): void {
    $admin = promotionAdmin();
    ['unit' => $unit, 'year1' => $year1, 'year2' => $year2, 'room1a' => $room1a, 'room2a' => $room2a] = promotionSetup();

    $s1 = Student::factory()->for($unit)->create(['nis' => '1001', 'name' => 'Siswa Naik 1']);
    $s2 = Student::factory()->for($unit)->create(['nis' => '1002', 'name' => 'Siswa Naik 2']);
    $sStay = Student::factory()->for($unit)->create(['nis' => '1003', 'name' => 'Siswa Tinggal']);

    foreach ([$s1, $s2, $sStay] as $student) {
        Enrollment::query()->create([
            'student_id' => $student->id,
            'classroom_id' => $room1a->id,
            'academic_year_id' => $year1->id,
        ]);
    }

    // Pratinjau menampilkan 3 siswa aktif di rombel asal.
    $this->actingAs($admin)
        ->get(route('admin.students.promotions.index', [
            'unit_id' => $unit->id,
            'source_year_id' => $year1->id,
            'source_classroom_id' => $room1a->id,
            'target_year_id' => $year2->id,
            'target_classroom_id' => $room2a->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/students/promotions')
            ->has('candidates', 3)
        );

    // Naikkan s1 dan s2, kecualikan sStay.
    $this->actingAs($admin)
        ->post(route('admin.students.promotions.store'), [
            'unit_id' => $unit->id,
            'source_year_id' => $year1->id,
            'source_classroom_id' => $room1a->id,
            'target_year_id' => $year2->id,
            'mode' => 'promote',
            'target_classroom_id' => $room2a->id,
            'student_ids' => [$s1->id, $s2->id],
        ])
        ->assertRedirect();

    expect(Enrollment::query()->where('academic_year_id', $year1->id)->count())->toBe(3)
        ->and(Enrollment::query()->where('academic_year_id', $year2->id)->where('classroom_id', $room2a->id)->count())->toBe(2)
        ->and(Enrollment::query()->where('student_id', $sStay->id)->where('academic_year_id', $year2->id)->exists())->toBeFalse()
        ->and(AuditLog::query()->where('action', 'students_promoted')->exists())->toBeTrue();

    // Jalankan ulang dengan payload sama → idempoten (tidak menggandakan atau error).
    $this->actingAs($admin)
        ->post(route('admin.students.promotions.store'), [
            'unit_id' => $unit->id,
            'source_year_id' => $year1->id,
            'source_classroom_id' => $room1a->id,
            'target_year_id' => $year2->id,
            'mode' => 'promote',
            'target_classroom_id' => $room2a->id,
            'student_ids' => [$s1->id, $s2->id],
        ])
        ->assertRedirect();

    expect(Enrollment::query()->where('academic_year_id', $year2->id)->count())->toBe(2);
});

test('kelulusan massal mengubah status siswa menjadi graduated dan mencatat mutasi lulus', function (): void {
    $admin = promotionAdmin();
    ['unit' => $unit, 'year1' => $year1, 'year2' => $year2, 'room1a' => $room1a] = promotionSetup();

    $student = Student::factory()->for($unit)->create(['nis' => '2001']);
    Enrollment::query()->create([
        'student_id' => $student->id,
        'classroom_id' => $room1a->id,
        'academic_year_id' => $year1->id,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.students.promotions.store'), [
            'unit_id' => $unit->id,
            'source_year_id' => $year1->id,
            'source_classroom_id' => $room1a->id,
            'target_year_id' => $year2->id,
            'mode' => 'graduate',
            'student_ids' => [$student->id],
        ])
        ->assertRedirect();

    expect($student->fresh()?->status)->toBe(StudentStatus::Graduated)
        ->and(StudentMutation::query()->where('student_id', $student->id)->where('type', MutationType::Graduated)->exists())->toBeTrue();
});

test('mutasi keluar mengubah status siswa menjadi pindah dan menonaktifkan wali yang tidak punya anak aktif lagi', function (): void {
    $admin = promotionAdmin();
    ['unit' => $unit] = promotionSetup();

    $studentOnly = Student::factory()->for($unit)->create(['nis' => '3001', 'status' => StudentStatus::Active]);
    $guardianOnlyUser = User::factory()->create(['username' => '081200000001', 'phone' => '081200000001', 'is_active' => true]);
    $guardianOnlyUser->assignRole(RoleEnum::Guardian->value);
    $guardianOnly = Guardian::query()->create([
        'user_id' => $guardianOnlyUser->id,
        'name' => 'Wali Satu Anak',
        'phone' => '081200000001',
        'relation' => 'ayah',
    ]);
    $guardianOnly->students()->attach($studentOnly->id, ['relation' => 'ayah', 'is_primary' => true]);

    // Wali kedua punya anak aktif lain + juga staf → tetap aktif.
    $siblingActive = Student::factory()->for($unit)->create(['nis' => '3002', 'status' => StudentStatus::Active]);
    $staffUser = User::factory()->create(['username' => 'guru.wali', 'phone' => '081200000002', 'is_active' => true]);
    $staffUser->assignRole([RoleEnum::Teacher->value, RoleEnum::Guardian->value]);
    $guardianShared = Guardian::query()->create([
        'user_id' => $staffUser->id,
        'name' => 'Guru Orang Tua',
        'phone' => '081200000002',
        'relation' => 'ibu',
    ]);
    $guardianShared->students()->attach($studentOnly->id, ['relation' => 'ibu', 'is_primary' => false]);
    $guardianShared->students()->attach($siblingActive->id, ['relation' => 'ibu', 'is_primary' => true]);

    $this->actingAs($admin)
        ->post(route('admin.students.mutations.store-out'), [
            'student_id' => $studentOnly->id,
            'mutated_on' => '2026-11-27',
            'school_name' => 'MI Negeri 1 Kolaka',
            'reason' => 'Orang tua pindah tugas dinas',
        ])
        ->assertRedirect();

    expect($studentOnly->fresh()?->status)->toBe(StudentStatus::Moved)
        ->and($guardianOnlyUser->fresh()?->is_active)->toBeFalse()
        ->and($staffUser->fresh()?->is_active)->toBeTrue()
        ->and(StudentMutation::query()->where('student_id', $studentOnly->id)->where('type', MutationType::Out)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'student_mutated_out')->exists())->toBeTrue();
});

test('mutasi masuk membuat siswa baru dengan enrollment tahun aktif dan catatan mutasi masuk', function (): void {
    $admin = promotionAdmin();
    ['unit' => $unit, 'year2' => $year2, 'room2a' => $room2a] = promotionSetup();

    $this->actingAs($admin)
        ->post(route('admin.students.mutations.store-in'), [
            'unit_id' => $unit->id,
            'nis' => '4001',
            'name' => 'Siswa Pindahan Masuk',
            'gender' => Gender::Male->value,
            'classroom_id' => $room2a->id,
            'mutated_on' => '2026-11-27',
            'school_name' => 'SDN 2 Kolaka',
            'reason' => 'Pindahan masuk semester ganjil',
        ])
        ->assertRedirect();

    $student = Student::query()->where('nis', '4001')->firstOrFail();

    expect($student->status)->toBe(StudentStatus::Active)
        ->and(Enrollment::query()->where('student_id', $student->id)->where('academic_year_id', $year2->id)->where('classroom_id', $room2a->id)->exists())->toBeTrue()
        ->and(StudentMutation::query()->where('student_id', $student->id)->where('type', MutationType::In)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'student_mutated_in')->exists())->toBeTrue();

    $this->actingAs($admin)
        ->get(route('admin.students.mutations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/students/mutations')
            ->has('mutations.data', 1)
        );
});
