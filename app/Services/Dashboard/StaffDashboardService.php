<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Domain\Attendance\AttendanceClassifier;
use App\Enums\ExamResultStatus;
use App\Enums\ExamSessionStatus;
use App\Enums\ExamStatus;
use App\Enums\LeaveRequestStatus;
use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Models\AttendanceDay;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\ExamSession;
use App\Models\LeaveRequest;
use App\Models\SchoolHoliday;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Services\Attendance\SchoolCalendarService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Menyusun bagian Beranda Staf dari keputusan per peran (DESIGN §3), hanya-baca.
 *
 * Setiap metode mengembalikan data satu bagian; controller yang memutuskan
 * bagian mana yang dikirim berdasarkan izin dan kunci fitur.
 */
final class StaffDashboardService
{
    private const NOT_YET_LIST_LIMIT = 8;

    private const CLASSROOM_LIST_LIMIT = 6;

    private const TREND_DAYS = 7;

    /** Rentang kalender yang ditelusuri untuk mengumpulkan TREND_DAYS hari sekolah (libur panjang dilewati). */
    private const TREND_LOOKBACK_DAYS = 20;

    public function __construct(private readonly SchoolCalendarService $calendar) {}

    /**
     * Presensi hari ini. Wali kelas (guru tanpa izin lihat seluruh unit) hanya melihat rombel yang ia walikan.
     *
     * @return array{
     *     date: string,
     *     scope: 'unit'|'homeroom',
     *     off_day: ?string,
     *     late_after: ?string,
     *     total: int,
     *     counts: array{present: int, late: int, excused: int, sick: int, absent: int, not_yet: int},
     *     classrooms: list<array{id: int, name: string, total: int, not_yet: int}>,
     *     not_yet_students: list<array{id: int, name: string, nis: string, classroom: string}>,
     *     trend: list<array{date: string, present: int, late: int, excused: int, sick: int, absent: int}>,
     * }
     */
    public function attendanceToday(User $user, ?Unit $unit, ?int $yearId): array
    {
        $today = CarbonImmutable::now(AttendanceClassifier::BUSINESS_TIMEZONE);
        $date = $today->toDateString();
        $scope = $this->isHomeroomOnly($user) ? 'homeroom' : 'unit';

        $classrooms = Classroom::query()
            ->where('is_active', true)
            ->when($yearId !== null, fn (Builder $q) => $q->where('academic_year_id', $yearId), fn (Builder $q) => $q->whereRaw('1 = 0'))
            ->when($unit !== null, fn (Builder $q) => $q->where('unit_id', $unit->id))
            ->when($scope === 'homeroom', fn (Builder $q) => $q->where('homeroom_teacher_id', $user->id))
            ->orderBy('grade')
            ->orderBy('name')
            ->get(['id', 'name', 'unit_id']);

        /** @var Collection<int, Enrollment> $enrollments */
        $enrollments = Enrollment::query()
            ->whereIn('classroom_id', $classrooms->modelKeys())
            ->when($yearId !== null, fn (Builder $q) => $q->where('academic_year_id', $yearId))
            ->whereHas('student', fn (Builder $q) => $q->where('status', StudentStatus::Active->value))
            ->with('student:id,name,nis')
            ->get(['id', 'student_id', 'classroom_id']);

        /** @var Collection<int, AttendanceDay> $days */
        $days = AttendanceDay::query()
            ->whereIn('student_id', $enrollments->pluck('student_id')->all())
            ->whereDate('school_date', $date)
            ->get(['id', 'student_id', 'status'])
            ->keyBy('student_id');

        $counts = ['present' => 0, 'late' => 0, 'excused' => 0, 'sick' => 0, 'absent' => 0, 'not_yet' => 0];
        $perClassroom = [];
        $notYet = [];
        $classroomNames = $classrooms->pluck('name', 'id');

        foreach ($enrollments as $enrollment) {
            $classroomId = $enrollment->classroom_id;
            $perClassroom[$classroomId] ??= ['total' => 0, 'not_yet' => 0];
            $perClassroom[$classroomId]['total']++;

            /** @var AttendanceDay|null $day */
            $day = $days->get($enrollment->student_id);

            if ($day === null) {
                $counts['not_yet']++;
                $perClassroom[$classroomId]['not_yet']++;
                $notYet[] = [
                    'id' => $enrollment->student->id,
                    'name' => $enrollment->student->name,
                    'nis' => $enrollment->student->nis,
                    'classroom' => (string) $classroomNames->get($classroomId, '-'),
                ];

                continue;
            }

            $counts[$day->status->value]++;
        }

        usort($notYet, fn (array $a, array $b): int => [$a['classroom'], $a['name']] <=> [$b['classroom'], $b['name']]);

        $classroomRows = array_values($classrooms
            ->filter(fn (Classroom $c): bool => isset($perClassroom[$c->id]))
            ->map(fn (Classroom $c): array => [
                'id' => $c->id,
                'name' => $c->name,
                'total' => $perClassroom[$c->id]['total'],
                'not_yet' => $perClassroom[$c->id]['not_yet'],
            ])
            ->sortByDesc('not_yet')
            ->take(self::CLASSROOM_LIST_LIMIT)
            ->all());

        $scheduleUnit = $unit ?? ($classrooms->isNotEmpty() ? Unit::query()->find($classrooms->first()->unit_id) : null);
        $studentIds = array_values($enrollments->map(fn (Enrollment $e): int => $e->student_id)->all());

        return [
            'trend' => $this->trend($studentIds, $scheduleUnit, $today),
            'date' => $date,
            'scope' => $scope,
            'off_day' => $scheduleUnit !== null ? $this->offDayLabel($scheduleUnit, $today) : null,
            'late_after' => $this->lateAfter($scheduleUnit),
            'total' => $enrollments->count(),
            'counts' => $counts,
            'classrooms' => $classroomRows,
            'not_yet_students' => $scope === 'homeroom'
                ? array_slice($notYet, 0, self::NOT_YET_LIST_LIMIT)
                : [],
        ];
    }

