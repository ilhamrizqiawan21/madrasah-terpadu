<?php

declare(strict_types=1);

use App\Enums\BehaviorRecordStatus;
use App\Enums\Feature;
use App\Enums\PointAccount;
use App\Enums\PointCategory;
use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\BehaviorRecord;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\FeatureFlag;
use App\Models\Guardian;
use App\Models\PointRule;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\BehaviorRecordedNotification;
use App\Services\Behavior\PointLedgerService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    FeatureFlag::query()->updateOrCreate(
        ['key' => Feature::Behavior->value],
        ['enabled' => true]
    );
});

it('records behavior for multiple students simultaneously, snapshots points, and notifies guardians without recorder name', function (): void {
    Notification::fake();
    Storage::fake('local');

    $guru = User::factory()->create(['name' => 'Ustadz Rahasia']);
    $guru->assignRole(Role::Teacher->value);

    $unit = Unit::factory()->create(['code' => 'MI']);
    $year = AcademicYear::factory()->create(['is_active' => true]);
    $classroom = Classroom::factory()->create(['unit_id' => $unit->id, 'academic_year_id' => $year->id]);

    $students = Student::factory()->count(2)->create(['unit_id' => $unit->id]);
    foreach ($students as $student) {
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'academic_year_id' => $year->id,
        ]);
    }

    $guardianUser = User::factory()->create(['is_active' => true]);
    $guardianUser->assignRole(Role::Guardian->value);
    $guardian = Guardian::factory()->create(['user_id' => $guardianUser->id]);
    $students[0]->guardians()->attach($guardian->id, ['relation' => 'ayah', 'is_primary' => true]);

    $rule = PointRule::factory()->create([
        'unit_id' => $unit->id,
        'code' => 'P-01',
        'name' => 'Terlambat apel pagi',
        'category' => PointCategory::Violation,
        'points' => 5,
    ]);

    $photo = UploadedFile::fake()->image('bukti.jpg');

    $this->actingAs($guru)
        ->post(route('admin.behavior.store'), [
            'student_ids' => $students->pluck('id')->all(),
            'point_rule_id' => $rule->id,
            'occurred_on' => '2026-11-27',
            'note' => 'Datang pukul 07.25',
            'evidence' => $photo,
        ])
        ->assertRedirect();

    expect(BehaviorRecord::query()->count())->toBe(2);

    $ledger = app(PointLedgerService::class);
    expect($ledger->balanceFor($students[0]->id, $year->id, PointAccount::Violation))->toBe(5)
        ->and($ledger->balanceFor($students[1]->id, $year->id, PointAccount::Violation))->toBe(5);

    // Changing rule points later does NOT alter historical record snapshot or ledger balance
    $rule->update(['points' => 25]);
    $record = BehaviorRecord::query()->where('student_id', $students[0]->id)->firstOrFail();
    expect($record->points)->toBe(5)
        ->and($record->evidence_path)->not->toBeNull();

    Storage::disk('local')->assertExists((string) $record->evidence_path);

    Notification::assertSentTo($guardianUser, BehaviorRecordedNotification::class, function (BehaviorRecordedNotification $n) use ($guardianUser): bool {
        return ! str_contains($n->body($guardianUser), 'Ustadz Rahasia');
    });
});

it('allows bk/admin to void a behavior record and restores the point balance via reversal entry', function (): void {
    $guru = User::factory()->create();
    $guru->assignRole(Role::Teacher->value);
    $bk = User::factory()->create();
    $bk->assignRole(Role::Counselor->value);

    $unit = Unit::factory()->create();
    $year = AcademicYear::factory()->create(['is_active' => true]);
    $student = Student::factory()->create(['unit_id' => $unit->id]);
    $rule = PointRule::factory()->create([
        'unit_id' => $unit->id,
        'category' => PointCategory::GoodDeed,
        'points' => 10,
    ]);

    $this->actingAs($guru)
        ->post(route('admin.behavior.store'), [
            'student_ids' => [$student->id],
            'point_rule_id' => $rule->id,
            'occurred_on' => '2026-11-27',
        ])
        ->assertRedirect();

    $record = BehaviorRecord::query()->firstOrFail();

    // Teacher cannot void
    $this->actingAs($guru)
        ->post(route('admin.behavior.void', $record), ['void_reason' => 'Salah pilih siswa'])
        ->assertForbidden();

    // BK can void
    $this->actingAs($bk)
        ->post(route('admin.behavior.void', $record), ['void_reason' => 'Salah pilih siswa'])
        ->assertRedirect();

    $record->refresh();
    expect($record->status)->toBe(BehaviorRecordStatus::Voided)
        ->and($record->void_reason)->toBe('Salah pilih siswa')
        ->and($record->voided_by)->toBe($bk->id);

    $ledger = app(PointLedgerService::class);
    expect($ledger->balanceFor($student->id, $year->id, PointAccount::Merit))->toBe(0);
});
