<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Domain\Attendance\AttendanceClassifier;
use App\Enums\AttendanceStatus;
use App\Enums\Feature;
use App\Enums\InvoiceStatus;
use App\Enums\StudentStatus;
use App\Models\AttendanceDay;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Unit;
use App\Services\Features\FeatureFlags;
use App\Support\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Dasbor Yayasan: susunan komponen mengikuti dasbor aplikasi lama (docs/legacy, arahan pemilik
 * 2026-11-28) dengan filter unit + periode. Hanya angka dari data nyata; komponen yang modulnya
 * belum ada (kas keluar, BOS, ZIS, PPDB, asrama, aset) dikirim sebagai `null` dan tampil jujur
 * "belum dicatat" di halaman.
 */
final class FoundationDashboardService
{
    private const string TZ = AttendanceClassifier::BUSINESS_TIMEZONE;

    /** @var array<string, string> */
    public const array PERIODS = [
        'hari_ini' => 'Hari ini',
        'kemarin' => 'Kemarin',
        'minggu_ini' => 'Minggu ini',
        'bulan_ini' => 'Bulan ini',
        'bulan_lalu' => 'Bulan lalu',
        'tahun_ini' => 'Tahun ini',
        'rentang' => 'Rentang tanggal',
        'semua' => 'Semua waktu',
    ];

    private const string REMAINING = 'CASE WHEN (invoices.amount - invoices.adjustment_total - invoices.paid_total) > 0 THEN (invoices.amount - invoices.adjustment_total - invoices.paid_total) ELSE 0 END';

    public function __construct(private readonly FeatureFlags $features) {}

    /**
     * @return array<string, mixed>
     */
    public function build(?Unit $unit, string $periodKey, ?string $from, ?string $to): array
    {
        [$periodKey, $start, $end] = $this->resolvePeriod($periodKey, $from, $to);
        $billingOn = $this->features->enabled(Feature::Billing);
        $walletOn = $this->features->enabled(Feature::Wallet);
        $now = CarbonImmutable::now(self::TZ);

        $cashIn = $this->cashIn($unit, $start, $end);

        return [
            'filters' => [
                'unit' => $unit !== null ? $unit->code : 'all',
                'period' => $periodKey,
                'from' => $start?->toDateString(),
                'to' => $end->toDateString(),
                'units' => Unit::query()->where('is_active', true)->orderBy('code')->get(['code', 'name'])
                    ->map(fn (Unit $u): array => ['code' => $u->code, 'name' => $u->name])->values()->all(),
                'periods' => array_map(
                    fn (string $key, string $label): array => ['value' => $key, 'label' => $label],
                    array_keys(self::PERIODS),
                    self::PERIODS,
                ),
            ],
            'modules' => ['billing' => $billingOn, 'wallet' => $walletOn],
            'services' => $this->services(),
            'cash_in' => $cashIn,
            'receivables' => $billingOn ? $this->receivables($unit, $start, $end) : null,
            'trend' => $this->trend($unit, $end->year),
            'composition' => $this->composition($unit, $start, $end, $cashIn['topups']),
            'wallet_flow' => $walletOn ? [
                'period' => $cashIn['topups'],
                'all_time' => $this->topups($unit, null, $now),
                'balance' => $this->walletBalance($unit),
            ] : null,
            'units' => $this->unitComparison($start, $end),
            'attendance' => $this->features->enabled(Feature::Attendance) ? $this->attendanceToday($now) : null,
            'top_arrears' => $billingOn ? $this->topArrears($unit) : [],
            'recent_cash' => $this->recentCash($unit),
        ];
    }

