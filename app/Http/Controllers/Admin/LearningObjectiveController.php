<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GradeLevel;
use App\Models\LearningObjective;
use App\Models\Subject;
use App\Models\Unit;
use App\Models\User;
use App\Services\Academic\LearningObjectiveService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Akademik > Tujuan Pembelajaran (TP) per mapel dan tingkat kelas.
 */
class LearningObjectiveController extends Controller
{
    public function __construct(private readonly LearningObjectiveService $service) {}

    public function index(Request $request): Response
    {
        $unitId = $request->integer('unit') ?: null;
        $subjectId = $request->integer('subject') ?: null;
        $grade = trim($request->string('grade')->toString());
        $search = trim($request->string('search')->toString());

        $paginator = LearningObjective::query()
            ->with('subject:id,unit_id,name,code')
            ->when($unitId !== null, fn (Builder $q) => $q->whereHas('subject', fn (Builder $s) => $s->where('unit_id', $unitId)))
            ->when($subjectId !== null, fn (Builder $q) => $q->where('subject_id', $subjectId))
            ->when($grade !== '', fn (Builder $q) => $q->where('grade', $grade))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $i) => $i->whereLike('code', "%{$search}%")->orWhereLike('description', "%{$search}%")))
            ->orderBy('subject_id')->orderBy('grade')->orderBy('code')->orderBy('id')
            ->paginate(25)->withQueryString();

        $subjects = Subject::query()->with('unit:id,code')->where('is_active', true)
            ->when($unitId !== null, fn (Builder $q) => $q->where('unit_id', $unitId))
            ->orderBy('unit_id')->orderBy('name')->get(['id', 'unit_id', 'name']);

        return Inertia::render('admin/academic/learning-objectives', [
            'rows' => [
                'data' => array_values(array_map(fn (LearningObjective $o): array => [
                    'id' => $o->id, 'subject_id' => $o->subject_id, 'subject_name' => $o->subject->name,
                    'grade' => $o->grade, 'code' => $o->code, 'description' => $o->description,
                ], $paginator->items())),
                'meta' => [
                    'current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(), 'from' => $paginator->firstItem(), 'to' => $paginator->lastItem(),
                ],
            ],
            'filters' => ['search' => $search, 'unit' => $unitId, 'subject' => $subjectId, 'grade' => $grade === '' ? null : $grade],
            'unitOptions' => array_values(Unit::query()->where('is_active', true)->orderBy('id')->get(['id', 'name'])
                ->map(fn (Unit $u): array => ['value' => (string) $u->id, 'label' => $u->name])->all()),
            'subjectOptions' => array_values($subjects->map(fn (Subject $s): array => ['value' => (string) $s->id, 'label' => "{$s->name} ({$s->unit->code})"])->all()),
            'gradeOptions' => array_values(GradeLevel::query()
                ->when($unitId !== null, fn (Builder $q) => $q->where('unit_id', $unitId))
                ->orderBy('sort_order')->orderBy('name')->pluck('name')->unique()
                ->map(fn (mixed $g): array => ['value' => (string) $g, 'label' => 'Kelas '.$g])->values()->all()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->service->save(null, $this->data($request, null), $this->actor($request));

        return $this->done('Tujuan pembelajaran ditambahkan.');
    }

    public function update(Request $request, LearningObjective $objective): RedirectResponse
    {
        $this->service->save($objective, $this->data($request, $objective), $this->actor($request));

        return $this->done('Tujuan pembelajaran diperbarui.');
    }

    public function destroy(Request $request, LearningObjective $objective): RedirectResponse
    {
        $this->service->delete($objective, $this->actor($request));

        return $this->done('Tujuan pembelajaran dihapus.');
    }

    /**
     * @return array{subject_id: int, grade: string, code: string, description: string}
     */
    private function data(Request $request, ?LearningObjective $objective): array
    {
        $data = $request->validate([
            'subject_id' => ['required', 'integer', Rule::exists('subjects', 'id')],
            'grade' => ['required', 'string', 'max:20'],
            'code' => ['required', 'string', 'max:30', Rule::unique('learning_objectives', 'code')
                ->where('subject_id', $request->integer('subject_id'))
                ->where('grade', trim($request->string('grade')->toString()))
                ->ignore($objective?->id)],
            'description' => ['required', 'string', 'max:2000'],
        ], [], ['subject_id' => 'mata pelajaran', 'grade' => 'tingkat kelas', 'code' => 'kode TP', 'description' => 'deskripsi target']);

        return [
            'subject_id' => (int) $data['subject_id'],
            'grade' => trim((string) $data['grade']),
            'code' => trim((string) $data['code']),
            'description' => trim((string) $data['description']),
        ];
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
