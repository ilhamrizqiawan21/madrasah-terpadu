<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StudentRequest;
use App\Http\Requests\Admin\UpdateStudentStatusRequest;
use App\Http\Resources\StudentResource;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Services\Students\StudentService;
use App\Support\SchoolContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentController extends Controller
{
    /** Saringan rombel khusus untuk siswa yang belum punya rombel di tahun ajaran yang dilihat. */
    private const WITHOUT_CLASSROOM = 'none';

    private const ALL_STATUSES = 'all';

    public function __construct(private readonly StudentService $service) {}

    public function index(Request $request): Response
    {
        $units = Unit::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']);
        $context = SchoolContext::resolve($request->session());
        $activeYear = AcademicYear::query()->where('is_active', true)->first();

        // Bawaan saringan unit = unit di topbar; rombel yang ditampilkan = tahun ajaran di topbar.
        $unit = $units->first(fn (Unit $unit): bool => $unit->id === $request->integer('unit_id'))
            ?? $units->first(fn (Unit $unit): bool => $unit->code === $context['unit']);
        $yearId = $context['academicYearId'];

        $classrooms = $unit === null || $yearId === null
            ? collect()
            : Classroom::query()
                ->where('unit_id', $unit->id)
                ->where('academic_year_id', $yearId)
                ->orderBy('grade')
                ->orderBy('name')
                ->orderBy('id')
                ->get(['id', 'name', 'is_active', 'academic_year_id']);

        $classroomFilter = (string) $request->query('classroom_id', '');
        $classroomId = $classrooms->first(fn (Classroom $classroom): bool => (string) $classroom->id === $classroomFilter)?->id;
        $withoutClassroom = $classroomFilter === self::WITHOUT_CLASSROOM && $yearId !== null;

        $statusFilter = (string) $request->query('status', StudentStatus::Active->value);
        $status = $statusFilter === self::ALL_STATUSES
            ? null
            : (StudentStatus::tryFrom($statusFilter) ?? StudentStatus::Active);
        $search = trim((string) $request->query('search', ''));

        $students = Student::query()
            ->with(['enrollments.classroom', 'enrollments.academicYear', 'primaryGuardians'])
            ->when(
                $unit === null,
                fn (Builder $query) => $query->whereKey([]),
                fn (Builder $query) => $query->where('unit_id', $unit?->id),
            )
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
            ->when($search !== '', fn (Builder $query) => $query->where(
                fn (Builder $query) => $query->whereLike('name', "%{$search}%")->orWhereLike('nis', "%{$search}%"),
            ))
            ->when($classroomId !== null, fn (Builder $query) => $query->whereHas(
                'enrollments',
                fn (Builder $query) => $query->where('classroom_id', $classroomId)->where('academic_year_id', $yearId),
            ))
            ->when($withoutClassroom, fn (Builder $query) => $query->whereDoesntHave(
                'enrollments',
                fn (Builder $query) => $query->where('academic_year_id', $yearId),
            ))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        $activeYearId = $activeYear?->id;
        $students->through(fn (Student $student): StudentResource => new StudentResource($student, $yearId, $activeYearId));

        // Pilihan rombel di form selalu dari tahun ajaran aktif, karena enrollment hanya diatur di tahun aktif.
        $enrollmentClassrooms = $unit === null || $activeYear === null
            ? collect()
            : ($activeYear->id === $yearId
                ? $classrooms->where('is_active', true)->values()
                : Classroom::query()
                    ->where('unit_id', $unit->id)
                    ->where('academic_year_id', $activeYear->id)
                    ->where('is_active', true)
                    ->orderBy('grade')
                    ->orderBy('name')
                    ->orderBy('id')
                    ->get(['id', 'name']));

        return Inertia::render('admin/students/index', [
            'students' => StudentResource::collection($students),
            'filters' => [
                'unit_id' => $unit?->id,
                'academic_year_id' => $yearId,
                'classroom_id' => $withoutClassroom ? self::WITHOUT_CLASSROOM : ($classroomId === null ? null : (string) $classroomId),
                'status' => $status === null ? self::ALL_STATUSES : $status->value,
                'search' => $search,
            ],
            // Membedakan "belum ada siswa" dari "tidak ada yang cocok dengan saringan" di empty state.
            'unitHasStudents' => $unit !== null && Student::query()->where('unit_id', $unit->id)->exists(),
            'academicYearLabel' => $context['academicYear'],
            'activeAcademicYear' => $activeYear === null ? null : ['id' => $activeYear->id, 'label' => $activeYear->label()],
            'unitOptions' => $units->map(fn (Unit $unit): array => [
                'id' => $unit->id,
                'code' => $unit->code,
                'name' => $unit->name,
            ])->values(),
            'classroomOptions' => $classrooms->map(fn (Classroom $classroom): array => [
                'id' => $classroom->id,
                'name' => $classroom->name,
            ])->values(),
            'enrollmentClassroomOptions' => $enrollmentClassrooms->map(fn (Classroom $classroom): array => [
                'id' => $classroom->id,
                'name' => $classroom->name,
            ])->values(),
            'statusOptions' => StudentStatus::options(),
            // Impor bisa membuat akun wali (T-207), jadi tombolnya butuh kedua izin.
            'canImport' => $request->user('web')?->can(Permission::StudentsManage->value) === true
                && $request->user('web')->can(Permission::GuardiansManage->value),
        ]);
    }

    public function store(StudentRequest $request): RedirectResponse
    {
        $this->service->create($request->integer('unit_id'), $request->studentData(), $request->photo(), $this->actor($request));

        return $this->done('Siswa berhasil ditambahkan.');
    }

    public function update(StudentRequest $request, Student $student): RedirectResponse
    {
        $this->service->update(
            $student,
            $request->studentData(),
            $request->photo(),
            $request->boolean('remove_photo'),
            $this->actor($request),
        );

        return $this->done('Data siswa berhasil diperbarui.');
    }

    public function updateStatus(UpdateStudentStatusRequest $request, Student $student): RedirectResponse
    {
        $status = $request->status();

        $this->service->setStatus($student, $status, $this->actor($request));

        return $this->done("Status {$student->name} menjadi {$status->label()}.");
    }

    /**
     * Foto siswa dari disk privat: staf berizin lihat siswa (rute admin) atau wali
     * yang terhubung (rute /wali). Wali lain mendapat 404, bukan 403 (AI_RULES §5).
     */
    public function photo(Request $request, Student $student): StreamedResponse
    {
        abort_unless($request->user('web')?->can('view', $student) === true, 404);

        $disk = Storage::disk(StudentService::DISK);

        abort_if($student->photo_path === null || ! $disk->exists($student->photo_path), 404);

        return $disk->response($student->photo_path, null, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
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
