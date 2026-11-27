<?php

declare(strict_types=1);

use App\Enums\Role as RoleEnum;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\StudentCard;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function cardPrintAdmin(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole(RoleEnum::Admin->value);

    return $user;
}

test('tamu dan peran tanpa izin ditolak mencetak kartu PDF', function (): void {
    $student = Student::factory()->create();

    $this->post(route('admin.students.cards.print', $student))
        ->assertRedirect(route('login'));

    $teacher = User::factory()->create(['is_active' => true]);
    $teacher->assignRole(RoleEnum::Teacher->value);

    $this->actingAs($teacher)
        ->post(route('admin.students.cards.print', $student))
        ->assertForbidden();
});

test('cetak kartu satu siswa menghasilkan PDF valid dan menerbitkan kartu QR aktif', function (): void {
    $admin = cardPrintAdmin();
    $student = Student::factory()->create();

    $response = $this->actingAs($admin)
        ->post(route('admin.students.cards.print', $student));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect(str_starts_with((string) $response->getContent(), '%PDF-'))->toBeTrue()
        ->and(StudentCard::query()->where('student_id', $student->id)->active()->count())->toBe(1);
});

test('cetak kartu rombel berisi 30 siswa selesai di bawah 10 detik', function (): void {
    $admin = cardPrintAdmin();
    $unit = Unit::factory()->create();
    $year = AcademicYear::factory()->create(['is_active' => true]);
    $classroom = Classroom::factory()->create([
        'unit_id' => $unit->id,
        'academic_year_id' => $year->id,
    ]);

    $students = Student::factory()->count(30)->create(['unit_id' => $unit->id]);
    foreach ($students as $student) {
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'academic_year_id' => $year->id,
        ]);
    }

    $startedAt = microtime(true);

    $response = $this->actingAs($admin)
        ->post(route('admin.students.cards.print-classroom'), [
            'classroom_id' => $classroom->id,
        ]);

    $elapsedSeconds = microtime(true) - $startedAt;

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect(str_starts_with((string) $response->getContent(), '%PDF-'))->toBeTrue()
        ->and($elapsedSeconds)->toBeLessThan(10.0)
        ->and(StudentCard::query()->active()->count())->toBe(30);
});
