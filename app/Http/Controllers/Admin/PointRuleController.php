<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\PointCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Behavior\ImportPointRulesRequest;
use App\Http\Requests\Admin\Behavior\StorePointRuleRequest;
use App\Http\Requests\Admin\Behavior\UpdatePointRuleRequest;
use App\Models\PointRule;
use App\Models\Unit;
use App\Models\User;
use App\Services\Behavior\PointRuleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PointRuleController extends Controller
{
    public function __construct(
        private readonly PointRuleService $service,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', PointRule::class);

        $search = trim((string) $request->string('search'));
        $unitId = $request->filled('unit_id') ? $request->integer('unit_id') : null;
        $category = $request->string('category')->toString();
        $status = $request->string('status')->toString();

        $rules = PointRule::query()
            ->with('unit:id,code,name')
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($sub) use ($search): void {
                    $sub->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->when($unitId !== null, fn ($q) => $q->where('unit_id', $unitId))
            ->when($category !== '', fn ($q) => $q->where('category', $category))
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('category')
            ->orderBy('code')
            ->paginate(20)
            ->withQueryString();

        $items = $rules->getCollection()
            ->map(fn (PointRule $rule): array => [
                'id' => $rule->id,
                'unit_id' => $rule->unit_id,
                'unit_name' => $rule->unit->name ?? 'Semua unit',
                'code' => $rule->code,
                'name' => $rule->name,
                'category' => $rule->category->value,
                'category_label' => $rule->category->label(),
                'level' => $rule->level,
                'points' => $rule->points,
                'is_active' => $rule->is_active,
            ])
            ->values()
            ->all();

        $user = $request->user();

        return Inertia::render('admin/behavior/rules', [
            'rules' => [
                'data' => $items,
                'meta' => [
                    'current_page' => $rules->currentPage(),
                    'last_page' => $rules->lastPage(),
                    'per_page' => $rules->perPage(),
                    'total' => $rules->total(),
                    'from' => $rules->firstItem(),
                    'to' => $rules->lastItem(),
                ],
            ],
            'units' => Unit::query()->orderBy('name')->get(['id', 'code', 'name']),
            'categories' => array_map(
                static fn (PointCategory $cat): array => ['value' => $cat->value, 'label' => $cat->label()],
                PointCategory::cases()
            ),
            'filters' => [
                'search' => $search,
                'unit_id' => $unitId !== null ? (string) $unitId : '',
                'category' => $category,
                'status' => $status,
            ],
            'canManage' => $user instanceof User && $user->can('create', PointRule::class),
        ]);
    }

    public function store(StorePointRuleRequest $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $this->service->create($actor, $request->validated());

        return back()->with('success', 'Aturan poin berhasil ditambahkan.');
    }

    public function update(UpdatePointRuleRequest $request, PointRule $pointRule): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $this->service->update($actor, $pointRule, $request->validated());

        return back()->with('success', 'Aturan poin berhasil diperbarui.');
    }

    public function toggle(Request $request, PointRule $pointRule): RedirectResponse
    {
        Gate::authorize('toggle', $pointRule);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $this->service->toggleActive($actor, $pointRule, (bool) $validated['is_active']);

        return back()->with('success', $validated['is_active'] ? 'Aturan poin diaktifkan.' : 'Aturan poin dinonaktifkan.');
    }

    public function import(ImportPointRulesRequest $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $unitId = $request->filled('unit_id') ? $request->integer('unit_id') : null;
        $result = $this->service->import($actor, $request->file('file'), $unitId);

        $message = "Impor selesai: {$result['created']} dibuat, {$result['updated']} diperbarui.";
        if ($result['errors'] !== []) {
            return back()->with('warning', $message)->with('importErrors', $result['errors']);
        }

        return back()->with('success', $message);
    }

    public function template(): StreamedResponse
    {
        Gate::authorize('create', PointRule::class);

        return $this->service->downloadTemplate();
    }
}
