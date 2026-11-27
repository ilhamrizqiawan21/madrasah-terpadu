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
use App\Models\MemorizationRecord;
use App\Models\RubricVersion;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\MemorizationRecordedNotification;
use App\Services\Quran\RubricService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\RubricSeeder;
use Database\Seeders\SurahSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SurahSeeder::class);
    $this->seed(RubricSeeder::class);

    FeatureFlag::query()->updateOrCreate(
        ['key' => Feature::Quran->value],
        ['enabled' => true]
    );
});

it('records quran and hadith memorizations, computes score server-side, notifies guardian, and blocks non-assigned teachers', function (): void {
    Notification::fake();

    $admin = User::factory()->create();
    $admin->assignRole(Role::Admin->value);
    $teacher = User::factory()->create();
    $teacher->assignRole(Role::Teacher->value);
    $otherTeacher = User::factory()->create();
    $otherTeacher->assignRole(Role::Teacher->value);

    $version = RubricVersion::query()->with('criteria')->firstOrFail();
    app(RubricService::class)->publish($admin, $version);

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

    $guardianUser = User::factory()->create(['is_active' => true]);
    $guardianUser->assignRole(Role::Guardian->value);
    $guardian = Guardian::factory()->create(['user_id' => $guardianUser->id]);
    $student->guardians()->attach($guardian->id, ['relation' => 'ayah', 'is_primary' => true]);

    $scores = [];
    foreach ($version->criteria as $c) {
        $scores[$c->id] = 1; // 1 mistake * 2 deduction = 98
    }

    // Non-assigned teacher is forbidden (403)
    $this->actingAs($otherTeacher)
        ->post(route('admin.quran.store'), [
            'student_id' => $student->id,
            'rubric_version_id' => $version->id,
            'kind' => 'quran',
            'type' => 'ziyadah',
            'surah_from' => 114,
            'ayah_from' => 1,
            'surah_to' => 114,
            'ayah_to' => 6,
            'recorded_on' => '2026-11-27',
            'scores' => $scores,
        ])
        ->assertForbidden();

    // Assigned teacher records Quran ziyadah
    $this->actingAs($teacher)
        ->post(route('admin.quran.store'), [
            'student_id' => $student->id,
            'rubric_version_id' => $version->id,
            'kind' => 'quran',
            'type' => 'ziyadah',
            'surah_from' => 114,
            'ayah_from' => 1,
            'surah_to' => 114,
            'ayah_to' => 6,
            'recorded_on' => '2026-11-27',
            'scores' => $scores,
        ])
        ->assertRedirect();

    $quranRecord = MemorizationRecord::query()->where('kind', 'quran')->firstOrFail();
    expect($quranRecord->final_score)->toBe(98.0)
        ->and($quranRecord->predicate)->toBe('A')
        ->and($quranRecord->passed)->toBeTrue();

    // Hadith record requires material_id and rejects type=ziyadah
    $hadith = MemorizationMaterial::factory()->create(['kind' => 'hadith']);

    $this->actingAs($teacher)
        ->post(route('admin.quran.store'), [
            'student_id' => $student->id,
            'rubric_version_id' => $version->id,
            'kind' => 'hadith',
            'memorization_material_id' => $hadith->id,
            'recorded_on' => '2026-11-27',
            'scores' => $scores,
        ])
        ->assertRedirect();

    $hadithRecord = MemorizationRecord::query()->where('kind', 'hadith')->firstOrFail();
    expect($hadithRecord->type)->toBeNull()
        ->and($hadithRecord->memorization_material_id)->toBe($hadith->id);

    Notification::assertSentToTimes($guardianUser, MemorizationRecordedNotification::class, 2);
});
