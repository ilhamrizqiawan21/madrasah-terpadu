<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ExamDeviceMode;
use App\Enums\ExamStatus;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ExamRequest;
use App\Http\Requests\Admin\UpdateExamStatusRequest;
use App\Http\Resources\ExamResource;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\Subject;
use App\Models\Unit;
use App\Models\User;
use App\Services\Cbt\ExamService;
use App\Support\SchoolContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CbtExamController extends Controller
{
    public function __construct(private readonly ExamService $service) {}

    public function index(Request $request): Response
    {
        $actor = $this->actor($request);
        $canManageAll = $actor->can(Permission::CbtManage->value);
        $canCreate = $canManageAll || $actor->can(Permission::CbtTeach->value);

        $units = Unit::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']);
        $context = SchoolContext::resolve($request->session());
        $activeYear = AcademicYear::query()->where('is_active', true)->first();

        $requestedUnit = $units->first(fn (Unit $unit): bool => $unit->id === $request->integer('unit_id'))
            ?? $units->first(fn (Unit $unit): bool => $unit->code === $context['unit']);
        $unitId = $requestedUnit?->id;
        $search = trim((string) $request->query('search', ''));

        $exams = Exam::query()
            ->visibleTo($actor)
            ->with(['subject', 'creator'])
            ->when(
                $unitId === null,
                fn (Builder $q) => $q->whereKey([]),
                fn (Builder $q) => $q->whereHas('subject', fn (Builder $s) => $s->where('unit_id', $unitId)),
            )
            ->when($search !== '', fn (Builder $q) => $q->whereLike('title', "%{$search}%"))
            ->orderByDesc('opens_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $subjectOptions = $unitId !== null
            ? Subject::query()
                ->visibleTo($actor)
                ->where('unit_id', $unitId)
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name'])
                ->map(fn (Subject $s): array => ['id' => $s->id, 'code' => $s->code, 'name' => $s->name])
                ->values()
            : collect();

        $classroomOptions = $unitId !== null && $activeYear !== null
            ? Classroom::query()
                ->where('unit_id', $unitId)
                ->where('academic_year_id', $activeYear->id)
                ->where('is_active', true)
                ->when(! $canManageAll, fn (Builder $q) => $q->whereHas(
                    'teachingAssignments',
                    fn (Builder $a) => $a->where('user_id', $actor->id),
                ))
                ->orderBy('grade')
                ->orderBy('name')
                ->get(['id', 'name', 'grade'])
                ->map(fn (Classroom $c): array => ['id' => $c->id, 'name' => $c->name, 'grade' => $c->grade])
                ->values()
            : collect();

        return Inertia::render('admin/cbt/exams/index', [
            'canCreate' => $canCreate,
            'exams' => ExamResource::collection($exams),
            'filters' => [
                'unit_id' => $unitId,
                'search' => $search,
            ],
            'unitOptions' => $units->map(fn (Unit $u): array => ['id' => $u->id, 'code' => $u->code, 'name' => $u->name])->values(),
            'subjectOptions' => $subjectOptions,
            'classroomOptions' => $classroomOptions,
            'deviceModeOptions' => ExamDeviceMode::options(),
        ]);
    }

    public function store(ExamRequest $request): RedirectResponse
    {
        $this->service->create($request->examData(), $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ujian berhasil dibuat.']);

        return back();
    }

    public function update(ExamRequest $request, Exam $exam): RedirectResponse
    {
        $this->service->update($exam, $request->examData(), $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengaturan ujian berhasil diperbarui.']);

        return back();
    }

    public function updateStatus(UpdateExamStatusRequest $request, Exam $exam): RedirectResponse
    {
        $status = ExamStatus::from((string) $request->validated('status'));
        $this->service->updateStatus($exam, $status, $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => "Status ujian diubah menjadi {$status->label()}."]);

        return back();
    }

    private function actor(Request $request): User
    {
        $user = $request->user('web');

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
