<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Extracurricular;
use App\Models\ExtracurricularAttendance;
use App\Models\ExtracurricularMember;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Services\Academic\ExtracurricularService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Akademik > Ekstrakurikuler (master) dan Anggota & Nilai Ekskul.
 */
class ExtracurricularController extends Controller
{
    public function __construct(private readonly ExtracurricularService $service) {}

    public function index(Request $request): Response
    {
        $unitId = $request->integer('unit') ?: null;
        $search = trim($request->string('search')->toString());

        $paginator = Extracurricular::query()
            ->with(['unit:id,name', 'coach:id,name'])
            ->withCount('members')
            ->when($unitId !== null, fn (Builder $q) => $q->where('unit_id', $unitId))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $i) => $i->whereLike('name', "%{$search}%")->orWhereLike('coach_name', "%{$search}%")))
            ->orderBy('unit_id')->orderBy('name')->orderBy('id')
            ->paginate(25)->withQueryString();

        return Inertia::render('admin/academic/extracurriculars', [
            'rows' => [
                'data' => array_values(array_map(fn (Extracurricular $e): array => [
                    'id' => $e->id, 'unit_id' => $e->unit_id, 'unit_name' => $e->unit->name, 'name' => $e->name,
                    'coach_type' => $e->coach_type, 'coach_user_id' => $e->coach_user_id, 'coach_name' => $e->coach_name,
                    'coach_label' => $e->coachLabel(), 'members_count' => $e->members_count,
                ], $paginator->items())),
                'meta' => [
                    'current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(), 'from' => $paginator->firstItem(), 'to' => $paginator->lastItem(),
                ],
            ],
            'filters' => ['search' => $search, 'unit' => $unitId],
            'unitOptions' => $this->unitOptions(),
            'teacherOptions' => array_values(User::query()->role(Role::Teacher->value)->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                ->map(fn (User $u): array => ['value' => (string) $u->id, 'label' => $u->name])->all()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->service->save(null, $this->data($request, null), $this->actor($request));

        return $this->done('Ekstrakurikuler ditambahkan.');
    }

    public function update(Request $request, Extracurricular $extracurricular): RedirectResponse
    {
        $this->service->save($extracurricular, $this->data($request, $extracurricular), $this->actor($request));

        return $this->done('Ekstrakurikuler diperbarui.');
    }

    public function destroy(Request $request, Extracurricular $extracurricular): RedirectResponse
    {
        $this->service->delete($extracurricular, $this->actor($request));

        return $this->done('Ekstrakurikuler dihapus.');
    }

    public function members(Request $request): Response
    {
        $yearId = AcademicYear::query()->where('is_active', true)->value('id');
        $ekskuls = Extracurricular::query()->with('unit:id,code')->orderBy('unit_id')->orderBy('name')->get(['id', 'unit_id', 'name']);
        $ekskul = $ekskuls->firstWhere('id', $request->integer('ekskul')) ?? $ekskuls->first();
        $classroomId = $request->integer('classroom') ?: null;

        $members = $ekskul === null || $yearId === null ? collect() : ExtracurricularMember::query()
            ->with('student:id,name,nis')
            ->where('extracurricular_id', $ekskul->id)
            ->where('academic_year_id', $yearId)
            ->when($classroomId !== null, fn (Builder $q) => $q->whereHas('student.enrollments', fn (Builder $e) => $e->where('classroom_id', $classroomId)->where('academic_year_id', $yearId)))
            ->get();

        $studentIds = $members->pluck('student_id')->all();
        $classByStudent = $yearId === null ? collect() : Enrollment::query()->with('classroom:id,name')
            ->where('academic_year_id', $yearId)->whereIn('student_id', $studentIds)->get()
            ->mapWithKeys(fn (Enrollment $e): array => [$e->student_id => $e->classroom->name]);
        $attendance = ExtracurricularAttendance::query()
            ->whereIn('extracurricular_member_id', $members->pluck('id')->all())
            ->selectRaw("extracurricular_member_id, count(*) as sessions, sum(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present")
            ->groupBy('extracurricular_member_id')->get()->keyBy('extracurricular_member_id');

        $classrooms = $ekskul === null || $yearId === null ? collect() : Classroom::query()
            ->where('unit_id', $ekskul->unit_id)->where('academic_year_id', $yearId)->where('is_active', true)
            ->orderBy('grade')->orderBy('name')->get(['id', 'name']);
        $candidateClassroom = $classrooms->firstWhere('id', $request->integer('from'))->id ?? $classroomId ?? $classrooms->first()?->id;
        $candidates = $candidateClassroom === null || $yearId === null ? collect() : Enrollment::query()
            ->with('student:id,name,nis,status')
            ->where('classroom_id', $candidateClassroom)->where('academic_year_id', $yearId)
            ->whereHas('student', fn (Builder $s) => $s->where('status', StudentStatus::Active->value))
            ->whereNotIn('student_id', $studentIds)
            ->get();

        return Inertia::render('admin/academic/extracurricular-members', [
            'ekskulOptions' => $ekskuls->map(fn (Extracurricular $e): array => ['value' => (string) $e->id, 'label' => "{$e->name} ({$e->unit->code})"])->values()->all(),
            'ekskulId' => $ekskul?->id,
            'classroomOptions' => $classrooms->map(fn (Classroom $c): array => ['value' => (string) $c->id, 'label' => $c->name])->values()->all(),
            'classroomId' => $classroomId,
            'candidateClassroomId' => $candidateClassroom,
            'members' => $members->sortBy(fn (ExtracurricularMember $m): string => $m->student->name)->values()->map(function (ExtracurricularMember $m) use ($classByStudent, $attendance): array {
                $row = $attendance->get($m->id);

                return [
                    'id' => $m->id, 'student_name' => $m->student->name, 'nis' => $m->student->nis,
                    'classroom' => $classByStudent->get($m->student_id, '—'),
                    'sessions' => (int) ($row?->getAttribute('sessions') ?? 0), 'present' => (int) ($row?->getAttribute('present') ?? 0),
                    'grade' => $m->grade,
                ];
            })->all(),
            'candidates' => $candidates->map(fn (Enrollment $e): array => ['value' => (string) $e->student_id, 'label' => "{$e->student->name} · {$e->student->nis}"])->values()->all(),
            'grades' => ExtracurricularService::GRADES,
            'statuses' => array_map(fn (string $k, string $v): array => ['value' => $k, 'label' => $v], array_keys(ExtracurricularService::STATUSES), ExtracurricularService::STATUSES),
        ]);
    }

    public function enroll(Request $request, Extracurricular $extracurricular): RedirectResponse
    {
        $data = $request->validate(['student_ids' => ['required', 'array', 'min:1', 'max:100'], 'student_ids.*' => ['integer', 'distinct']], [], ['student_ids' => 'siswa']);
        $yearId = AcademicYear::query()->where('is_active', true)->value('id');
        abort_if($yearId === null, 404);

        $ids = Student::query()->whereIn('id', array_map(intval(...), $data['student_ids']))
            ->where('unit_id', $extracurricular->unit_id)->where('status', StudentStatus::Active->value)
            ->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
        $added = $this->service->enroll($extracurricular, (int) $yearId, array_values($ids), $this->actor($request));

        return $this->done("{$added} siswa didaftarkan.");
    }

    public function grade(Request $request, ExtracurricularMember $member): RedirectResponse
    {
        $data = $request->validate(['grade' => ['nullable', Rule::in(ExtracurricularService::GRADES)]]);
        $this->service->grade($member, $data['grade'] ?? null, $this->actor($request));

        return $this->done('Predikat tersimpan.');
    }

    public function removeMember(Request $request, ExtracurricularMember $member): RedirectResponse
    {
        $this->service->removeMember($member, $this->actor($request));

        return $this->done('Anggota dikeluarkan dari ekskul.');
    }

    public function attendance(Request $request, Extracurricular $extracurricular): RedirectResponse
    {
        $data = $request->validate([
            'met_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'statuses' => ['required', 'array'],
            'statuses.*' => ['required', Rule::in(array_keys(ExtracurricularService::STATUSES))],
        ], [], ['met_on' => 'tanggal pertemuan']);

        $statuses = [];
        foreach ((array) $data['statuses'] as $memberId => $status) {
            $statuses[(int) $memberId] = (string) $status;
        }
        $this->service->recordAttendance($extracurricular, (string) $data['met_on'], $statuses, $this->actor($request));

        return $this->done('Presensi ekskul tersimpan.');
    }

    /**
     * @return array{unit_id: int, name: string, coach_type: string, coach_user_id: int|null, coach_name: string|null}
     */
    private function data(Request $request, ?Extracurricular $ekskul): array
    {
        $data = $request->validate([
            'unit_id' => ['required', 'integer', Rule::exists('units', 'id')->where('is_active', true)],
            'name' => ['required', 'string', 'max:100', Rule::unique('extracurriculars', 'name')->where('unit_id', $request->integer('unit_id'))->ignore($ekskul?->id)],
            'coach_type' => ['required', Rule::in(['internal', 'external'])],
            'coach_user_id' => ['nullable', 'required_if:coach_type,internal', 'integer', Rule::exists('users', 'id')],
            'coach_name' => ['nullable', 'required_if:coach_type,external', 'string', 'max:100'],
        ], [], ['unit_id' => 'unit', 'name' => 'nama kegiatan', 'coach_type' => 'status pembina', 'coach_user_id' => 'guru pembina', 'coach_name' => 'nama pelatih']);

        $internal = $data['coach_type'] === 'internal';

        return [
            'unit_id' => (int) $data['unit_id'],
            'name' => trim((string) $data['name']),
            'coach_type' => (string) $data['coach_type'],
            'coach_user_id' => $internal ? (int) $data['coach_user_id'] : null,
            'coach_name' => $internal ? null : trim((string) $data['coach_name']),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function unitOptions(): array
    {
        return array_values(Unit::query()->where('is_active', true)->orderBy('id')->get(['id', 'name'])
            ->map(fn (Unit $u): array => ['value' => (string) $u->id, 'label' => $u->name])->all());
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
