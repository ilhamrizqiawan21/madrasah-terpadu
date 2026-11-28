<?php

declare(strict_types=1);

use App\Enums\ExamRequirementScope;
use App\Enums\Feature;
use App\Enums\InvoiceStatus;
use App\Enums\Role as RoleEnum;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamEligibilityOverride;
use App\Models\ExamPeriod;
use App\Models\FeeType;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\StudentExamPin;
use App\Models\Subject;
use App\Models\Unit;
use App\Models\User;
use App\Services\Cbt\ExamSessionService;
use App\Services\Features\FeatureFlags;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('hanya admin yang dapat memberi atau mencabut dispensasi ujian; bendahara dan guru ditolak 403', function (): void {
    $mi = Unit::factory()->create(['code' => 'MI', 'name' => 'Madrasah Ibtidaiyah']);
    $student = Student::factory()->for($mi)->create();

    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    app(FeatureFlags::class)->update([
        Feature::Billing->value => true,
        Feature::ExamEligibility->value => true,
    ], $admin);

    $period = ExamPeriod::factory()->for($mi)->active()->create(['created_by' => $admin->id]);

    $treasurer = User::factory()->create();
    $treasurer->assignRole(RoleEnum::Treasurer->value);

    $teacher = User::factory()->create();
    $teacher->assignRole(RoleEnum::Teacher->value);

    $this->actingAs($treasurer)
        ->post(route('admin.exam-periods.dispensations.store', $period), [
            'student_id' => $student->id,
            'reason' => 'Janji bayar pekan depan',
        ])
        ->assertForbidden();

    $this->actingAs($teacher)
        ->post(route('admin.exam-periods.dispensations.store', $period), [
            'student_id' => $student->id,
            'reason' => 'Janji bayar pekan depan',
        ])
        ->assertForbidden();

    $this->actingAs($admin)
        ->post(route('admin.exam-periods.dispensations.store', $period), [
            'student_id' => $student->id,
            'reason' => 'Surat pernyataan orang tua',
        ])
        ->assertRedirect();

    $override = ExamEligibilityOverride::query()->first();
    expect($override)->not->toBeNull();

    $this->actingAs($treasurer)
        ->post(route('admin.exam-periods.dispensations.revoke', $override))
        ->assertForbidden();
});

test('dispensasi melayakkan siswa tanpa mengubah tagihan dan pencabutan mengembalikan status tidak layak', function (): void {
    $mi = Unit::factory()->create(['code' => 'MI', 'name' => 'Madrasah Ibtidaiyah']);
    $year = AcademicYear::factory()->active()->create();
    $classroom = Classroom::factory()->for($mi)->for($year)->create();
    $student = Student::factory()->for($mi)->create(['nis' => '2026099']);
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

    $spp = FeeType::factory()->for($mi)->create();
    $period = ExamPeriod::factory()->for($mi)->active()->create(['created_by' => $admin->id]);
    $period->requirements()->create([
        'fee_type_id' => $spp->id,
        'invoice_scope' => ExamRequirementScope::AllOpen,
    ]);

    $invoice = Invoice::factory()->for($student)->for($spp)->create([
        'amount' => 200_000,
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

    // Belum layak sebelum dispensasi
    expect(fn () => $sessionService->startOrResume($exam, $student, 'lab', null))
        ->toThrow(Exception::class, 'Hubungi admin sekolah.');

    // Admin beri dispensasi
    $this->actingAs($admin)
        ->post(route('admin.exam-periods.dispensations.store', $period), [
            'student_id' => $student->id,
            'reason' => 'Dispensasi Kepala Madrasah',
        ])
        ->assertRedirect();

    $override = ExamEligibilityOverride::query()->where('student_id', $student->id)->firstOrFail();

    // Tagihan tidak berubah
    expect($invoice->fresh()?->status)->toBe(InvoiceStatus::Unpaid)
        ->and($invoice->fresh()?->paid_total)->toBe(0)
        ->and(AuditLog::query()->where('action', 'exam_dispensation.granted')->exists())->toBeTrue();

    // Sekarang layak
    $session = $sessionService->startOrResume($exam, $student, 'lab', null);
    expect($session->student_id)->toBe($student->id);

    // Hapus sesi untuk menguji kembali setelah dispensasi dicabut
    $session->delete();

    $this->actingAs($admin)
        ->post(route('admin.exam-periods.dispensations.revoke', $override))
        ->assertRedirect();

    expect($override->fresh()?->revoked_at)->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'exam_dispensation.revoked')->exists())->toBeTrue();

    // Dicabut -> tidak layak lagi
    expect(fn () => $sessionService->startOrResume($exam, $student, 'lab', null))
        ->toThrow(Exception::class, 'Hubungi admin sekolah.');
});
