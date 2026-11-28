<?php

declare(strict_types=1);

use App\Enums\Role as RoleEnum;
use App\Models\GradeLevel;
use App\Models\LearningObjective;
use App\Models\Subject;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleEnum::Admin->value);
    $this->unit = Unit::factory()->create(['code' => 'MI']);
    $this->subject = Subject::factory()->for($this->unit)->create(['code' => 'MTK', 'name' => 'Matematika']);
    GradeLevel::query()->create(['unit_id' => $this->unit->id, 'name' => '1', 'sort_order' => 1]);
});

test('TP tersimpan per mapel dan kelas, kode unik, dan bisa disaring', function (): void {
    $payload = ['subject_id' => $this->subject->id, 'grade' => '1', 'code' => 'TP 1.1', 'description' => 'Membilang 1 sampai 20.'];

    $this->actingAs($this->admin)->post(route('admin.learning-objectives.store'), $payload)->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->post(route('admin.learning-objectives.store'), $payload)->assertSessionHasErrors('code');
    $this->actingAs($this->admin)->post(route('admin.learning-objectives.store'), [...$payload, 'grade' => '2'])->assertSessionHasNoErrors();

    expect(LearningObjective::query()->count())->toBe(2);

    $this->actingAs($this->admin)->get(route('admin.learning-objectives.index', ['grade' => '1', 'search' => 'membilang']))
        ->assertInertia(fn (Assert $page) => $page->component('admin/academic/learning-objectives')
            ->has('rows.data', 1)->where('rows.data.0.subject_name', 'Matematika')
            ->where('gradeOptions.0.label', 'Kelas 1'));
});
