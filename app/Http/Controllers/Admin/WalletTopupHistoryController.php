<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Enums\StudentStatus;
use App\Enums\WalletEntryType;
use App\Exceptions\Wallet\InsufficientBalanceException;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Models\WalletEntry;
use App\Notifications\WalletTopupCancelledNotification;
use App\Services\Notifications\GuardianNotifier;
use App\Services\Reports\ReportExportService;
use App\Services\Wallet\WalletService;
use App\Support\AppTimezone;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Kantin > Riwayat Isi Saldo: jurnal isi saldo tunai & online, setor tunai, pembatalan, rekap.
 */
class WalletTopupHistoryController extends Controller
{
    public const array PERIODS = [
        'today' => 'Hari ini',
        'yesterday' => 'Kemarin',
        'this_week' => 'Minggu ini',
        'this_month' => 'Bulan ini',
        'last_month' => 'Bulan lalu',
        'all' => 'Semua waktu',
        'custom' => 'Rentang tanggal',
    ];

    private const string CASH_PREFIX = 'topup-cash:';

    private const string CANCEL_PREFIX = 'topup-cancel:';

    public function __construct(
        private readonly WalletService $wallets,
        private readonly GuardianNotifier $notifier,
        private readonly ReportExportService $exports,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $user = $this->actor($request);

        $paginator = $this->query($filters)
            ->with(['wallet.student:id,nis,name,unit_id', 'wallet.student.unit:id,code', 'creator:id,name'])
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(50)->withQueryString();

        $entries = $paginator->items();
        $cancellations = $this->cancellations(array_map(static fn (WalletEntry $e): int => $e->id, $entries));
        $summary = $this->summary($filters);
        $yearId = AcademicYear::query()->where('is_active', true)->value('id');

        return Inertia::render('admin/wallets/topups', [
            'rows' => [
                'data' => array_values(array_map(fn (WalletEntry $e): array => $this->row($e, $cancellations), $entries)),
                'meta' => [
                    'current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(), 'from' => $paginator->firstItem(), 'to' => $paginator->lastItem(),
                ],
            ],
            'summary' => $summary,
            'filters' => [
                'period' => $filters['period'], 'from' => $filters['fromInput'], 'to' => $filters['toInput'],
                'unit' => $filters['unit'], 'classroom' => $filters['classroom'], 'method' => $filters['method'], 'search' => $filters['search'],
            ],
            'periodLabel' => $filters['label'],
            'periodOptions' => array_map(fn (string $k, string $v): array => ['value' => $k, 'label' => $v], array_keys(self::PERIODS), self::PERIODS),
            'unitOptions' => array_values(Unit::query()->where('is_active', true)->orderBy('id')->get(['id', 'name'])
                ->map(fn (Unit $u): array => ['value' => (string) $u->id, 'label' => $u->name])->all()),
            'classroomOptions' => $yearId === null ? [] : array_values(Classroom::query()->where('academic_year_id', $yearId)->where('is_active', true)
                ->when($filters['unit'] !== null, fn (Builder $q) => $q->where('unit_id', $filters['unit']))
                ->orderBy('unit_id')->orderBy('grade')->orderBy('name')->get(['id', 'name'])
                ->map(fn (Classroom $c): array => ['value' => (string) $c->id, 'label' => $c->name])->all()),
            // Untuk dialog setor tunai: siswa aktif beserta rombel tahun aktif agar bisa disaring per rombel.
            'students' => $this->studentPicker($yearId),
            'limits' => [
                'min_top_up' => (int) config('sekolah.wallet.min_top_up', 10_000),
                'max_top_up' => (int) config('sekolah.wallet.max_top_up', 1_000_000),
            ],
            'canTopup' => $user->can(Permission::WalletTopup->value),
            'canCancel' => $user->can(Permission::WalletManage->value),
        ]);
    }

    public function cancel(Request $request, WalletEntry $entry): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:200']], [], ['reason' => 'alasan pembatalan']);
        $user = $this->actor($request);
        $alreadyCancelled = WalletEntry::query()->where('idempotency_key', self::CANCEL_PREFIX.$entry->id)->exists();

        try {
            $reversal = $this->wallets->cancelCashTopUp($entry, $user, (string) $data['reason']);
        } catch (InsufficientBalanceException) {
            throw ValidationException::withMessages([
                'reason' => 'Saldo siswa sudah terpakai sehingga isi saldo ini tidak bisa dibatalkan penuh. Gunakan penyesuaian saldo bila perlu.',
            ]);
        }

        if (! $alreadyCancelled) {
            $student = Student::query()->findOrFail((int) $entry->wallet()->value('student_id'));
            $this->notifier->notifyStudentGuardians($student, new WalletTopupCancelledNotification(
                entryId: $entry->id,
                studentName: $student->nickname ?? $student->name,
                amount: $entry->amount,
                balanceAfter: $reversal->balance_after,
            ));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Isi saldo dibatalkan dan wali diberi tahu.']);

        return back();
    }

