<?php

declare(strict_types=1);

use App\Enums\Role as RoleEnum;
use App\Enums\SettingKey;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Graduation;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Services\Settings\SettingsRepository;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleEnum::Admin->value);
    $this->unit = Unit::factory()->create(['code' => 'MI']);
    $this->year = AcademicYear::factory()->active()->create();
    $this->classroom = Classroom::factory()->for($this->unit)->for($this->year)->create(['grade' => '6', 'name' => '6-A']);
    $this->student = Student::factory()->create(['unit_id' => $this->unit->id, 'name' => 'Ahmad']);
    Enrollment::query()->create(['student_id' => $this->student->id, 'classroom_id' => $this->classroom->id, 'academic_year_id' => $this->year->id]);
});

test('status kelulusan ditetapkan, nomor SKL tetap, dan SKL hanya untuk yang lulus', function (): void {
    $this->actingAs($this->admin)->get(route('admin.graduations.letter', $this->student))->assertNotFound();

    $this->actingAs($this->admin)->put(route('admin.graduations.decide', $this->student), ['status' => 'passed'])->assertSessionHasNoErrors();
    $number = Graduation::query()->sole()->letter_number;
    expect($number)->toEndWith('/SKL/MI/'.$this->year->starts_on->format('Y'))->toStartWith('001');

    $this->actingAs($this->admin)->put(route('admin.graduations.decide', $this->student), ['status' => 'failed']);
    $this->actingAs($this->admin)->put(route('admin.graduations.decide', $this->student), ['status' => 'passed']);
    expect(Graduation::query()->sole()->letter_number)->toBe($number);

    $this->actingAs($this->admin)->get(route('admin.graduations.index', ['search' => 'ahm']))
        ->assertInertia(fn (Assert $page) => $page->component('admin/academic/graduations')
            ->where('rows.data.0.status', 'passed')->where('rows.data.0.classroom', '6-A'));

    app(SettingsRepository::class)->setMany([SettingKey::City->value => 'Kolaka'], $this->admin);

    $this->actingAs($this->admin)->get(route('admin.graduations.letter', $this->student))
        ->assertInertia(fn (Assert $page) => $page->component('admin/academic/graduation-letter')->where('letter.number', $number)->where('school.city', 'Kolaka'));
});

test('siswa yang tidak terdaftar di tahun aktif tidak bisa ditetapkan', function (): void {
    $other = Student::factory()->create(['unit_id' => $this->unit->id]);
    $this->actingAs($this->admin)->put(route('admin.graduations.decide', $other), ['status' => 'passed'])->assertNotFound();
});
