<?php

declare(strict_types=1);

use App\Enums\ExamSessionStatus;
use App\Enums\ExamStatus;
use App\Enums\Feature;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\Role as RoleEnum;
use App\Models\AcademicYear;
use App\Models\AttendanceDay;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\FeeType;
use App\Models\Invoice;
use App\Models\LeaveRequest;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Services\Features\FeatureFlags;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('beranda staf menampilkan ringkasan data nyata sesuai unit aktif dan izin pengguna', function (): void {
    $mi = Unit::factory()->create(['code' => 'MI', 'name' => 'Madrasah Ibtidaiyah']);
    $year = AcademicYear::factory()->active()->create();
    Classroom::factory()->for($mi)->for($year)->create(['name' => '1-A']);
    Student::factory()->for($mi)->count(2)->create();

    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dashboard')
            ->where('summary.unit_code', 'MI')
            ->where('summary.active_students', 2)
            ->where('summary.active_classrooms', 1)
            ->where('permissions.manage_students', true)
        );
});

/**
 * @return array{unit: Unit, year: AcademicYear}
 */
function dashboardSchool(): array
{
    $unit = Unit::factory()->create(['code' => 'MI', 'name' => 'Madrasah Ibtidaiyah']);
    $year = AcademicYear::factory()->active()->create();

    return ['unit' => $unit, 'year' => $year];
}

function enrollIn(Classroom $classroom, int $count): Collection
{
    return Student::factory()->count($count)->create(['unit_id' => $classroom->unit_id])
        ->each(fn (Student $student) => Enrollment::factory()->create([
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'academic_year_id' => $classroom->academic_year_id,
        ]));
}

test('beranda staf menghitung presensi hari ini per status dan siswa belum tap', function (): void {
    ['unit' => $unit, 'year' => $year] = dashboardSchool();
    $classroom = Classroom::factory()->for($unit)->for($year)->create(['name' => '2-A']);
    $students = enrollIn($classroom, 4);
    $today = CarbonImmutable::now('Asia/Makassar')->toDateString();

    AttendanceDay::query()->create(['student_id' => $students[0]->id, 'school_date' => $today, 'status' => 'present', 'decided_by' => 'scan']);
    AttendanceDay::query()->create(['student_id' => $students[1]->id, 'school_date' => $today, 'status' => 'late', 'decided_by' => 'scan']);

    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('attendance.scope', 'unit')
            ->where('attendance.total', 4)
            ->where('attendance.counts.present', 1)
            ->where('attendance.counts.late', 1)
            ->where('attendance.counts.not_yet', 2)
            ->where('attendance.classrooms.0.name', '2-A')
            ->where('attendance.classrooms.0.not_yet', 2)
            ->has('queue')
            ->has('dataGaps')
        );
});

test('wali kelas hanya melihat presensi rombel yang ia walikan beserta nama siswa belum tap', function (): void {
    ['unit' => $unit, 'year' => $year] = dashboardSchool();
    $teacher = User::factory()->create();
    $teacher->assignRole(RoleEnum::Teacher->value);

    $own = Classroom::factory()->for($unit)->for($year)->create(['name' => '2-A', 'homeroom_teacher_id' => $teacher->id]);
    $other = Classroom::factory()->for($unit)->for($year)->create(['name' => '3-A']);
    $mine = enrollIn($own, 2);
    $others = enrollIn($other, 5);

    foreach ([$mine[0], $others[0]] as $student) {
        LeaveRequest::query()->create([
            'student_id' => $student->id, 'requested_by' => $teacher->id, 'type' => 'sick',
            'starts_on' => now()->toDateString(), 'ends_on' => now()->toDateString(),
            'reason' => 'Demam', 'status' => 'pending',
        ]);
    }

    $this->actingAs($teacher)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('attendance.scope', 'homeroom')
            ->where('attendance.total', 2)
            ->has('attendance.not_yet_students', 2)
            ->where('attendance.not_yet_students.0.classroom', '2-A')
            ->where('queue', null)
            ->where('dataGaps', null)
            ->where('leaves.count', 1)
            ->where('leaves.items.0.student_name', $mine[0]->name)
        );
});

