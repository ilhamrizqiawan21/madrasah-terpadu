<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Services\Cbt\StudentExamPinService;
use App\Support\SchoolContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CbtStudentPinController extends Controller
{
    public function __construct(private readonly StudentExamPinService $service) {}

    public function index(Request $request): Response
    {
        $actor = $this->actor($request);
        $canManageAll = $actor->can(Permission::CbtManage->value);

        $units = Unit::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']);
        $context = SchoolContext::resolve($request->session());
        $activeYear = AcademicYear::query()->where('is_active', true)->first();

        $requestedUnit = $units->first(fn (Unit $unit): bool => $unit->id === $request->integer('unit_id'))
            ?? $units->first(fn (Unit $unit): bool => $unit->code === $context['unit']);
        $unitId = $requestedUnit?->id;

        $classrooms = $unitId !== null && $activeYear !== null
            ? Classroom::query()
                ->where('unit_id', $unitId)
                ->where('academic_year_id', $activeYear->id)
                ->where('is_active', true)
                ->when(! $canManageAll, fn (Builder $q) => $q->where('homeroom_teacher_id', $actor->id))
                ->orderBy('grade')
                ->orderBy('name')
                ->get(['id', 'name', 'grade', 'homeroom_teacher_id'])
            : collect();

        $selectedClassroom = $classrooms->first(fn (Classroom $c): bool => $c->id === $request->integer('classroom_id'))
            ?? $classrooms->first();

        $students = $selectedClassroom !== null && $activeYear !== null
            ? Student::query()
                ->with('examPin:id,student_id,reset_at')
                ->where('status', StudentStatus::Active)
                ->whereHas('enrollments', function (Builder $q) use ($selectedClassroom, $activeYear): void {
                    $q->where('classroom_id', $selectedClassroom->id)
                        ->where('academic_year_id', $activeYear->id);
                })
                ->orderBy('name')
                ->orderBy('id')
                ->get(['id', 'nis', 'name'])
                ->map(fn (Student $student): array => [
                    'id' => $student->id,
                    'nis' => $student->nis,
                    'name' => $student->name,
                    'has_pin' => $student->examPin !== null,
                    'reset_at' => $student->examPin?->reset_at->toIso8601String(),
                ])
                ->values()
            : collect();

        $canResetSelected = $selectedClassroom !== null
            && ($canManageAll || ($actor->can(Permission::CbtTeach->value) && $selectedClassroom->homeroom_teacher_id === $actor->id));

        return Inertia::render('admin/cbt/pins/index', [
            'canReset' => $canResetSelected,
            'filters' => [
                'unit_id' => $unitId,
                'classroom_id' => $selectedClassroom?->id,
            ],
            'activeYearLabel' => $activeYear?->label(),
            'unitOptions' => $units->map(fn (Unit $unit): array => [
                'id' => $unit->id,
                'code' => $unit->code,
                'name' => $unit->name,
            ])->values(),
            'classroomOptions' => $classrooms->map(fn (Classroom $classroom): array => [
                'id' => $classroom->id,
                'name' => $classroom->name,
                'grade' => $classroom->grade,
            ])->values(),
            'students' => $students,
            'generatedPins' => $request->session()->get('generatedPins'),
        ]);
    }

    public function resetStudent(Request $request, Student $student): RedirectResponse
    {
        $actor = $this->actor($request);
        abort_unless($this->service->canManageStudent($actor, $student), 403);

        $pin = $this->service->resetForStudent($student, $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => "PIN ujian {$student->name} berhasil dibuat ulang."]);

        return back()->with('generatedPins', [
            [
                'student_id' => $student->id,
                'nis' => $student->nis,
                'name' => $student->name,
                'pin' => $pin,
            ],
        ]);
    }

    public function resetClassroom(Request $request, Classroom $classroom): RedirectResponse
    {
        $actor = $this->actor($request);
        abort_unless($this->service->canManageClassroom($actor, $classroom), 403);

        $pins = $this->service->resetForClassroom($classroom, $actor);

        if ($pins === []) {
            Inertia::flash('toast', ['type' => 'info', 'message' => "Tidak ada siswa aktif di rombel {$classroom->name}."]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => count($pins)." PIN ujian rombel {$classroom->name} berhasil dibuat ulang."]);

        return back()->with('generatedPins', $pins);
    }

    private function actor(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
