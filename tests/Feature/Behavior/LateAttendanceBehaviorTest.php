<?php

declare(strict_types=1);

use App\Enums\AttendanceDecider;
use App\Enums\AttendanceStatus;
use App\Enums\Feature;
use App\Enums\PointAccount;
use App\Enums\PointCategory;
use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\AttendanceDay;
use App\Models\BehaviorRecord;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\FeatureFlag;
use App\Models\PointRule;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Services\Behavior\PointLedgerService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    FeatureFlag::query()->updateOrCreate(
        ['key' => Feature::Behavior->value],
        ['enabled' => true]
    );
});

it('lists late students for a date and allows bk to record violations for selected late students at once', function (): void {
    $bk = User::factory()->create();
    $bk->assignRole(Role::Counselor->value);

    $unit = Unit::factory()->create(['code' => 'MI']);
    $year = AcademicYear::factory()->create(['is_active' => true]);
    $classroom = Classroom::factory()->create([
        'unit_id' => $unit->id,
        'academic_year_id' => $year->id,
        'name' => 'IV-A',
    ]);

    $late1 = Student::factory()->create(['unit_id' => $unit->id, 'name' => 'Budi Terlambat']);
    $late2 = Student::factory()->create(['unit_id' => $unit->id, 'name' => 'Citra Terlambat']);
    $present = Student::factory()->create(['unit_id' => $unit->id, 'name' => 'Dewi Tepat Waktu']);

    foreach ([$late1, $late2, $present] as $student) {
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'academic_year_id' => $year->id,
        ]);
    }

    $date = '2026-11-27';

    AttendanceDay::query()->create([
        'student_id' => $late1->id,
        'school_date' => $date,
        'status' => AttendanceStatus::Late->value,
        'checked_in_at' => "{$date} 07:20:00",
        'decided_by' => AttendanceDecider::Scan->value,
    ]);
    AttendanceDay::query()->create([
        'student_id' => $late2->id,
        'school_date' => $date,
        'status' => AttendanceStatus::Late->value,
        'checked_in_at' => "{$date} 07:25:00",
        'decided_by' => AttendanceDecider::Scan->value,
    ]);
    AttendanceDay::query()->create([
        'student_id' => $present->id,
        'school_date' => $date,
        'status' => AttendanceStatus::Present->value,
        'checked_in_at' => "{$date} 06:55:00",
        'decided_by' => AttendanceDecider::Scan->value,
    ]);

    $rule = PointRule::factory()->create([
        'unit_id' => $unit->id,
        'code' => 'P-03',
        'name' => 'Terlambat masuk sekolah',
        'category' => PointCategory::Violation,
        'points' => 3,
    ]);

    $this->actingAs($bk)
        ->get(route('admin.behavior.late.index', ['date' => $date]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/behavior/late')
            ->has('lateStudents', 2)
            ->has('violationRules', 1)
        );

    $this->actingAs($bk)
        ->post(route('admin.behavior.store'), [
            'student_ids' => [$late1->id, $late2->id],
            'point_rule_id' => $rule->id,
            'occurred_on' => $date,
            'note' => 'Terlambat gerbang',
        ])
        ->assertRedirect();

    expect(BehaviorRecord::query()->count())->toBe(2)
        ->and(app(PointLedgerService::class)->balanceFor($late1->id, $year->id, PointAccount::Violation))->toBe(3)
        ->and(app(PointLedgerService::class)->balanceFor($late2->id, $year->id, PointAccount::Violation))->toBe(3);
});
