<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Rubric;
use App\Models\RubricVersion;
use App\Models\User;
use App\Services\Quran\RubricService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RubricController extends Controller
{
    public function __construct(
        private readonly RubricService $service,
    ) {}

    public function index(Request $request): Response
    {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless($actor->can(Permission::QuranManage->value), 403);

        $versions = RubricVersion::query()
            ->with(['rubric:id,name,unit_id', 'criteria'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (RubricVersion $v): array => [
                'id' => $v->id,
                'rubric_id' => $v->rubric_id,
                'rubric_name' => $v->rubric->name,
                'version_number' => $v->version_number,
                'pass_threshold' => $v->pass_threshold,
                'published_at' => $v->published_at?->toDateTimeString(),
                'criteria' => $v->criteria->map(fn ($c): array => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'mode' => $c->mode,
                    'weight' => $c->weight,
                    'max_score' => $c->max_score,
                    'deduction_per_error' => $c->deduction_per_error,
                ])->values()->all(),
            ])
            ->all();

        return Inertia::render('admin/quran/rubrics', [
            'versions' => $versions,
        ]);
    }

    public function publish(Request $request, RubricVersion $version): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless($actor->can(Permission::QuranManage->value), 403);

        $this->service->publish($actor, $version);

        return back()->with('success', 'Versi rubrik diterbitkan dan sekarang siap dipakai untuk setoran.');
    }

    public function createDraft(Request $request, Rubric $rubric): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless($actor->can(Permission::QuranManage->value), 403);

        $validated = $request->validate([
            'pass_threshold' => ['required', 'numeric', 'min:1', 'max:100'],
            'criteria' => ['required', 'array', 'min:1'],
            'criteria.*.name' => ['required', 'string', 'max:100'],
            'criteria.*.mode' => ['required', 'in:direct,deduction'],
            'criteria.*.weight' => ['required', 'numeric', 'min:1', 'max:100'],
            'criteria.*.max_score' => ['required', 'integer', 'min:1', 'max:1000'],
            'criteria.*.deduction_per_error' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        /** @var list<array{name: string, mode: string, weight: float|int, max_score: int, deduction_per_error: float|int}> $criteria */
        $criteria = $validated['criteria'];
        $this->service->createDraftVersion($actor, $rubric, (float) $validated['pass_threshold'], $criteria);

        return back()->with('success', 'Draf versi rubrik baru berhasil dibuat.');
    }
}
