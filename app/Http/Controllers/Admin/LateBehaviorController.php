<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\AttendanceStatus;
use App\Enums\BehaviorRecordStatus;
use App\Enums\PointCategory;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AttendanceDay;
use App\Models\BehaviorRecord;
use App\Models\Enrollment;
use App\Models\PointRule;
use App\Models\Unit;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LateBehaviorController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('create', BehaviorRecord::class);

        $timezone = config('sekolah.timezone', 'Asia/Makassar');
        $tz = is_string($timezone) ? $timezone : 'Asia/Makassar';
        $today = CarbonImmutable::now($tz)->toDateString();
        $date = (string) $request->query('date', $today);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = $today;
        }

        $unitId = $request->filled('unit_id') ? $request->integer('unit_id') : null;
        $activeYear = AcademicYear::query()->where('is_active', true)->first();

        $lateDays = AttendanceDay::query()
            ->with('student.unit:id,code,name')
            ->whereDate('school_date', $date)
            ->where('status', AttendanceStatus::Late->value)
            ->when($unitId !== null, function ($q) use ($unitId): void {
                $q->whereHas('student', fn ($sq) => $sq->where('unit_id', $unitId));
            })
            ->orderBy('checked_in_at')
            ->get();

        $studentIds = $lateDays->pluck('student_id')->all();

        $classroomNames = $activeYear !== null && $studentIds !== []
            ? Enrollment::query()
                ->with('classroom:id,name')
                ->where('academic_year_id', $activeYear->id)
                ->whereIn('student_id', $studentIds)
                ->get()
                ->mapWithKeys(fn (Enrollment $e): array => [$e->student_id => $e->classroom->name])
                ->all()
            : [];

        /** @var list<int> $alreadyRecordedIds */
        $alreadyRecordedIds = $studentIds !== []
            ? BehaviorRecord::query()
                ->whereDate('occurred_on', $date)
                ->where('category', PointCategory::Violation->value)
                ->where('status', BehaviorRecordStatus::Active->value)
                ->whereIn('student_id', $studentIds)
                ->pluck('student_id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->unique()
                ->values()
                ->all()
            : [];

        $lateStudents = $lateDays->map(fn (AttendanceDay $day): array => [
            'student_id' => $day->student_id,
            'nis' => $day->student->nis,
            'name' => $day->student->name,
            'unit_code' => $day->student->unit->code,
            'classroom_name' => $classroomNames[$day->student_id] ?? '—',
            'checked_in_at' => $day->checked_in_at?->timezone($tz)->format('H.i') ?? '—',
            'already_recorded' => in_array($day->student_id, $alreadyRecordedIds, true),
        ])->values()->all();

        $violationRules = PointRule::query()
            ->active()
            ->where('category', PointCategory::Violation->value)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'points'])
            ->map(fn (PointRule $r): array => [
                'id' => $r->id,
                'code' => $r->code,
                'name' => $r->name,
                'points' => $r->points,
            ])
            ->values()
            ->all();

        return Inertia::render('admin/behavior/late', [
            'lateStudents' => $lateStudents,
            'violationRules' => $violationRules,
            'units' => Unit::query()->orderBy('name')->get(['id', 'code', 'name']),
            'filters' => [
                'date' => $date,
                'unit_id' => $unitId !== null ? (string) $unitId : '',
            ],
        ]);
    }
}
