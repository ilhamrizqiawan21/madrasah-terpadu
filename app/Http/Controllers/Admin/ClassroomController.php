<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ClassroomRequest;
use App\Http\Requests\Admin\CopyClassroomsRequest;
use App\Http\Requests\Admin\UpdateClassroomStatusRequest;
use App\Http\Resources\ClassroomResource;
use App\Models\AcademicYear;
use App\Models\ClassGroup;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\GradeLevel;
use App\Models\Major;
use App\Models\Unit;
use App\Models\User;
use App\Services\Classrooms\ClassroomService;
use App\Support\SchoolContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClassroomController extends Controller
{
    public function __construct(private readonly ClassroomService $service) {}

    public function index(Request $request): Response
    {
        $units = Unit::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']);
        $years = AcademicYear::query()->orderByDesc('name')->orderByDesc('semester')->get();
        $context = SchoolContext::resolve($request->session());

        // Bawaan saringan = unit & tahun ajaran yang dipilih di topbar.
        $requestedUnit = $units->first(fn (Unit $unit): bool => $unit->id === $request->integer('unit_id'))
            ?? $units->first(fn (Unit $unit): bool => $unit->code === $context['unit']);
        $requestedYear = $years->first(fn (AcademicYear $year): bool => $year->id === $request->integer('academic_year_id'));
        $unitId = $requestedUnit?->id;
        $yearId = $requestedYear !== null ? $requestedYear->id : $context['academicYearId'];
        $search = trim((string) $request->query('search', ''));

        $classrooms = Classroom::query()
            ->with(['unit', 'academicYear', 'homeroomTeacher', 'major'])
            ->when(
                $unitId === null || $yearId === null,
                fn (Builder $query) => $query->whereKey([]),
                fn (Builder $query) => $query->where('unit_id', $unitId)->where('academic_year_id', $yearId),
            )
            ->when($search !== '', fn (Builder $query) => $query->whereLike('name', "%{$search}%"))
            ->orderBy('grade')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        // Jumlah siswa aktif per rombel di halaman ini; satu kueri agregat.
        $studentCounts = Enrollment::query()
            ->whereIn('classroom_id', $classrooms->getCollection()->modelKeys())
            ->whereHas('student', fn (Builder $q) => $q->where('status', StudentStatus::Active->value))
            ->selectRaw('classroom_id, count(*) as aggregate')
            ->groupBy('classroom_id')
            ->pluck('aggregate', 'classroom_id')
            ->map(fn (mixed $count): int => (int) $count);

        return Inertia::render('admin/classrooms/index', [
            'classrooms' => ClassroomResource::collection($classrooms),
            'studentCounts' => $studentCounts,
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
            // Pilihan master (Tingkat Kelas, Rombel, Jurusan) untuk unit terpilih; teks lama tetap diterima.
            'gradeOptions' => $unitId === null ? [] : GradeLevel::query()->where('unit_id', $unitId)->orderBy('sort_order')->orderBy('name')->pluck('name')->values()->all(),
            'groupOptions' => $unitId === null ? [] : ClassGroup::query()->where('unit_id', $unitId)->orderBy('name')->pluck('name')->values()->all(),
            'majorOptions' => $unitId === null ? [] : Major::query()->where('unit_id', $unitId)->orderBy('name')->get(['id', 'name'])
                ->map(fn (Major $m): array => ['id' => $m->id, 'name' => $m->name])->values()->all(),
            'teacherOptions' => ClassroomService::homeroomCandidates()
                ->orderBy('name')
                ->orderBy('id')
                ->get(['id', 'name'])
                ->map(fn (User $teacher): array => ['id' => $teacher->id, 'name' => $teacher->name])
                ->values(),
        ]);
    }

    public function store(ClassroomRequest $request): RedirectResponse
    {
        $this->service->create($request->classroomData(), $this->actor($request));

        return $this->done('success', 'Rombel berhasil ditambahkan.');
    }

    public function update(ClassroomRequest $request, Classroom $classroom): RedirectResponse
    {
        $this->service->update($classroom, $request->classroomData(), $this->actor($request));

        return $this->done('success', 'Rombel berhasil diperbarui.');
    }

    public function updateStatus(UpdateClassroomStatusRequest $request, Classroom $classroom): RedirectResponse
    {
        $active = $request->boolean('is_active');

        $this->service->setActive($classroom, $active, $this->actor($request));

        return $this->done('success', $active ? 'Rombel diaktifkan kembali.' : 'Rombel dinonaktifkan.');
    }

    public function copy(CopyClassroomsRequest $request): RedirectResponse
    {
        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        $target = AcademicYear::query()->findOrFail($request->integer('academic_year_id'));
        $source = AcademicYear::query()->findOrFail($request->integer('source_academic_year_id'));

        $result = $this->service->copyFromYear($unit, $source, $target, $this->actor($request));

        if ($result['copied'] === 0 && $result['skipped'] === 0) {
            return $this->done('info', "Tidak ada rombel aktif {$unit->code} di {$source->label()} untuk disalin.");
        }

        $message = "{$result['copied']} rombel disalin";

        if ($result['skipped'] > 0) {
            $message .= ", {$result['skipped']} dilewati karena namanya sudah ada";
        }

        return $this->done('success', $message.'.');
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
