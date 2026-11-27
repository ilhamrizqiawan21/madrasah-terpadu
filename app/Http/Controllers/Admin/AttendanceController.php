<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Attendance\AttendanceClassifier;
use App\Enums\AttendanceStatus;
use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CorrectAttendanceRequest;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Services\Attendance\DailyAttendanceService;
use App\Support\SchoolContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function __construct(private readonly DailyAttendanceService $service) {}

    public function index(Request $request): Response
    {
        $actor = $this->actor($request);
        $context = SchoolContext::resolve($request->session());
        $yearId = $context['academicYearId']
            ?? AcademicYear::query()->where('is_active', true)->value('id');

        $units = Unit::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']);
        $unit = $units->first(fn (Unit $u): bool => $u->code === $context['unit']) ?? $units->first();

        $classrooms = Classroom::query()
            ->where('is_active', true)
            ->when($yearId !== null, fn ($q) => $q->where('academic_year_id', $yearId))
            ->when($unit !== null, fn ($q) => $q->where('unit_id', $unit->id))
            ->orderBy('grade')
            ->orderBy('name')
            ->get(['id', 'name', 'homeroom_teacher_id', 'academic_year_id']);

        $selectedClassroom = $classrooms->first(fn (Classroom $c): bool => $c->id === $request->integer('classroom_id'))
            ?? $classrooms->first();

        $mode = $request->query('mode') === 'monthly' ? 'monthly' : 'daily';
        $today = CarbonImmutable::now(AttendanceClassifier::BUSINESS_TIMEZONE);
        $date = (string) $request->query('date', $today->toDateString());
        $month = (string) $request->query('month', $today->format('Y-m'));

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = $today->toDateString();
        }

        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = $today->format('Y-m');
        }

        $daily = $selectedClassroom !== null && $mode === 'daily'
            ? $this->service->dailyForClassroom($selectedClassroom, $date)
            : [
                'counts' => ['present' => 0, 'late' => 0, 'excused' => 0, 'sick' => 0, 'absent' => 0, 'not_yet' => 0],
                'rows' => [],
            ];

        $monthlyRows = $selectedClassroom !== null && $mode === 'monthly'
            ? $this->service->monthlyForClassroom($selectedClassroom, $month)
            : [];

        $canCorrect = $selectedClassroom !== null && $this->canCorrectClassroom($actor, $selectedClassroom);

        return Inertia::render('admin/attendance/index', [
            'mode' => $mode,
            'filters' => [
                'classroom_id' => $selectedClassroom?->id,
                'date' => $date,
                'month' => $month,
            ],
            'classrooms' => $classrooms->map(fn (Classroom $c): array => [
                'id' => $c->id,
                'name' => $c->name,
            ])->values()->all(),
            'daily' => $daily,
            'monthlyRows' => $monthlyRows,
            'canCorrect' => $canCorrect,
            'statusOptions' => array_map(
                fn (AttendanceStatus $s): array => ['value' => $s->value, 'label' => $s->label()],
                AttendanceStatus::cases(),
            ),
        ]);
    }

    public function correct(CorrectAttendanceRequest $request): RedirectResponse
    {
        $actor = $this->actor($request);
        $validated = $request->validated();

        /** @var Student $student */
        $student = Student::query()->findOrFail((int) $validated['student_id']);

        if (! $actor->hasRole(Role::Admin->value)) {
            $activeYearId = AcademicYear::query()->where('is_active', true)->value('id');
            $isHomeroom = Enrollment::query()
                ->where('student_id', $student->id)
                ->when($activeYearId !== null, fn ($q) => $q->where('academic_year_id', $activeYearId))
                ->whereHas('classroom', fn ($q) => $q->where('homeroom_teacher_id', $actor->id))
                ->exists();

            abort_unless($isHomeroom, 403);
        }

        $this->service->correct(
            student: $student,
            schoolDate: (string) $validated['school_date'],
            status: AttendanceStatus::from((string) $validated['status']),
            reason: (string) $validated['reason'],
            actor: $actor,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Presensi {$student->name} diperbarui.",
        ]);

        return back();
    }

    private function canCorrectClassroom(User $actor, Classroom $classroom): bool
    {
        if (! $actor->can(Permission::AttendanceManage->value)) {
            return false;
        }

        if ($actor->hasRole(Role::Admin->value)) {
            return true;
        }

        return $classroom->homeroom_teacher_id === $actor->id;
    }

    private function actor(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
