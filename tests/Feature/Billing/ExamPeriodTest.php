<?php

declare(strict_types=1);

use App\Enums\ExamDelivery;
use App\Enums\ExamPeriodStatus;
use App\Enums\ExamRequirementScope;
use App\Enums\Feature;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\Role as RoleEnum;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamPeriod;
use App\Models\FeeType;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\StudentExamPin;
use App\Models\Subject;
use App\Models\Unit;
use App\Models\User;
use App\Services\Billing\ManualPaymentService;
use App\Services\Cbt\ExamSessionService;
use App\Services\Features\FeatureFlags;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('rute kelayakan ujian ditolak 404 saat fitur nonaktif dan hanya bisa diakses peran berizin', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);

    $this->actingAs($admin)
        ->get(route('admin.exam-periods.index'))
        ->assertNotFound();

    app(FeatureFlags::class)->update([
        Feature::Billing->value => true,
        Feature::ExamEligibility->value => true,
    ], $admin);

    $this->actingAs($admin)
        ->get(route('admin.exam-periods.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/billing/exam-periods'));

    $treasurer = User::factory()->create();
    $treasurer->assignRole(RoleEnum::Treasurer->value);
    $this->actingAs($treasurer)->get(route('admin.exam-periods.index'))->assertOk();

    $unitHead = User::factory()->create();
    $unitHead->assignRole(RoleEnum::UnitHead->value);
    $this->actingAs($unitHead)->get(route('admin.exam-periods.index'))->assertOk();

    $teacher = User::factory()->create();
    $teacher->assignRole(RoleEnum::Teacher->value);
    $this->actingAs($teacher)->get(route('admin.exam-periods.index'))->assertForbidden();
});

test('bendahara dan admin dapat membuat, mengubah, mengaktifkan, dan menutup periode ujian beserta syarat tagihan', function (): void {
    $mi = Unit::factory()->create(['code' => 'MI', 'name' => 'Madrasah Ibtidaiyah']);
    $spp = FeeType::factory()->for($mi)->create(['name' => 'SPP MI']);

    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    app(FeatureFlags::class)->update([
        Feature::Billing->value => true,
        Feature::ExamEligibility->value => true,
    ], $admin);

    $treasurer = User::factory()->create();
    $treasurer->assignRole(RoleEnum::Treasurer->value);

    $this->actingAs($treasurer)
        ->post(route('admin.exam-periods.store'), [
            'unit_id' => $mi->id,
            'name' => 'PAS Ganjil 2026',
            'delivery' => ExamDelivery::Cbt->value,
            'starts_on' => '2026-12-01',
            'ends_on' => '2026-12-07',
            'requirements' => [
                [
                    'fee_type_id' => $spp->id,
                    'invoice_scope' => ExamRequirementScope::UpToPeriod->value,
                    'period_until' => '2026-11-01',
                ],
            ],
        ])
        ->assertRedirect();

    $period = ExamPeriod::query()->where('name', 'PAS Ganjil 2026')->first();
    expect($period)->not->toBeNull()
        ->and($period?->status)->toBe(ExamPeriodStatus::Draft)
        ->and($period?->requirements)->toHaveCount(1)
        ->and($period?->requirements->first()?->period_until?->toDateString())->toBe('2026-11-01');

    expect(AuditLog::query()->where('action', 'exam_period.created')->exists())->toBeTrue();

    $this->actingAs($treasurer)
        ->patch(route('admin.exam-periods.activate', $period))
        ->assertRedirect();

    expect($period?->fresh()?->status)->toBe(ExamPeriodStatus::Active);

    $this->actingAs($treasurer)
        ->patch(route('admin.exam-periods.close', $period))
        ->assertRedirect();

    expect($period?->fresh()?->status)->toBe(ExamPeriodStatus::Closed);
});

test('pembayaran tagihan masuk langsung melayakkan siswa untuk memulai sesi ujian CBT', function (): void {
    $mi = Unit::factory()->create(['code' => 'MI', 'name' => 'Madrasah Ibtidaiyah']);
    $year = AcademicYear::factory()->active()->create();
    $classroom = Classroom::factory()->for($mi)->for($year)->create(['name' => '6-A']);
    $student = Student::factory()->for($mi)->create(['nis' => '2026001']);
    Enrollment::factory()->create([
        'student_id' => $student->id,
        'classroom_id' => $classroom->id,
        'academic_year_id' => $year->id,
    ]);
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    app(FeatureFlags::class)->update([
        Feature::Billing->value => true,
        Feature::ExamEligibility->value => true,
        Feature::Cbt->value => true,
    ], $admin);

    StudentExamPin::query()->create([
        'student_id' => $student->id,
        'pin_hash' => bcrypt('123456'),
        'reset_by' => $admin->id,
        'reset_at' => now(),
    ]);

    $spp = FeeType::factory()->for($mi)->create(['name' => 'SPP MI']);
    $period = ExamPeriod::factory()->for($mi)->active()->create(['created_by' => $admin->id]);
    $period->requirements()->create([
        'fee_type_id' => $spp->id,
        'invoice_scope' => ExamRequirementScope::UpToPeriod,
        'period_until' => '2026-11-01',
    ]);

    $invoice = Invoice::factory()->for($student)->for($spp)->create([
        'period' => '2026-11-01',
        'amount' => 150_000,
        'adjustment_total' => 0,
        'paid_total' => 0,
        'status' => InvoiceStatus::Unpaid,
    ]);

    $subject = Subject::factory()->for($mi)->create();
    $exam = Exam::factory()->create([
        'exam_period_id' => $period->id,
        'subject_id' => $subject->id,
        'classroom_ids' => [$classroom->id],
        'status' => 'running',
        'opens_at' => now()->subMinute(),
        'closes_at' => now()->addHour(),
        'device_modes' => ['lab'],
        'created_by' => $admin->id,
    ]);

    $sessionService = app(ExamSessionService::class);

    expect(fn () => $sessionService->startOrResume($exam, $student, 'lab', null))
        ->toThrow(Exception::class, 'Hubungi admin sekolah.');

    // Bayar sebagian -> tetap belum layak
    app(ManualPaymentService::class)->receive(
        $admin,
        $student,
        PaymentMethod::Cash,
        [['invoice_id' => $invoice->id, 'amount' => 100_000]],
    );

    expect(fn () => $sessionService->startOrResume($exam, $student, 'lab', null))
        ->toThrow(Exception::class, 'Hubungi admin sekolah.');

    // Lunasi sisa -> langsung layak
    app(ManualPaymentService::class)->receive(
        $admin,
        $student,
        PaymentMethod::Cash,
        [['invoice_id' => $invoice->id, 'amount' => 50_000]],
    );

    $session = $sessionService->startOrResume($exam, $student, 'lab', null);
    expect($session->student_id)->toBe($student->id);
});
