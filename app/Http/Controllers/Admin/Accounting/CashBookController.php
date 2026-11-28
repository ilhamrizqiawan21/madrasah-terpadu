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
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Akuntansi & Kas > Buku Kas Umum: jurnal kas masuk/keluar dengan saldo berjalan (ADR-030).
 */
class CashBookController extends Controller
{
    public function __construct(
        private readonly CashLedgerService $ledger,
        private readonly ReportExportService $exports,
    ) {}

    public function index(Request $request): Response
    {
        $f = $this->filters($request);
        $range = $f['range'];

        $paginator = $this->query($f)->with(['account:id,code,name', 'fundingAccount:id,name', 'recorder:id,name'])
            ->orderBy('entry_date')->orderBy('id')
            ->paginate(50)->withQueryString();
        $entries = $paginator->items();

        $summary = $this->summary($f);
        // Saldo berjalan hanya bermakna bila tidak disaring arah/kata kunci.
        $running = null;
        if ($f['direction'] === null && $f['search'] === '' && $entries !== []) {
            $first = $entries[0];
            $before = (int) $this->scoped($f)
                ->when($range->start !== null, fn (Builder $q) => $q->where('entry_date', '>=', $range->start?->toDateString()))
                ->where(fn (Builder $q) => $q->where('entry_date', '<', $first->entry_date->toDateString())
                    ->orWhere(fn (Builder $x) => $x->where('entry_date', '>=', $first->entry_date->toDateString())
                        ->where('entry_date', '<', $first->entry_date->addDay()->toDateString())
                        ->where('id', '<', $first->id)))
                ->sum(self::signedSql());
            $running = $summary['opening'] + $before;
        }

        $reversed = self::reversedIds(array_map(static fn (CashEntry $e): int => $e->id, $entries));
        $rows = [];
        foreach ($entries as $e) {
            if ($running !== null) {
                $running += $e->signedAmount();
            }
            $rows[] = self::present($e, $reversed, $running);
        }

        $unitId = $f['unit'];
        $accounts = $unitId === null ? collect() : CashAccount::query()->where('unit_id', $unitId)->where('is_active', true)
            ->with('allowedFundingAccounts:id')->orderBy('code')->orderBy('name')->get();
        $balances = $unitId === null ? [] : $this->ledger->fundingBalances($unitId);

        return Inertia::render('admin/accounting/cash-book', [
            'rows' => [
                'data' => $rows,
                'meta' => [
                    'current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(), 'from' => $paginator->firstItem(), 'to' => $paginator->lastItem(),
                ],
            ],
            'summary' => $summary,
            'showRunning' => $running !== null,
            'filters' => [...$range->toFilters(), 'unit' => $unitId, 'direction' => $f['direction'], 'box' => $f['box'], 'search' => $f['search']],
            'periodLabel' => $range->label,
            'periodOptions' => PeriodRange::options(),
            'unitOptions' => CashAccountController::unitOptions(),
            'boxOptions' => array_map(fn (string $k, string $v): array => ['value' => $k, 'label' => $v], array_keys(CashEntry::BOXES), CashEntry::BOXES),
            'expenseAccounts' => $accounts->where('direction', CashAccount::OUT)->values()->map(fn (CashAccount $a): array => [
                'id' => $a->id, 'name' => $a->label(), 'allowed' => $a->allowedFundingAccounts->pluck('id')->map(fn (mixed $id): int => (int) $id)->all(),
            ])->all(),
            'fundingAccounts' => $accounts->where('direction', CashAccount::IN)->values()->map(fn (CashAccount $a): array => [
                'id' => $a->id, 'name' => $a->label(), 'balance' => $balances[$a->id] ?? 0,
            ])->all(),
            'today' => CarbonImmutable::now(AppTimezone::name())->toDateString(),
        ]);
    }

