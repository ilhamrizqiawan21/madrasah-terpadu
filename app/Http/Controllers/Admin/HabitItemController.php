<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\HabitAspect;
use App\Http\Controllers\Controller;
use App\Models\HabitItem;
use App\Models\Unit;
use App\Models\User;
use App\Services\Habits\HabitItemService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class HabitItemController extends Controller
{
    public function __construct(
        private readonly HabitItemService $service,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', HabitItem::class);

        $unitId = $request->filled('unit_id') ? $request->integer('unit_id') : null;
        $aspect = $request->string('aspect')->toString();

        $items = HabitItem::query()
            ->with('unit:id,code,name')
            ->when($unitId !== null, fn ($q) => $q->where('unit_id', $unitId))
            ->when($aspect !== '', fn ($q) => $q->where('aspect', $aspect))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (HabitItem $item): array => [
                'id' => $item->id,
                'unit_id' => $item->unit_id,
                'unit_name' => $item->unit->name ?? 'Semua unit',
                'name' => $item->name,
                'aspect' => $item->aspect->value,
                'aspect_label' => $item->aspect->label(),
                'sort_order' => $item->sort_order,
                'is_active' => $item->is_active,
                'is_locked' => $item->is_locked,
            ])
            ->values()
            ->all();

        $user = $request->user();

        return Inertia::render('admin/habits/items', [
            'items' => $items,
            'units' => Unit::query()->orderBy('name')->get(['id', 'code', 'name']),
            'aspects' => array_map(
                static fn (HabitAspect $a): array => ['value' => $a->value, 'label' => $a->label()],
                HabitAspect::cases()
            ),
            'filters' => [
                'unit_id' => $unitId !== null ? (string) $unitId : '',
                'aspect' => $aspect,
            ],
            'canManage' => $user instanceof User && $user->can('create', HabitItem::class),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', HabitItem::class);

        $validated = $request->validate([
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'name' => ['required', 'string', 'max:150'],
            'aspect' => ['required', Rule::enum(HabitAspect::class)],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $this->service->create($actor, $validated);

        return back()->with('success', 'Item pembiasaan berhasil ditambahkan.');
    }

    public function update(Request $request, HabitItem $habitItem): RedirectResponse
    {
        Gate::authorize('update', $habitItem);

        $validated = $request->validate([
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'name' => ['required', 'string', 'max:150'],
            'aspect' => ['required', Rule::enum(HabitAspect::class)],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $this->service->update($actor, $habitItem, $validated);

        return back()->with('success', 'Item pembiasaan berhasil diperbarui.');
    }

    public function toggle(Request $request, HabitItem $habitItem): RedirectResponse
    {
        Gate::authorize('toggle', $habitItem);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $this->service->toggleActive($actor, $habitItem, (bool) $validated['is_active']);

        return back()->with('success', $validated['is_active'] ? 'Item diaktifkan.' : 'Item dinonaktifkan.');
    }

    public function move(Request $request, HabitItem $habitItem): RedirectResponse
    {
        Gate::authorize('reorder', $habitItem);

        $validated = $request->validate([
            'direction' => ['required', Rule::in(['up', 'down'])],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $this->service->move($actor, $habitItem, (string) $validated['direction']);

        return back();
    }

    public function destroy(Request $request, HabitItem $habitItem): RedirectResponse
    {
        Gate::authorize('delete', $habitItem);

        /** @var User $actor */
        $actor = $request->user();
        $deleted = $this->service->delete($actor, $habitItem);

        return back()->with('success', $deleted ? 'Item pembiasaan dihapus.' : 'Item memiliki catatan sehingga dinonaktifkan.');
    }
}
