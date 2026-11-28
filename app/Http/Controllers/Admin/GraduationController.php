<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Graduation;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Services\Academic\GraduationService;
use App\Services\Settings\SettingsRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Akademik > Kelulusan & SKL: daftar siswa tahun ajaran aktif, status lulus, dan cetak SKL.
 */
class GraduationController extends Controller
{
    public function __construct(private readonly GraduationService $service) {}

    public function index(Request $request): Response
    {
        $year = AcademicYear::query()->where('is_active', true)->first();
        $unitId = $request->integer('unit') ?: null;
        $classroomId = $request->integer('classroom') ?: null;
        $search = trim($request->string('search')->toString());

        $paginator = $year === null ? null : Enrollment::query()
            ->with(['student:id,name,nis,unit_id', 'classroom:id,name,unit_id'])
            ->where('academic_year_id', $year->id)
            ->when($unitId !== null, fn (Builder $q) => $q->whereHas('classroom', fn (Builder $c) => $c->where('unit_id', $unitId)))
            ->when($classroomId !== null, fn (Builder $q) => $q->where('classroom_id', $classroomId))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $s) => $s->whereLike('students.name', "%{$search}%")->orWhereLike('students.nis', "%{$search}%")))
            ->join('students', 'students.id', '=', 'enrollments.student_id')
            ->orderBy('students.name')->orderBy('enrollments.id')
            ->select('enrollments.*')
            ->paginate(50)->withQueryString();

        $enrollments = $paginator === null ? [] : $paginator->items();
        $studentIds = array_map(fn (Enrollment $e): int => $e->student_id, $enrollments);
        $graduations = $year === null ? collect() : Graduation::query()->where('academic_year_id', $year->id)
            ->whereIn('student_id', $studentIds)->get()->keyBy('student_id');
        $averages = $year === null ? collect() : DB::table('exam_results')
            ->join('exam_sessions', 'exam_sessions.id', '=', 'exam_results.exam_session_id')
            ->whereIn('exam_sessions.student_id', $studentIds)
            ->whereNotNull('exam_results.score')
            ->whereBetween('exam_results.computed_at', [$year->starts_on->startOfDay(), $year->ends_on->endOfDay()])
            ->groupBy('exam_sessions.student_id')
            ->selectRaw('exam_sessions.student_id as student_id, avg(exam_results.score) as average')
            ->pluck('average', 'student_id');

        $classrooms = $year === null ? collect() : Classroom::query()->where('academic_year_id', $year->id)
            ->when($unitId !== null, fn (Builder $q) => $q->where('unit_id', $unitId))
            ->orderBy('unit_id')->orderBy('grade')->orderBy('name')->get(['id', 'name']);

        return Inertia::render('admin/academic/graduations', [
            'yearLabel' => $year?->label(),
            'rows' => [
                'data' => array_values(array_map(function (Enrollment $e) use ($graduations, $averages): array {
                    $g = $graduations->get($e->student_id);
                    $avg = $averages->get($e->student_id);

                    return [
                        'id' => $e->student_id, 'nis' => $e->student->nis, 'name' => $e->student->name,
                        'classroom' => $e->classroom->name,
                        'average' => $avg === null ? null : round((float) $avg, 2),
                        'status' => $g?->status, 'letter_number' => $g?->letter_number,
                    ];
                }, $enrollments)),
                'meta' => [
                    'current_page' => $paginator?->currentPage() ?? 1, 'last_page' => $paginator?->lastPage() ?? 1,
                    'per_page' => $paginator?->perPage() ?? 50, 'total' => $paginator?->total() ?? 0,
                    'from' => $paginator?->firstItem(), 'to' => $paginator?->lastItem(),
                ],
            ],
            'filters' => ['search' => $search, 'unit' => $unitId, 'classroom' => $classroomId],
            'unitOptions' => array_values(Unit::query()->where('is_active', true)->orderBy('id')->get(['id', 'name'])
                ->map(fn (Unit $u): array => ['value' => (string) $u->id, 'label' => $u->name])->all()),
            'classroomOptions' => array_values($classrooms->map(fn (Classroom $c): array => ['value' => (string) $c->id, 'label' => $c->name])->all()),
        ]);
    }

    public function decide(Request $request, Student $student): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in([Graduation::PASSED, Graduation::FAILED])]]);
        $year = AcademicYear::query()->where('is_active', true)->firstOrFail();
        abort_unless(Enrollment::query()->where('student_id', $student->id)->where('academic_year_id', $year->id)->exists(), 404);

        $this->service->decide($student, $year, (string) $data['status'], $this->actor($request));

        $label = $data['status'] === Graduation::PASSED ? 'Lulus' : 'Tidak Lulus';
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$student->name} ditetapkan {$label}."]);

        return back();
    }

    public function letter(Student $student, SettingsRepository $settings): Response
    {
        $year = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $graduation = Graduation::query()->where('student_id', $student->id)->where('academic_year_id', $year->id)
            ->where('status', Graduation::PASSED)->firstOrFail();
        $student->load(['unit.head:id,name', 'enrollments' => fn ($q) => $q->where('academic_year_id', $year->id)->with('classroom:id,name')]);
        $identity = $settings->identity();

        return Inertia::render('admin/academic/graduation-letter', [
            'school' => [
                'foundation' => $identity['foundation_name'], 'unit' => $student->unit->name,
                'address' => $identity['address'], 'phone' => $identity['phone'],
                'head' => $student->unit->head?->name,
            ],
            'letter' => [
                'number' => $graduation->letter_number, 'year' => $year->name,
                'date' => $graduation->decided_at->translatedFormat('j F Y'),
            ],
            'student' => [
                'name' => $student->name, 'nis' => $student->nis, 'nisn' => $student->nisn,
                'birth' => trim(($student->birth_place ?? '').', '.($student->birth_date?->translatedFormat('j F Y') ?? ''), ', '),
                'classroom' => $student->enrollments->first()?->classroom->name,
            ],
        ]);
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
