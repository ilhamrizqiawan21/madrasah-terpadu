<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Controller;
use App\Models\CashAccount;
use App\Models\CashEntry;
use App\Services\Accounting\CashLedgerService;
use App\Services\Reports\ReportExportService;
use App\Support\AppTimezone;
use App\Support\Money;
use App\Support\PeriodRange;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Akuntansi & Kas > Pemasukan Non-Siswa (hibah, sewa, CSR, bantuan). Ditulis ke Buku Kas Umum.
 */
class ExternalIncomeController extends Controller
{
    public const string SOURCE = 'external_income';

    public function __construct(
        private readonly CashLedgerService $ledger,
        private readonly ReportExportService $exports,
    ) {}

    public function index(Request $request): Response
    {
        $f = $this->filters($request);
        $paginator = $this->query($f)->with(['account:id,code,name', 'recorder:id,name'])
            ->orderByDesc('entry_date')->orderByDesc('id')
            ->paginate(50)->withQueryString();
        $entries = $paginator->items();
        $reversed = CashBookController::reversedIds(array_map(static fn (CashEntry $e): int => $e->id, $entries));

        $total = (int) $this->query($f)->sum('amount');
        $cancelled = (int) $this->query($f)->whereIn('id', CashEntry::query()->whereNotNull('reverses_entry_id')->where('source_type', self::SOURCE)->select('reverses_entry_id'))->sum('amount');

        $unitId = $f['unit'];

        return Inertia::render('admin/accounting/external-income', [
            'rows' => [
                'data' => array_values(array_map(fn (CashEntry $e): array => CashBookController::present($e, $reversed), $entries)),
                'meta' => [
                    'current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(), 'from' => $paginator->firstItem(), 'to' => $paginator->lastItem(),
                ],
            ],
            'summary' => ['total' => $total - $cancelled, 'count' => $paginator->total(), 'cancelled' => $cancelled],
            'filters' => [...$f['range']->toFilters(), 'unit' => $unitId, 'account' => $f['account'], 'search' => $f['search']],
            'periodLabel' => $f['range']->label,
            'periodOptions' => PeriodRange::options(),
            'unitOptions' => CashAccountController::unitOptions(),
            'incomeAccounts' => CashAccount::query()->with('unit:id,code')->where('direction', CashAccount::IN)->where('is_active', true)->whereNull('system_key')
                ->when($unitId !== null, fn (Builder $q) => $q->where('unit_id', $unitId))
                ->orderBy('unit_id')->orderBy('code')->orderBy('name')->get()
                ->map(fn (CashAccount $a): array => ['id' => $a->id, 'unit_id' => $a->unit_id, 'name' => $a->label().' ('.$a->unit->code.')'])->values()->all(),
            'boxOptions' => array_map(fn (string $k, string $v): array => ['value' => $k, 'label' => $v], array_keys(CashEntry::BOXES), CashEntry::BOXES),
            'today' => CarbonImmutable::now(AppTimezone::name())->toDateString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'cash_account_id' => ['required', 'integer', Rule::exists('cash_accounts', 'id')->where('direction', CashAccount::IN)->whereNull('system_key')],
            'counterparty' => ['required', 'string', 'max:120'],
            'cash_box' => ['required', Rule::in(array_keys(CashEntry::BOXES))],
            'reference_no' => ['nullable', 'string', 'max:50'],
            'amount' => ['required', 'integer', 'min:1', 'max:10000000000'],
            'entry_date' => ['required', 'date_format:Y-m-d'],
            'description' => ['required', 'string', 'max:255'],
        ], [], [
            'cash_account_id' => 'pos pendapatan', 'counterparty' => 'pengirim dana', 'cash_box' => 'rekening penampung',
            'reference_no' => 'no. bukti', 'amount' => 'nominal', 'entry_date' => 'tanggal terima', 'description' => 'uraian',
        ]);

        $account = CashAccount::query()->findOrFail((int) $data['cash_account_id']);
        $entry = $this->ledger->record([
            'unit_id' => $account->unit_id, 'entry_date' => (string) $data['entry_date'], 'cash_account_id' => $account->id,
            'amount' => (int) $data['amount'], 'cash_box' => (string) $data['cash_box'],
            'reference_no' => $data['reference_no'] ?? null, 'counterparty' => (string) $data['counterparty'],
            'description' => (string) $data['description'], 'source_type' => self::SOURCE,
        ], CashAccountController::actor($request));

        return CashAccountController::done('Pemasukan '.Money::format($entry->amount).' dicatat ke Buku Kas Umum.');
    }