test('beranda staf menampilkan izin menunggu dan ujian yang sedang berlangsung', function (): void {
    ['unit' => $unit, 'year' => $year] = dashboardSchool();
    $classroom = Classroom::factory()->for($unit)->for($year)->create();
    $students = enrollIn($classroom, 2);
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);

    LeaveRequest::query()->create([
        'student_id' => $students[0]->id, 'requested_by' => $admin->id, 'type' => 'sick',
        'starts_on' => now()->toDateString(), 'ends_on' => now()->toDateString(),
        'reason' => 'Demam', 'status' => 'pending',
    ]);

    $exam = Exam::factory()->create([
        'status' => ExamStatus::Running,
        'opens_at' => now()->subHour(),
        'closes_at' => now()->addHour(),
    ]);
    ExamSession::factory()->create(['exam_id' => $exam->id, 'student_id' => $students[0]->id]);
    ExamSession::factory()->create(['exam_id' => $exam->id, 'student_id' => $students[1]->id, 'status' => ExamSessionStatus::Submitted]);
    Exam::factory()->create(['status' => ExamStatus::Draft, 'opens_at' => now()]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('leaves.count', 1)
            ->where('leaves.items.0.student_name', $students[0]->name)
            ->has('exams.today', 1)
            ->where('exams.today.0.id', $exam->id)
            ->where('exams.today.0.in_progress', 1)
            ->where('exams.today.0.finished', 1)
        );
});

test('bagian beranda tidak dikirim bila modulnya dimatikan atau peran tidak berizin', function (): void {
    dashboardSchool();
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    app(FeatureFlags::class)->update([
        Feature::Attendance->value => false,
        Feature::Cbt->value => false,
    ], $admin);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('attendance', null)
            ->where('leaves', null)
            ->where('exams', null)
        );

    $treasurer = User::factory()->create();
    $treasurer->assignRole(RoleEnum::Treasurer->value);

    $this->actingAs($treasurer)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('attendance', null)
            ->where('exams', null)
            ->where('dataGaps', null)
            ->where('queue', null)
            ->where('recentMutations', [])
        );
});

test('data induk yang perlu dilengkapi dihitung dari unit dan tahun ajaran terpilih', function (): void {
    ['unit' => $unit, 'year' => $year] = dashboardSchool();
    Classroom::factory()->for($unit)->for($year)->create(['homeroom_teacher_id' => null]);
    Student::factory()->count(3)->create(['unit_id' => $unit->id]);

    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('dataGaps.students_without_classroom', 3)
            ->where('dataGaps.students_without_guardian', 3)
            ->where('dataGaps.classrooms_without_homeroom', 1)
        );
});

test('tren presensi berisi hari sekolah terakhir dan melewati akhir pekan unit', function (): void {
    // Jumat 27 Nov 2026 WITA; akhir pekan bawaan Sabtu–Minggu.
    $this->travelTo(CarbonImmutable::parse('2026-11-27 09:00', 'Asia/Makassar'));
    ['unit' => $unit, 'year' => $year] = dashboardSchool();
    $classroom = Classroom::factory()->for($unit)->for($year)->create();
    $students = enrollIn($classroom, 2);

    AttendanceDay::query()->create(['student_id' => $students[0]->id, 'school_date' => '2026-11-23', 'status' => 'late', 'decided_by' => 'scan']);
    AttendanceDay::query()->create(['student_id' => $students[1]->id, 'school_date' => '2026-11-23', 'status' => 'present', 'decided_by' => 'scan']);
    AttendanceDay::query()->create(['student_id' => $students[0]->id, 'school_date' => '2026-11-22', 'status' => 'present', 'decided_by' => 'manual']);

    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('attendance.trend', 7)
            ->where('attendance.trend.6.date', '2026-11-27')
            ->where('attendance.trend.2.date', '2026-11-23')
            ->where('attendance.trend.2.late', 1)
            ->where('attendance.trend.2.present', 1)
            ->where('attendance.trend.1.date', '2026-11-20')
        );
});

test('lonceng perlu tindakan hanya memuat hitungan nyata sesuai izin dan rombel wali kelas', function (): void {
    ['unit' => $unit, 'year' => $year] = dashboardSchool();
    $teacher = User::factory()->create();
    $teacher->assignRole(RoleEnum::Teacher->value);
    $own = Classroom::factory()->for($unit)->for($year)->create(['homeroom_teacher_id' => $teacher->id]);
    $other = Classroom::factory()->for($unit)->for($year)->create();
    $mine = enrollIn($own, 1);
    $others = enrollIn($other, 2);

    foreach ([$mine[0], $others[0], $others[1]] as $student) {
        LeaveRequest::query()->create([
            'student_id' => $student->id, 'requested_by' => $teacher->id, 'type' => 'sick',
            'starts_on' => now()->toDateString(), 'ends_on' => now()->toDateString(),
            'reason' => 'Demam', 'status' => 'pending',
        ]);
    }

    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);

    $this->actingAs($teacher)->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('attention', 1)
            ->where('attention.0.key', 'leaves')
            ->where('attention.0.count', 1)
        );

    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('attention.0.count', 3));

    $treasurer = User::factory()->create();
    $treasurer->assignRole(RoleEnum::Treasurer->value);

    $this->actingAs($treasurer)->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('attention', []));
});