    /**
     * @return array{count: int, items: list<array{id: int, student_name: string, type_label: string, starts_on: string, ends_on: string, submitted_at: string}>}
     */
    public function pendingLeaves(?Unit $unit, int $limit = 4): array
    {
        $query = LeaveRequest::query()
            ->where('status', LeaveRequestStatus::Pending->value)
            ->when($unit !== null, fn (Builder $q) => $q->whereHas('student', fn (Builder $s) => $s->where('unit_id', $unit->id)));

        $count = (clone $query)->count();

        $items = array_values($query
            ->with('student:id,name')
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(fn (LeaveRequest $leave): array => [
                'id' => $leave->id,
                'student_name' => $leave->student->name,
                'type_label' => $leave->type->label(),
                'starts_on' => $leave->starts_on->toDateString(),
                'ends_on' => $leave->ends_on->toDateString(),
                'submitted_at' => $leave->created_at->toIso8601String(),
            ])
            ->all());

        return ['count' => $count, 'items' => $items];
    }

    /**
     * Ujian yang berjalan sekarang atau dibuka hari ini, plus jumlah hasil yang menunggu nilai esai.
     *
     * @return array{
     *     today: list<array{id: int, title: string, subject: string, status: string, status_label: string, opens_at: string, closes_at: string, in_progress: int, finished: int}>,
     *     awaiting_essay: list<array{id: int, title: string, subject: string, count: int}>,
     * }
     */
    public function exams(User $user): array
    {
        $tz = AttendanceClassifier::BUSINESS_TIMEZONE;
        $startOfDay = CarbonImmutable::now($tz)->startOfDay()->utc();
        $endOfDay = CarbonImmutable::now($tz)->endOfDay()->utc();

        /** @var Collection<int, Exam> $todayExams */
        $todayExams = Exam::query()
            ->visibleTo($user)
            ->with('subject:id,name')
            ->where('status', '!=', ExamStatus::Draft->value)
            ->where(fn (Builder $q) => $q
                ->where('status', ExamStatus::Running->value)
                ->orWhereBetween('opens_at', [$startOfDay, $endOfDay]))
            ->orderBy('opens_at')
            ->orderBy('id')
            ->limit(10)
            ->get();

        $sessionCounts = ExamSession::query()
            ->whereIn('exam_id', $todayExams->pluck('id')->all())
            ->select('exam_id', 'status', DB::raw('count(*) as aggregate'))
            ->groupBy('exam_id', 'status')
            ->get()
            ->groupBy('exam_id');

        $today = array_values($todayExams->map(function (Exam $exam) use ($sessionCounts): array {
            $rows = $sessionCounts->get($exam->id, collect());
            $inProgress = 0;
            $finished = 0;

            /** @var ExamSession $row */
            foreach ($rows as $row) {
                $amount = (int) $row->getAttribute('aggregate');

                if ($row->status->isFinished()) {
                    $finished += $amount;
                } else {
                    $inProgress += $amount;
                }
            }

            return [
                'id' => $exam->id,
                'title' => $exam->title,
                'subject' => $exam->subject->name,
                'status' => $exam->status->value,
                'status_label' => $exam->status->label(),
                'opens_at' => $exam->opens_at->toIso8601String(),
                'closes_at' => $exam->closes_at->toIso8601String(),
                'in_progress' => $inProgress,
                'finished' => $finished,
            ];
        })->all());

        $visibleExamIds = Exam::query()->visibleTo($user)->select('id');

        $awaiting = ExamResult::query()
            ->where('exam_results.status', ExamResultStatus::AwaitingEssay->value)
            ->join('exam_sessions', 'exam_sessions.id', '=', 'exam_results.exam_session_id')
            ->whereIn('exam_sessions.exam_id', $visibleExamIds)
            ->select('exam_sessions.exam_id', DB::raw('count(*) as aggregate'))
            ->groupBy('exam_sessions.exam_id')
            ->orderBy('exam_sessions.exam_id')
            ->get();

        $awaitingExams = Exam::query()
            ->with('subject:id,name')
            ->whereIn('id', $awaiting->pluck('exam_id')->all())
            ->get(['id', 'title', 'subject_id'])
            ->keyBy('id');

        $awaitingEssay = array_values($awaiting
            ->map(function (ExamResult $row) use ($awaitingExams): ?array {
                /** @var Exam|null $exam */
                $exam = $awaitingExams->get((int) $row->getAttribute('exam_id'));

                return $exam === null ? null : [
                    'id' => $exam->id,
                    'title' => $exam->title,
                    'subject' => $exam->subject->name,
                    'count' => (int) $row->getAttribute('aggregate'),
                ];
            })
            ->filter()
            ->sortByDesc('count')
            ->take(5)
            ->all());

        return ['today' => $today, 'awaiting_essay' => $awaitingEssay];
    }

