<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MemorizationMaterial;
use App\Models\Unit;
use App\Models\User;
use App\Services\Quran\MemorizationMaterialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MemorizationMaterialController extends Controller
{
    public function __construct(
        private readonly MemorizationMaterialService $service,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', MemorizationMaterial::class);

        $kind = $request->string('kind')->toString();
        $unitId = $request->filled('unit_id') ? $request->integer('unit_id') : null;

        $materials = MemorizationMaterial::query()
            ->with('unit:id,code,name')
            ->when($kind !== '', fn ($q) => $q->where('kind', $kind))
            ->when($unitId !== null, fn ($q) => $q->where('unit_id', $unitId))
            ->orderBy('kind')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        $items = $materials->getCollection()
            ->map(fn (MemorizationMaterial $m): array => [
                'id' => $m->id,
                'unit_id' => $m->unit_id,
                'unit_name' => $m->unit->name ?? 'Semua unit',
                'kind' => $m->kind,
                'kind_label' => $m->kind === 'hadith' ? 'Hadits' : "Do'a",
                'title' => $m->title,
                'arabic_text' => $m->arabic_text,
                'translation' => $m->translation,
                'source' => $m->source,
                'sort_order' => $m->sort_order,
                'is_active' => $m->is_active,
            ])
            ->values()
            ->all();

        $user = $request->user();

        return Inertia::render('admin/quran/materials', [
            'materials' => [
                'data' => $items,
                'meta' => [
                    'current_page' => $materials->currentPage(),
                    'last_page' => $materials->lastPage(),
                    'per_page' => $materials->perPage(),
                    'total' => $materials->total(),
                    'from' => $materials->firstItem(),
                    'to' => $materials->lastItem(),
                ],
            ],
            'units' => Unit::query()->orderBy('name')->get(['id', 'code', 'name']),
            'filters' => [
                'kind' => $kind,
                'unit_id' => $unitId !== null ? (string) $unitId : '',
            ],
            'canManage' => $user instanceof User && $user->can('create', MemorizationMaterial::class),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', MemorizationMaterial::class);

        $validated = $request->validate([
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'kind' => ['required', Rule::in(['hadith', 'prayer'])],
            'title' => ['required', 'string', 'max:180'],
            'arabic_text' => ['nullable', 'string'],
            'translation' => ['nullable', 'string'],
            'source' => ['nullable', 'string', 'max:150'],
            'sort_order' => ['nullable', 'integer', 'min:1', 'max:5000'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $this->service->create($actor, $validated);

        return back()->with('success', 'Materi hafalan berhasil ditambahkan.');
    }

    public function update(Request $request, MemorizationMaterial $material): RedirectResponse
    {
        Gate::authorize('update', $material);

        $validated = $request->validate([
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'kind' => ['required', Rule::in(['hadith', 'prayer'])],
            'title' => ['required', 'string', 'max:180'],
            'arabic_text' => ['nullable', 'string'],
            'translation' => ['nullable', 'string'],
            'source' => ['nullable', 'string', 'max:150'],
            'sort_order' => ['nullable', 'integer', 'min:1', 'max:5000'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $this->service->update($actor, $material, $validated);

        return back()->with('success', 'Materi hafalan berhasil diperbarui.');
    }

    public function toggle(Request $request, MemorizationMaterial $material): RedirectResponse
    {
        Gate::authorize('toggle', $material);

        $validated = $request->validate(['is_active' => ['required', 'boolean']]);

        /** @var User $actor */
        $actor = $request->user();
        $this->service->toggleActive($actor, $material, (bool) $validated['is_active']);

        return back()->with('success', $validated['is_active'] ? 'Materi diaktifkan.' : 'Materi dinonaktifkan.');
    }

    public function destroy(Request $request, MemorizationMaterial $material): RedirectResponse
    {
        Gate::authorize('delete', $material);

        /** @var User $actor */
        $actor = $request->user();
        $deleted = $this->service->delete($actor, $material);

        return back()->with('success', $deleted ? 'Materi dihapus.' : 'Materi memiliki setoran sehingga dinonaktifkan.');
    }
}
