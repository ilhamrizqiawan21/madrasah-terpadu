<?php

declare(strict_types=1);

use App\Enums\BehaviorRecordStatus;
use App\Enums\Feature;
use App\Enums\PointCategory;
use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\BehaviorRecord;
use App\Models\DisciplineFollowup;
use App\Models\DisciplineThreshold;
use App\Models\FeatureFlag;
use App\Models\PointRule;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    FeatureFlag::query()->updateOrCreate(
        ['key' => Feature::Behavior->value],
        ['enabled' => true],
    );
});

it('authorizes reports.view and matches behavior report counts across modes and exports', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(Role::Admin->value);

    $teacher = User::factory()->create();
    $teacher->assignRole(Role::Teacher->value);

    $unit = Unit::factory()->create();
    $year = AcademicYear::factory()->create(['is_active' => true]);
    $student = Student::factory()->create(['unit_id' => $unit->id]);

    $rulePos = PointRule::query()->create([
        'unit_id' => $unit->id,
        'code' => 'K-01',
        'category' => PointCategory::GoodDeed,
        'name' => 'Menolong teman',
        'points' => 10,
        'is_active' => true,
    ]);
    $ruleNeg = PointRule::query()->create([
        'unit_id' => $unit->id,
        'code' => 'P-01',
        'category' => PointCategory::Violation,
        'name' => 'Terlambat upacara',
        'points' => 15,
        'is_active' => true,
    ]);

    BehaviorRecord::query()->create([
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'point_rule_id' => $rulePos->id,
        'category' => PointCategory::GoodDeed,
        'points' => 10,
        'occurred_on' => '2026-11-27',
        'recorded_by' => $admin->id,
        'status' => BehaviorRecordStatus::Active,
    ]);
    BehaviorRecord::query()->create([
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'point_rule_id' => $ruleNeg->id,
        'category' => PointCategory::Violation,
        'points' => 15,
        'occurred_on' => '2026-11-27',
        'recorded_by' => $admin->id,
        'status' => BehaviorRecordStatus::Active,
    ]);

    $threshold = DisciplineThreshold::query()->create([
        'unit_id' => $unit->id,
        'points' => 10,
        'action_name' => 'Panggilan orang tua',
        'notify_guardian' => false,
        'is_active' => true,
    ]);
    DisciplineFollowup::query()->create([
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'discipline_threshold_id' => $threshold->id,
        'triggered_at' => now(),
        'status' => 'open',
    ]);

    $this->actingAs($teacher)
        ->get(route('admin.reports.behavior.index'))
        ->assertForbidden();

    $this->actingAs($admin)
        ->get(route('admin.reports.behavior.index', ['mode' => 'records', 'date_from' => '2026-11-01', 'date_to' => '2026-11-30']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/behavior')
            ->where('summary.records', 2)
            ->where('summary.positive_points', 10)
            ->where('summary.violation_points', 15)
            ->where('summary.open_followups', 1)
            ->has('rows.data', 2)
        );

    $this->actingAs($admin)
        ->get(route('admin.reports.behavior.index', ['mode' => 'top_points', 'date_from' => '2026-11-01', 'date_to' => '2026-11-30']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('rows.data.0.violation', 15)
            ->where('rows.data.0.positive', 10)
        );

    $this->actingAs($admin)
        ->get(route('admin.reports.behavior.index', ['mode' => 'followups', 'date_from' => '2026-11-01', 'date_to' => '2026-11-30']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rows.data', 1)
        );

    $this->actingAs($admin)
        ->get(route('admin.reports.behavior.pdf', ['mode' => 'records', 'date_from' => '2026-11-01', 'date_to' => '2026-11-30']))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $this->actingAs($admin)
        ->get(route('admin.reports.behavior.excel', ['mode' => 'records', 'date_from' => '2026-11-01', 'date_to' => '2026-11-30']))
        ->assertOk();
});
