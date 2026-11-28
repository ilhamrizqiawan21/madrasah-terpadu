<?php

declare(strict_types=1);

use App\Enums\Role as RoleEnum;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Extracurricular;
use App\Models\ExtracurricularAttendance;
use App\Models\ExtracurricularMember;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleEnum::Admin->value);
    $this->unit = Unit::factory()->create(['code' => 'MI']);
    $this->year = AcademicYear::factory()->active()->create();
    $this->classroom = Classroom::factory()->for($this->unit)->for($this->year)->create(['grade' => '1', 'name' => '1-A']);
    $this->student = Student::factory()->create(['unit_id' => $this->unit->id, 'name' => 'Ahmad']);
    Enrollment::query()->create(['student_id' => $this->student->id, 'classroom_id' => $this->classroom->id, 'academic_year_id' => $this->year->id]);
});

test('ekskul wajib guru pembina bila internal dan nama pelatih bila eksternal', function (): void {
    $this->actingAs($this->admin)->post(route('admin.extracurriculars.store'), [
        'unit_id' => $this->unit->id, 'name' => 'Pramuka', 'coach_type' => 'internal',
    ])->assertSessionHasErrors('coach_user_id');

    $this->actingAs($this->admin)->post(route('admin.extracurriculars.store'), [
        'unit_id' => $this->unit->id, 'name' => 'Futsal', 'coach_type' => 'external', 'coach_name' => 'Coach Budi',
    ])->assertSessionHasNoErrors();

    expect(Extracurricular::query()->sole()->coachLabel())->toBe('Coach Budi (pelatih luar)');
});

test('daftar anggota, presensi, dan predikat rapor', function (): void {
    $ekskul = Extracurricular::query()->create(['unit_id' => $this->unit->id, 'name' => 'Futsal', 'coach_type' => 'external', 'coach_name' => 'Budi']);
    $foreign = Student::factory()->create(['unit_id' => Unit::factory()->create()->id]);

    $this->actingAs($this->admin)->post(route('admin.extracurriculars.enroll', $ekskul), ['student_ids' => [$this->student->id, $foreign->id]])
        ->assertSessionHasNoErrors();
    $member = ExtracurricularMember::query()->sole();
    expect($member->student_id)->toBe($this->student->id);

    $this->actingAs($this->admin)->post(route('admin.extracurriculars.attendance', $ekskul), [
        'met_on' => now()->toDateString(), 'statuses' => [$member->id => 'present'],
    ])->assertSessionHasNoErrors();
    expect(ExtracurricularAttendance::query()->count())->toBe(1);

    $this->actingAs($this->admin)->patch(route('admin.extracurriculars.grade', $member), ['grade' => 'E'])->assertSessionHasErrors('grade');
    $this->actingAs($this->admin)->patch(route('admin.extracurriculars.grade', $member), ['grade' => 'A'])->assertSessionHasNoErrors();

    $this->actingAs($this->admin)->get(route('admin.extracurriculars.members', ['ekskul' => $ekskul->id]))
        ->assertInertia(fn (Assert $page) => $page->component('admin/academic/extracurricular-members')
            ->where('members.0.grade', 'A')->where('members.0.present', 1)->where('members.0.classroom', '1-A')
            ->has('candidates', 0));
});
