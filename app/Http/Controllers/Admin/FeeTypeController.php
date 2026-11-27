<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\FeeRecurrence;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Billing\StoreFeeTypeRequest;
use App\Http\Requests\Admin\Billing\UpdateFeeTypeRequest;
use App\Models\FeeType;
use App\Models\Unit;
use App\Models\User;
use App\Services\Billing\FeeTypeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FeeTypeController extends Controller
{
    public function __construct(
        private readonly FeeTypeService $service,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', FeeType::class);

        $search = trim((string) $request->string('search'));
        $unitId = $request->filled('unit_id') ? $request->integer('unit_id') : null;
        $recurrence = $request->string('recurrence')->toString();
        $status = $request->string('status')->toString();

        $feeTypes = FeeType::query()
            ->with('unit:id,code,name')
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($sub) use ($search): void {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%");
                });
            })
            ->when($unitId !== null, fn ($q) => $q->where('unit_id', $unitId))
            ->when($recurrence !== '', fn ($q) => $q->where('recurrence', $recurrence))
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderByRaw('CASE WHEN unit_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('unit_id')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $items = $feeTypes->getCollection()
            ->map(fn (FeeType $feeType): array => [
                'id' => $feeType->id,
                'unit_id' => $feeType->unit_id,
                'unit_name' => $feeType->unit->name ?? 'Semua unit',
                'name' => $feeType->name,
                'category' => $feeType->category,
                'recurrence' => $feeType->recurrence->value,
                'recurrence_label' => $feeType->recurrence->label(),
                'default_amount' => $feeType->default_amount,
                'is_active' => $feeType->is_active,
            ])
            ->values()
            ->all();

        $user = $request->user();

        return Inertia::render('admin/billing/fee-types', [
            'feeTypes' => [
                'data' => $items,
                'meta' => [
                    'current_page' => $feeTypes->currentPage(),
                    'last_page' => $feeTypes->lastPage(),
                    'per_page' => $feeTypes->perPage(),
                    'total' => $feeTypes->total(),
                    'from' => $feeTypes->firstItem(),
                    'to' => $feeTypes->lastItem(),
                ],
            ],
            'units' => Unit::query()->orderBy('name')->get(['id', 'code', 'name']),
            'recurrences' => array_map(
                static fn (FeeRecurrence $item): array => ['value' => $item->value, 'label' => $item->label()],
                FeeRecurrence::cases(),
            ),
            'filters' => [
                'search' => $search,
                'unit_id' => $unitId !== null ? (string) $unitId : '',
                'recurrence' => $recurrence,
                'status' => $status,
            ],
            'canManage' => $user instanceof User && $user->can('create', FeeType::class),
        ]);
    }

    public function store(StoreFeeTypeRequest $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $this->service->create($actor, $request->validated());

        return back()->with('success', 'Jenis tagihan berhasil ditambahkan.');
    }

    public function update(UpdateFeeTypeRequest $request, FeeType $feeType): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $this->service->update($actor, $feeType, $request->validated());

        return back()->with('success', 'Jenis tagihan berhasil diperbarui.');
    }

    public function toggle(Request $request, FeeType $feeType): RedirectResponse
    {
        Gate::authorize('toggle', $feeType);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $isActive = (bool) $validated['is_active'];
        $this->service->toggleActive($actor, $feeType, $isActive);

        return back()->with('success', $isActive ? 'Jenis tagihan diaktifkan.' : 'Jenis tagihan dinonaktifkan.');
    }

    public function destroy(Request $request, FeeType $feeType): RedirectResponse
    {
        Gate::authorize('delete', $feeType);

        /** @var User $actor */
        $actor = $request->user();
        $this->service->delete($actor, $feeType);

        return back()->with('success', 'Jenis tagihan berhasil dihapus.');
    }
}