    public function exportPdf(Request $request): HttpResponse
    {
        [$headers, $rows, $subtitle] = $this->exportData($request);

        return $this->exports->toPdfResponse('pemasukan-non-siswa.pdf', 'Jurnal Pemasukan Non-Siswa', $subtitle, $headers, $rows);
    }

    public function exportExcel(Request $request): BinaryFileResponse
    {
        [$headers, $rows] = $this->exportData($request);

        return $this->exports->toXlsxResponse('pemasukan-non-siswa.xlsx', $headers, $rows);
    }

    /**
     * @return array{0: list<string>, 1: list<list<string|int|null>>, 2: string}
     */
    private function exportData(Request $request): array
    {
        $f = $this->filters($request);
        $entries = $this->query($f)->with(['account:id,code,name'])->orderBy('entry_date')->orderBy('id')->limit(10000)->get();
        $reversed = CashBookController::reversedIds($entries->pluck('id')->map(fn (mixed $id): int => (int) $id)->all());
        $rows = [];
        $total = 0;
        foreach ($entries as $e) {
            $cancelled = isset($reversed[$e->id]);
            $total += $cancelled ? 0 : $e->amount;
            $rows[] = [
                $e->entry_date->format('d/m/Y'), $e->reference_no ?? '-', $e->account->label(), (string) $e->counterparty,
                $e->description, CashEntry::BOXES[$e->cash_box] ?? $e->cash_box, $e->amount, $cancelled ? 'Dikoreksi: '.$reversed[$e->id] : 'Masuk',
            ];
        }

        return [
            ['Tanggal', 'No. bukti', 'Pos pendapatan', 'Pengirim', 'Uraian', 'Rekening', 'Nominal (Rp)', 'Status'],
            $rows,
            $f['range']->label.' · Total masuk '.Money::format($total),
        ];
    }

    /**
     * @return array{range: PeriodRange, unit: int|null, account: int|null, search: string}
     */
    private function filters(Request $request): array
    {
        return [
            'range' => PeriodRange::fromRequest($request, 'this_month'),
            'unit' => $request->integer('unit') ?: null,
            'account' => $request->integer('account') ?: null,
            'search' => trim($request->string('search')->toString()),
        ];
    }

    /**
     * Entri pemasukan asli (bukan entri koreksi).
     *
     * @param  array{range: PeriodRange, unit: int|null, account: int|null, search: string}  $f
     * @return Builder<CashEntry>
     */
    private function query(array $f): Builder
    {
        $range = $f['range'];

        return CashEntry::query()
            ->where('source_type', self::SOURCE)
            ->whereNull('reverses_entry_id')
            ->when($f['unit'] !== null, fn (Builder $q) => $q->where('unit_id', $f['unit']))
            ->when($f['account'] !== null, fn (Builder $q) => $q->where('cash_account_id', $f['account']))
            ->when($range->start !== null, fn (Builder $q) => $q->where('entry_date', '>=', $range->start?->toDateString()))
            ->when($range->end !== null, fn (Builder $q) => $q->where('entry_date', '<', $range->end?->addDay()->toDateString()))
            ->when($f['search'] !== '', fn (Builder $q) => $q->where(fn (Builder $i) => $i->whereLike('description', "%{$f['search']}%")->orWhereLike('counterparty', "%{$f['search']}%")));
    }
}
