<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\User;
use App\Services\Habits\HabitRecapService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HabitRecapController extends Controller
{
    public function __construct(
        private readonly HabitRecapService $service,
    ) {}

    public function index(Request $request): Response
    {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless($actor->can(Permission::HabitsVerify->value), 403);

        $timezone = config('sekolah.timezone', 'Asia/Makassar');
        $tz = is_string($timezone) ? $timezone : 'Asia/Makassar';
        $today = CarbonImmutable::now($tz);

        $period = $request->query('period') === 'semester' ? 'semester' : 'month';
        $month = (string) $request->query('month', $today->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = $today->format('Y-m');
        }

        $activeYear = AcademicYear::query()->where('is_active', true)->first();
        $canViewAll = $actor->hasAnyRole([Role::Admin->value, Role::UnitHead->value, Role::Counselor->value]);

        $requestedClassroomId = $request->filled('classroom_id') ? $request->integer('classroom_id') : null;
        if ($requestedClassroomId !== null) {
            $explicit = Classroom::query()->findOrFail($requestedClassroomId);
            abort_unless($canViewAll || $explicit->homeroom_teacher_id === $actor->id, 403);
        }

        $classrooms = Classroom::query()
            ->with(['unit:id,code', 'academicYear'])
            ->where('is_active', true)
            ->when($activeYear !== null, fn ($q) => $q->where('academic_year_id', $activeYear->id))
            ->when(! $canViewAll, fn ($q) => $q->where('homeroom_teacher_id', $actor->id))
            ->orderBy('grade')
            ->orderBy('name')
            ->get();

        /** @var Classroom|null $selectedClassroom */
        $selectedClassroom = $requestedClassroomId !== null
            ? $classrooms->firstWhere('id', $requestedClassroomId)
            : $classrooms->first();

        $recap = $selectedClassroom !== null
            ? $this->service->recapForClassroom($selectedClassroom, $period, $month)
            : ['effective_days' => 0, 'spiritual_items' => 0, 'social_items' => 0, 'rows' => []];

        return Inertia::render('admin/habits/recap', [
            'classrooms' => $classrooms->map(fn (Classroom $c): array => [
                'id' => $c->id,
                'label' => ($c->unit->code ?? '').' · '.$c->name,
            ])->values()->all(),
            'selectedClassroomId' => $selectedClassroom?->id,
            'period' => $period,
            'month' => $month,
            'meta' => [
                'effective_days' => $recap['effective_days'],
                'spiritual_items' => $recap['spiritual_items'],
                'social_items' => $recap['social_items'],
            ],
            'rows' => $recap['rows'],
        ]);
    }
}
