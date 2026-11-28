<?php

declare(strict_types=1);

use App\Enums\MutationType;
use App\Enums\Role as RoleEnum;
use App\Enums\StudentStatus;
use App\Models\Student;
use App\Models\StudentMutation;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleEnum::Admin->value);
    $this->unit = Unit::factory()->create(['code' => 'MI']);
});

test('biodata keluarga siswa tersimpan dan divalidasi', function (): void {
    $this->actingAs($this->admin)->post(route('admin.students.store'), [
        'unit_id' => $this->unit->id, 'nis' => '2026001', 'name' => 'Nur Aisyah', 'gender' => 'P', 'status' => 'active',
        'family_card_number' => '7401010101010001', 'religion' => 'Islam',
        'father_name' => 'Ahmad', 'father_occupation' => 'Petani', 'mother_name' => 'Fatimah', 'mother_occupation' => 'Guru',
    ])->assertSessionHasNoErrors();

    $student = Student::query()->where('nis', '2026001')->sole();
    expect($student->family_card_number)->toBe('7401010101010001')
        ->and($student->father_name)->toBe('Ahmad')
        ->and($student->mother_occupation)->toBe('Guru');

    $this->actingAs($this->admin)->post(route('admin.students.store'), [
        'unit_id' => $this->unit->id, 'nis' => '2026002', 'name' => 'X', 'gender' => 'L', 'status' => 'active',
        'family_card_number' => '123',
    ])->assertSessionHasErrors('family_card_number');
});

test('daftar siswa disaring jenis kelamin dan dicari lewat NISN atau No. KK', function (): void {
    Student::factory()->create(['unit_id' => $this->unit->id, 'name' => 'Putra', 'gender' => 'L', 'nisn' => '0011223344']);
    Student::factory()->create(['unit_id' => $this->unit->id, 'name' => 'Putri', 'gender' => 'P', 'family_card_number' => '7401999999990001']);

    $this->actingAs($this->admin)->get(route('admin.students.index', ['unit_id' => $this->unit->id, 'gender' => 'P']))
        ->assertInertia(fn (Assert $page) => $page->has('students.data', 1)->where('students.data.0.name', 'Putri')
            ->where('filters.gender', 'P')->where('canManage', true));

    $this->actingAs($this->admin)->get(route('admin.students.index', ['unit_id' => $this->unit->id, 'search' => '0011223344']))
        ->assertInertia(fn (Assert $page) => $page->has('students.data', 1)->where('students.data.0.name', 'Putra'));

    $this->actingAs($this->admin)->get(route('admin.students.index', ['unit_id' => $this->unit->id, 'search' => '7401999999990001']))
        ->assertInertia(fn (Assert $page) => $page->has('students.data', 1)->where('students.data.0.name', 'Putri'));
});

test('luluskan satu siswa mencatat mutasi kelulusan dan mengubah status', function (): void {
    $student = Student::factory()->create(['unit_id' => $this->unit->id]);

    $this->actingAs($this->admin)->post(route('admin.students.mutations.store-out'), [
        'student_id' => $student->id, 'mutated_on' => now('Asia/Makassar')->toDateString(), 'reason' => 'Lulus', 'graduate' => true,
    ])->assertSessionHasNoErrors();

    expect($student->fresh()->status)->toBe(StudentStatus::Graduated)
        ->and(StudentMutation::query()->sole()->type)->toBe(MutationType::Graduated);
    $this->assertDatabaseHas('audit_logs', ['action' => 'student_graduated', 'subject_id' => $student->id]);
});

test('drop out tercatat terpisah dari pindah sekolah dan arsip menampilkan sisa tagihan', function (): void {
    $student = Student::factory()->create(['unit_id' => $this->unit->id, 'name' => 'Siswa DO']);

    $this->actingAs($this->admin)->post(route('admin.students.mutations.store-out'), [
        'student_id' => $student->id, 'mutated_on' => now('Asia/Makassar')->toDateString(), 'reason' => 'Drop out', 'dropout' => true,
    ])->assertSessionHasNoErrors();

    expect($student->fresh()->status)->toBe(StudentStatus::Inactive)
        ->and(StudentMutation::query()->sole()->type)->toBe(MutationType::DropOut);

    $this->actingAs($this->admin)->get(route('admin.students.mutations.index', ['type' => 'dropout']))
        ->assertInertia(fn (Assert $page) => $page->has('mutations.data', 1)
            ->where('mutations.data.0.type.label', 'Drop out')
            ->where('mutations.data.0.outstanding', null));

    $this->actingAs($this->admin)->post(route('admin.students.mutations.store-out'), [
        'student_id' => Student::factory()->create(['unit_id' => $this->unit->id])->id,
        'mutated_on' => now('Asia/Makassar')->toDateString(), 'reason' => 'x', 'dropout' => true, 'graduate' => true,
    ])->assertSessionHasErrors('dropout');
});