    /**
     * @return array{0: string, 1: ?CarbonImmutable, 2: CarbonImmutable}
     */
    private function resolvePeriod(string $key, ?string $from, ?string $to): array
    {
        $now = CarbonImmutable::now(self::TZ);
        $key = array_key_exists($key, self::PERIODS) ? $key : 'bulan_ini';

        if ($key === 'rentang') {
            $start = $this->parseDate($from);
            $end = $this->parseDate($to);

            if ($start === null || $end === null || $start->greaterThan($end)) {
                $key = 'bulan_ini';
            } else {
                return [$key, $start->startOfDay(), $end->endOfDay()];
            }
        }

        return match ($key) {
            'hari_ini' => [$key, $now->startOfDay(), $now->endOfDay()],
            'kemarin' => [$key, $now->subDay()->startOfDay(), $now->subDay()->endOfDay()],
            'minggu_ini' => [$key, $now->startOfWeek(), $now->endOfDay()],
            'bulan_lalu' => [$key, $now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            'tahun_ini' => [$key, $now->startOfYear(), $now->endOfDay()],
            'semua' => [$key, null, $now->endOfDay()],
            default => [$key, $now->startOfMonth(), $now->endOfMonth()],
        };
    }

    /**
     * Kolom waktu disimpan dalam zona aplikasi (config app.timezone), jadi batas kueri memakai zona yang sama.
     */
    private function db(?CarbonImmutable $time): ?string
    {
        return $time?->setTimezone((string) config('app.timezone'))->toDateTimeString();
    }

    private function parseDate(?string $value): ?CarbonImmutable
    {
        if ($value === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, self::TZ);

        return $date instanceof CarbonImmutable ? $date : null;
    }

    /**
     * Status layanan pendukung (setara pita "WA Gateway / Midtrans / Backup" aplikasi lama).
     *
     * @return list<array{key: string, label: string, status: string, tone: string}>
     */
    private function services(): array
    {
        $midtransKey = (string) config('services.midtrans.server_key');

        $midtrans = match (true) {
            ! $this->features->enabled(Feature::Midtrans) => ['Nonaktif', 'muted'],
            $midtransKey === '' => ['Kunci belum diisi', 'warning'],
            (bool) config('services.midtrans.is_production') => ['Produksi', 'success'],
            default => ['Sandbox', 'warning'],
        };

        return [
            ['key' => 'whatsapp', 'label' => 'WhatsApp', 'status' => 'Belum tersambung', 'tone' => 'muted'],
            ['key' => 'midtrans', 'label' => 'Midtrans', 'status' => $midtrans[0], 'tone' => $midtrans[1]],
            ['key' => 'backup', 'label' => 'Cadangan data', 'status' => 'Belum diatur', 'tone' => 'muted'],
        ];
    }

    /**
     * Kas masuk = pembayaran tagihan (tidak dibalik) + isi saldo dompet kantin.
     *
     * @return array{total: int, invoices: int, topups: int, count: int}
     */
    private function cashIn(?Unit $unit, ?CarbonImmutable $start, CarbonImmutable $end): array
    {
        /** @var object{total: int|string|null, tx: int|string|null}|null $row */
        $row = $this->paymentsQuery($unit, $start, $end)
            ->selectRaw('coalesce(sum(payments.amount), 0) as total, count(*) as tx')
            ->toBase()
            ->first();

        $invoices = (int) ($row->total ?? 0);
        $topups = $this->topups($unit, $start, $end);

        return [
            'total' => $invoices + $topups,
            'invoices' => $invoices,
            'topups' => $topups,
            'count' => (int) ($row->tx ?? 0),
        ];
    }

    /**
     * @return Builder<Payment>
     */
    private function paymentsQuery(?Unit $unit, ?CarbonImmutable $start, CarbonImmutable $end): Builder
    {
        return Payment::query()
            ->join('students', 'students.id', '=', 'payments.student_id')
            ->whereNull('payments.reversed_at')
            ->when($start !== null, fn (Builder $q) => $q->where('payments.paid_at', '>=', $this->db($start)))
            ->where('payments.paid_at', '<=', $this->db($end))
            ->when($unit !== null, fn (Builder $q) => $q->where('students.unit_id', $unit?->id));
    }

    private function topupsQuery(?Unit $unit, ?CarbonImmutable $start, CarbonImmutable $end): QueryBuilder
    {
        return DB::table('wallet_entries')
            ->join('wallets', 'wallets.id', '=', 'wallet_entries.wallet_id')
            ->join('students', 'students.id', '=', 'wallets.student_id')
            ->where('wallet_entries.type', 'topup')
            ->when($start !== null, fn (QueryBuilder $q) => $q->where('wallet_entries.created_at', '>=', $this->db($start)))
            ->where('wallet_entries.created_at', '<=', $this->db($end))
            ->when($unit !== null, fn (QueryBuilder $q) => $q->where('students.unit_id', $unit?->id));
    }

    private function topups(?Unit $unit, ?CarbonImmutable $start, CarbonImmutable $end): int
    {
        return (int) $this->topupsQuery($unit, $start, $end)->sum('wallet_entries.amount');
    }

    private function walletBalance(?Unit $unit): int
    {
        return (int) DB::table('wallets')
            ->join('students', 'students.id', '=', 'wallets.student_id')
            ->when($unit !== null, fn (QueryBuilder $q) => $q->where('students.unit_id', $unit?->id))
            ->sum('wallets.balance');
    }

    /**
     * Total piutang berjalan + capaian tagihan yang jatuh tempo di periode (lunas ÷ nilai bersih).
     *
     * @return array{total: int, overdue: int, due_in_period: int, paid_in_period: int, collection_rate: ?float}
     */
    private function receivables(?Unit $unit, ?CarbonImmutable $start, CarbonImmutable $end): array
    {
        $today = CarbonImmutable::now(self::TZ)->toDateString();
        $base = fn (): Builder => Invoice::query()
            ->join('students', 'students.id', '=', 'invoices.student_id')
            ->when($unit !== null, fn (Builder $q) => $q->where('students.unit_id', $unit?->id));

        /** @var object{total: int|string|null, overdue: int|string|null}|null $open */
        $open = $base()
            ->whereIn('invoices.status', [InvoiceStatus::Unpaid->value, InvoiceStatus::Partial->value])
            ->selectRaw('coalesce(sum('.self::REMAINING.'), 0) as total')
            ->selectRaw('coalesce(sum(CASE WHEN invoices.due_date < ? THEN '.self::REMAINING.' ELSE 0 END), 0) as overdue', [$today])
            ->toBase()
            ->first();

        /** @var object{net: int|string|null, paid: int|string|null}|null $period */
        $period = $base()
            ->where('invoices.status', '!=', InvoiceStatus::Cancelled->value)
            ->when($start !== null, fn (Builder $q) => $q->where('invoices.due_date', '>=', $start?->toDateString()))
            ->where('invoices.due_date', '<=', $end->toDateString())
            ->selectRaw('coalesce(sum(invoices.amount - invoices.adjustment_total), 0) as net, coalesce(sum(invoices.paid_total), 0) as paid')
            ->toBase()
            ->first();

        $net = (int) ($period->net ?? 0);
        $paid = (int) ($period->paid ?? 0);

        return [
            'total' => (int) ($open->total ?? 0),
            'overdue' => (int) ($open->overdue ?? 0),
            'due_in_period' => $net,
            'paid_in_period' => $paid,
            'collection_rate' => $net > 0 ? round($paid / $net * 100, 1) : null,
        ];
    }

    /**
     * Kas masuk per bulan dalam satu tahun (WITA). Dua kueri ringan per bulan agar tanpa fungsi tanggal dialek.
     *
     * @return list<array{month: int, invoices: int, topups: int}>
     */
    private function trend(?Unit $unit, int $year): array
    {
        $rows = [];

        foreach (range(1, 12) as $month) {
            $start = CarbonImmutable::create($year, $month, 1, 0, 0, 0, self::TZ);
            if (! $start instanceof CarbonImmutable) {
                continue;
            }
            $end = $start->endOfMonth();

            $rows[] = [
                'month' => $month,
                'invoices' => (int) $this->paymentsQuery($unit, $start, $end)->sum('payments.amount'),
                'topups' => $this->topups($unit, $start, $end),
            ];
        }

        return $rows;
    }

    /**
     * Komposisi kas masuk periode per kategori jenis tagihan, ditambah isi saldo kantin.
     *
     * @return list<array{label: string, amount: int}>
     */
    private function composition(?Unit $unit, ?CarbonImmutable $start, CarbonImmutable $end, int $topups): array
    {
        $rows = DB::table('payment_allocations')
            ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
            ->join('invoices', 'invoices.id', '=', 'payment_allocations.invoice_id')
            ->join('fee_types', 'fee_types.id', '=', 'invoices.fee_type_id')
            ->join('students', 'students.id', '=', 'payments.student_id')
            ->whereNull('payments.reversed_at')
            ->when($start !== null, fn (QueryBuilder $q) => $q->where('payments.paid_at', '>=', $this->db($start)))
            ->where('payments.paid_at', '<=', $this->db($end))
            ->when($unit !== null, fn (QueryBuilder $q) => $q->where('students.unit_id', $unit?->id))
            ->selectRaw('fee_types.category as category, coalesce(sum(payment_allocations.amount), 0) as amount')
            ->groupBy('fee_types.category')
            ->orderByDesc('amount')
            ->orderBy('fee_types.category')
            ->get();

        $items = $rows->map(fn (object $r): array => [
            'label' => trim((string) $r->category) !== '' ? (string) $r->category : 'Tagihan lain',
            'amount' => (int) $r->amount,
        ])->filter(fn (array $r): bool => $r['amount'] > 0)->values()->all();

        if ($topups > 0) {
            $items[] = ['label' => 'Isi saldo kantin', 'amount' => $topups];
        }

        return array_values($items);
    }

    /**
     * Perbandingan satuan pendidikan: siswa aktif dan penerimaan tagihan periode per unit.
     *
     * @return list<array{code: string, name: string, students: int, cash_in: int}>
     */
    private function unitComparison(?CarbonImmutable $start, CarbonImmutable $end): array
    {
        $units = Unit::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']);
        $students = Student::query()->where('status', StudentStatus::Active->value)
            ->selectRaw('unit_id, count(*) as aggregate')->groupBy('unit_id')->pluck('aggregate', 'unit_id');
        $cash = $this->paymentsQuery(null, $start, $end)
            ->selectRaw('students.unit_id as unit_id, coalesce(sum(payments.amount), 0) as total')
            ->groupBy('students.unit_id')
            ->pluck('total', 'unit_id');

        return array_values($units->map(fn (Unit $u): array => [
            'code' => $u->code,
            'name' => $u->name,
            'students' => (int) ($students[$u->id] ?? 0),
            'cash_in' => (int) ($cash[$u->id] ?? 0),
        ])->all());
    }

    /**
     * Presensi gerbang hari ini per unit: hadir tepat, terlambat, izin/sakit, alpa.
     *
     * @return list<array{code: string, present: int, late: int, excused: int, absent: int}>
     */
    private function attendanceToday(CarbonImmutable $now): array
    {
        $units = Unit::query()->where('is_active', true)->orderBy('code')->get(['id', 'code']);
        $rows = AttendanceDay::query()
            ->join('students', 'students.id', '=', 'attendance_days.student_id')
            ->whereDate('attendance_days.school_date', $now->toDateString())
            ->selectRaw('students.unit_id as unit_id, attendance_days.status as status, count(*) as aggregate')
            ->groupBy('students.unit_id', 'attendance_days.status')
            ->toBase()
            ->get();

        return array_values($units->map(function (Unit $u) use ($rows): array {
            $count = fn (array $statuses): int => (int) $rows
                ->filter(fn (object $r): bool => (int) $r->unit_id === $u->id && in_array($r->status, $statuses, true))
                ->sum('aggregate');

            return [
                'code' => $u->code,
                'present' => $count([AttendanceStatus::Present->value]),
                'late' => $count([AttendanceStatus::Late->value]),
                'excused' => $count([AttendanceStatus::Excused->value, AttendanceStatus::Sick->value]),
                'absent' => $count([AttendanceStatus::Absent->value]),
            ];
        })->all());
    }

    /**
     * Lima tagihan dengan sisa terbesar, lengkap dengan nomor WhatsApp wali utama untuk penagihan.
     *
     * @return list<array{invoice_id: int, description: string, student_id: int, student_name: string, unit_code: string, remaining: int, due_date: string, whatsapp: ?string}>
     */
    private function topArrears(?Unit $unit): array
    {
        $invoices = Invoice::query()
            ->with(['student:id,name,unit_id', 'student.unit:id,code', 'student.guardians:id,phone'])
            ->whereIn('status', [InvoiceStatus::Unpaid->value, InvoiceStatus::Partial->value])
            ->when($unit !== null, fn (Builder $q) => $q->whereHas('student', fn (Builder $s) => $s->where('unit_id', $unit?->id)))
            ->select('invoices.*')
            ->selectRaw(self::REMAINING.' as remaining_amount')
            ->orderByDesc('remaining_amount')
            ->orderBy('invoices.due_date')
            ->orderBy('invoices.id')
            ->limit(5)
            ->get();

        return array_values($invoices->map(function (Invoice $invoice): array {
            $guardians = $invoice->student->guardians;
            /** @var Guardian|null $primary */
            $primary = $guardians->first(fn (Guardian $g): bool => (bool) $g->getRelation('link')->getAttribute('is_primary')) ?? $guardians->first();

            return [
                'invoice_id' => $invoice->id,
                'description' => $invoice->description,
                'student_id' => $invoice->student_id,
                'student_name' => $invoice->student->name,
                'unit_code' => $invoice->student->unit->code,
                'remaining' => $invoice->remaining(),
                'due_date' => $invoice->due_date->toDateString(),
                'whatsapp' => $this->waNumber($primary?->phone),
            ];
        })->all());
    }

    private function waNumber(?string $phone): ?string
    {
        $normalized = PhoneNumber::normalize($phone);

        if ($normalized === null || ! str_starts_with($normalized, '08')) {
            return null;
        }

        return '62'.substr($normalized, 1);
    }

    /**
     * Transaksi kas masuk terbaru (pembayaran tagihan dan isi saldo), gabungan lima teratas.
     *
     * @return list<array{key: string, kind: string, title: string, detail: string, amount: int, at: string}>
     */
    private function recentCash(?Unit $unit): array
    {
        $payments = Payment::query()
            ->with('student:id,name')
            ->whereNull('reversed_at')
            ->when($unit !== null, fn (Builder $q) => $q->whereHas('student', fn (Builder $s) => $s->where('unit_id', $unit?->id)))
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (Payment $p): array => [
                'key' => 'payment-'.$p->id,
                'kind' => 'Pembayaran tagihan',
                'title' => $p->student->name,
                'detail' => $p->method->label().' · '.$p->receipt_number,
                'amount' => $p->amount,
                'at' => $p->paid_at->toIso8601String(),
            ]);

        $topups = DB::table('wallet_entries')
            ->join('wallets', 'wallets.id', '=', 'wallet_entries.wallet_id')
            ->join('students', 'students.id', '=', 'wallets.student_id')
            ->where('wallet_entries.type', 'topup')
            ->when($unit !== null, fn (QueryBuilder $q) => $q->where('students.unit_id', $unit?->id))
            ->orderByDesc('wallet_entries.created_at')
            ->orderByDesc('wallet_entries.id')
            ->limit(5)
            ->get(['wallet_entries.id', 'wallet_entries.amount', 'wallet_entries.description', 'wallet_entries.created_at', 'students.name'])
            ->map(fn (object $e): array => [
                'key' => 'topup-'.$e->id,
                'kind' => 'Isi saldo kantin',
                'title' => (string) $e->name,
                'detail' => (string) ($e->description ?? 'Isi saldo'),
                'amount' => (int) $e->amount,
                'at' => CarbonImmutable::parse((string) $e->created_at, (string) config('app.timezone'))->toIso8601String(),
            ]);

        return array_values($payments->concat($topups)
            ->sortByDesc('at')
            ->take(5)
            ->values()
            ->all());
    }
}
