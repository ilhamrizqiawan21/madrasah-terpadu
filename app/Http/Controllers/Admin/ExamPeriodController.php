<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ExamDelivery;
use App\Enums\ExamPeriodStatus;
use App\Enums\ExamRequirementScope;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ExamPeriodRequest;
use App\Models\ExamPeriod;
use App\Models\ExamPeriodRequirement;
use App\Models\FeeType;
use App\Models\Unit;
use App\Models\User;
use App\Services\Billing\ExamPeriodService;
use App\Support\SchoolContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengelolaan periode ujian dan syarat tagihan kelayakan (T-611, ADR-016).
 */
class ExamPeriodController extends Controller
{
    public function __construct(
        private readonly ExamPeriodService $service,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ExamPeriod::class);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $context = SchoolContext::resolve($request->session());
        $units = Unit::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']);

        $unitCode = $request->string('unit')->trim()->toString();
        if ($unitCode === '' && $context['unit'] !== null) {
            $unitCode = $context['unit'];
        }
        $selectedUnit = $unitCode !== '' && $unitCode !== 'all'
            ? $units->firstWhere('code', $unitCode)
            : null;

        $statusFilter = $request->string('status')->trim()->toString();

        $periods = ExamPeriod::query()
            ->with(['unit:id,code,name', 'requirements.feeType:id,name,recurrence'])
            ->when($selectedUnit !== null, fn (Builder $q) => $q->where('unit_id', $selectedUnit->id))
            ->when($statusFilter !== '', fn (Builder $q) => $q->where('status', $statusFilter))
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (ExamPeriod $period): array => [
                'id' => $period->id,
                'unit_id' => $period->unit_id,
                'unit_code' => $period->unit->code,
                'unit_name' => $period->unit->name,
                'name' => $period->name,
                'delivery' => [
                    'value' => $period->delivery->value,
                    'label' => $period->delivery->label(),
                ],
                'starts_on' => $period->starts_on->toDateString(),
                'ends_on' => $period->ends_on->toDateString(),
                'status' => [
                    'value' => $period->status->value,
                    'label' => $period->status->label(),
                ],
                'requirements' => array_values($period->requirements->map(fn (ExamPeriodRequirement $req): array => [
                    'id' => $req->id,
                    'fee_type_id' => $req->fee_type_id,
                    'fee_type_name' => $req->feeType->name,
                    'invoice_scope' => [
                        'value' => $req->invoice_scope->value,
                        'label' => $req->invoice_scope->label(),
                    ],
                    'period_until' => $req->period_until?->toDateString(),
                ])->all()),
            ]);

        $feeTypes = FeeType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'unit_id', 'name', 'recurrence'])
            ->map(fn (FeeType $fee): array => [
                'id' => $fee->id,
                'unit_id' => $fee->unit_id,
                'name' => $fee->name,
                'recurrence' => $fee->recurrence->value,
            ])
            ->values()
            ->all();

        return Inertia::render('admin/billing/exam-periods', [
            'periods' => $periods,
            'units' => $units->map(fn (Unit $u): array => ['id' => $u->id, 'code' => $u->code, 'name' => $u->name])->values()->all(),
            'feeTypes' => $feeTypes,
            'deliveries' => array_map(fn (ExamDelivery $d): array => ['value' => $d->value, 'label' => $d->label()], ExamDelivery::cases()),
            'statuses' => array_map(fn (ExamPeriodStatus $s): array => ['value' => $s->value, 'label' => $s->label()], ExamPeriodStatus::cases()),
            'scopes' => array_map(fn (ExamRequirementScope $s): array => ['value' => $s->value, 'label' => $s->label()], ExamRequirementScope::cases()),
            'filters' => [
                'unit' => $selectedUnit !== null ? $selectedUnit->code : 'all',
                'status' => $statusFilter,
            ],
            'canManage' => $user->can(Permission::InvoicesIssue->value),
        ]);
    }

    public function store(ExamPeriodRequest $request): RedirectResponse
    {
        Gate::authorize('create', ExamPeriod::class);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        /** @var array{unit_id: int, name: string, delivery: string, starts_on: string, ends_on: string, requirements: list<array{fee_type_id: int, invoice_scope: string, period_until?: ?string}>} $validated */
        $validated = $request->validated();
        $this->service->create($user, $validated);

        return back()->with('success', 'Periode ujian berhasil ditambahkan.');
    }

    public function update(ExamPeriodRequest $request, ExamPeriod $examPeriod): RedirectResponse
    {
        Gate::authorize('update', $examPeriod);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        /** @var array{name: string, delivery: string, starts_on: string, ends_on: string, requirements: list<array{fee_type_id: int, invoice_scope: string, period_until?: ?string}>} $validated */
        $validated = $request->validated();
        $this->service->update($user, $examPeriod, $validated);

        return back()->with('success', 'Periode ujian berhasil diperbarui.');
    }

    public function activate(Request $request, ExamPeriod $examPeriod): RedirectResponse
    {
        Gate::authorize('changeStatus', $examPeriod);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $this->service->activate($user, $examPeriod);

        return back()->with('success', 'Periode ujian diaktifkan.');
    }

    public function close(Request $request, ExamPeriod $examPeriod): RedirectResponse
    {
        Gate::authorize('changeStatus', $examPeriod);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $this->service->close($user, $examPeriod);

        return back()->with('success', 'Periode ujian ditutup.');
    }
}
