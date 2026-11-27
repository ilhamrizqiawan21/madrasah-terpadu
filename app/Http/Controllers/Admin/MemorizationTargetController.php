<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\MemorizationKind;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\MemorizationMaterial;
use App\Models\MemorizationTarget;
use App\Models\Surah;
use App\Models\Unit;
use App\Models\User;
use App\Services\Quran\MemorizationTargetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MemorizationTargetController extends Controller
{
    public function __construct(
        private readonly MemorizationTargetService $service,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', MemorizationTarget::class);

        $unitId = $request->filled('unit_id') ? $request->integer('unit_id') : null;
        $kind = $request->string('kind')->toString();

        $targets = MemorizationTarget::query()
            ->with(['unit:id,code,name', 'academicYear'])
            ->when($unitId !== null, fn ($q) => $q->where('unit_id', $unitId))
            ->when($kind !== '', fn ($q) => $q->where('kind', $kind))
            ->orderByDesc('academic_year_id')
            ->orderBy('unit_id')
            ->orderBy('grade')
            ->paginate(20)
            ->withQueryString();

        $items = $targets->getCollection()
            ->map(fn (MemorizationTarget $t): array => [
                'id' => $t->id,
                'unit_id' => $t->unit_id,
                'unit_code' => $t->unit->code,
                'grade' => $t->grade,
                'academic_year_id' => $t->academic_year_id,
                'academic_year_label' => $t->academicYear->label(),
                'kind' => $t->kind,
                'kind_label' => MemorizationKind::from($t->kind)->label(),
                'title' => $t->title,
                'ranges' => $t->ranges ?? [],
                'material_ids' => $t->material_ids ?? [],
                'is_active' => $t->is_active,
            ])
            ->values()
            ->all();

        $user = $request->user();

        return Inertia::render('admin/quran/targets', [
            'targets' => [
                'data' => $items,
                'meta' => [
                    'current_page' => $targets->currentPage(),
                    'last_page' => $targets->lastPage(),
                    'per_page' => $targets->perPage(),
                    'total' => $targets->total(),
                    'from' => $targets->firstItem(),
                    'to' => $targets->lastItem(),
                ],
            ],
            'units' => Unit::query()->orderBy('name')->get(['id', 'code', 'name']),
            'academicYears' => AcademicYear::query()->orderByDesc('id')->get()->map(fn (AcademicYear $y): array => [
                'id' => $y->id,
                'label' => $y->label(),
                'is_active' => $y->is_active,
            ])->all(),
            'surahs' => Surah::query()->orderBy('number')->get(['number', 'name_latin', 'ayah_count']),
            'materials' => MemorizationMaterial::query()->active()->orderBy('sort_order')->get(['id', 'kind', 'title']),
            'filters' => [
                'unit_id' => $unitId !== null ? (string) $unitId : '',
                'kind' => $kind,
            ],
            'canManage' => $user instanceof User && $user->can('create', MemorizationTarget::class),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', MemorizationTarget::class);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'grade' => ['required', 'string', 'max:10'],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'kind' => ['required', Rule::enum(MemorizationKind::class)],
            'title' => ['required', 'string', 'max:180'],
            'ranges' => ['nullable', 'array'],
            'ranges.*.surah' => ['required_with:ranges', 'integer'],
            'ranges.*.ayah_from' => ['required_with:ranges', 'integer'],
            'ranges.*.ayah_to' => ['required_with:ranges', 'integer'],
            'material_ids' => ['nullable', 'array'],
            'material_ids.*' => ['integer'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $this->service->create($actor, $validated);

        return back()->with('success', 'Target hafalan berhasil ditambahkan.');
    }

    public function update(Request $request, MemorizationTarget $target): RedirectResponse
    {
        Gate::authorize('update', $target);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'grade' => ['required', 'string', 'max:10'],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'kind' => ['required', Rule::enum(MemorizationKind::class)],
            'title' => ['required', 'string', 'max:180'],
            'ranges' => ['nullable', 'array'],
            'ranges.*.surah' => ['required_with:ranges', 'integer'],
            'ranges.*.ayah_from' => ['required_with:ranges', 'integer'],
            'ranges.*.ayah_to' => ['required_with:ranges', 'integer'],
            'material_ids' => ['nullable', 'array'],
            'material_ids.*' => ['integer'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $this->service->update($actor, $target, $validated);

        return back()->with('success', 'Target hafalan berhasil diperbarui.');
    }

    public function toggle(Request $request, MemorizationTarget $target): RedirectResponse
    {
        Gate::authorize('toggle', $target);

        $validated = $request->validate(['is_active' => ['required', 'boolean']]);

        /** @var User $actor */
        $actor = $request->user();
        $this->service->toggleActive($actor, $target, (bool) $validated['is_active']);

        return back()->with('success', $validated['is_active'] ? 'Target diaktifkan.' : 'Target dinonaktifkan.');
    }
}
