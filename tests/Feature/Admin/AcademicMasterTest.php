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
});

test('jenis master tak dikenal 404 dan guru ditolak', function (): void {
    $this->actingAs($this->admin)->get('/admin/akademik/master/lain')->assertNotFound();

    $teacher = User::factory()->create();
    $teacher->assignRole('guru');
    $this->actingAs($teacher)->get(route('admin.academic-masters.index', 'tingkat'))->assertForbidden();
});

test('admin mengelola tingkat, rombel, dan jurusan dengan nama unik per unit', function (string $kind, string $model): void {
    $this->actingAs($this->admin)->post(route('admin.academic-masters.store', $kind), ['unit_id' => $this->unit->id, 'name' => 'A', 'sort_order' => 1])
        ->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->post(route('admin.academic-masters.store', $kind), ['unit_id' => $this->unit->id, 'name' => 'A', 'sort_order' => 2])
        ->assertSessionHasErrors('name');

    $row = $model::query()->sole();
    $this->actingAs($this->admin)->put(route('admin.academic-masters.update', ['kind' => $kind, 'id' => $row->id]), ['unit_id' => $this->unit->id, 'name' => 'B', 'sort_order' => 3])
        ->assertSessionHasNoErrors();
    expect($row->fresh()->name)->toBe('B');

    $this->actingAs($this->admin)->get(route('admin.academic-masters.index', $kind))
        ->assertInertia(fn (Assert $page) => $page->component('admin/academic/master')->where('kind', $kind)->has('rows.data', 1));

    $this->actingAs($this->admin)->delete(route('admin.academic-masters.destroy', ['kind' => $kind, 'id' => $row->id]))->assertSessionHasNoErrors();
    expect($model::query()->count())->toBe(0);
})->with([
    ['tingkat', GradeLevel::class],
    ['rombel', ClassGroup::class],
    ['jurusan', Major::class],
]);

test('tingkat yang dipakai rombel tidak bisa dihapus', function (): void {
    $level = GradeLevel::query()->create(['unit_id' => $this->unit->id, 'name' => '1', 'sort_order' => 1]);
    $year = AcademicYear::factory()->active()->create();
    Classroom::factory()->for($this->unit)->for($year)->create(['grade' => '1', 'name' => '1-A']);

    $this->actingAs($this->admin)->delete(route('admin.academic-masters.destroy', ['kind' => 'tingkat', 'id' => $level->id]))
        ->assertSessionHasErrors('master');
    expect(GradeLevel::query()->count())->toBe(1);
});