    /**
     * Data induk yang perlu dilengkapi pada unit & tahun ajaran terpilih.
     *
     * @return array{students_without_classroom: int, students_without_guardian: int, classrooms_without_homeroom: int}
     */
    public function dataGaps(?Unit $unit, ?int $yearId): array
    {
        $activeStudents = fn (): Builder => Student::query()
            ->where('status', StudentStatus::Active->value)
            ->when($unit !== null, fn (Builder $q) => $q->where('unit_id', $unit->id));

        return [
            'students_without_classroom' => $yearId === null ? 0 : $activeStudents()
                ->whereDoesntHave('enrollments', fn (Builder $q) => $q->where('academic_year_id', $yearId))
                ->count(),
            'students_without_guardian' => $activeStudents()->whereDoesntHave('guardians')->count(),
            'classrooms_without_homeroom' => $yearId === null ? 0 : Classroom::query()
                ->where('is_active', true)
                ->where('academic_year_id', $yearId)
                ->when($unit !== null, fn (Builder $q) => $q->where('unit_id', $unit->id))
                ->whereNull('homeroom_teacher_id')
                ->count(),
        ];
    }

    /**
     * @return array{pending_jobs: int, failed_jobs: int}
     */
    public function queueHealth(): array
    {
        $connection = config('queue.default');
        $usesDatabase = config("queue.connections.{$connection}.driver") === 'database';

        return [
            'pending_jobs' => $usesDatabase ? DB::table((string) config("queue.connections.{$connection}.table", 'jobs'))->count() : 0,
            'failed_jobs' => DB::table((string) config('queue.failed.table', 'failed_jobs'))->count(),
        ];
    }

