<?php

declare(strict_types=1);

use App\Enums\Role as RoleEnum;
use App\Enums\Semester;
use App\Models\AcademicYear;
use App\Models\Unit;
use App\Models\User;
use App\Support\Navigation;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('guru tidak menerima grup menu keuangan pada navigasi server', function (): void {
    $teacher = User::factory()->create();
    $teacher->assignRole(RoleEnum::Teacher->value);

    $this->actingAs($teacher)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(function (Assert $page): void {
            $navigation = $page->toArray()['props']['navigation'];
            $keys = array_column($navigation, 'key');

            expect($keys)->toContain('pengguna', 'absensi', 'kesiswaan', 'e-learning')
                ->not->toContain('keuangan', 'laporan', 'pengaturan');
        });
});

test('setiap item menu navigasi lolos otorisasi untuk peran tersebut', function (RoleEnum $role): void {
    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole($role->value);

    $groups = Navigation::forUser($user);

    foreach ($groups as $group) {
        foreach ($group['items'] as $item) {
            $this->actingAs($user)
                ->get($item['href'])
                ->assertOk();
        }
    }

    expect($groups)->toBeArray();
})->with(RoleEnum::cases());

test('rute modul staf menolak pengguna tanpa izin yang sesuai', function (): void {
    $teacher = User::factory()->create();
    $teacher->assignRole(RoleEnum::Teacher->value);

    $this->actingAs($teacher)
        ->get(route('admin.invoices.index'))
        ->assertForbidden();
});

test('pemilih unit dan tahun ajaran menyimpan konteks ke dalam sesi', function (): void {
    $admin = User::factory()->withTwoFactor()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    Unit::factory()->create(['code' => 'MI']);
    Unit::factory()->create(['code' => 'TPQ']);
    AcademicYear::factory()->active()->create(['name' => '2026/2027', 'semester' => Semester::Ganjil]);
    $previous = AcademicYear::factory()->create(['name' => '2025/2026', 'semester' => Semester::Genap]);

    $this->actingAs($admin)
        ->post(route('admin.context.update'), [
            'unit' => 'TPQ',
            'academic_year_id' => $previous->id,
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('schoolContext.unit', 'TPQ')
            ->where('schoolContext.academicYear', '2025/2026 Genap')
            ->where('schoolContext.academicYearId', $previous->id),
        );
});

test('konteks bawaan memakai unit aktif pertama dan tahun ajaran yang sedang aktif', function (): void {
    $admin = User::factory()->withTwoFactor()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    Unit::factory()->create(['code' => 'RA']);
    Unit::factory()->create(['code' => 'MI']);
    Unit::factory()->create(['code' => 'TPQ', 'is_active' => false]);
    AcademicYear::factory()->create(['name' => '2025/2026', 'semester' => Semester::Genap]);
    AcademicYear::factory()->active()->create(['name' => '2026/2027', 'semester' => Semester::Ganjil]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('schoolContext.unit', 'MI')
            ->where('schoolContext.units', ['MI', 'RA'])
            ->where('schoolContext.academicYear', '2026/2027 Ganjil')
            ->has('schoolContext.academicYears', 2),
        );
});

test('tanpa unit dan tahun ajaran, konteks kosong tanpa nilai karangan', function (): void {
    $admin = User::factory()->withTwoFactor()->create();
    $admin->assignRole(RoleEnum::Admin->value);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('schoolContext.unit', null)
            ->where('schoolContext.units', [])
            ->where('schoolContext.academicYear', null)
            ->where('schoolContext.academicYears', []),
        );
});

test('pemilih konteks menolak unit nonaktif dan tahun ajaran yang tidak ada', function (): void {
    $admin = User::factory()->withTwoFactor()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    Unit::factory()->create(['code' => 'TPQ', 'is_active' => false]);

    $this->actingAs($admin)
        ->post(route('admin.context.update'), ['unit' => 'TPQ', 'academic_year_id' => 999])
        ->assertSessionHasErrors(['unit', 'academic_year_id']);
});

test('konteks sekolah tidak dibagikan ke tamu, wali, dan petugas di luar area staf', function (): void {
    Unit::factory()->create(['code' => 'MI']);
    AcademicYear::factory()->active()->create(['name' => '2026/2027', 'semester' => Semester::Ganjil]);

    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('schoolContext', null));

    $guardian = User::factory()->create();
    $guardian->assignRole(RoleEnum::Guardian->value);

    $this->actingAs($guardian)
        ->get(route('guardian.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('schoolContext', null));

    $gateOfficer = User::factory()->create();
    $gateOfficer->assignRole(RoleEnum::GateOfficer->value);

    $this->actingAs($gateOfficer)
        ->get(route('gate.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('schoolContext', null));
});

test('tahun ajaran terpilih di luar daftar terbaru tetap dipakai dan ditampilkan di pemilih', function (): void {
    $admin = User::factory()->withTwoFactor()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    $oldest = AcademicYear::factory()->create(['name' => '2010/2011', 'semester' => Semester::Ganjil]);

    foreach (range(2020, 2025) as $start) {
        AcademicYear::factory()->create(['name' => $start.'/'.($start + 1), 'semester' => Semester::Ganjil]);
    }

    $this->actingAs($admin)
        ->post(route('admin.context.update'), ['academic_year_id' => $oldest->id])
        ->assertRedirect();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('schoolContext.academicYearId', $oldest->id)
            ->where('schoolContext.academicYear', '2010/2011 Ganjil')
            ->has('schoolContext.academicYears', 7)
            ->where('schoolContext.academicYears', fn ($years): bool => collect($years)->contains('id', $oldest->id)),
        );
});
