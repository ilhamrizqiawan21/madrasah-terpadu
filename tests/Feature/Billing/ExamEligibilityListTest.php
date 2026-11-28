<?php

declare(strict_types=1);

use App\Enums\ExamDelivery;
use App\Enums\ExamRequirementScope;
use App\Enums\Feature;
use App\Enums\InvoiceStatus;
use App\Enums\Role as RoleEnum;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\ExamEligibilityOverride;
use App\Models\ExamPeriod;
use App\Models\FeeType;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Services\Features\FeatureFlags;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('daftar kelayakan menampilkan status layak, belum layak, dan dispensasi per rombel serta membatasi wali kelas ke rombelnya saja', function (): void {
    $mi = Unit::factory()->create(['code' => 'MI', 'name' => 'Madrasah Ibtidaiyah']);
    $year = AcademicYear::factory()->active()->create();

    $homeroomTeacher = User::factory()->create();
    $homeroomTeacher->assignRole(RoleEnum::Teacher->value);

    $otherTeacher = User::factory()->create();
    $otherTeacher->assignRole(RoleEnum::Teacher->value);

    $class1A = Classroom::factory()->for($mi)->for($year)->create([
        'name' => '1-A',
        'homeroom_teacher_id' => $homeroomTeacher->id,
    ]);
    $class2A = Classroom::factory()->for($mi)->for($year)->create([
        'name' => '2-A',
    ]);

    $sEligible = Student::factory()->for($mi)->create(['name' => 'Ahmad Layak', 'nis' => '1001']);
    $sIneligible = Student::factory()->for($mi)->create(['name' => 'Budi Tunggak', 'nis' => '1002']);
    $sDispensed = Student::factory()->for($mi)->create(['name' => 'Citra Dispensasi', 'nis' => '1003']);
    $sOtherClass = Student::factory()->for($mi)->create(['name' => 'Dina Kelas Lain', 'nis' => '2001']);

    foreach ([[$sEligible, $class1A], [$sIneligible, $class1A], [$sDispensed, $class1A], [$sOtherClass, $class2A]] as [$st, $cls]) {
        Enrollment::factory()->create([
            'student_id' => $st->id,
            'classroom_id' => $cls->id,
            'academic_year_id' => $year->id,
        ]);
    }

    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    app(FeatureFlags::class)->update([
        Feature::Billing->value => true,
        Feature::ExamEligibility->value => true,
    ], $admin);

    $spp = FeeType::factory()->for($mi)->create(['name' => 'SPP']);
    $period = ExamPeriod::factory()->for($mi)->active()->create([
        'name' => 'PAS Kertas Ganjil',
        'delivery' => ExamDelivery::Paper,
        'created_by' => $admin->id,
    ]);
    $period->requirements()->create([
        'fee_type_id' => $spp->id,
        'invoice_scope' => ExamRequirementScope::AllOpen,
    ]);

    Invoice::factory()->for($sEligible)->for($spp)->create(['amount' => 100_000, 'paid_total' => 100_000, 'status' => InvoiceStatus::Paid]);
    Invoice::factory()->for($sIneligible)->for($spp)->create(['amount' => 150_000, 'paid_total' => 0, 'status' => InvoiceStatus::Unpaid]);
    Invoice::factory()->for($sDispensed)->for($spp)->create(['amount' => 150_000, 'paid_total' => 0, 'status' => InvoiceStatus::Unpaid]);

    ExamEligibilityOverride::query()->create([
        'exam_period_id' => $period->id,
        'student_id' => $sDispensed->id,
        'reason' => 'Dispensasi admin',
        'granted_by' => $admin->id,
        'granted_at' => now(),
    ]);

    // Admin melihat semua 4 siswa
    $this->actingAs($admin)
        ->get(route('admin.exam-periods.eligibility.index', $period))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/billing/exam-eligibility')
            ->where('summary.total', 4)
            ->where('summary.eligible', 2)
            ->where('summary.ineligible', 1)
            ->where('summary.dispensed', 1)
        );

    // Wali kelas 1-A hanya melihat 3 siswa rombelnya (baca saja)
    $this->actingAs($homeroomTeacher)
        ->get(route('admin.exam-periods.eligibility.index', $period))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.total', 3)
            ->where('canGrantDispensation', false)
        );

    // Wali kelas 1-A mencoba buka rombel 2-A -> 403
    $this->actingAs($homeroomTeacher)
        ->get(route('admin.exam-periods.eligibility.index', ['examPeriod' => $period->id, 'classroom_id' => $class2A->id]))
        ->assertForbidden();

    // Guru bukan wali kelas -> 403
    $this->actingAs($otherTeacher)
        ->get(route('admin.exam-periods.eligibility.index', $period))
        ->assertForbidden();
});

test('ekspor pdf dan excel daftar kelayakan ujian menetralkan formula injection', function (): void {
    $mi = Unit::factory()->create(['code' => 'MI', 'name' => 'Madrasah Ibtidaiyah']);
    $year = AcademicYear::factory()->active()->create();
    $class1A = Classroom::factory()->for($mi)->for($year)->create(['name' => '1-A']);

    $student = Student::factory()->for($mi)->create([
        'name' => '=CMD("calc")',
        'nis' => '1009',
    ]);
    Enrollment::factory()->create([
        'student_id' => $student->id,
        'classroom_id' => $class1A->id,
        'academic_year_id' => $year->id,
    ]);

    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    app(FeatureFlags::class)->update([
        Feature::Billing->value => true,
        Feature::ExamEligibility->value => true,
    ], $admin);

    $period = ExamPeriod::factory()->for($mi)->active()->create(['created_by' => $admin->id]);

    $pdfResponse = $this->actingAs($admin)
        ->get(route('admin.exam-periods.eligibility.pdf', $period));
    $pdfResponse->assertOk();
    expect((string) $pdfResponse->getContent())->toStartWith('%PDF');

    $xlsxResponse = $this->actingAs($admin)
        ->get(route('admin.exam-periods.eligibility.excel', $period));
    $xlsxResponse->assertOk();

    $file = $xlsxResponse->getFile();
    $reader = new XlsxReader;
    $reader->open($file->getPathname());

    $cells = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $cells[] = $row->toArray();
        }
    }
    $reader->close();

    // Baris 0 = header, baris 1 = siswa -> nama berawalan '=' harus dinetralkan dengan tanda kutip tunggal
    expect($cells[1][1])->toBe("'=CMD(\"calc\")");
});
