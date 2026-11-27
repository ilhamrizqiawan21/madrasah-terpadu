<?php

declare(strict_types=1);

use App\Enums\GuardianRelation;
use App\Enums\Role as RoleEnum;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Policies\StudentPolicy;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');
});

function createOwnershipGuardian(Student $student): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole(RoleEnum::Guardian->value);

    $guardian = Guardian::query()->create([
        'user_id' => $user->id,
        'name' => $user->name,
        'phone' => '0813'.str_pad((string) $user->id, 8, '0', STR_PAD_LEFT),
        'relation' => GuardianRelation::Mother,
    ]);

    $guardian->students()->attach($student->id, [
        'relation' => GuardianRelation::Mother->value,
        'is_primary' => true,
    ]);

    return $user;
}

test('semua rute /wali/anak/{student} mengembalikan 404 untuk anak milik wali lain', function (string $routeName): void {
    $unit = Unit::factory()->create(['code' => 'MI']);
    Storage::disk('local')->put('students/photos/own.jpg', 'jpg-bytes');
    Storage::disk('local')->put('students/photos/other.jpg', 'jpg-bytes');

    $ownChild = Student::factory()->for($unit)->create(['photo_path' => 'students/photos/own.jpg']);
    $otherChild = Student::factory()->for($unit)->create(['photo_path' => 'students/photos/other.jpg']);
    $guardianUser = createOwnershipGuardian($ownChild);

    $this->actingAs($guardianUser)
        ->get(route($routeName, ['student' => $ownChild->id]))
        ->assertOk();

    $this->actingAs($guardianUser)
        ->get(route($routeName, ['student' => $otherChild->id]))
        ->assertNotFound();

    $this->actingAs($guardianUser)
        ->get(route($routeName, ['student' => 999999]))
        ->assertNotFound();
})->with([
    'foto anak' => 'guardian.students.photo',
]);

test('StudentPolicy hanya mengizinkan staf berizin lihat siswa dan wali terkait', function (): void {
    $unit = Unit::factory()->create(['code' => 'MI']);
    $child = Student::factory()->for($unit)->create();
    $otherChild = Student::factory()->for($unit)->create();

    $guardianUser = createOwnershipGuardian($child);
    $otherGuardian = createOwnershipGuardian($otherChild);

    $teacher = User::factory()->create();
    $teacher->assignRole(RoleEnum::Teacher->value);

    $policy = new StudentPolicy;

    expect($policy->view($guardianUser, $child))->toBeTrue()
        ->and($policy->view($guardianUser, $otherChild))->toBeFalse()
        ->and($policy->view($otherGuardian, $child))->toBeFalse()
        ->and($policy->view($teacher, $child))->toBeTrue();
});
