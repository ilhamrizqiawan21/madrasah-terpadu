<?php

declare(strict_types=1);

use App\Enums\Role as RoleEnum;
use App\Models\AcademicYear;
use App\Models\ClassGroup;
use App\Models\Classroom;
use App\Models\GradeLevel;
use App\Models\Major;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleEnum::Admin->value);
    $this->unit = Unit::factory()->create(['code' => 'MI']);
    $this->other = Unit::factory()->create(['code' => 'RA']);
    $this->year = AcademicYear::factory()->active()->create();
});

test('kelas menyimpan jurusan dan kuota, serta halaman mengirim pilihan master unit', function (): void {
    GradeLevel::query()->create(['unit_id' => $this->unit->id, 'name' => '1', 'sort_order' => 1]);
    ClassGroup::query()->create(['unit_id' => $this->unit->id, 'name' => 'A']);
    $major = Major::query()->create(['unit_id' => $this->unit->id, 'name' => 'Tahfidz']);
    $foreignMajor = Major::query()->create(['unit_id' => $this->other->id, 'name' => 'Lain']);

    $this->actingAs($this->admin)->post(route('admin.classrooms.store'), [
        'unit_id' => $this->unit->id, 'academic_year_id' => $this->year->id,
        'grade' => '1', 'name' => '1-A', 'major_id' => $major->id, 'capacity' => 28,
    ])->assertSessionHasNoErrors();

    $classroom = Classroom::query()->sole();
    expect($classroom->major_id)->toBe($major->id)->and($classroom->capacity)->toBe(28);

    $this->actingAs($this->admin)->put(route('admin.classrooms.update', $classroom), [
        'grade' => '1', 'name' => '1-A', 'major_id' => $foreignMajor->id,
    ])->assertSessionHasErrors('major_id');

    $this->actingAs($this->admin)->get(route('admin.classrooms.index', ['unit_id' => $this->unit->id, 'academic_year_id' => $this->year->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('gradeOptions', ['1'])
            ->where('groupOptions', ['A'])
            ->where('majorOptions.0.name', 'Tahfidz')
            ->where('classrooms.data.0.major_name', 'Tahfidz')
            ->where('classrooms.data.0.capacity', 28));
});
