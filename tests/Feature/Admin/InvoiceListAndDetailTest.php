<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\InvoiceStatus;
use App\Enums\Role as RoleEnum;
use App\Enums\Semester;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\FeeType;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Services\Features\FeatureFlags;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function enableBillingForInvoices(User $actor): void
{
    app(FeatureFlags::class)->update([
        Feature::Billing->value => true,
    ], $actor);
}

test('tamu ditolak, fitur nonaktif 404, dan peran tanpa izin tagihan ditolak 403', function (): void {
    $this->get('/admin/keuangan/tagihan')
        ->assertRedirect('/login');

    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);

    $this->actingAs($admin)
        ->get('/admin/keuangan/tagihan')
        ->assertNotFound();

    enableBillingForInvoices($admin);

    $teacher = User::factory()->create();
    $teacher->assignRole(RoleEnum::Teacher->value);

    $this->actingAs($teacher)
        ->get('/admin/keuangan/tagihan')
        ->assertForbidden();

    $unitHead = User::factory()->create();
    $unitHead->assignRole(RoleEnum::UnitHead->value);

    $this->actingAs($unitHead)
        ->get('/admin/keuangan/tagihan')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/billing/invoices'));
});

test('filter menunggak bekerja akurat pada batas pergantian hari WITA dan mengabaikan lunas atau dibatalkan', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    enableBillingForInvoices($admin);

    $mi = Unit::factory()->create(['code' => 'MI']);
    $student = Student::factory()->create(['unit_id' => $mi->id, 'name' => 'Zaidan']);
    $spp = FeeType::factory()->create(['unit_id' => $mi->id, 'name' => 'SPP']);
    $kegiatan = FeeType::factory()->create(['unit_id' => $mi->id, 'name' => 'Kegiatan']);
    $buku = FeeType::factory()->create(['unit_id' => $mi->id, 'name' => 'Buku']);

    $unpaidDue = Invoice::factory()->create([
        'student_id' => $student->id,
        'fee_type_id' => $spp->id,
        'period' => '2026-11-01',
        'due_date' => '2026-11-10',
        'status' => InvoiceStatus::Unpaid,
    ]);

    Invoice::factory()->create([
        'student_id' => $student->id,
        'fee_type_id' => $kegiatan->id,
        'period' => '2026-11-01',
        'due_date' => '2026-11-10',
        'paid_total' => 150000,
        'status' => InvoiceStatus::Paid,
    ]);

    Invoice::factory()->create([
        'student_id' => $student->id,
        'fee_type_id' => $buku->id,
        'period' => '2026-11-01',
        'due_date' => '2026-11-10',
        'status' => InvoiceStatus::Cancelled,
    ]);

    // 23:59 WITA pada tanggal jatuh tempo: belum menunggak
    $this->travelTo(now('Asia/Makassar')->setDate(2026, 11, 10)->setTime(23, 59, 0));

    $this->actingAs($admin)
        ->get(route('admin.invoices.index', ['status' => 'overdue']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('invoices.data', 0));

    // 00:01 WITA keesokan harinya: unpaidDue menjadi menunggak
    $this->travelTo(now('Asia/Makassar')->setDate(2026, 11, 11)->setTime(0, 1, 0));

    $this->actingAs($admin)
        ->get(route('admin.invoices.index', ['status' => 'overdue']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('invoices.data', 1)
            ->where('invoices.data.0.id', $unpaidDue->id)
            ->where('invoices.data.0.is_overdue', true));
});

test('filter unit, rombel, jenis tagihan, periode, dan pencarian siswa berfungsi dengan benar', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    enableBillingForInvoices($admin);

    $mi = Unit::factory()->create(['code' => 'MI']);
    $ra = Unit::factory()->create(['code' => 'RA']);
    $year = AcademicYear::factory()->active()->create(['name' => '2026/2027', 'semester' => Semester::Ganjil]);
    $class1A = Classroom::factory()->create(['unit_id' => $mi->id, 'academic_year_id' => $year->id, 'name' => '1A']);

    $s1 = Student::factory()->create(['unit_id' => $mi->id, 'nis' => '2026001', 'name' => 'Fauzan']);
    $s2 = Student::factory()->create(['unit_id' => $ra->id, 'nis' => '2026002', 'name' => 'Hana']);
    Enrollment::factory()->create(['student_id' => $s1->id, 'classroom_id' => $class1A->id, 'academic_year_id' => $year->id]);

    $spp = FeeType::factory()->create(['unit_id' => $mi->id, 'name' => 'SPP MI']);
    $i1 = Invoice::factory()->create([
        'student_id' => $s1->id,
        'fee_type_id' => $spp->id,
        'period' => '2026-08-01',
        'due_date' => '2026-08-10',
    ]);
    Invoice::factory()->create([
        'student_id' => $s2->id,
        'fee_type_id' => $spp->id,
        'period' => '2026-09-01',
        'due_date' => '2026-09-10',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.invoices.index', [
            'unit_id' => $mi->id,
            'classroom_id' => $class1A->id,
            'fee_type_id' => $spp->id,
            'period' => '2026-08',
            'search' => '2026001',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('invoices.data', 1)
            ->where('invoices.data.0.id', $i1->id));
});

test('detail tagihan dan kartu tagihan siswa menghitung total, terbayar, dan sisa dengan benar', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    enableBillingForInvoices($admin);

    $mi = Unit::factory()->create(['code' => 'MI']);
    $student = Student::factory()->create(['unit_id' => $mi->id, 'name' => 'Salma']);
    $f1 = FeeType::factory()->create(['unit_id' => $mi->id]);
    $f2 = FeeType::factory()->create(['unit_id' => $mi->id]);
    $f3 = FeeType::factory()->create(['unit_id' => $mi->id]);

    $inv1 = Invoice::factory()->create([
        'student_id' => $student->id,
        'fee_type_id' => $f1->id,
        'amount' => 200000,
        'adjustment_total' => 50000,
        'paid_total' => 100000,
        'status' => InvoiceStatus::Partial,
    ]);

    Invoice::factory()->create([
        'student_id' => $student->id,
        'fee_type_id' => $f2->id,
        'amount' => 150000,
        'adjustment_total' => 0,
        'paid_total' => 150000,
        'status' => InvoiceStatus::Paid,
    ]);

    // Dibatalkan tidak ikut total kartu tagihan
    Invoice::factory()->create([
        'student_id' => $student->id,
        'fee_type_id' => $f3->id,
        'amount' => 300000,
        'status' => InvoiceStatus::Cancelled,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.invoices.show', $inv1))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/billing/invoice-show')
            ->where('invoice.id', $inv1->id)
            ->where('invoice.remaining', 50000));

    $this->actingAs($admin)
        ->get(route('admin.students.invoices', $student))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/billing/student-card')
            ->where('summary.total_billed', 300000)
            ->where('summary.total_paid', 250000)
            ->where('summary.total_remaining', 50000)
            ->has('invoices', 3));
});