    /**
     * Rekap per status untuk hari sekolah terakhir (termasuk hari ini), melewati akhir pekan
     * dan hari libur unit. Satu kueri agregat untuk seluruh rentang.
     *
     * @param  list<int>  $studentIds
     * @return list<array{date: string, present: int, late: int, excused: int, sick: int, absent: int}>
     */
    private function trend(array $studentIds, ?Unit $unit, CarbonImmutable $today): array
    {
        if ($studentIds === [] || $unit === null) {
            return [];
        }

        $weekend = $this->calendar->weekendDaysForUnit($unit);
        $start = $today->subDays(self::TREND_LOOKBACK_DAYS);

        $holidays = SchoolHoliday::query()
            ->whereBetween('holiday_date', [$start->toDateString(), $today->toDateString()])
            ->where('is_day_off', true)
            ->where(fn (Builder $q) => $q->whereNull('unit_id')->orWhere('unit_id', $unit->id))
            ->pluck('holiday_date')
            ->map(fn (mixed $value): string => substr((string) $value, 0, 10))
            ->all();

        $schoolDays = [];

        for ($day = $today; $day->greaterThanOrEqualTo($start) && count($schoolDays) < self::TREND_DAYS; $day = $day->subDay()) {
            if (in_array($day->dayOfWeekIso, $weekend, true) || in_array($day->toDateString(), $holidays, true)) {
                continue;
            }

            $schoolDays[] = $day->toDateString();
        }

        if ($schoolDays === []) {
            return [];
        }

        $schoolDays = array_reverse($schoolDays);
        $empty = ['present' => 0, 'late' => 0, 'excused' => 0, 'sick' => 0, 'absent' => 0];
        $rows = array_fill_keys($schoolDays, $empty);

        AttendanceDay::query()
            ->whereIn('student_id', $studentIds)
            ->whereBetween('school_date', [$schoolDays[0], end($schoolDays)])
            ->select('school_date', 'status', DB::raw('count(*) as aggregate'))
            ->groupBy('school_date', 'status')
            ->get()
            ->each(function (AttendanceDay $row) use (&$rows): void {
                $date = $row->school_date->toDateString();

                if (isset($rows[$date])) {
                    $rows[$date][$row->status->value] += (int) $row->getAttribute('aggregate');
                }
            });

        $trend = [];

        foreach ($rows as $date => $counts) {
            $trend[] = ['date' => $date, ...$counts];
        }

        return $trend;
    }

    private function isHomeroomOnly(User $user): bool
    {
        return ! $user->hasAnyRole([Role::Admin->value, Role::UnitHead->value, Role::Counselor->value])
            && ! $user->can(Permission::StudentsManage->value);
    }

    private function offDayLabel(Unit $unit, CarbonImmutable $today): ?string
    {
        if (in_array($today->dayOfWeekIso, $this->calendar->weekendDaysForUnit($unit), true)) {
            return 'Hari ini akhir pekan '.$unit->code.'.';
        }

        if (! $this->calendar->isHoliday($unit, $today)) {
            return null;
        }

        $title = SchoolHoliday::query()
            ->whereDate('holiday_date', $today->toDateString())
            ->where('is_day_off', true)
            ->where(fn (Builder $q) => $q->whereNull('unit_id')->orWhere('unit_id', $unit->id))
            ->orderBy('id')
            ->value('title');

        return 'Hari ini libur'.($title !== null ? ': '.$title : '').'.';
    }

    private function lateAfter(?Unit $unit): ?string
    {
        $value = $unit?->attendance_settings['late_after'] ?? null;

        return is_string($value) ? str_replace(':', '.', substr($value, 0, 5)) : null;
    }
}
