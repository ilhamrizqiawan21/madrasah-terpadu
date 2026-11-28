<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Controller;
use App\Models\CashAccount;
use App\Models\CashEntry;
use App\Models\Unit;
use App\Models\User;
use App\Services\Accounting\CashLedgerService;
use App\Services\Audit\AuditLogger;
use App\Services\Reports\ReportExportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Akuntansi & Kas > Pos Akun (COA) dan Alokasi Dana (ADR-030).
 */
class CashAccountController extends Controller
{
    public const array DIRECTIONS = ['in' => 'Pendapatan (kas masuk)', 'out' => 'Pengeluaran (kas keluar)'];

    public function __construct(
        private readonly CashLedgerService $ledger,
        private readonly AuditLogger $audit,
        private readonly ReportExportService $exports,
    ) {}

    public function index(Request $request): Response
    {
        $unitId = $request->integer('unit') ?: null;
        $direction = array_key_exists($request->string('direction')->toString(), self::DIRECTIONS) ? $request->string('direction')->toString() : null;
        $search = trim($request->string('search')->toString());

        $paginator = $this->query($unitId, $direction, $search)->with('unit:id,code,name')
            ->paginate(50)->withQueryString();

        $balances = [];
        foreach (array_unique(array_map(static fn (CashAccount $a): int => $a->unit_id, $paginator->items())) as $uid) {
            $balances += $this->ledger->fundingBalances($uid);
        }
        $used = CashEntry::query()->whereIn('cash_account_id', array_map(static fn (CashAccount $a): int => $a->id, $paginator->items()))
            ->distinct()->pluck('cash_account_id')->map(fn (mixed $id): int => (int) $id)->flip()->all();

        return Inertia::render('admin/accounting/accounts', [
            'rows' => [
                'data' => array_values(array_map(fn (CashAccount $a): array => [
                    'id' => $a->id, 'unit_id' => $a->unit_id, 'unit_name' => $a->unit->name, 'code' => $a->code, 'name' => $a->name,
                    'direction' => $a->direction, 'is_active' => $a->is_active, 'is_system' => $a->system_key !== null,
                    'balance' => $a->direction === CashAccount::IN ? ($balances[$a->id] ?? 0) : null,
                    'used' => isset($used[$a->id]),
                ], $paginator->items())),
                'meta' => [
                    'current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(), 'from' => $paginator->firstItem(), 'to' => $paginator->lastItem(),
                ],
            ],
            'filters' => ['search' => $search, 'unit' => $unitId, 'direction' => $direction],
            'unitOptions' => self::unitOptions(),
            'directionOptions' => array_map(fn (string $k, string $v): array => ['value' => $k, 'label' => $v], array_keys(self::DIRECTIONS), self::DIRECTIONS),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->data($request, null);
        DB::transaction(function () use ($data, $request): void {
            $account = CashAccount::query()->create($data);
            $this->audit->record('cash_account.created', $account, null, $data, self::actor($request)->id);
        });

        return self::done('Pos akun ditambahkan.');
    }

    public function update(Request $request, CashAccount $account): RedirectResponse
    {
        $data = $this->data($request, $account);
        $hasEntries = CashEntry::query()->where('cash_account_id', $account->id)->orWhere('funding_account_id', $account->id)->exists();
        if (($data['direction'] !== $account->direction || $data['unit_id'] !== $account->unit_id) && ($hasEntries || $account->system_key !== null)) {
            throw ValidationException::withMessages(['direction' => 'Jenis aliran dan unit tidak bisa diubah karena pos ini sudah dipakai di buku kas.']);
        }

        DB::transaction(function () use ($data, $account, $request): void {
            $before = $account->only(array_keys($data));
            $account->fill($data)->save();
            $this->audit->record('cash_account.updated', $account, $before, $data, self::actor($request)->id);
        });

        return self::done('Pos akun diperbarui.');
    }

    public function destroy(Request $request, CashAccount $account): RedirectResponse
    {
        if ($account->system_key !== null) {
            throw ValidationException::withMessages(['master' => 'Pos sistem tidak bisa dihapus.']);
        }
        if (CashEntry::query()->where('cash_account_id', $account->id)->orWhere('funding_account_id', $account->id)->exists()) {
            throw ValidationException::withMessages(['master' => 'Pos ini sudah dipakai di buku kas. Nonaktifkan saja agar riwayat tetap utuh.']);
        }

        DB::transaction(function () use ($account, $request): void {
            $this->audit->record('cash_account.deleted', $account, $account->only(['unit_id', 'name', 'direction']), null, self::actor($request)->id);
            $account->delete();
        });

        return self::done('Pos akun dihapus.');
    }

    public function exportPdf(Request $request): HttpResponse
    {
        $unitId = $request->integer('unit') ?: null;
        $rows = [];
        foreach ($this->query($unitId, null, '')->with('unit:id,code')->get() as $a) {
            $rows[] = [$a->unit->code, $a->code ?? '-', $a->name, self::DIRECTIONS[$a->direction] ?? $a->direction, $a->is_active ? 'Aktif' : 'Nonaktif'];
        }

        return $this->exports->toPdfResponse('bagan-akun.pdf', 'Bagan Pos Akun (COA)', 'Pos pendapatan dan pengeluaran buku kas', ['Unit', 'Kode', 'Nama pos', 'Jenis aliran', 'Status'], $rows, 'portrait');
    }

    public function allocations(Request $request): Response
    {
        $unitId = $request->integer('unit') ?: (int) Unit::query()->where('is_active', true)->orderBy('id')->value('id');

        $accounts = CashAccount::query()->where('unit_id', $unitId)->where('is_active', true)
            ->with('allowedFundingAccounts:id,name')
            ->orderBy('direction')->orderBy('code')->orderBy('name')->get();

        return Inertia::render('admin/accounting/allocations', [
            'unitId' => $unitId,
            'unitOptions' => self::unitOptions(),
            'expenseAccounts' => $accounts->where('direction', CashAccount::OUT)->values()->map(fn (CashAccount $a): array => [
                'id' => $a->id, 'name' => $a->label(),
                'allowed' => $a->allowedFundingAccounts->map(fn (CashAccount $f): array => ['id' => $f->id, 'name' => $f->name])->values()->all(),
            ])->all(),
            'incomeAccounts' => $accounts->where('direction', CashAccount::IN)->values()->map(fn (CashAccount $a): array => ['id' => $a->id, 'name' => $a->label()])->all(),
        ]);
    }

    public function updateAllocation(Request $request, CashAccount $account): RedirectResponse
    {
        abort_unless($account->direction === CashAccount::OUT, 404);
        $data = $request->validate([
            'income_ids' => ['array'],
            'income_ids.*' => ['integer', 'distinct', Rule::exists('cash_accounts', 'id')->where('unit_id', $account->unit_id)->where('direction', CashAccount::IN)],
        ], [], ['income_ids' => 'pos pemasukan', 'income_ids.*' => 'pos pemasukan']);

        $ids = array_values(array_map(intval(...), $data['income_ids'] ?? []));
        DB::transaction(function () use ($account, $ids, $request): void {
            $before = $account->allowedFundingAccounts()->pluck('cash_accounts.id')->all();
            $account->allowedFundingAccounts()->sync($ids);
            $this->audit->record('cash_allocation.updated', $account, ['income_ids' => $before], ['income_ids' => $ids], self::actor($request)->id);
        });

        return self::done($ids === [] ? 'Aturan dihapus: pos ini boleh dibiayai semua sumber dana.' : 'Aturan alokasi dana disimpan.');
    }

    /**
     * @return Builder<CashAccount>
     */
    private function query(?int $unitId, ?string $direction, string $search): Builder
    {
        return CashAccount::query()
            ->when($unitId !== null, fn (Builder $q) => $q->where('unit_id', $unitId))
            ->when($direction !== null, fn (Builder $q) => $q->where('direction', $direction))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $i) => $i->whereLike('name', "%{$search}%")->orWhereLike('code', "%{$search}%")))
            ->orderBy('unit_id')->orderBy('direction')->orderBy('code')->orderBy('name')->orderBy('id');
    }

    /**
     * @return array{unit_id: int, code: string|null, name: string, direction: string, is_active: bool}
     */
    private function data(Request $request, ?CashAccount $account): array
    {
        $data = $request->validate([
            'unit_id' => ['required', 'integer', Rule::exists('units', 'id')->where('is_active', true)],
            'code' => ['nullable', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:100', Rule::unique('cash_accounts', 'name')->where('unit_id', $request->integer('unit_id'))->ignore($account?->id)],
            'direction' => ['required', Rule::in(array_keys(self::DIRECTIONS))],
            'is_active' => ['required', 'in:0,1'],
        ], [], ['unit_id' => 'unit', 'code' => 'kode', 'name' => 'nama pos', 'direction' => 'jenis aliran', 'is_active' => 'status']);

        $code = isset($data['code']) ? trim((string) $data['code']) : '';

        return [
            'unit_id' => (int) $data['unit_id'], 'code' => $code === '' ? null : $code, 'name' => trim((string) $data['name']),
            'direction' => (string) $data['direction'], 'is_active' => (string) $data['is_active'] === '1',
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function unitOptions(): array
    {
        return array_values(Unit::query()->where('is_active', true)->orderBy('id')->get(['id', 'name'])
            ->map(fn (Unit $u): array => ['value' => (string) $u->id, 'label' => $u->name])->all());
    }

    public static function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    public static function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
