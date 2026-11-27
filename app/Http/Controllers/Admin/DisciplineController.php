<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\DisciplineFollowup;
use App\Models\DisciplineThreshold;
use App\Models\Unit;
use App\Models\User;
use App\Services\Behavior\DisciplineFollowupService;
use App\Services\Behavior\DisciplineThresholdService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DisciplineController extends Controller
{
    public function __construct(
        private readonly DisciplineThresholdService $thresholdService,
        private readonly DisciplineFollowupService $followupService,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', DisciplineFollowup::class);

        $status = $request->string('status')->toString() ?: 'open';
        $search = trim($request->string('search')->toString());
        $activeYear = AcademicYear::query()->where('is_active', true)->first();

        $followups = DisciplineFollowup::query()
            ->with([
                'student:id,nis,name',
                'threshold:id,points,action_name,notify_guardian',
                'doneByUser:id,name',
            ])
            ->when($activeYear !== null, fn ($q) => $q->where('academic_year_id', $activeYear->id))
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->whereHas('student', fn ($s) => $s->where(fn ($w) => $w->whereLike('name', "%{$search}%")->orWhereLike('nis', "%{$search}%"))))
            ->orderByRaw("case when status = 'open' then 0 else 1 end")
            ->orderByDesc('triggered_at')
            ->paginate(20)
            ->withQueryString();

        $followupItems = $followups->getCollection()
            ->map(fn (DisciplineFollowup $f): array => [
                'id' => $f->id,
                'student_name' => $f->student->name,
                'student_nis' => $f->student->nis,
                'threshold_points' => $f->threshold->points,
                'action_name' => $f->threshold->action_name,
                'notify_guardian' => $f->threshold->notify_guardian,
                'triggered_at' => $f->triggered_at->toDateTimeString(),
                'status' => $f->status,
                'note' => $f->note,
                'done_by_name' => $f->doneByUser->name ?? null,
            ])
            ->values()
            ->all();

        $thresholds = DisciplineThreshold::query()
            ->with('unit:id,code,name')
            ->orderBy('unit_id')
            ->orderBy('points')
            ->get()
            ->map(fn (DisciplineThreshold $t): array => [
                'id' => $t->id,
                'unit_id' => $t->unit_id,
                'unit_name' => $t->unit->name ?? 'Semua unit',
                'points' => $t->points,
                'action_name' => $t->action_name,
                'notify_guardian' => $t->notify_guardian,
                'is_active' => $t->is_active,
            ])
            ->all();

        $user = $request->user();

        return Inertia::render('admin/behavior/discipline', [
            'followups' => [
                'data' => $followupItems,
                'meta' => [
                    'current_page' => $followups->currentPage(),
                    'last_page' => $followups->lastPage(),
                    'per_page' => $followups->perPage(),
                    'total' => $followups->total(),
                    'from' => $followups->firstItem(),
                    'to' => $followups->lastItem(),
                ],
            ],
            'thresholds' => $thresholds,
            'units' => Unit::query()->orderBy('name')->get(['id', 'code', 'name']),
            'filters' => [
                'status' => $status,
                'search' => $search,
            ],
            'canManageThresholds' => $user instanceof User && $user->can('create', DisciplineThreshold::class),
            'canCompleteFollowups' => $user instanceof User && $user->can('complete', new DisciplineFollowup),
        ]);
    }

    public function storeThreshold(Request $request): RedirectResponse
    {
        Gate::authorize('create', DisciplineThreshold::class);

        $validated = $request->validate([
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'points' => ['required', 'integer', 'min:1', 'max:5000'],
            'action_name' => ['required', 'string', 'max:120'],
            'notify_guardian' => ['required', 'boolean'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $this->thresholdService->create($actor, $validated);

        return back()->with('success', 'Ambang batas disiplin berhasil ditambahkan.');
    }

    public function updateThreshold(Request $request, DisciplineThreshold $threshold): RedirectResponse
    {
        Gate::authorize('update', $threshold);

        $validated = $request->validate([
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'points' => ['required', 'integer', 'min:1', 'max:5000'],
            'action_name' => ['required', 'string', 'max:120'],
            'notify_guardian' => ['required', 'boolean'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $this->thresholdService->update($actor, $threshold, $validated);

        return back()->with('success', 'Ambang batas disiplin berhasil diperbarui.');
    }

    public function toggleThreshold(Request $request, DisciplineThreshold $threshold): RedirectResponse
    {
        Gate::authorize('toggle', $threshold);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $this->thresholdService->toggleActive($actor, $threshold, (bool) $validated['is_active']);

        return back()->with('success', $validated['is_active'] ? 'Ambang diaktifkan.' : 'Ambang dinonaktifkan.');
    }

    public function completeFollowup(Request $request, DisciplineFollowup $followup): RedirectResponse
    {
        Gate::authorize('complete', $followup);

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:1000'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $this->followupService->markDone($actor, $followup, (string) $validated['note']);

        return back()->with('success', 'Tindak lanjut ditandai selesai.');
    }
}
