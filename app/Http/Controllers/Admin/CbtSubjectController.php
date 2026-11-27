<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SubjectRequest;
use App\Http\Requests\Admin\UpdateSubjectStatusRequest;
use App\Http\Resources\SubjectResource;
use App\Http\Resources\TeachingAssignmentResource;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\Unit;
use App\Models\User;
use App\Services\Cbt\SubjectService;
use App\Services\Cbt\TeachingAssignmentService;
use App\Support\SchoolContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CbtSubjectController extends Controller
{
    public function __construct(private readonly SubjectService $service) {}

    public function index(Request $request): Response
    {
        $actor = $this->actor($request);
        $canManage = $actor->can(Permission::CbtManage->value);

        $units = Unit::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']);
        $years = AcademicYear::query()->orderByDesc('name')->orderByDesc('semester')->get();
        $context = SchoolContext::resolve($request->session());

        $requestedUnit = $units->first(fn (Unit $unit): bool => $unit->id === $request->integer('unit_id'))
            ?? $units->first(fn (Unit $unit): bool => $unit->code === $context['unit']);
        $requestedYear = $years->first(fn (AcademicYear $year): bool => $year->id === $request->integer('academic_year_id'));
        $unitId = $requestedUnit?->id;
        $yearId = $requestedYear !== null ? $requestedYear->id : $context['academicYearId'];
        $search = trim((string) $request->query('search', ''));

        $subjects = Subject::query()
            ->visibleTo($actor)
            ->with('unit')
            ->withCount([
                'teachingAssignments' => fn (Builder $query) => $query->when(
                    $yearId !== null,
                    fn (Builder $inner) => $inner->where('academic_year_id', $yearId),
                ),
            ])
            ->when(
                $unitId === null,
                fn (Builder $query) => $query->whereKey([]),
                fn (Builder $query) => $query->where('unit_id', $unitId),
            )
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->whereLike('name', "%{$search}%")
                        ->orWhereLike('code', "%{$search}%");
                });
            })
            ->orderBy('code')
            ->orderBy('id')
            ->paginate(25, ['*'], 'subjects_page')
            ->withQueryString();

        $assignments = TeachingAssignment::query()
            ->visibleTo($actor)
            ->with(['teacher', 'subject', 'classroom', 'academicYear'])
            ->when(
                $unitId === null || $yearId === null,
                fn (Builder $query) => $query->whereKey([]),
                fn (Builder $query) => $query
                    ->where('academic_year_id', $yearId)
                    ->whereHas('classroom', fn (Builder $inner) => $inner->where('unit_id', $unitId)),
            )
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->whereHas('teacher', fn (Builder $q) => $q->whereLike('name', "%{$search}%"))
                        ->orWhereHas('subject', fn (Builder $q) => $q->whereLike('name', "%{$search}%")->orWhereLike('code', "%{$search}%"))
                        ->orWhereHas('classroom', fn (Builder $q) => $q->whereLike('name', "%{$search}%"));
                });
            })
            ->orderBy('id')
            ->paginate(25, ['*'], 'assignments_page')
            ->withQueryString();

        $subjectOptions = $canManage && $unitId !== null
            ? Subject::query()
                ->where('unit_id', $unitId)
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name'])
                ->map(fn (Subject $subject): array => [
                    'id' => $subject->id,
                    'code' => $subject->code,
                    'name' => $subject->name,
                ])
                ->values()
            : collect();

        $classroomOptions = $canManage && $unitId !== null && $yearId !== null
            ? Classroom::query()
                ->where('unit_id', $unitId)
                ->where('academic_year_id', $yearId)
                ->where('is_active', true)
                ->orderBy('grade')
                ->orderBy('name')
                ->get(['id', 'name', 'grade'])
                ->map(fn (Classroom $classroom): array => [
                    'id' => $classroom->id,
                    'name' => $classroom->name,
                    'grade' => $classroom->grade,
                ])
                ->values()
            : collect();

        $teacherOptions = $canManage
            ? TeachingAssignmentService::teacherCandidates()
                ->orderBy('name')
                ->orderBy('id')
                ->get(['id', 'name'])
                ->map(fn (User $teacher): array => ['id' => $teacher->id, 'name' => $teacher->name])
                ->values()
            : collect();

        return Inertia::render('admin/cbt/subjects/index', [
            'canManage' => $canManage,
            'subjects' => SubjectResource::collection($subjects),
            'assignments' => TeachingAssignmentResource::collection($assignments),
            'filters' => [
                'unit_id' => $unitId,
                'academic_year_id' => $yearId,
                'search' => $search,
            ],
            'unitOptions' => $units->map(fn (Unit $unit): array => [
                'id' => $unit->id,
                'code' => $unit->code,
                'name' => $unit->name,
            ])->values(),
            'academicYearOptions' => $years->map(fn (AcademicYear $year): array => [
                'id' => $year->id,
                'label' => $year->label(),
                'is_active' => $year->is_active,
            ])->values(),
            'subjectOptions' => $subjectOptions,
            'classroomOptions' => $classroomOptions,
            'teacherOptions' => $teacherOptions,
        ]);
    }

    public function store(SubjectRequest $request): RedirectResponse
    {
        $this->service->create($request->subjectData(), $this->actor($request));

        return $this->done('success', 'Mata pelajaran berhasil ditambahkan.');
    }

    public function update(SubjectRequest $request, Subject $subject): RedirectResponse
    {
        $data = $request->subjectData();
        $this->service->update($subject, ['code' => $data['code'], 'name' => $data['name']], $this->actor($request));

        return $this->done('success', 'Mata pelajaran berhasil diperbarui.');
    }

    public function updateStatus(UpdateSubjectStatusRequest $request, Subject $subject): RedirectResponse
    {
        $active = $request->boolean('is_active');
        $this->service->setActive($subject, $active, $this->actor($request));

        return $this->done('success', $active ? 'Mata pelajaran diaktifkan kembali.' : 'Mata pelajaran dinonaktifkan.');
    }

    private function done(string $type, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);

        return back();
    }

    private function actor(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