    public function storeExpense(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'unit_id' => ['required', 'integer', Rule::exists('units', 'id')->where('is_active', true)],
            'cash_account_id' => ['required', 'integer', Rule::exists('cash_accounts', 'id')->where('direction', CashAccount::OUT)],
            'funding_account_id' => ['required', 'integer'],
            'cash_box' => ['required', Rule::in(array_keys(CashEntry::BOXES))],
            'reference_no' => ['nullable', 'string', 'max:50'],
            'counterparty' => ['nullable', 'string', 'max:120'],
            'amount' => ['required', 'integer', 'min:1', 'max:10000000000'],
            'entry_date' => ['required', 'date_format:Y-m-d'],
            'description' => ['required', 'string', 'max:255'],
        ], [], [
            'unit_id' => 'unit', 'cash_account_id' => 'pos pengeluaran', 'funding_account_id' => 'sumber dana', 'cash_box' => 'kas',
            'reference_no' => 'no. bukti', 'counterparty' => 'toko/penerima', 'amount' => 'nominal', 'entry_date' => 'tanggal', 'description' => 'uraian',
        ]);

        $entry = $this->ledger->record([
            'unit_id' => (int) $data['unit_id'], 'entry_date' => (string) $data['entry_date'], 'cash_account_id' => (int) $data['cash_account_id'],
            'amount' => (int) $data['amount'], 'cash_box' => (string) $data['cash_box'], 'funding_account_id' => (int) $data['funding_account_id'],
            'reference_no' => $data['reference_no'] ?? null, 'counterparty' => $data['counterparty'] ?? null,
            'description' => (string) $data['description'], 'source_type' => 'expense',
        ], CashAccountController::actor($request));

        return CashAccountController::done('Pengeluaran '.Money::format($entry->amount).' dicatat.');
    }

    public function reverse(Request $request, CashEntry $entry): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:200']], [], ['reason' => 'alasan koreksi']);
        $this->ledger->reverse($entry, (string) $data['reason'], CashAccountController::actor($request));

        return CashAccountController::done('Entri dikoreksi dengan entri pembalik. Riwayat aslinya tetap tercatat.');
    }

    public function exportPdf(Request $request): HttpResponse
    {
        [$headers, $rows, $subtitle] = $this->exportData($request);

        return $this->exports->toPdfResponse('buku-kas-umum.pdf', 'Buku Kas Umum', $subtitle, $headers, $rows);
    }

    public function exportExcel(Request $request): BinaryFileResponse
    {
        [$headers, $rows] = $this->exportData($request);

        return $this->exports->toXlsxResponse('buku-kas-umum.xlsx', $headers, $rows);
    }

    /**
     * @return array{0: list<string>, 1: list<list<string|int|null>>, 2: string}
     */
    private function exportData(Request $request): array
    {
        $f = $this->filters($request);
        $summary = $this->summary($f);
        $balance = $summary['opening'];
        $rows = [['', '', 'Saldo awal periode', '', null, null, $balance]];
        $entries = $this->query($f)->with(['account:id,code,name', 'fundingAccount:id,name'])->orderBy('entry_date')->orderBy('id')->limit(10000)->get();
        foreach ($entries as $e) {
            $balance += $e->signedAmount();
            $rows[] = [
                $e->entry_date->format('d/m/Y'), $e->reference_no ?? '-', $e->account->label(),
                $e->description.($e->counterparty !== null ? ' · '.$e->counterparty : ''),
                $e->direction === CashAccount::IN ? $e->amount : null,
                $e->direction === CashAccount::OUT ? $e->amount : null,
                $f['direction'] === null && $f['search'] === '' ? $balance : null,
            ];
        }

        $subtitle = $f['range']->label.' · Masuk '.Money::format($summary['in']).' · Keluar '.Money::format($summary['out']).' · Saldo akhir '.Money::format($summary['closing']);

        return [['Tanggal', 'No. bukti', 'Pos akun', 'Uraian', 'Masuk (Rp)', 'Keluar (Rp)', 'Saldo (Rp)'], $rows, $subtitle];
    }

    /**
     * @return array{range: PeriodRange, unit: int|null, direction: string|null, box: string|null, search: string}
     */
    private function filters(Request $request): array
    {
        $direction = in_array($request->string('direction')->toString(), [CashAccount::IN, CashAccount::OUT], true) ? $request->string('direction')->toString() : null;
        $box = array_key_exists($request->string('box')->toString(), CashEntry::BOXES) ? $request->string('box')->toString() : null;

        return [
            'range' => PeriodRange::fromRequest($request, 'this_month'),
            'unit' => $request->integer('unit') ?: null,
            'direction' => $direction,
            'box' => $box,
            'search' => trim($request->string('search')->toString()),
        ];
    }

    /**
     * Unit + kas saja (dasar saldo).
     *
     * @param  array{unit: int|null, box: string|null}  $f
     * @return Builder<CashEntry>
     */
    private function scoped(array $f): Builder
    {
        return CashEntry::query()
            ->when($f['unit'] !== null, fn (Builder $q) => $q->where('unit_id', $f['unit']))
            ->when($f['box'] !== null, fn (Builder $q) => $q->where('cash_box', $f['box']));
    }

    /**
     * @param  array{range: PeriodRange, unit: int|null, direction: string|null, box: string|null, search: string}  $f
     * @return Builder<CashEntry>
     */
    private function query(array $f): Builder
    {
        $range = $f['range'];

        return $this->scoped($f)
            ->when($range->start !== null, fn (Builder $q) => $q->where('entry_date', '>=', $range->start?->toDateString()))
            ->when($range->end !== null, fn (Builder $q) => $q->where('entry_date', '<', $range->end?->addDay()->toDateString()))
            ->when($f['direction'] !== null, fn (Builder $q) => $q->where('direction', $f['direction']))
            ->when($f['search'] !== '', fn (Builder $q) => $q->where(fn (Builder $i) => $i->whereLike('description', "%{$f['search']}%")
                ->orWhereLike('reference_no', "%{$f['search']}%")->orWhereLike('counterparty', "%{$f['search']}%")));
    }

    /**
     * @param  array{range: PeriodRange, unit: int|null, direction: string|null, box: string|null, search: string}  $f
     * @return array{opening: int, in: int, out: int, closing: int}
     */
    private function summary(array $f): array
    {
        $range = $f['range'];
        $opening = $range->start === null ? 0 : (int) $this->scoped($f)->where('entry_date', '<', $range->start->toDateString())->sum(self::signedSql());
        $period = $this->scoped($f)
            ->when($range->start !== null, fn (Builder $q) => $q->where('entry_date', '>=', $range->start?->toDateString()))
            ->when($range->end !== null, fn (Builder $q) => $q->where('entry_date', '<', $range->end?->addDay()->toDateString()))
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE 0 END), 0) AS total_in")
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'out' THEN amount ELSE 0 END), 0) AS total_out")
            ->first();
        $in = (int) ($period?->getAttribute('total_in') ?? 0);
        $out = (int) ($period?->getAttribute('total_out') ?? 0);

        return ['opening' => $opening, 'in' => $in, 'out' => $out, 'closing' => $opening + $in - $out];
    }

    private static function signedSql(): Expression
    {
        return DB::raw("CASE WHEN direction = 'in' THEN amount ELSE -amount END");
    }

    /**
     * @param  array<int>  $ids
     * @return array<int, string> id entri asli => alasan koreksi
     */
    public static function reversedIds(array $ids): array
    {
        return $ids === [] ? [] : CashEntry::query()->whereIn('reverses_entry_id', $ids)
            ->pluck('reversal_reason', 'reverses_entry_id')->map(fn (mixed $r): string => (string) $r)->all();
    }

    /**
     * @param  array<int, string>  $reversed
     * @return array<string, mixed>
     */
    public static function present(CashEntry $e, array $reversed, ?int $running = null): array
    {
        return [
            'id' => $e->id,
            'entry_date' => $e->entry_date->toDateString(),
            'reference_no' => $e->reference_no,
            'account' => $e->account->label(),
            'funding' => $e->direction === CashAccount::OUT ? $e->fundingAccount->name : null,
            'description' => $e->description,
            'counterparty' => $e->counterparty,
            'cash_box' => $e->cash_box,
            'direction' => $e->direction,
            'amount' => $e->amount,
            'running' => $running,
            'is_reversal' => $e->reverses_entry_id !== null,
            'reversal_reason' => $e->reversal_reason,
            'reversed_reason' => $reversed[$e->id] ?? null,
            'can_reverse' => $e->reverses_entry_id === null && ! isset($reversed[$e->id]),
            'recorder' => $e->relationLoaded('recorder') ? $e->recorder->name : null,
        ];
    }
}
