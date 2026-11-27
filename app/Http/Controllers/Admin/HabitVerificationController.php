<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\HabitItem;
use App\Models\HabitLog;
use App\Models\User;
use App\Services\Habits\HabitVerificationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class HabitVerificationController extends Controller
{
    public function __construct(
        private readonly HabitVerificationService $service,
    ) {}

    public function index(Request $request): Response
    {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless($actor->can(Permission::HabitsVerify->value), 403);

        $timezone = config('sekolah.timezone', 'Asia/Makassar');
        $tz = is_string($timezone) ? $timezone : 'Asia/Makassar';
        $today = CarbonImmutable::now($tz)->toDateString();
        $date = (string) $request->query('date', $today);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = $today;
        }

        $activeYear = AcademicYear::query()->where('is_active', true)->first();
        $canViewAll = $actor->hasAnyRole([Role::Admin->value, Role::UnitHead->value, Role::Counselor->value]);

        $requestedClassroomId = $request->filled('classroom_id') ? $request->integer('classroom_id') : null;
        if ($requestedClassroomId !== null) {
            $explicitClassroom = Classroom::query()->findOrFail($requestedClassroomId);
            abort_unless($canViewAll || $explicitClassroom->homeroom_teacher_id === $actor->id, 403);
        }

        $classrooms = Classroom::query()
            ->with('unit:id,code')
            ->where('is_active', true)
            ->when($activeYear !== null, fn ($q) => $q->where('academic_year_id', $activeYear->id))
            ->when(! $canViewAll, fn ($q) => $q->where('homeroom_teacher_id', $actor->id))
            ->orderBy('grade')
            ->orderBy('name')
            ->get(['id', 'unit_id', 'academic_year_id', 'grade', 'name', 'homeroom_teacher_id']);

        /** @var Classroom|null $selectedClassroom */
        $selectedClassroom = $requestedClassroomId !== null
            ? $classrooms->firstWhere('id', $requestedClassroomId)
            : $classrooms->first();

        $itemsList = [];
        $rows = [];

        if ($selectedClassroom !== null) {
            $items = HabitItem::query()
                ->active()
                ->forUnit($selectedClassroom->unit_id)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'name', 'aspect']);

            $itemsList = $items->map(fn (HabitItem $i): array => [
                'id' => $i->id,
                'name' => $i->name,
            ])->values()->all();

            $enrollments = Enrollment::query()
                ->with('student:id,nis,name')
                ->where('classroom_id', $selectedClassroom->id)
                ->where('academic_year_id', $selectedClassroom->academic_year_id)
                ->get();

            $studentIds = $enrollments->pluck('student_id')->all();

            $logs = $studentIds !== []
                ? HabitLog::query()
                    ->whereIn('student_id', $studentIds)
                    ->whereDate('log_date', $date)
                    ->get()
                : new Collection;

            $logMap = [];
            foreach ($logs as $log) {
                $logMap[$log->student_id.'-'.$log->habit_item_id] = $log;
            }

            $rows = $enrollments
                ->sortBy(fn (Enrollment $e): string => $e->student->name)
                ->map(function (Enrollment $enrollment) use ($items, $logMap): array {
                    $cells = [];
                    foreach ($items as $item) {
                        /** @var HabitLog|null $log */
                        $log = $logMap[$enrollment->student_id.'-'.$item->id] ?? null;
                        $cells[$item->id] = $log !== null ? [
                            'log_id' => $log->id,
                            'done' => $log->done,
                            'verification' => $log->verification,
                            'note' => $log->note,
                        ] : null;
                    }

                    return [
                        'student_id' => $enrollment->student_id,
                        'student_name' => $enrollment->student->name,
                        'student_nis' => $enrollment->student->nis,
                        'cells' => $cells,
                    ];
                })
                ->values()
                ->all();
        }

        return Inertia::render('admin/habits/verify', [
            'classrooms' => $classrooms->map(fn (Classroom $c): array => [
                'id' => $c->id,
                'label' => ($c->unit->code ?? '').' · '.$c->name,
            ])->values()->all(),
            'selectedClassroomId' => $selectedClassroom?->id,
            'date' => $date,
            'items' => $itemsList,
            'rows' => $rows,
        ]);
    }

    public function verifyBulk(Request $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless($actor->can(Permission::HabitsVerify->value), 403);

        $validated = $request->validate([
            'classroom_id' => ['required', 'integer', 'exists:classrooms,id'],
            'log_date' => ['required', 'date'],
            'log_ids' => ['present', 'array'],
            'log_ids.*' => ['integer', 'exists:habit_logs,id'],
        ]);

        $classroom = Classroom::query()->findOrFail((int) $validated['classroom_id']);
        $this->authorizeClassroom($actor, $classroom);

        /** @var list<int> $logIds */
        $logIds = array_map('intval', $validated['log_ids']);
        $count = $this->service->verifyBulk($actor, $classroom, (string) $validated['log_date'], $logIds);

        return back()->with('success', "{$count} catatan pembiasaan diverifikasi.");
    }

    public function reject(Request $request, HabitLog $habitLog): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless($actor->can(Permission::HabitsVerify->value), 403);

        $validated = $request->validate([
            'classroom_id' => ['required', 'integer', 'exists:classrooms,id'],
            'note' => ['required', 'string', 'max:500'],
        ]);

        $classroom = Classroom::query()->findOrFail((int) $validated['classroom_id']);
        $this->authorizeClassroom($actor, $classroom);

        $this->service->reject($actor, $classroom, $habitLog, (string) $validated['note']);

        return back()->with('success', 'Catatan pembiasaan ditolak dan wali diberitahu.');
    }

    private function authorizeClassroom(User $actor, Classroom $classroom): void
    {
        $canViewAll = $actor->hasAnyRole([Role::Admin->value, Role::UnitHead->value, Role::Counselor->value]);
        abort_unless($canViewAll || $classroom->homeroom_teacher_id === $actor->id, 403);
    }
}
