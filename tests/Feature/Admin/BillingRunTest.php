<?php

declare(strict_types=1);

use App\Enums\BillingTargetType;
use App\Enums\Feature;
use App\Enums\InvoiceStatus;
use App\Enums\Role as RoleEnum;
use App\Enums\Semester;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\BillingRun;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\FeeType;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Services\Billing\BillingRunService;
use App\Services\Features\FeatureFlags;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function enableBillingForRun(User $actor): void
{
    app(FeatureFlags::class)->update([
        Feature::Billing->value => true,
    ], $actor);
}

test('tamu ditolak, fitur nonaktif 404, dan peran tanpa izin penerbitan ditolak 403', function (): void {
    $this->get('/admin/keuangan/terbitkan')
        ->assertRedirect('/login');

    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);

    $this->actingAs($admin)
        ->get('/admin/keuangan/terbitkan')
        ->assertNotFound();

    enableBillingForRun($admin);

    $unitHead = User::factory()->create();
    $unitHead->assignRole(RoleEnum::UnitHead->value);

    $this->actingAs($unitHead)
        ->get('/admin/keuangan/terbitkan')
        ->assertForbidden();

    $treasurer = User::factory()->create();
    $treasurer->assignRole(RoleEnum::Treasurer->value);

    $this->actingAs($treasurer)
        ->get('/admin/keuangan/terbitkan')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/billing/issue'));
});

test('pratinjau dan eksekusi target rombel hanya menerbitkan untuk siswa aktif rombel itu dan menyimpan nominal per siswa', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    enableBillingForRun($admin);

    $treasurer = User::factory()->create();
    $treasurer->assignRole(RoleEnum::Treasurer->value);

    $mi = Unit::factory()->create(['code' => 'MI', 'name' => 'MI Darul Istiqamah']);
    $year = AcademicYear::factory()->active()->create([
        'name' => '2026/2027',
        'semester' => Semester::Ganjil,
        'starts_on' => '2026-07-13',
        'ends_on' => '2026-12-20',
    ]);

    $class1A = Classroom::factory()->create([
        'unit_id' => $mi->id,
        'academic_year_id' => $year->id,
        'grade' => '1',
        'name' => '1A',
    ]);
    $class1B = Classroom::factory()->create([
        'unit_id' => $mi->id,
        'academic_year_id' => $year->id,
        'grade' => '1',
        'name' => '1B',
    ]);

    $active1 = Student::factory()->create(['unit_id' => $mi->id, 'name' => 'Ahmad Aktif', 'status' => StudentStatus::Active]);
    $active2 = Student::factory()->create(['unit_id' => $mi->id, 'name' => 'Budi Keringanan', 'status' => StudentStatus::Active]);
    $inactive = Student::factory()->create(['unit_id' => $mi->id, 'name' => 'Citra Nonaktif', 'status' => StudentStatus::Inactive]);
    $otherClass = Student::factory()->create(['unit_id' => $mi->id, 'name' => 'Dina Kelas Lain', 'status' => StudentStatus::Active]);

    Enrollment::factory()->create(['student_id' => $active1->id, 'classroom_id' => $class1A->id, 'academic_year_id' => $year->id]);
    Enrollment::factory()->create(['student_id' => $active2->id, 'classroom_id' => $class1A->id, 'academic_year_id' => $year->id]);
    Enrollment::factory()->create(['student_id' => $inactive->id, 'classroom_id' => $class1A->id, 'academic_year_id' => $year->id]);
    Enrollment::factory()->create(['student_id' => $otherClass->id, 'classroom_id' => $class1B->id, 'academic_year_id' => $year->id]);

    $spp = FeeType::factory()->create([
        'unit_id' => $mi->id,
        'name' => 'SPP Bulanan MI',
        'category' => 'SPP',
        'recurrence' => 'monthly',
        'default_amount' => 150000,
    ]);

    $this->actingAs($treasurer)
        ->postJson(route('admin.billing-runs.preview'), [
            'fee_type_id' => $spp->id,
            'target_type' => BillingTargetType::Classroom->value,
            'classroom_id' => $class1A->id,
            'period_from' => '2026-07',
            'period_to' => '2026-08',
            'due_mode' => 'day',
            'due_day' => 10,
            'default_amount' => 150000,
        ])
        ->assertOk()
        ->assertJsonPath('summary.will_create', 4)
        ->assertJsonPath('summary.already_exists', 0)
        ->assertJsonPath('summary.skipped_inactive', 2);

    $this->actingAs($treasurer)
        ->post(route('admin.billing-runs.store'), [
            'fee_type_id' => $spp->id,
            'target_type' => BillingTargetType::Classroom->value,
            'classroom_id' => $class1A->id,
            'period_from' => '2026-07',
            'period_to' => '2026-08',
            'due_mode' => 'day',
            'due_day' => 10,
            'default_amount' => 150000,
            'overrides' => [
                "{$active2->id}:2026-07-01" => 100000,
                "{$active2->id}:2026-08-01" => 100000,
            ],
        ])
        ->assertRedirect();

    expect(Invoice::query()->count())->toBe(4)
        ->and(Invoice::query()->where('student_id', $inactive->id)->count())->toBe(0)
        ->and(Invoice::query()->where('student_id', $otherClass->id)->count())->toBe(0);

    $budiJuly = Invoice::query()
        ->where('student_id', $active2->id)
        ->whereDate('period', '2026-07-01')
        ->firstOrFail();

    expect($budiJuly->amount)->toBe(100000)
        ->and($budiJuly->status)->toBe(InvoiceStatus::Unpaid)
        ->and($budiJuly->due_date->toDateString())->toBe('2026-07-10');

    $run = BillingRun::query()->firstOrFail();
    expect($run->invoice_count)->toBe(4)
        ->and($run->total_amount)->toBe(500000);

    expect(AuditLog::query()->where('action', 'billing_run.executed')->where('subject_id', $run->id)->exists())->toBeTrue();
});

