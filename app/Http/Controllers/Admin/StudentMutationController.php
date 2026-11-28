<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Feature;
use App\Enums\Gender;
use App\Enums\InvoiceStatus;
use App\Enums\MutationType;
use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StudentMutationInRequest;
use App\Http\Requests\Admin\StudentMutationOutRequest;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\StudentMutation;
use App\Models\Unit;
use App\Models\User;
use App\Services\Features\FeatureFlags;
use App\Services\Students\StudentMutationService;
use App\Support\SchoolContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Daftar mutasi siswa (masuk/keluar/lulus) beserta formulir pencatatan (T-212).
 */
class StudentMutationController extends Controller
{
    public function __construct(private readonly StudentMutationService $service) {}

    public function index(Request $request): Response
    {
        $context = SchoolContext::resolve($request->session());
        $units = Unit::query()->where('is_active', true)->orderBy('id')->get(['id', 'code', 'name']);
        $unitId = $request->has('unit_id')
            ? ($request->filled('unit_id') ? $request->integer('unit_id') : null)
            : $units->firstWhere('code', $context['unit'])?->id;

        $type = $request->string('type')->toString();
        $search = trim($request->string('search')->toString());

        $query = StudentMutation::query()
            ->with(['student.unit', 'recorder'])
            ->when($unitId !== null, fn (Builder $q) => $q->whereHas('student', fn (Builder $s) => $s->where('unit_id', $unitId)))
            ->when(MutationType::tryFrom($type) !== null, fn (Builder $q) => $q->where('type', $type))
            ->when($search !== '', function (Builder $q) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->whereHas('student', fn (Builder $s) => $s->where('name', 'like', $like)->orWhere('nis', 'like', $like));
            })
            ->orderByDesc('mutated_on')
            ->orderByDesc('id');

        $mutations = $query->paginate(25)->withQueryString();

        $activeYear = AcademicYear::query()->where('is_active', true)->first();

        $activeClassrooms = $activeYear === null
            ? collect()
            : Classroom::query()
                ->where('academic_year_id', $activeYear->id)
                ->where('is_active', true)
                ->orderBy('grade')
                ->orderBy('name')
                ->get(['id', 'unit_id', 'name']);

        $activeStudents = Student::query()
            ->with('unit')
            ->where('status', StudentStatus::Active)
            ->when($unitId !== null, fn (Builder $q) => $q->where('unit_id', $unitId))
            ->orderBy('name')
            ->orderBy('id')
            ->limit(250)
            ->get(['id', 'unit_id', 'nis', 'name']);

        // Keterangan tagihan (arsip mutasi aplikasi lama): sisa tagihan terbuka siswa saat keluar.
        $billingOn = app(FeatureFlags::class)->enabled(Feature::Billing);
        $outstanding = $billingOn
            ? Invoice::query()
                ->whereIn('student_id', $mutations->getCollection()->pluck('student_id')->unique()->values()->all())
                ->whereIn('status', [InvoiceStatus::Unpaid->value, InvoiceStatus::Partial->value])
                ->selectRaw('student_id, coalesce(sum(CASE WHEN (amount - adjustment_total - paid_total) > 0 THEN (amount - adjustment_total - paid_total) ELSE 0 END), 0) as remaining')
                ->groupBy('student_id')
                ->pluck('remaining', 'student_id')
            : collect();

        return Inertia::render('admin/students/mutations', [
            'billingEnabled' => $billingOn,
            'mutations' => [
                'data' => $mutations->getCollection()->map(fn (StudentMutation $mutation): array => [
                    'id' => $mutation->id,
                    'type' => ['value' => $mutation->type->value, 'label' => $mutation->type->label()],
                    'mutated_on' => $mutation->mutated_on->toDateString(),
                    'school_name' => $mutation->school_name,
                    'reason' => $mutation->reason,
                    'student' => [
                        'id' => $mutation->student->id,
                        'nis' => $mutation->student->nis,
                        'name' => $mutation->student->name,
                        'unit' => $mutation->student->unit->code,
                    ],
                    'recorded_by' => $mutation->recorder->name,
                    'outstanding' => $billingOn ? (int) ($outstanding[$mutation->student_id] ?? 0) : null,
                ])->values(),
                'meta' => [
                    'current_page' => $mutations->currentPage(),
                    'last_page' => $mutations->lastPage(),
                    'per_page' => $mutations->perPage(),
                    'total' => $mutations->total(),
                    'from' => $mutations->firstItem(),
                    'to' => $mutations->lastItem(),
                ],
            ],
            'filters' => [
                'unit_id' => $unitId,
                'type' => $type,
                'search' => $search,
            ],
            'unitOptions' => $units->map(fn (Unit $unit): array => [
                'id' => $unit->id,
                'code' => $unit->code,
                'name' => $unit->name,
            ])->values(),
            'typeOptions' => MutationType::options(),
            'genderOptions' => array_map(
                fn (Gender $gender): array => ['value' => $gender->value, 'label' => $gender->label()],
                Gender::cases(),
            ),
            'classroomOptions' => $activeClassrooms->map(fn (Classroom $c): array => [
                'id' => $c->id,
                'unit_id' => $c->unit_id,
                'name' => $c->name,
            ])->values(),
            'activeStudentOptions' => $activeStudents->map(fn (Student $s): array => [
                'id' => $s->id,
                'nis' => $s->nis,
                'name' => $s->name,
                'unit' => $s->unit->code,
            ])->values(),
        ]);
    }

    public function storeIn(StudentMutationInRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $this->service->mutateIn([
            'unit_id' => (int) $data['unit_id'],
            'nis' => (string) $data['nis'],
            'nisn' => $data['nisn'] ?? null,
            'name' => (string) $data['name'],
            'nickname' => $data['nickname'] ?? null,
            'gender' => (string) $data['gender'],
            'birth_place' => $data['birth_place'] ?? null,
            'birth_date' => $data['birth_date'] ?? null,
            'address' => $data['address'] ?? null,
            'classroom_id' => isset($data['classroom_id']) ? (int) $data['classroom_id'] : null,
            'mutated_on' => (string) $data['mutated_on'],
            'school_name' => $data['school_name'] ?? null,
            'reason' => (string) $data['reason'],
        ], $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Mutasi masuk siswa berhasil dicatat.']);

        return to_route('admin.students.mutations.index');
    }

    public function storeOut(StudentMutationOutRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $student = Student::query()->findOrFail((int) $data['student_id']);

        try {
            $this->service->mutateOut(
                student: $student,
                mutatedOn: (string) $data['mutated_on'],
                schoolName: $data['school_name'] ?? null,
                reason: (string) $data['reason'],
                actor: $actor,
                graduate: $request->boolean('graduate'),
                dropout: $request->boolean('dropout'),
            );
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['student_id' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Mutasi keluar siswa berhasil dicatat.']);

        return to_route('admin.students.mutations.index');
    }
}
