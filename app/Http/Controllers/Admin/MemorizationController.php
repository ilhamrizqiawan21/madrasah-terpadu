<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\MemorizationKind;
use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\MemorizationMaterial;
use App\Models\MemorizationRecord;
use App\Models\RubricVersion;
use App\Models\Student;
use App\Models\Surah;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\Quran\MemorizationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MemorizationController extends Controller
{
    public function __construct(
        private readonly MemorizationService $service,
    ) {}

    public function index(Request $request): Response
    {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless($actor->can(Permission::QuranView->value), 403);

        $activeYear = AcademicYear::query()->where('is_active', true)->first();
        $allowedClassroomIds = $this->allowedClassroomIds($actor, $activeYear?->id);

        $students = $activeYear !== null
            ? Enrollment::query()
                ->with(['student:id,nis,name', 'classroom:id,name'])
                ->where('academic_year_id', $activeYear->id)
                ->when($allowedClassroomIds !== null, fn ($q) => $q->whereIn('classroom_id', $allowedClassroomIds))
                ->get()
                ->map(fn (Enrollment $e): array => [
                    'id' => $e->student_id,
                    'nis' => $e->student->nis,
                    'name' => $e->student->name,
                    'classroom_name' => $e->classroom->name,
                ])
                ->sortBy('name')
                ->values()
                ->all()
            : [];

        $publishedVersions = RubricVersion::query()
            ->with(['rubric:id,name', 'criteria'])
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->get()
            ->map(fn (RubricVersion $v): array => [
                'id' => $v->id,
                'label' => "{$v->rubric->name} v{$v->version_number}",
                'pass_threshold' => $v->pass_threshold,
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

        $recentRecords = MemorizationRecord::query()
            ->with(['student:id,nis,name', 'material:id,title'])
            ->orderByDesc('recorded_on')
            ->orderByDesc('id')
            ->limit(25)
            ->get()
            ->map(fn (MemorizationRecord $r): array => [
                'id' => $r->id,
                'student_name' => $r->student->name,
                'kind' => $r->kind,
                'kind_label' => MemorizationKind::from($r->kind)->label(),
                'type' => $r->type,
                'summary' => $r->kind === 'quran'
                    ? "QS {$r->surah_from}:{$r->ayah_from} – {$r->surah_to}:{$r->ayah_to}"
                    : ($r->material->title ?? '—'),
                'final_score' => $r->final_score,
                'predicate' => $r->predicate,
                'passed' => $r->passed,
                'recorded_on' => $r->recorded_on->toDateString(),
            ])
            ->all();

        $timezone = config('sekolah.timezone', 'Asia/Makassar');
        $today = CarbonImmutable::now(is_string($timezone) ? $timezone : 'Asia/Makassar')->toDateString();

        return Inertia::render('admin/quran/index', [
            'students' => $students,
            'rubricVersions' => $publishedVersions,
            'surahs' => Surah::query()->orderBy('number')->get(['number', 'name_latin', 'ayah_count']),
            'materials' => MemorizationMaterial::query()->active()->orderBy('sort_order')->get(['id', 'kind', 'title']),
            'recentRecords' => $recentRecords,
            'today' => $today,
            'canRecord' => $actor->can(Permission::QuranRecord->value),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless($actor->can(Permission::QuranRecord->value), 403);

        $validated = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'rubric_version_id' => ['required', 'integer', 'exists:rubric_versions,id'],
            'kind' => ['required', Rule::enum(MemorizationKind::class)],
            'type' => ['nullable', 'string'],
            'memorization_material_id' => ['nullable', 'integer'],
            'surah_from' => ['nullable', 'integer'],
            'ayah_from' => ['nullable', 'integer'],
            'surah_to' => ['nullable', 'integer'],
            'ayah_to' => ['nullable', 'integer'],
            'recorded_on' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
            'scores' => ['required', 'array'],
        ]);

        $student = Student::query()->findOrFail((int) $validated['student_id']);
        $this->authorizeStudent($actor, $student);

        $version = RubricVersion::query()->findOrFail((int) $validated['rubric_version_id']);
        $record = $this->service->record($actor, $student, $version, $validated);

        return back()->with(
            'success',
            "Setoran {$student->name} tersimpan: nilai {$record->final_score} ({$record->predicate})."
        );
    }

    /**
     * @return list<int>|null
     */
    private function allowedClassroomIds(User $actor, ?int $academicYearId): ?array
    {
        if ($actor->hasAnyRole([Role::Admin->value, Role::UnitHead->value, Role::Counselor->value])) {
            return null;
        }

        $homeroomIds = Classroom::query()
            ->where('homeroom_teacher_id', $actor->id)
            ->when($academicYearId !== null, fn ($q) => $q->where('academic_year_id', $academicYearId))
            ->pluck('id')
            ->all();

        $assignedIds = TeachingAssignment::query()
            ->where('user_id', $actor->id)
            ->when($academicYearId !== null, fn ($q) => $q->where('academic_year_id', $academicYearId))
            ->pluck('classroom_id')
            ->all();

        return array_values(array_unique(array_map('intval', array_merge($homeroomIds, $assignedIds))));
    }

    private function authorizeStudent(User $actor, Student $student): void
    {
        $activeYearId = AcademicYear::query()->where('is_active', true)->value('id');
        $allowedClassroomIds = $this->allowedClassroomIds($actor, $activeYearId !== null ? (int) $activeYearId : null);

        if ($allowedClassroomIds === null) {
            return;
        }

        $isInAllowedClassroom = $allowedClassroomIds !== []
            && Enrollment::query()
                ->where('student_id', $student->id)
                ->when($activeYearId !== null, fn ($q) => $q->where('academic_year_id', $activeYearId))
                ->whereIn('classroom_id', $allowedClassroomIds)
                ->exists();

        abort_unless($isInAllowedClassroom, 403);
    }
}
