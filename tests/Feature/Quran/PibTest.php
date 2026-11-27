<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\FeatureFlag;
use App\Models\PibChapter;
use App\Models\PibMaterial;
use App\Models\PibScore;
use App\Models\PibSubchapter;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    FeatureFlag::query()->updateOrCreate(
        ['key' => Feature::Quran->value],
        ['enabled' => true]
    );
});

it('manages pib curriculum and records student scores as 90 minus mistakes', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(Role::Admin->value);
    $teacher = User::factory()->create();
    $teacher->assignRole(Role::Teacher->value);

    $unit = Unit::factory()->create();
    $year = AcademicYear::factory()->create(['is_active' => true]);
    $classroom = Classroom::factory()->create([
        'unit_id' => $unit->id,
        'academic_year_id' => $year->id,
        'homeroom_teacher_id' => $teacher->id,
    ]);

    $student = Student::factory()->create(['unit_id' => $unit->id]);
    Enrollment::factory()->create([
        'student_id' => $student->id,
        'classroom_id' => $classroom->id,
        'academic_year_id' => $year->id,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.quran.pib.chapters.store'), ['unit_id' => $unit->id, 'name' => 'Thaharah'])
        ->assertRedirect();

    $chapter = PibChapter::query()->firstOrFail();

    $this->actingAs($admin)
        ->post(route('admin.quran.pib.subchapters.store'), ['pib_chapter_id' => $chapter->id, 'name' => 'Wudhu'])
        ->assertRedirect();

    $sub = PibSubchapter::query()->firstOrFail();

    $this->actingAs($admin)
        ->post(route('admin.quran.pib.materials.store'), [
            'pib_subchapter_id' => $sub->id,
            'name' => 'Niat dan Membasuh Muka',
            'weight' => 10,
        ])
        ->assertRedirect();

    $material = PibMaterial::query()->firstOrFail();

    // Mistakes > 90 rejected
    $this->actingAs($teacher)
        ->post(route('admin.quran.pib.scores.store'), [
            'student_id' => $student->id,
            'pib_material_id' => $material->id,
            'mistakes' => 95,
        ])
        ->assertSessionHasErrors('mistakes');

    // Valid mistakes -> score = 90 - 5 = 85
    $this->actingAs($teacher)
        ->post(route('admin.quran.pib.scores.store'), [
            'student_id' => $student->id,
            'pib_material_id' => $material->id,
            'mistakes' => 5,
        ])
        ->assertRedirect();

    $score = PibScore::query()->firstOrFail();
    expect($score->mistakes)->toBe(5)
        ->and($score->score)->toBe(85);
});