test('terbit ulang tidak menggandakan tagihan dan tagihan sekali memakai tanggal mulai tahun ajaran aktif', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    enableBillingForRun($admin);

    $mi = Unit::factory()->create(['code' => 'MI']);
    $year = AcademicYear::factory()->active()->create([
        'name' => '2026/2027',
        'semester' => Semester::Ganjil,
        'starts_on' => '2026-07-13',
        'ends_on' => '2026-12-20',
    ]);

    $student = Student::factory()->create(['unit_id' => $mi->id, 'status' => StudentStatus::Active]);
    $onceFee = FeeType::factory()->create([
        'unit_id' => $mi->id,
        'name' => 'Uang Pangkal',
        'category' => 'Uang Pangkal',
        'recurrence' => 'once',
        'default_amount' => 750000,
    ]);

    $payload = [
        'fee_type_id' => $onceFee->id,
        'target_type' => BillingTargetType::Students->value,
        'student_ids' => [$student->id],
        'due_mode' => 'date',
        'due_date' => '2026-08-01',
        'default_amount' => 750000,
    ];

    $this->actingAs($admin)->post(route('admin.billing-runs.store'), $payload)->assertRedirect();

    $invoice = Invoice::query()->firstOrFail();
    expect($invoice->period?->toDateString())->toBe('2026-07-13')
        ->and($invoice->due_date->toDateString())->toBe('2026-08-01');

    // Preview ulang menandai sudah ada
    $this->actingAs($admin)
        ->postJson(route('admin.billing-runs.preview'), $payload)
        ->assertOk()
        ->assertJsonPath('summary.will_create', 0)
        ->assertJsonPath('summary.already_exists', 1);

    // Terbit ulang tidak menggandakan
    $this->actingAs($admin)->post(route('admin.billing-runs.store'), $payload)->assertRedirect();
    expect(Invoice::query()->count())->toBe(1);
});

test('penerbitan tagihan idempoten di MariaDB', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);

    $mi = Unit::factory()->create(['code' => 'MI']);
    $year = AcademicYear::factory()->active()->create(['starts_on' => '2026-07-13', 'ends_on' => '2026-12-20']);
    $student = Student::factory()->create(['unit_id' => $mi->id, 'status' => StudentStatus::Active]);
    $feeType = FeeType::factory()->create(['unit_id' => $mi->id, 'recurrence' => 'monthly', 'default_amount' => 100000]);

    $service = app(BillingRunService::class);
    $data = [
        'fee_type_id' => $feeType->id,
        'target_type' => BillingTargetType::Unit->value,
        'unit_id' => $mi->id,
        'period_from' => '2026-07',
        'period_to' => '2026-07',
        'due_mode' => 'day',
        'due_day' => 10,
        'default_amount' => 100000,
    ];

    $first = $service->execute($admin, $data);
    $second = $service->execute($admin, $data);

    expect($first->invoice_count)->toBe(1)
        ->and($second->invoice_count)->toBe(0)
        ->and(Invoice::query()->count())->toBe(1);
})->group('mysql');
