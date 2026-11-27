<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\FeatureFlag;
use App\Models\Guardian;
use App\Models\MemorizationMaterial;
use App\Models\MemorizationTarget;
use App\Models\MemorizedAyah;
use App\Models\RubricVersion;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Services\Quran\MemorizationProgressService;
use App\Services\Quran\MemorizationService;
use App\Services\Quran\RubricService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\RubricSeeder;
use Database\Seeders\SurahSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SurahSeeder::class);
    $this->seed(RubricSeeder::class);

    FeatureFlag::query()->updateOrCreate(
        ['key' => Feature::Quran->value],
        ['enabled' => true]
    );
});

it('tracks unique memorized ayahs without double counting and calculates guardian progress per kind', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(Role::Admin->value);
    $teacher = User::factory()->create();
    $teacher->assignRole(Role::Teacher->value);

    $version = RubricVersion::query()->with('criteria')->firstOrFail();
    $version = app(RubricService::class)->publish($admin, $version);

    $unit = Unit::factory()->create();
    $year = AcademicYear::factory()->create(['is_active' => true]);
    $classroom = Classroom::factory()->create([
        'unit_id' => $unit->id,
        'academic_year_id' => $year->id,
        'grade' => '1',
        'homeroom_teacher_id' => $teacher->id,
    ]);

    $student = Student::factory()->create(['unit_id' => $unit->id]);
    Enrollment::factory()->create([
        'student_id' => $student->id,
        'classroom_id' => $classroom->id,
        'academic_year_id' => $year->id,
    ]);

    $guardianUser = User::factory()->create(['is_active' => true]);
    $guardianUser->assignRole(Role::Guardian->value);
    $guardian = Guardian::factory()->create(['user_id' => $guardianUser->id]);
    $student->guardians()->attach($guardian->id, ['relation' => 'ibu', 'is_primary' => true]);

    // Target: An-Nas (114, 6 ayahs) + 2 hadiths
    MemorizationTarget::factory()->create([
        'unit_id' => $unit->id,
        'grade' => '1',
        'academic_year_id' => $year->id,
        'kind' => 'quran',
        'ranges' => [['surah' => 114, 'ayah_from' => 1, 'ayah_to' => 6]],
    ]);

    $h1 = MemorizationMaterial::factory()->create(['kind' => 'hadith', 'title' => 'Hadits 1']);
    $h2 = MemorizationMaterial::factory()->create(['kind' => 'hadith', 'title' => 'Hadits 2']);
    MemorizationTarget::factory()->create([
        'unit_id' => $unit->id,
        'grade' => '1',
        'academic_year_id' => $year->id,
        'kind' => 'hadith',
        'ranges' => null,
        'material_ids' => [$h1->id, $h2->id],
    ]);

    $scores = [];
    foreach ($version->criteria as $c) {
        $scores[$c->id] = 0; // 100 score -> passed
    }

    $service = app(MemorizationService::class);

    // Record ayahs 1..3 twice (overlapping 2..4) -> total unique memorized = 4 (NOT 6)
    $service->record($teacher, $student, $version, [
        'kind' => 'quran',
        'type' => 'ziyadah',
        'surah_from' => 114,
        'ayah_from' => 1,
        'surah_to' => 114,
        'ayah_to' => 3,
        'recorded_on' => '2026-11-26',
        'scores' => $scores,
    ]);
    $service->record($teacher, $student, $version, [
        'kind' => 'quran',
        'type' => 'ziyadah',
        'surah_from' => 114,
        'ayah_from' => 2,
        'surah_to' => 114,
        'ayah_to' => 4,
        'recorded_on' => '2026-11-27',
        'scores' => $scores,
    ]);

    expect(MemorizedAyah::query()->where('student_id', $student->id)->count())->toBe(4);

    // Pass 1 of 2 hadiths -> 50%
    $service->record($teacher, $student, $version, [
        'kind' => 'hadith',
        'memorization_material_id' => $h1->id,
        'recorded_on' => '2026-11-27',
        'scores' => $scores,
    ]);

    $progress = app(MemorizationProgressService::class)->progressForStudent($student, $year);
    expect($progress['quran']['achieved'])->toBe(4)
        ->and($progress['quran']['target'])->toBe(6)
        ->and($progress['hadith']['achieved'])->toBe(1)
        ->and($progress['hadith']['target'])->toBe(2)
        ->and($progress['hadith']['pct'])->toBe(50.0);

    $this->actingAs($guardianUser)
        ->get(route('guardian.students.quran.index', $student))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('guardian/quran')
            ->where('progress.quran.achieved', 4)
            ->where('progress.hadith.pct', 50)
        );
});
