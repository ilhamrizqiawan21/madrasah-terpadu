<?php

declare(strict_types=1);

use App\Enums\Role as RoleEnum;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('beranda staf menampilkan ringkasan data nyata sesuai unit aktif dan izin pengguna', function (): void {
    $mi = Unit::factory()->create(['code' => 'MI', 'name' => 'Madrasah Ibtidaiyah']);
    $year = AcademicYear::factory()->active()->create();
    Classroom::factory()->for($mi)->for($year)->create(['name' => '1-A']);
    Student::factory()->for($mi)->count(2)->create();

    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dashboard')
            ->where('summary.unit_code', 'MI')
            ->where('summary.active_students', 2)
            ->where('summary.active_classrooms', 1)
            ->where('permissions.manage_students', true)
        );
});