    public function exportPdf(Request $request): HttpResponse
    {
        [$headers, $rows, $subtitle] = $this->exportData($request);

        return $this->exports->toPdfResponse('rekap-isi-saldo.pdf', 'Rekap Isi Saldo Dompet Kantin', $subtitle, $headers, $rows);
    }

    public function exportExcel(Request $request): BinaryFileResponse
    {
        [$headers, $rows] = $this->exportData($request);

        return $this->exports->toXlsxResponse('rekap-isi-saldo.xlsx', $headers, $rows);
    }

    /**
     * @return array{0: list<string>, 1: list<list<string|int|null>>, 2: string}
     */
    private function exportData(Request $request): array
    {
        $filters = $this->filters($request);
        $entries = $this->query($filters)
            ->with(['wallet.student:id,nis,name,unit_id', 'wallet.student.unit:id,code', 'creator:id,name'])
            ->orderBy('created_at')->orderBy('id')
            ->limit(5000)->get();
        $cancellations = $this->cancellations($entries->pluck('id')->map(fn (mixed $id): int => (int) $id)->all());
        $summary = $this->summary($filters);

        $rows = [];
        foreach ($entries as $e) {
            $student = $e->wallet->student;
            $cash = str_starts_with($e->idempotency_key, self::CASH_PREFIX);
            $rows[] = [
                $e->created_at->timezone(AppTimezone::name())->format('d/m/Y H.i'),
                $student->nis,
                $student->name,
                $student->unit->code,
                $cash ? 'Tunai di loket' : 'Online (Midtrans)',
                $e->creator !== null ? $e->creator->name : 'Sistem',
                $e->amount,
                isset($cancellations[$e->id]) ? 'Dibatalkan: '.$cancellations[$e->id] : 'Masuk',
            ];
        }

        $subtitle = "{$filters['label']} · Masuk ".Money::format($summary['net']).' ('.$summary['count'].' transaksi; tunai '
            .Money::format($summary['cash']).', online '.Money::format($summary['online']).')';

        return [['Waktu (WITA)', 'NIS', 'Nama siswa', 'Unit', 'Metode', 'Petugas', 'Nominal (Rp)', 'Status'], $rows, $subtitle];
    }

    /**
     * @return array{period: string, label: string, start: ?CarbonImmutable, end: ?CarbonImmutable, fromInput: ?string, toInput: ?string, unit: ?int, classroom: ?int, method: ?string, search: string}
     */
    private function filters(Request $request): array
    {
        $tz = AppTimezone::name();
        $now = CarbonImmutable::now($tz);
        $period = array_key_exists($request->string('period')->toString(), self::PERIODS) ? $request->string('period')->toString() : 'today';
        $fromInput = $request->date('from')?->toDateString();
        $toInput = $request->date('to')?->toDateString();

        [$start, $end] = match ($period) {
            'today' => [$now->startOfDay(), $now->endOfDay()],
            'yesterday' => [$now->subDay()->startOfDay(), $now->subDay()->endOfDay()],
            'this_week' => [$now->startOfWeek(CarbonImmutable::MONDAY), $now->endOfDay()],
            'this_month' => [$now->startOfMonth(), $now->endOfDay()],
            'last_month' => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            'custom' => [
                $fromInput !== null ? CarbonImmutable::parse($fromInput, $tz)->startOfDay() : null,
                $toInput !== null ? CarbonImmutable::parse($toInput, $tz)->endOfDay() : null,
            ],
            default => [null, null],
        };

        $label = match ($period) {
            'custom' => 'Rentang '.($start?->format('d/m/Y') ?? '…').' – '.($end?->format('d/m/Y') ?? '…'),
            'all' => 'Semua waktu',
            default => self::PERIODS[$period].' ('.$start?->format('d/m/Y').($start?->isSameDay($end) ? '' : ' – '.$end?->format('d/m/Y')).')',
        };

        $method = in_array($request->string('method')->toString(), ['cash', 'online'], true) ? $request->string('method')->toString() : null;

        return [
            'period' => $period, 'label' => $label, 'start' => $start, 'end' => $end,
            'fromInput' => $fromInput, 'toInput' => $toInput,
            'unit' => $request->integer('unit') ?: null, 'classroom' => $request->integer('classroom') ?: null,
            'method' => $method, 'search' => trim($request->string('search')->toString()),
        ];
    }

