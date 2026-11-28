<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Feature;
use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\StudentMutation;
use App\Models\Unit;
use App\Models\User;
use App\Services\Dashboard\FoundationDashboardService;
use App\Services\Dashboard\StaffDashboardService;
use App\Services\Features\FeatureFlags;
use App\Support\Navigation;
use App\Support\SchoolContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Beranda Staf: bagian disusun dari keputusan per peran (DESIGN §3), hanya bagian
 * yang izin dan kunci fiturnya terpenuhi yang dikirim (`null` = tidak ditampilkan).
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly StaffDashboardService $dashboard,
        private readonly FeatureFlags $features,
        private readonly FoundationDashboardService $foundation,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $context = SchoolContext::resolve($request->session());
        $unit = $context['unit'] !== null
            ? Unit::query()->where('code', $context['unit'])->first()
            : null;
        $yearId = $context['academicYearId'];

        $canViewStudents = $user->can(Permission::StudentsView->value);
        $canManageStudents = $user->can(Permission::StudentsManage->value);
        $canManageClassrooms = $user->can(Permission::ClassroomsManage->value);
        $canManageGuardians = $user->can(Permission::GuardiansManage->value);
        $attendanceOn = $this->features->enabled(Feature::Attendance) && $user->can(Permission::AttendanceView->value);
        $isAdmin = $user->hasRole(Role::Admin->value);
        $cbtOn = $this->features->enabled(Feature::Cbt) && $user->can(Permission::CbtView->value);
        $billingOn = $this->features->enabled(Feature::Billing);
        $walletOn = $this->features->enabled(Feature::Wallet);
        $canViewAllFinanceUnits = $isAdmin || $user->hasRole(Role::Treasurer->value);
        $canViewFinance = $canViewAllFinanceUnits || $user->hasRole(Role::UnitHead->value);
        $financeOn = $billingOn || $walletOn;
        $financeScopeAll = $canViewAllFinanceUnits && $request->query('finance_scope') === 'all';
        $financeUnit = $financeScopeAll ? null : $unit;

        return Inertia::render('admin/dashboard', [
            'summary' => [
                'unit_code' => $unit?->code,
                'unit_name' => $unit?->name,
                'academic_year' => $context['academicYear'],
                'roles' => $user->getRoleNames()
                    ->map(fn (string $name): string => Role::tryFrom($name)?->label() ?? $name)
                    ->values()
                    ->all(),
                'active_students' => $canViewStudents ? $this->activeStudents($unit) : null,
                'active_classrooms' => ($canViewStudents || $canManageClassrooms) ? $this->activeClassrooms($unit, $yearId) : null,
                'guardians' => $canManageGuardians ? $this->guardians($unit) : null,
            ],
            'attendance' => $attendanceOn ? $this->dashboard->attendanceToday($user, $unit, $yearId) : null,
            'leaves' => $attendanceOn ? $this->dashboard->pendingLeaves($user, $unit, $yearId) : null,
            'exams' => $cbtOn ? $this->dashboard->exams($user) : null,
            'dataGaps' => $canManageStudents ? $this->dashboard->dataGaps($unit, $yearId) : null,
            'queue' => $isAdmin ? $this->dashboard->queueHealth() : null,
            'heldModules' => $isAdmin ? $this->dashboard->heldModules() : [],
            // Susunan kartu dasbor yayasan aplikasi lama; digantikan oleh dasbor keuangan nyata saat billing/wallet aktif (T-617).
            'foundation' => (! $financeOn && $canViewAllFinanceUnits) ? [
                'legacy_url' => (string) config('sekolah.legacy_finance_url'),
                'modules' => [
                    ['key' => 'cash-in', 'label' => 'Kas masuk', 'detail' => 'SPP, BOS termin, penerimaan lain', 'status' => 'held'],
                    ['key' => 'cash-out', 'label' => 'Kas keluar', 'detail' => 'Operasional, gaji, belanja BOS', 'status' => 'deferred'],
                    ['key' => 'receivables', 'label' => 'Piutang SPP', 'detail' => 'Tunggakan dan capaian tagihan', 'status' => 'held'],
                    ['key' => 'wallet', 'label' => 'Dompet siswa', 'detail' => 'Saldo dan isi saldo kantin', 'status' => 'held'],
                    ['key' => 'bos', 'label' => 'Dana BOS & RKAS', 'detail' => 'Saldo, termin, SPJ BKU', 'status' => 'deferred'],
                    ['key' => 'zis', 'label' => 'Kas ZIS', 'detail' => 'Zakat, infaq, penyaluran', 'status' => 'deferred'],
                    ['key' => 'ppdb', 'label' => 'Penerimaan PPDB', 'detail' => 'Pendaftar dan konversi', 'status' => 'out_of_scope'],
                    ['key' => 'dormitory', 'label' => 'Hunian asrama', 'detail' => 'Kamar dan keterisian', 'status' => 'planned'],
                ],
            ] : null,
            'finance' => ($financeOn && $canViewFinance)
                ? $this->dashboard->finance($financeUnit, $yearId, $billingOn, $walletOn, $canViewAllFinanceUnits)
                : null,
            // Dasbor Yayasan (susunan dasbor aplikasi lama) untuk admin & bendahara: filter unit + periode sendiri.
            'yayasan' => $canViewAllFinanceUnits ? [
                ...$this->foundation->build(
                    $this->foundationUnit($request),
                    $request->string('periode')->toString(),
                    $request->string('dari')->toString() ?: null,
                    $request->string('sampai')->toString() ?: null,
                ),
                'pending_modules' => $this->pendingModules($user),
            ] : null,
            'security' => $isAdmin ? $this->dashboard->security() : null,
            'units' => $isAdmin && $attendanceOn ? $this->dashboard->unitComparison($yearId) : null,
            'recentMutations' => $canManageStudents ? $this->recentMutations($unit) : [],
            'permissions' => [
                'view_students' => $canViewStudents,
                'manage_students' => $canManageStudents,
                'manage_classrooms' => $canManageClassrooms,
                'manage_guardians' => $canManageGuardians,
                'import_students' => $canManageStudents && $canManageGuardians,
                'manage_attendance' => $attendanceOn && $user->can(Permission::AttendanceManage->value),
                'manage_cbt' => $cbtOn && $user->can(Permission::CbtManage->value),
            ],
        ]);
    }

    private function foundationUnit(Request $request): ?Unit
    {
        $code = $request->string('unit')->toString();

        return $code === '' || $code === 'all'
            ? null
            : Unit::query()->where('code', $code)->where('is_active', true)->first();
    }

    /**
     * Komponen dasbor lama yang modulnya belum dibangun; tautan hanya bila pengguna boleh membuka halaman "Segera"-nya.
     *
     * @return list<array{key: string, label: string, detail: string, href: ?string}>
     */
    private function pendingModules(User $user): array
    {
        $modules = [
            ['cash_out', 'Kas keluar', 'Operasional, gaji, dan belanja per unit', 'buku-kas'],
            ['bos', 'Dana BOS & RKAS', 'Saldo kas BOS, termin, dan SPJ', 'bos-rkas'],
            ['zis', 'Kas ZIS', 'Zakat, infak, dan penyalurannya', 'zis'],
            ['ppdb', 'Penerimaan PPDB', 'Pendaftar dan yang menjadi siswa', 'ppdb-pendaftar'],
            ['dormitory', 'Hunian asrama', 'Keterisian kamar asrama', 'asrama'],
            ['assets', 'Aset & perpustakaan', 'Kondisi aset dan buku terlambat', 'inventaris'],
        ];

        return array_map(function (array $m) use ($user): array {
            $item = Navigation::soonItem($m[3]);
            $allowed = $item !== null
                && $user->canAny(explode('|', $item['permission']))
                && ($item['feature'] === null || $this->features->enabled($item['feature']));

            return [
                'key' => $m[0],
                'label' => $m[1],
                'detail' => $m[2],
                'href' => $allowed ? route('admin.soon', ['module' => $m[3]], absolute: false) : null,
            ];
        }, $modules);
    }

    private function activeStudents(?Unit $unit): int
    {
        return Student::query()
            ->where('status', StudentStatus::Active)
            ->when($unit !== null, fn (Builder $q) => $q->where('unit_id', $unit->id))
            ->count();
    }

    private function activeClassrooms(?Unit $unit, ?int $yearId): int
    {
        return Classroom::query()
            ->where('is_active', true)
            ->when($unit !== null, fn (Builder $q) => $q->where('unit_id', $unit->id))
            ->when($yearId !== null, fn (Builder $q) => $q->where('academic_year_id', $yearId))
            ->count();
    }

    private function guardians(?Unit $unit): int
    {
        return Guardian::query()
            ->when($unit !== null, fn (Builder $q) => $q->whereHas('students', fn (Builder $s) => $s->where('unit_id', $unit->id)))
            ->count();
    }

    /**
     * @return list<array{id: int, type: array{value: string, label: string}, mutated_on: string, reason: string, student_name: string, student_nis: string, unit_code: string}>
     */
    private function recentMutations(?Unit $unit): array
    {
        return array_values(StudentMutation::query()
            ->with(['student.unit'])
            ->when($unit !== null, fn (Builder $q) => $q->whereHas('student', fn (Builder $s) => $s->where('unit_id', $unit->id)))
            ->orderByDesc('mutated_on')
            ->orderByDesc('id')
            ->limit(4)
            ->get()
            ->map(fn (StudentMutation $mutation): array => [
                'id' => $mutation->id,
                'type' => ['value' => $mutation->type->value, 'label' => $mutation->type->label()],
                'mutated_on' => $mutation->mutated_on->toDateString(),
                'reason' => $mutation->reason,
                'student_name' => $mutation->student->name,
                'student_nis' => $mutation->student->nis,
                'unit_code' => $mutation->student->unit->code,
            ])
            ->all());
    }
}
