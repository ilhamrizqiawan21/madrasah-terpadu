<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Subject;
use App\Models\User;
use App\Services\Elearning\AssignmentService;
use App\Support\AppTimezone;
use App\Support\SchoolContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * E-Learning > Tugas & PR per mapel dan rombel (T-1603).
 */
class AssignmentController extends Controller
{
    public function __construct(
        private readonly AssignmentService $service,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Assignment::class);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $context = SchoolContext::resolve($request->session());
        $activeYearId = $context['academicYearId'] ?? AcademicYear::query()->where('is_active', true)->value('id');

        $classroomId = $request->integer('classroom_id') ?: null;
        $subjectId = $request->integer('subject_id') ?: null;
        $search = trim($request->string('search')->toString());

        $isAdmin = $user->hasRole(Role::Admin->value);
        $isTeacher = $user->hasRole(Role::Teacher->value);

        $paginator = Assignment::query()
            ->with(['subject:id,unit_id,name', 'classroom:id,unit_id,name', 'teacher:id,name'])
            ->when($classroomId !== null, fn (Builder $q) => $q->where('classroom_id', $classroomId))
            ->when($subjectId !== null, fn (Builder $q) => $q->where('subject_id', $subjectId))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $sq) => $sq
                ->where('title', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")))
            ->orderByDesc('due_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Assignment $a): array => [
                'id' => $a->id,
                'subject_id' => $a->subject_id,
                'subject_name' => $a->subject->name,
                'classroom_id' => $a->classroom_id,
                'classroom_name' => $a->classroom->name,
                'teacher_id' => $a->teacher_id,
                'teacher_name' => $a->teacher->name,
                'title' => $a->title,
                'description' => $a->description,
                'due_at' => $a->due_at->setTimezone(AppTimezone::name())->toIso8601String(),
                'due_date' => $a->due_at->setTimezone(AppTimezone::name())->toDateString(),
                'due_time' => $a->due_at->setTimezone(AppTimezone::name())->format('H:i'),
                'has_attachment' => $a->attachment_path !== null,
                'can_edit' => $isAdmin || ($isTeacher && $a->teacher_id === $user->id),
            ]);

        $subjects = Subject::query()
            ->with('unit:id,code')
            ->where('is_active', true)
            ->orderBy('unit_id')
            ->orderBy('name')
            ->get(['id', 'unit_id', 'name'])
            ->map(fn (Subject $s): array => ['id' => $s->id, 'unit_id' => $s->unit_id, 'label' => "{$s->name} ({$s->unit->code})"])
            ->values()
            ->all();

        $classrooms = Classroom::query()
            ->with('unit:id,code')
            ->where('is_active', true)
            ->when($activeYearId !== null, fn (Builder $q) => $q->where('academic_year_id', $activeYearId))
            ->orderBy('unit_id')
            ->orderBy('grade')
            ->orderBy('name')
            ->get(['id', 'unit_id', 'name'])
            ->map(fn (Classroom $c): array => ['id' => $c->id, 'unit_id' => $c->unit_id, 'label' => "{$c->name} ({$c->unit->code})"])
            ->values()
            ->all();

        return Inertia::render('admin/elearning/assignments', [
            'assignments' => $paginator,
            'subjects' => $subjects,
            'classrooms' => $classrooms,
            'filters' => [
                'classroom_id' => $classroomId !== null ? (string) $classroomId : '',
                'subject_id' => $subjectId !== null ? (string) $subjectId : '',
                'search' => $search,
            ],
            'canCreate' => $user->can('create', Assignment::class),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Assignment::class);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $this->validatePayload($request);
        $this->service->save(null, $validated, $request->file('attachment'), $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tugas berhasil ditambahkan.']);

        return back();
    }

    public function update(Request $request, Assignment $assignment): RedirectResponse
    {
        Gate::authorize('update', $assignment);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $this->validatePayload($request);
        $this->service->save($assignment, $validated, $request->file('attachment'), $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tugas berhasil diperbarui.']);

        return back();
    }

    public function destroy(Request $request, Assignment $assignment): RedirectResponse
    {
        Gate::authorize('delete', $assignment);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $this->service->delete($assignment, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tugas berhasil dihapus.']);

        return back();
    }

    public function attachment(Assignment $assignment): StreamedResponse
    {
        Gate::authorize('download', $assignment);

        abort_if($assignment->attachment_path === null || ! Storage::disk('local')->exists($assignment->attachment_path), 404);

        return Storage::disk('local')->download($assignment->attachment_path);
    }

    /**
     * @return array{subject_id: int, classroom_id: int, title: string, description: ?string, due_date: string, due_time: string}
     */
    private function validatePayload(Request $request): array
    {
        /** @var array{subject_id: int, classroom_id: int, title: string, description?: ?string, due_date: string, due_time: string} $validated */
        $validated = $request->validate(
            [
                'subject_id' => ['required', 'integer', 'exists:subjects,id'],
                'classroom_id' => ['required', 'integer', 'exists:classrooms,id'],
                'title' => ['required', 'string', 'max:180'],
                'description' => ['nullable', 'string', 'max:3000'],
                'due_date' => ['required', 'date'],
                'due_time' => ['required', 'date_format:H:i'],
                'attachment' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:2048'],
            ],
            [],
            [
                'subject_id' => 'mata pelajaran',
                'classroom_id' => 'rombel',
                'title' => 'judul tugas',
                'description' => 'deskripsi',
                'due_date' => 'tanggal tenggat',
                'due_time' => 'jam tenggat',
                'attachment' => 'lampiran',
            ],
        );

        return [
            'subject_id' => (int) $validated['subject_id'],
            'classroom_id' => (int) $validated['classroom_id'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'due_date' => $validated['due_date'],
            'due_time' => $validated['due_time'],
        ];
    }
}