test('dasbor keuangan muncul saat billing atau wallet aktif untuk admin, bendahara, dan kepala unit saja', function (): void {
    ['unit' => $mi, 'year' => $year] = dashboardSchool();
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);

    // Bawaan: billing & wallet nonaktif -> foundation tampil, finance null.
    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('finance', null)
            ->has('foundation.modules', 8)
        );

    app(FeatureFlags::class)->update([
        Feature::Billing->value => true,
    ], $admin);

    foreach ([RoleEnum::Admin, RoleEnum::Treasurer, RoleEnum::UnitHead] as $role) {
        $staff = User::factory()->create();
        $staff->assignRole($role->value);

        $this->actingAs($staff)
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('foundation', null)
                ->has('finance')
                ->where('finance.billing_enabled', true)
            );
    }

    foreach ([RoleEnum::Teacher, RoleEnum::Counselor] as $role) {
        $staff = User::factory()->create();
        $staff->assignRole($role->value);

        $this->actingAs($staff)
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('finance', null)
                ->where('foundation', null)
            );
    }
});

test('dasbor keuangan menghitung kas masuk, piutang per jenis dan rombel, tunggakan teratas, dan semua unit sesuai ledger', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-28 10:00', 'Asia/Makassar'));

    $mi = Unit::factory()->create(['code' => 'MI', 'name' => 'Madrasah Ibtidaiyah']);
    $ra = Unit::factory()->create(['code' => 'RA', 'name' => 'Raudhatul Athfal']);
    $year = AcademicYear::factory()->active()->create([
        'starts_on' => '2026-07-01',
        'ends_on' => '2027-06-30',
    ]);

    $class1A = Classroom::factory()->for($mi)->for($year)->create(['name' => '1-A']);
    $class2A = Classroom::factory()->for($mi)->for($year)->create(['name' => '2-A']);
    $classRa = Classroom::factory()->for($ra)->for($year)->create(['name' => 'RA-A']);

    [$s1] = enrollIn($class1A, 1)->all();
    [$s2] = enrollIn($class2A, 1)->all();
    [$sRa] = enrollIn($classRa, 1)->all();

    $sppMi = FeeType::factory()->for($mi)->create(['name' => 'SPP MI']);
    $gedungMi = FeeType::factory()->for($mi)->create(['name' => 'Uang Gedung MI']);
    $sppRa = FeeType::factory()->for($ra)->create(['name' => 'SPP RA']);

    // Tagihan MI:
    // s1: menunggak SPP 200.000 (belum bayar) + Uang Gedung 500.000 (sebagian: diskon 50.000, bayar 150.000 -> sisa 300.000, belum jatuh tempo)
    Invoice::factory()->for($s1)->for($sppMi)->create([
        'amount' => 200_000,
        'adjustment_total' => 0,
        'paid_total' => 0,
        'due_date' => '2026-11-10',
        'status' => InvoiceStatus::Unpaid,
    ]);
    Invoice::factory()->for($s1)->for($gedungMi)->create([
        'amount' => 500_000,
        'adjustment_total' => 50_000,
        'paid_total' => 150_000,
        'due_date' => '2026-12-10',
        'status' => InvoiceStatus::Partial,
    ]);
    // s2: menunggak SPP 350.000 + dibatalkan 100.000 (harus diabaikan)
    Invoice::factory()->for($s2)->for($sppMi)->create([
        'amount' => 350_000,
        'adjustment_total' => 0,
        'paid_total' => 0,
        'due_date' => '2026-11-15',
        'status' => InvoiceStatus::Unpaid,
    ]);
    Invoice::factory()->for($s2)->for($sppMi)->create([
        'period' => '2026-10-01',
        'amount' => 100_000,
        'adjustment_total' => 0,
        'paid_total' => 0,
        'due_date' => '2026-11-10',
        'status' => InvoiceStatus::Cancelled,
    ]);
    // sRa: menunggak SPP RA 120.000
    Invoice::factory()->for($sRa)->for($sppRa)->create([
        'amount' => 120_000,
        'adjustment_total' => 0,
        'paid_total' => 0,
        'due_date' => '2026-11-20',
        'status' => InvoiceStatus::Unpaid,
    ]);

    // Pembayaran MI:
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);

    // 1. Tunai hari ini 150.000
    Payment::query()->create([
        'student_id' => $s1->id,
        'method' => PaymentMethod::Cash,
        'amount' => 150_000,
        'paid_at' => CarbonImmutable::parse('2026-11-28 08:30', 'Asia/Makassar')->utc(),
        'received_by' => $admin->id,
        'receipt_number' => 'KW-2026-000001',
        'created_at' => now(),
    ]);
    // 2. Transfer bulan ini (bukan hari ini) 250.000
    Payment::query()->create([
        'student_id' => $s2->id,
        'method' => PaymentMethod::Transfer,
        'amount' => 250_000,
        'paid_at' => CarbonImmutable::parse('2026-11-05 09:00', 'Asia/Makassar')->utc(),
        'received_by' => $admin->id,
        'receipt_number' => 'KW-2026-000002',
        'created_at' => now(),
    ]);
    // 3. Midtrans bulan lalu (tahun ajaran ini) 300.000
    Payment::query()->create([
        'student_id' => $s1->id,
        'method' => PaymentMethod::Midtrans,
        'amount' => 300_000,
        'paid_at' => CarbonImmutable::parse('2026-10-10 12:00', 'Asia/Makassar')->utc(),
        'received_by' => null,
        'receipt_number' => 'KW-2026-000003',
        'created_at' => now(),
    ]);
    // 4. Pembayaran dibalik (harus diabaikan)
    Payment::query()->create([
        'student_id' => $s1->id,
        'method' => PaymentMethod::Cash,
        'amount' => 999_000,
        'paid_at' => CarbonImmutable::parse('2026-11-28 09:00', 'Asia/Makassar')->utc(),
        'received_by' => $admin->id,
        'receipt_number' => 'KW-2026-000004',
        'reversed_at' => now(),
        'reversal_reason' => 'Salah input',
        'reversed_by' => $admin->id,
        'created_at' => now(),
    ]);
    // 5. Pembayaran RA bulan ini 100.000
    Payment::query()->create([
        'student_id' => $sRa->id,
        'method' => PaymentMethod::Cash,
        'amount' => 100_000,
        'paid_at' => CarbonImmutable::parse('2026-11-28 09:30', 'Asia/Makassar')->utc(),
        'received_by' => $admin->id,
        'receipt_number' => 'KW-2026-000005',
        'created_at' => now(),
    ]);

    app(FeatureFlags::class)->update([
        Feature::Billing->value => true,
        Feature::Wallet->value => true,
    ], $admin);

    // Scope unit terpilih (MI default karena urut kode: MI < RA)
    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('finance.scope', 'unit')
            ->where('finance.unit_code', 'MI')
            ->where('finance.cash_in.today.total', 150_000)
            ->where('finance.cash_in.today.by_method.cash', 150_000)
            ->where('finance.cash_in.month.total', 400_000)
            ->where('finance.cash_in.month.by_method.transfer', 250_000)
            ->where('finance.cash_in.academic_year.total', 700_000)
            ->where('finance.cash_in.academic_year.by_method.midtrans', 300_000)
            ->where('finance.receivables.total', 850_000)
            ->where('finance.receivables.overdue', 550_000)
            ->where('finance.receivables.overdue_count', 2)
            ->where('finance.receivables.by_fee_type.0.name', 'SPP MI')
            ->where('finance.receivables.by_fee_type.0.total', 550_000)
            ->where('finance.receivables.by_classroom.0.name', '2-A')
            ->where('finance.receivables.by_classroom.0.overdue', 350_000)
            ->where('finance.receivables.top_arrears.0.student_id', $s2->id)
            ->where('finance.receivables.top_arrears.0.overdue_amount', 350_000)
            ->where('finance.receivables.top_arrears.1.student_id', $s1->id)
            ->where('finance.receivables.top_arrears.1.overdue_amount', 200_000)
            ->has('finance.by_unit', 2)
        );

    // Scope semua unit untuk admin/bendahara
    $this->actingAs($admin)
        ->get(route('admin.dashboard', ['finance_scope' => 'all']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('finance.scope', 'all')
            ->where('finance.cash_in.today.total', 250_000)
            ->where('finance.receivables.total', 970_000)
            ->where('finance.receivables.overdue', 670_000)
        );
});
