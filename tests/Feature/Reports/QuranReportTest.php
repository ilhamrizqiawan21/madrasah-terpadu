<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\FeatureFlag;
use App\Models\MemorizationRecord;
use App\Models\PibChapter;
use App\Models\PibMaterial;
use App\Models\PibScore;
use App\Models\PibSubchapter;
use App\Models\Rubric;
use App\Models\RubricVersion;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    FeatureFlag::query()->updateOrCreate(
        ['key' => Feature::Quran->value],
        ['enabled' => true],
    );
});

it('authorizes reports.view and matches quran and pib report counts across modes and exports', function (): void {
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
    $student = Student::factory()->create(['unit_id' => $unit->id, 'status' => StudentStatus::Active]);
    Enrollment::factory()->create([
        'student_id' => $student->id,
        'classroom_id' => $classroom->id,
        'academic_year_id' => $year->id,
    ]);

    $rubric = Rubric::query()->create(['unit_id' => $unit->id, 'name' => 'Tahfidz']);
    $version = RubricVersion::query()->create(['rubric_id' => $rubric->id, 'version_number' => 1, 'pass_threshold' => 75, 'published_at' => now()]);

    MemorizationRecord::query()->create([
        'student_id' => $student->id,
        'teacher_id' => $teacher->id,
        'rubric_version_id' => $version->id,
        'kind' => 'surah',
        'type' => 'ziyadah',
        'surah_from' => 114,
        'ayah_from' => 1,
        'surah_to' => 114,
        'ayah_to' => 6,
        'scores' => [1 => 90],
        'final_score' => 90,
        'predicate' => 'Mumtaz',
        'passed' => true,
        'recorded_on' => '2026-11-27',
    ]);

    $chap = PibChapter::query()->create(['unit_id' => $unit->id, 'name' => 'Thaharah', 'sort_order' => 1]);
    $sub = PibSubchapter::query()->create(['pib_chapter_id' => $chap->id, 'name' => 'Wudhu', 'sort_order' => 1]);
    $mat = PibMaterial::query()->create(['pib_subchapter_id' => $sub->id, 'name' => 'Niat', 'weight' => 10, 'sort_order' => 1]);

    PibScore::query()->create([
        'student_id' => $student->id,
        'pib_material_id' => $mat->id,
        'academic_year_id' => $year->id,
        'mistakes' => 4,
        'score' => 86,
        'assessed_by' => $teacher->id,
    ]);

    $this->actingAs($teacher)->get(route('admin.reports.quran.index'))->assertForbidden();

    $this->actingAs($admin)
        ->get(route('admin.reports.quran.index', ['mode' => 'classroom', 'date_from' => '2026-11-01', 'date_to' => '2026-11-30']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/quran')
            ->where('summary.deposits', 1)
            ->where('summary.passed', 1)
            ->where('summary.pib_scores', 1)
            ->where('rows.data.0.deposits', 1)
            ->where('rows.data.0.pib_count', 1)
        );

    $this->actingAs($admin)
        ->get(route('admin.reports.quran.index', ['mode' => 'teacher', 'date_from' => '2026-11-01', 'date_to' => '2026-11-30']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('rows.data.0.deposits', 1)
        );

    $this->actingAs($admin)
        ->get(route('admin.reports.quran.index', ['mode' => 'pib', 'date_from' => '2026-11-01', 'date_to' => '2026-11-30']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('rows.data.0.avg_score', 86)
        );

    $this->actingAs($admin)
        ->get(route('admin.reports.quran.pdf', ['mode' => 'classroom', 'date_from' => '2026-11-01', 'date_to' => '2026-11-30']))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $this->actingAs($admin)
        ->get(route('admin.reports.quran.excel', ['mode' => 'classroom', 'date_from' => '2026-11-01', 'date_to' => '2026-11-30']))
        ->assertOk();
});
