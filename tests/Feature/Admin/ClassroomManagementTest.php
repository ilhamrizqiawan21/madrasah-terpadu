<?php

declare(strict_types=1);

use App\Enums\Role as RoleEnum;
use App\Enums\Semester;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\Unit;
use App\Models\User;
use App\Support\SchoolContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function classroomAdmin(): User
{
    $admin = User::factory()->withTwoFactor()->create();
    $admin->assignRole(RoleEnum::Admin->value);

    return $admin;
}

function homeroomTeacher(string $name = 'Guru Wali'): User
{
    $teacher = User::factory()->create(['name' => $name]);
    $teacher->assignRole(RoleEnum::Teacher->value);

    return $teacher;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function classroomPayload(Unit $unit, AcademicYear $year, array $overrides = []): array
{
    return [
        'unit_id' => $unit->id,
        'academic_year_id' => $year->id,
        'grade' => '1',
        'name' => '1-A',
        'homeroom_teacher_id' => null,
        ...$overrides,
    ];
}

test('tamu diarahkan ke halaman login saat membuka rombel', function (): void {
    $this->get(route('admin.classrooms.index'))->assertRedirect(route('login'));
});

test('peran selain admin ditolak mengelola rombel', function (RoleEnum $role): void {
    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole($role->value);
    $unit = Unit::factory()->create();
    $year = AcademicYear::factory()->create();
    $classroom = Classroom::factory()->for($unit)->for($year)->create();

    $this->actingAs($user)->get(route('admin.classrooms.index'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.classrooms.store'), classroomPayload($unit, $year, ['name' => '1-B']))->assertForbidden();
    $this->actingAs($user)->put(route('admin.classrooms.update', $classroom), classroomPayload($unit, $year))->assertForbidden();
    $this->actingAs($user)->patch(route('admin.classrooms.status.update', $classroom), ['is_active' => false])->assertForbidden();
    $this->actingAs($user)->post(route('admin.classrooms.copy'), [
        'unit_id' => $unit->id,
        'academic_year_id' => AcademicYear::factory()->create()->id,
        'source_academic_year_id' => $year->id,
    ])->assertForbidden();

    expect(Classroom::query()->count())->toBe(1)
        ->and($classroom->fresh()->is_active)->toBeTrue();
})->with([
    RoleEnum::Treasurer,
    RoleEnum::Teacher,
    RoleEnum::UnitHead,
]);

test('daftar rombel memakai unit dan tahun ajaran dari konteks topbar sebagai bawaan', function (): void {
    $mi = Unit::factory()->create(['code' => 'MI']);
    $tpq = Unit::factory()->create(['code' => 'TPQ']);
    $active = AcademicYear::factory()->active()->create(['name' => '2026/2027', 'semester' => Semester::Ganjil]);
    $previous = AcademicYear::factory()->create(['name' => '2025/2026', 'semester' => Semester::Genap]);
    $teacher = homeroomTeacher('Siti Aminah');

    Classroom::factory()->for($tpq)->for($active)->create(['grade' => '1', 'name' => 'Jilid 1']);
    Classroom::factory()->for($tpq)->for($previous)->create(['grade' => '2', 'name' => 'Jilid 2']);
    Classroom::factory()->for($mi)->for($active)->create(['grade' => '1', 'name' => '1-A']);

    $this->actingAs(classroomAdmin())
        ->withSession([SchoolContext::SESSION_UNIT => 'TPQ'])
        ->get(route('admin.classrooms.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/classrooms/index')
            ->where('filters.unit_id', $tpq->id)
            ->where('filters.academic_year_id', $active->id)
            ->has('classrooms.data', 1)
            ->where('classrooms.data.0.name', 'Jilid 1')
            ->has('unitOptions', 2)
            ->has('academicYearOptions', 2)
            ->where('teacherOptions.0.name', $teacher->name));
});

test('daftar rombel diurutkan per tingkat lalu nama dan dapat dicari', function (): void {
    $unit = Unit::factory()->create();
    $year = AcademicYear::factory()->active()->create();
    $teacher = homeroomTeacher();

    Classroom::factory()->for($unit)->for($year)->create(['grade' => '2', 'name' => '2-A']);
    Classroom::factory()->for($unit)->for($year)->create(['grade' => '1', 'name' => '1-B', 'homeroom_teacher_id' => $teacher->id]);
    Classroom::factory()->for($unit)->for($year)->create(['grade' => '1', 'name' => '1-A']);

    $admin = classroomAdmin();
    $query = ['unit_id' => $unit->id, 'academic_year_id' => $year->id];

    $this->actingAs($admin)
        ->get(route('admin.classrooms.index', $query))
        ->assertInertia(fn (Assert $page) => $page
            ->has('classrooms.data', 3)
            ->where('classrooms.data.0.name', '1-A')
            ->where('classrooms.data.1.name', '1-B')
            ->where('classrooms.data.1.homeroom_teacher.name', $teacher->name)
            ->where('classrooms.data.2.name', '2-A'));

    $this->actingAs($admin)
        ->get(route('admin.classrooms.index', [...$query, 'search' => '1-b']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('classrooms.data', 1)
            ->where('classrooms.data.0.name', '1-B'));
});

test('daftar rombel tetap terbuka saat belum ada unit atau tahun ajaran', function (): void {
    $this->actingAs(classroomAdmin())
        ->get(route('admin.classrooms.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.unit_id', null)
            ->where('filters.academic_year_id', null)
            ->has('classrooms.data', 0));
});

test('pilihan wali kelas hanya berisi guru aktif', function (): void {
    homeroomTeacher('Ahmad');
    $inactive = User::factory()->inactive()->create(['name' => 'Guru Nonaktif']);
    $inactive->assignRole(RoleEnum::Teacher->value);
    $treasurer = User::factory()->create(['name' => 'Bendahara']);
    $treasurer->assignRole(RoleEnum::Treasurer->value);

    $this->actingAs(classroomAdmin())
        ->get(route('admin.classrooms.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('teacherOptions', 1)
            ->where('teacherOptions.0.name', 'Ahmad'));
});

test('admin menambah rombel dengan wali kelas dan tercatat di audit', function (): void {
    $unit = Unit::factory()->create();
    $year = AcademicYear::factory()->active()->create();
    $teacher = homeroomTeacher();

    $this->actingAs(classroomAdmin())
        ->post(route('admin.classrooms.store'), classroomPayload($unit, $year, ['homeroom_teacher_id' => $teacher->id]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $classroom = Classroom::query()->sole();

    expect($classroom->unit_id)->toBe($unit->id)
        ->and($classroom->academic_year_id)->toBe($year->id)
        ->and($classroom->grade)->toBe('1')
        ->and($classroom->name)->toBe('1-A')
        ->and($classroom->homeroom_teacher_id)->toBe($teacher->id)
        ->and($classroom->is_active)->toBeTrue()
        ->and(AuditLog::query()->where('action', 'created')->where('subject_type', Classroom::class)->count())->toBe(1);
});

test('nama rombel unik per unit dan tahun ajaran', function (): void {
    $unit = Unit::factory()->create();
    $otherUnit = Unit::factory()->create();
    $year = AcademicYear::factory()->create();
    $otherYear = AcademicYear::factory()->create();
    Classroom::factory()->for($unit)->for($year)->create(['name' => '1-A']);
    $admin = classroomAdmin();

    $this->actingAs($admin)
        ->post(route('admin.classrooms.store'), classroomPayload($unit, $year))
        ->assertSessionHasErrors('name');

    $this->actingAs($admin)
        ->post(route('admin.classrooms.store'), classroomPayload($otherUnit, $year))
        ->assertSessionHasNoErrors();

    $this->actingAs($admin)
        ->post(route('admin.classrooms.store'), classroomPayload($unit, $otherYear))
        ->assertSessionHasNoErrors();

    expect(Classroom::query()->count())->toBe(3);
});

test('hanya guru aktif yang bisa menjadi wali kelas', function (Closure $makeUser): void {
    $unit = Unit::factory()->create();
    $year = AcademicYear::factory()->create();
    $user = $makeUser();

    $this->actingAs(classroomAdmin())
        ->post(route('admin.classrooms.store'), classroomPayload($unit, $year, ['homeroom_teacher_id' => $user->id]))
        ->assertSessionHasErrors('homeroom_teacher_id');

    expect(Classroom::query()->count())->toBe(0);
})->with([
    'bendahara' => [function (): User {
        $user = User::factory()->create();
        $user->assignRole(RoleEnum::Treasurer->value);

        return $user;
    }],
    'tanpa peran' => [fn (): User => User::factory()->create()],
    'guru nonaktif' => [function (): User {
        $user = User::factory()->inactive()->create();
        $user->assignRole(RoleEnum::Teacher->value);

        return $user;
    }],
]);

test('isian rombel divalidasi', function (array $overrides, string $field): void {
    $unit = Unit::factory()->create();
    $year = AcademicYear::factory()->create();

    $this->actingAs(classroomAdmin())
        ->post(route('admin.classrooms.store'), classroomPayload($unit, $year, $overrides))
        ->assertSessionHasErrors($field);

    expect(Classroom::query()->count())->toBe(0);
})->with([
    'nama kosong' => [['name' => ''], 'name'],
    'nama terlalu panjang' => [['name' => str_repeat('A', 31)], 'name'],
    'tingkat kosong' => [['grade' => ''], 'grade'],
    'tingkat terlalu panjang' => [['grade' => str_repeat('1', 11)], 'grade'],
    'unit tidak ada' => [['unit_id' => 999999], 'unit_id'],
    'tahun ajaran tidak ada' => [['academic_year_id' => 999999], 'academic_year_id'],
]);

test('admin mengubah rombel tanpa memindahkan unit dan tahun ajarannya', function (): void {
    $unit = Unit::factory()->create();
    $year = AcademicYear::factory()->create();
    $classroom = Classroom::factory()->for($unit)->for($year)->create(['grade' => '1', 'name' => '1-A']);
    $teacher = homeroomTeacher();

    $this->actingAs(classroomAdmin())
        ->put(route('admin.classrooms.update', $classroom), [
            'grade' => '2',
            'name' => '2-A',
            'homeroom_teacher_id' => $teacher->id,
            'unit_id' => Unit::factory()->create()->id,
        ])
        ->assertSessionHasNoErrors();

    $classroom->refresh();

    expect($classroom->name)->toBe('2-A')
        ->and($classroom->grade)->toBe('2')
        ->and($classroom->homeroom_teacher_id)->toBe($teacher->id)
        ->and($classroom->unit_id)->toBe($unit->id)
        ->and(AuditLog::query()->where('action', 'updated')->where('subject_id', $classroom->id)->count())->toBe(1);
});

test('mengubah rombel dengan nama yang sama tidak dianggap ganda', function (): void {
    $unit = Unit::factory()->create();
    $year = AcademicYear::factory()->create();
    $classroom = Classroom::factory()->for($unit)->for($year)->create(['name' => '1-A']);
    Classroom::factory()->for($unit)->for($year)->create(['name' => '1-B']);
    $admin = classroomAdmin();

    $this->actingAs($admin)
        ->put(route('admin.classrooms.update', $classroom), ['grade' => '1', 'name' => '1-A'])
        ->assertSessionHasNoErrors();

    $this->actingAs($admin)
        ->put(route('admin.classrooms.update', $classroom), ['grade' => '1', 'name' => '1-B'])
        ->assertSessionHasErrors('name');
});

test('admin menonaktifkan dan mengaktifkan kembali rombel dengan audit', function (): void {
    $classroom = Classroom::factory()->create();
    $admin = classroomAdmin();

    $this->actingAs($admin)
        ->patch(route('admin.classrooms.status.update', $classroom), ['is_active' => false])
        ->assertSessionHasNoErrors();
    expect($classroom->fresh()->is_active)->toBeFalse();

    $this->actingAs($admin)
        ->patch(route('admin.classrooms.status.update', $classroom), ['is_active' => true])
        ->assertSessionHasNoErrors();
    expect($classroom->fresh()->is_active)->toBeTrue()
        ->and(AuditLog::query()->where('subject_type', Classroom::class)->whereIn('action', ['deactivated', 'activated'])->count())->toBe(2);
});

test('salin rombel dari tahun sebelumnya melewati nama yang sudah ada', function (): void {
    $unit = Unit::factory()->create();
    $otherUnit = Unit::factory()->create();
    $source = AcademicYear::factory()->create(['name' => '2025/2026', 'semester' => Semester::Genap]);
    $target = AcademicYear::factory()->active()->create(['name' => '2026/2027', 'semester' => Semester::Ganjil]);
    $teacher = homeroomTeacher();

    Classroom::factory()->for($unit)->for($source)->create(['grade' => '1', 'name' => '1-A', 'homeroom_teacher_id' => $teacher->id]);
    Classroom::factory()->for($unit)->for($source)->create(['grade' => '1', 'name' => '1-B']);
    Classroom::factory()->for($unit)->for($source)->create(['grade' => '2', 'name' => '2-A', 'is_active' => false]);
    Classroom::factory()->for($otherUnit)->for($source)->create(['name' => 'Jilid 1']);
    Classroom::factory()->for($unit)->for($target)->create(['grade' => '1', 'name' => '1-B']);

    $this->actingAs(classroomAdmin())
        ->post(route('admin.classrooms.copy'), [
            'unit_id' => $unit->id,
            'academic_year_id' => $target->id,
            'source_academic_year_id' => $source->id,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.message', '1 rombel disalin, 1 dilewati karena namanya sudah ada.');

    $copied = Classroom::query()
        ->where('academic_year_id', $target->id)
        ->where('unit_id', $unit->id)
        ->orderBy('name')
        ->get();

    expect($copied->pluck('name')->all())->toBe(['1-A', '1-B'])
        ->and($copied->first()->homeroom_teacher_id)->toBe($teacher->id)
        ->and($copied->first()->is_active)->toBeTrue()
        ->and(Classroom::query()->where('academic_year_id', $target->id)->where('unit_id', $otherUnit->id)->count())->toBe(0);

    $audit = AuditLog::query()->where('action', 'copied')->sole();
    expect($audit->after['copied'])->toBe(1)
        ->and($audit->after['skipped'])->toBe(1);
});

test('salin rombel tidak membawa wali kelas yang bukan guru aktif lagi', function (): void {
    $unit = Unit::factory()->create();
    $source = AcademicYear::factory()->create();
    $target = AcademicYear::factory()->create();
    $former = homeroomTeacher();
    $former->forceFill(['is_active' => false])->save();

    Classroom::factory()->for($unit)->for($source)->create(['name' => '1-A', 'homeroom_teacher_id' => $former->id]);

    $this->actingAs(classroomAdmin())
        ->post(route('admin.classrooms.copy'), [
            'unit_id' => $unit->id,
            'academic_year_id' => $target->id,
            'source_academic_year_id' => $source->id,
        ])
        ->assertSessionHasNoErrors();

    expect(Classroom::query()->where('academic_year_id', $target->id)->sole()->homeroom_teacher_id)->toBeNull();
});

test('salin rombel menolak sumber yang sama dengan tujuan', function (): void {
    $unit = Unit::factory()->create();
    $year = AcademicYear::factory()->create();

    $this->actingAs(classroomAdmin())
        ->post(route('admin.classrooms.copy'), [
            'unit_id' => $unit->id,
            'academic_year_id' => $year->id,
            'source_academic_year_id' => $year->id,
        ])
        ->assertSessionHasErrors('source_academic_year_id');
});

test('tahun ajaran yang dipakai rombel tidak dapat dihapus', function (): void {
    $year = AcademicYear::factory()->create();
    Classroom::factory()->for($year)->create();

    $this->actingAs(classroomAdmin())
        ->delete(route('admin.academic-years.destroy', $year))
        ->assertRedirect()
        ->assertInertiaFlash('toast.message', 'Tahun ajaran ini sudah dipakai rombel sehingga tidak dapat dihapus.');

    expect($year->fresh())->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'deleted')->count())->toBe(0);
});
