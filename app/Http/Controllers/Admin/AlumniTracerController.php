<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\AlumniActivity;
use App\Enums\AlumniIncomeRange;
use App\Enums\CurriculumRelevance;
use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Models\AlumniTracerResponse;
use App\Models\Student;
use App\Models\User;
use App\Services\Students\AlumniTracerService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Data Alumni (rekap tracer study) dan Survei Alumni (kuesioner diisi admin atas nama alumni).
 */
class AlumniTracerController extends Controller
{
    public function __construct(private readonly AlumniTracerService $service) {}

    public function index(Request $request): Response
    {
        $activity = AlumniActivity::tryFrom($request->string('activity')->toString());
        $search = trim($request->string('search')->toString());

        $responses = AlumniTracerResponse::query()
            ->with('student:id,name,nis,unit_id', 'student.unit:id,code')
            ->when($activity !== null, fn (Builder $q) => $q->where('activity', $activity?->value))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $inner) => $inner
                ->whereLike('institution', "%{$search}%")
                ->orWhereHas('student', fn (Builder $s) => $s->whereLike('name', "%{$search}%")->orWhereLike('nis', "%{$search}%"))))
            ->orderByDesc('graduation_year')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $counts = AlumniTracerResponse::query()
            ->selectRaw('activity, count(*) as total')
            ->groupBy('activity')
            ->pluck('total', 'activity');

        return Inertia::render('admin/students/alumni', [
            'responses' => [
                'data' => $responses->getCollection()->map(fn (AlumniTracerResponse $r): array => [
                    'id' => $r->id,
                    'student_name' => $r->student->name,
                    'student_nis' => $r->student->nis,
                    'unit_code' => $r->student->unit->code,
                    'graduation_year' => $r->graduation_year,
                    'activity' => $r->activity->value,
                    'activity_label' => $r->activity->label(),
                    'institution' => $r->institution,
                    'program' => $r->program,
                    'income_label' => $r->income_range?->label(),
                    'relevance_label' => $r->relevance->label(),
                    'feedback' => $r->feedback,
                ])->values()->all(),
                'meta' => [
                    'current_page' => $responses->currentPage(),
                    'last_page' => $responses->lastPage(),
                    'per_page' => $responses->perPage(),
                    'total' => $responses->total(),
                    'from' => $responses->firstItem(),
                    'to' => $responses->lastItem(),
                ],
            ],
            'summary' => array_map(fn (AlumniActivity $a): array => [
                'value' => $a->value,
                'label' => $a->label(),
                'total' => (int) ($counts[$a->value] ?? 0),
            ], AlumniActivity::cases()),
            'filters' => ['activity' => $activity?->value, 'search' => $search],
        ]);
    }

    public function create(Request $request): Response
    {
        $search = trim($request->string('q')->toString());

        $alumni = Student::query()
            ->where('status', StudentStatus::Graduated->value)
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $inner) => $inner->whereLike('name', "%{$search}%")->orWhereLike('nis', "%{$search}%")))
            ->with('unit:id,code')
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'name', 'nis', 'unit_id']);

        $year = (int) now('Asia/Makassar')->year;

        return Inertia::render('admin/students/alumni-survey', [
            'alumni' => $alumni->map(fn (Student $s): array => [
                'id' => $s->id,
                'label' => "{$s->name} · {$s->nis} · {$s->unit->code}",
            ])->values()->all(),
            'search' => $search,
            'years' => range($year, $year - 15),
            'activityOptions' => self::options(AlumniActivity::cases()),
            'incomeOptions' => self::options(AlumniIncomeRange::cases()),
            'relevanceOptions' => self::options(CurriculumRelevance::cases()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $year = (int) now('Asia/Makassar')->year;
        $data = $request->validate([
            'student_id' => ['required', 'integer', Rule::exists('students', 'id')],
            'graduation_year' => ['required', 'integer', 'between:'.($year - 40).','.$year],
            'activity' => ['required', Rule::enum(AlumniActivity::class)],
            'institution' => ['nullable', 'string', 'max:150'],
            'program' => ['nullable', 'string', 'max:120'],
            'income_range' => ['nullable', Rule::enum(AlumniIncomeRange::class)],
            'relevance' => ['required', Rule::enum(CurriculumRelevance::class)],
            'feedback' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'student_id' => 'alumni', 'graduation_year' => 'tahun kelulusan', 'activity' => 'status aktivitas',
            'institution' => 'kampus / tempat kerja', 'program' => 'prodi / posisi', 'income_range' => 'rentang penghasilan',
            'relevance' => 'kesesuaian kurikulum', 'feedback' => 'saran',
        ]);

        /** @var array{student_id: int, graduation_year: int, activity: string, institution: ?string, program: ?string, income_range: ?string, relevance: string, feedback: ?string} $payload */
        $payload = [
            'student_id' => (int) $data['student_id'],
            'graduation_year' => (int) $data['graduation_year'],
            'activity' => (string) $data['activity'],
            'institution' => $data['institution'] ?? null,
            'program' => $data['program'] ?? null,
            'income_range' => $data['income_range'] ?? null,
            'relevance' => (string) $data['relevance'],
            'feedback' => $data['feedback'] ?? null,
        ];

        $this->service->record($payload, $this->actor($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Data kuesioner alumni tersimpan.']);

        return to_route('admin.alumni.index');
    }

    public function destroy(Request $request, AlumniTracerResponse $response): RedirectResponse
    {
        $this->service->delete($response, $this->actor($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Jawaban alumni dihapus.']);

        return back();
    }

    /**
     * @param  list<AlumniActivity|AlumniIncomeRange|CurriculumRelevance>  $cases
     * @return list<array{value: string, label: string}>
     */
    private static function options(array $cases): array
    {
        return array_map(fn ($c): array => ['value' => $c->value, 'label' => $c->label()], $cases);
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
