<?php

declare(strict_types=1);

use App\Enums\Role as RoleEnum;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\StaffProfile;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function profileAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);

    return $admin;
}

test('admin dapat membuat dan mengubah profil staf berupa unit, NIP, dan jabatan', function (): void {
    $admin = profileAdmin();
    $mi = Unit::factory()->create(['code' => 'MI', 'name' => 'Madrasah Ibtidaiyah']);
    $ra = Unit::factory()->create(['code' => 'RA', 'name' => 'Raudhatul Athfal']);

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Guru Profil',
            'username' => 'guru.profil',
            'phone' => '081234567890',
            'roles' => [RoleEnum::Teacher->value],
            'unit_id' => $mi->id,
            'nip' => '199001012020011001',
            'position' => 'Guru Kelas 1',
        ])
        ->assertRedirect();

    $teacher = User::query()->where('username', 'guru.profil')->firstOrFail();
    $profile = StaffProfile::query()->where('user_id', $teacher->id)->firstOrFail();

    expect($profile->unit_id)->toBe($mi->id)
        ->and($profile->nip)->toBe('199001012020011001')
        ->and($profile->position)->toBe('Guru Kelas 1');

    $this->actingAs($admin)
        ->put(route('admin.users.update', $teacher), [
            'name' => 'Guru Profil',
            'username' => 'guru.profil',
            'phone' => '081234567890',
            'roles' => [RoleEnum::Teacher->value],
            'unit_id' => $ra->id,
            'nip' => '199001012020011002',
            'position' => 'Wali Kelas A',
        ])
        ->assertRedirect();

    expect($profile->fresh()?->unit_id)->toBe($ra->id)
        ->and($profile->fresh()?->nip)->toBe('199001012020011002')
        ->and($profile->fresh()?->position)->toBe('Wali Kelas A');
});

test('daftar pengguna staf menampilkan profil dan penugasan wali kelas pada tahun ajaran aktif', function (): void {
    $admin = profileAdmin();
    $mi = Unit::factory()->create(['code' => 'MI']);
    $year = AcademicYear::factory()->active()->create();

    $teacher = User::factory()->create(['name' => 'Ustadz Ahmad', 'username' => 'ustadz.ahmad']);
    $teacher->assignRole(RoleEnum::Teacher->value);
    StaffProfile::query()->create([
        'user_id' => $teacher->id,
        'unit_id' => $mi->id,
        'nip' => '19890101',
        'position' => 'Wali Kelas',
    ]);
    Classroom::factory()->for($mi)->for($year)->create([
        'name' => '1-A',
        'homeroom_teacher_id' => $teacher->id,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.users.index', ['search' => 'ustadz.ahmad']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/index')
            ->has('users.data', 1)
            ->where('users.data.0.profile.unit_code', 'MI')
            ->where('users.data.0.profile.nip', '19890101')
            ->where('users.data.0.profile.position', 'Wali Kelas')
            ->where('users.data.0.homeroom_classrooms.0', 'MI · 1-A')
        );
});