    /**
     * @param  array{start: ?CarbonImmutable, end: ?CarbonImmutable, unit: ?int, classroom: ?int, method: ?string, search: string}  $f
     * @return Builder<WalletEntry>
     */
    private function query(array $f): Builder
    {
        $yearId = $f['classroom'] === null ? null : AcademicYear::query()->where('is_active', true)->value('id');

        return WalletEntry::query()
            ->where('type', WalletEntryType::Topup->value)
            ->when($f['start'] !== null, fn (Builder $q) => $q->where('created_at', '>=', $f['start']))
            ->when($f['end'] !== null, fn (Builder $q) => $q->where('created_at', '<=', $f['end']))
            ->when($f['method'] === 'cash', fn (Builder $q) => $q->where('idempotency_key', 'like', self::CASH_PREFIX.'%'))
            ->when($f['method'] === 'online', fn (Builder $q) => $q->where('idempotency_key', 'not like', self::CASH_PREFIX.'%'))
            ->when($f['unit'] !== null || $f['search'] !== '' || $f['classroom'] !== null, fn (Builder $q) => $q->whereHas('wallet.student', function (Builder $s) use ($f, $yearId): void {
                $s->when($f['unit'] !== null, fn (Builder $x) => $x->where('unit_id', $f['unit']))
                    ->when($f['search'] !== '', fn (Builder $x) => $x->where(fn (Builder $y) => $y->whereLike('name', "%{$f['search']}%")->orWhereLike('nis', "%{$f['search']}%")))
                    ->when($f['classroom'] !== null, fn (Builder $x) => $x->whereHas('enrollments', fn (Builder $e) => $e->where('classroom_id', $f['classroom'])->where('academic_year_id', $yearId)));
            }));
    }

    /**
     * @param  array{start: ?CarbonImmutable, end: ?CarbonImmutable, unit: ?int, classroom: ?int, method: ?string, search: string}  $f
     * @return array{count: int, gross: int, cash: int, online: int, cancelled: int, net: int}
     */
    private function summary(array $f): array
    {
        $rows = $this->query($f)->get(['id', 'amount', 'idempotency_key']);
        $cancelled = $this->cancellations($rows->pluck('id')->map(fn (mixed $id): int => (int) $id)->all());
        $cancelledTotal = (int) $rows->filter(fn (WalletEntry $e): bool => isset($cancelled[$e->id]))->sum('amount');
        $active = $rows->reject(fn (WalletEntry $e): bool => isset($cancelled[$e->id]));
        $cash = (int) $active->filter(fn (WalletEntry $e): bool => str_starts_with($e->idempotency_key, self::CASH_PREFIX))->sum('amount');
        $gross = (int) $rows->sum('amount');

        return [
            'count' => $active->count(), 'gross' => $gross, 'cash' => $cash,
            'online' => $gross - $cancelledTotal - $cash, 'cancelled' => $cancelledTotal, 'net' => $gross - $cancelledTotal,
        ];
    }

    /**
     * @param  array<int>  $topUpIds  urutan tidak penting
     * @return array<int, string> top-up id => alasan pembatalan
     */
    private function cancellations(array $topUpIds): array
    {
        if ($topUpIds === []) {
            return [];
        }

        $result = [];
        foreach (array_chunk($topUpIds, 500) as $chunk) {
            WalletEntry::query()
                ->where('type', WalletEntryType::Reversal->value)
                ->where('reference_type', WalletEntry::class)
                ->whereIn('reference_id', $chunk)
                ->where('idempotency_key', 'like', self::CANCEL_PREFIX.'%')
                ->get(['reference_id', 'description'])
                ->each(function (WalletEntry $r) use (&$result): void {
                    $result[(int) $r->reference_id] = (string) preg_replace('/^Batal isi saldo: /', '', (string) $r->description);
                });
        }

        return $result;
    }

    /**
     * @param  array<int, string>  $cancellations
     * @return array<string, mixed>
     */
    private function row(WalletEntry $e, array $cancellations): array
    {
        $cash = str_starts_with($e->idempotency_key, self::CASH_PREFIX);
        $student = $e->wallet->student;

        return [
            'id' => $e->id,
            'created_at' => $e->created_at->toIso8601String(),
            'nis' => $student->nis, 'name' => $student->name, 'unit_code' => $student->unit->code,
            'method' => $cash ? 'cash' : 'online',
            'method_label' => $cash ? 'Tunai di loket' : 'Online (Midtrans)',
            'note' => $e->description,
            'actor' => $e->creator !== null ? $e->creator->name : 'Sistem',
            'amount' => $e->amount,
            'cancelled' => isset($cancellations[$e->id]),
            'cancel_reason' => $cancellations[$e->id] ?? null,
        ];
    }

    /**
     * @return list<array{id: int, nis: string, name: string, classroom_id: int|null, classroom: string|null}>
     */
    private function studentPicker(?int $yearId): array
    {
        $students = Student::query()->where('status', StudentStatus::Active->value)
            ->orderBy('name')->orderBy('id')->get(['id', 'nis', 'name']);
        $classes = $yearId === null ? collect() : Enrollment::query()->with('classroom:id,name')
            ->where('academic_year_id', $yearId)->whereIn('student_id', $students->pluck('id'))->get()->keyBy('student_id');

        return array_values($students->map(function (Student $s) use ($classes): array {
            $enrollment = $classes->get($s->id);

            return [
                'id' => $s->id, 'nis' => $s->nis, 'name' => $s->name,
                'classroom_id' => $enrollment?->classroom_id, 'classroom' => $enrollment?->classroom->name,
            ];
        })->all());
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
