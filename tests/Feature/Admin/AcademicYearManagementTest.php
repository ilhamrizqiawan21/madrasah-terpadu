<?php

declare(strict_types=1);

use App\Enums\Role as RoleEnum;
use App\Enums\Semester;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function yearAdmin(): User
{
    $admin = User::factory()->withTwoFactor()->create();
    $admin->assignRole(RoleEnum::Admin->value);

    return $admin;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function yearPayload(array $overrides = []): array
{
    return [
        'name' => '2026/2027',
        'semester' => 'ganjil',
        'starts_on' => '2026-07-13',
        'ends_on' => '2026-12-19',
        ...$overrides,
    ];
}

test('tamu diarahkan ke halaman login saat membuka tahun ajaran', function (): void {
    $this->get(route('admin.academic-years.index'))->assertRedirect(route('login'));
});

test('peran selain admin ditolak mengelola tahun ajaran', function (RoleEnum $role): void {
    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole($role->value);
    $year = AcademicYear::factory()->create();

    $this->actingAs($user)->get(route('admin.academic-years.index'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.academic-years.store'), yearPayload())->assertForbidden();
    $this->actingAs($user)->put(route('admin.academic-years.update', $year), yearPayload())->assertForbidden();
    $this->actingAs($user)->post(route('admin.academic-years.activate', $year))->assertForbidden();
    $this->actingAs($user)->delete(route('admin.academic-years.destroy', $year))->assertForbidden();

    expect(AcademicYear::query()->count())->toBe(1);
})->with([
    RoleEnum::Treasurer,
    RoleEnum::Teacher,
    RoleEnum::UnitHead,
]);

test('admin melihat tahun ajaran terbaru lebih dulu, genap sebelum ganjil', function (): void {
    AcademicYear::factory()->create(['name' => '2025/2026', 'semester' => Semester::Genap]);
    AcademicYear::factory()->create(['name' => '2026/2027', 'semester' => Semester::Ganjil]);
    AcademicYear::factory()->create(['name' => '2026/2027', 'semester' => Semester::Genap]);

    $this->actingAs(yearAdmin())
        ->get(route('admin.academic-years.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/academic-years/index')
            ->has('academicYears.data', 3)
            ->where('academicYears.data.0.name', '2026/2027')
            ->where('academicYears.data.0.semester.value', 'genap')
            ->where('academicYears.data.1.semester.value', 'ganjil')
            ->where('academicYears.data.2.name', '2025/2026'));
});

test('admin menambah tahun ajaran nonaktif dan tercatat di audit', function (): void {
    $admin = yearAdmin();

    $this->actingAs($admin)
        ->post(route('admin.academic-years.store'), yearPayload())
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $year = AcademicYear::query()->sole();

    expect($year->name)->toBe('2026/2027')
        ->and($year->semester)->toBe(Semester::Ganjil)
        ->and($year->starts_on->toDateString())->toBe('2026-07-13')
        ->and($year->is_active)->toBeFalse()
        ->and(AuditLog::query()->where('action', 'created')->where('subject_type', AcademicYear::class)->count())->toBe(1);
});

test('format tahun ajaran, semester, dan tanggal divalidasi', function (array $overrides, string $field): void {
    $this->actingAs(yearAdmin())
        ->post(route('admin.academic-years.store'), yearPayload($overrides))
        ->assertSessionHasErrors($field);

    expect(AcademicYear::query()->count())->toBe(0);
})->with([
    'bukan YYYY/YYYY' => [['name' => '2026-2027'], 'name'],
    'tahun tidak berurutan' => [['name' => '2026/2028'], 'name'],
    'semester tidak dikenal' => [['semester' => 'pendek'], 'semester'],
    'selesai sebelum mulai' => [['starts_on' => '2026-12-19', 'ends_on' => '2026-07-13'], 'ends_on'],
]);

test('tahun ajaran dan semester yang sama tidak boleh ganda', function (): void {
    AcademicYear::factory()->create(['name' => '2026/2027', 'semester' => Semester::Ganjil]);

    $this->actingAs(yearAdmin())
        ->post(route('admin.academic-years.store'), yearPayload())
        ->assertSessionHasErrors('semester');

    $this->actingAs(yearAdmin())
        ->post(route('admin.academic-years.store'), yearPayload(['semester' => 'genap', 'starts_on' => '2027-01-04', 'ends_on' => '2027-06-19']))
        ->assertSessionHasNoErrors();
});

test('mengaktifkan satu tahun ajaran menonaktifkan yang lain', function (): void {
    $old = AcademicYear::factory()->active()->create(['name' => '2025/2026', 'semester' => Semester::Genap]);
    $new = AcademicYear::factory()->create(['name' => '2026/2027', 'semester' => Semester::Ganjil]);

    $this->actingAs(yearAdmin())
        ->post(route('admin.academic-years.activate', $new))
        ->assertSessionHasNoErrors();

    expect($new->fresh()->is_active)->toBeTrue()
        ->and($old->fresh()->is_active)->toBeFalse()
        ->and(AcademicYear::query()->where('is_active', true)->count())->toBe(1);

    $audit = AuditLog::query()->where('action', 'activated')->sole();
    expect($audit->subject_id)->toBe($new->id)
        ->and($audit->before['previous_active_id'])->toBe($old->id);
});

test('mengaktifkan tahun ajaran yang sudah aktif tidak mengubah apa pun', function (): void {
    $year = AcademicYear::factory()->active()->create();

    $this->actingAs(yearAdmin())->post(route('admin.academic-years.activate', $year));

    expect($year->fresh()->is_active)->toBeTrue()
        ->and(AuditLog::query()->where('action', 'activated')->count())->toBe(0);
});

test('admin mengubah periode tahun ajaran', function (): void {
    $year = AcademicYear::factory()->create(['name' => '2026/2027', 'semester' => Semester::Ganjil]);

    $this->actingAs(yearAdmin())
        ->put(route('admin.academic-years.update', $year), yearPayload(['ends_on' => '2026-12-20']))
        ->assertSessionHasNoErrors();

    expect($year->fresh()->ends_on->toDateString())->toBe('2026-12-20')
        ->and(AuditLog::query()->where('action', 'updated')->count())->toBe(1);
});

test('tahun ajaran nonaktif dapat dihapus, yang aktif ditolak', function (): void {
    $active = AcademicYear::factory()->active()->create(['name' => '2026/2027']);
    $inactive = AcademicYear::factory()->create(['name' => '2025/2026']);
    $admin = yearAdmin();

    $this->actingAs($admin)
        ->delete(route('admin.academic-years.destroy', $active))
        ->assertRedirect();
    expect($active->fresh())->not->toBeNull();

    $this->actingAs($admin)
        ->delete(route('admin.academic-years.destroy', $inactive))
        ->assertRedirect();
    expect($inactive->fresh())->toBeNull()
        ->and(AuditLog::query()->where('action', 'deleted')->where('subject_id', $inactive->id)->count())->toBe(1);
});
