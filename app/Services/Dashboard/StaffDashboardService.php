<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Domain\Attendance\AttendanceClassifier;
use App\Enums\ExamResultStatus;
use App\Enums\ExamStatus;
use App\Enums\Feature;
use App\Enums\InvoiceStatus;
use App\Enums\LeaveRequestStatus;
use App\Enums\PaymentMethod;
use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Enums\TransferProofStatus;
use App\Models\AcademicYear;
use App\Models\AttendanceDay;
use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\DisciplineFollowup;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\ExamSession;
use App\Models\HabitLog;
use App\Models\Invoice;
use App\Models\LeaveRequest;
use App\Models\Payment;
use App\Models\RewardRedemption;
use App\Models\SchoolHoliday;
use App\Models\Student;
use App\Models\TransferProof;
use App\Models\Unit;
use App\Models\User;
use App\Services\Attendance\SchoolCalendarService;
use App\Services\Features\FeatureFlags;
use App\Support\AppTimezone;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

    private const ATTENTION_TTL_SECONDS = 60;

    /** Rentang kalender yang ditelusuri untuk mengumpulkan TREND_DAYS hari sekolah (libur panjang dilewati). */
    private const TREND_LOOKBACK_DAYS = 20;

    public function __construct(
        private readonly SchoolCalendarService $calendar,
        private readonly FeatureFlags $features,
    ) {}

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
    public function pendingLeaves(User $user, ?Unit $unit, ?int $yearId, int $limit = 4): array
    {
        $homeroomOnly = $this->isHomeroomOnly($user);

        $query = LeaveRequest::query()
            ->where('status', LeaveRequestStatus::Pending->value)
            ->when($unit !== null, fn (Builder $q) => $q->whereHas('student', fn (Builder $s) => $s->where('unit_id', $unit->id)))
            // Wali kelas hanya diminta memutuskan izin siswa rombel yang ia walikan.
            ->when($homeroomOnly, fn (Builder $q) => $q->whereHas('student.enrollments', fn (Builder $e) => $e
                ->where('academic_year_id', $yearId)
                ->whereHas('classroom', fn (Builder $c) => $c->where('homeroom_teacher_id', $user->id))));

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
        $startOfDay = CarbonImmutable::now($tz)->startOfDay()->setTimezone(AppTimezone::name());
        $endOfDay = CarbonImmutable::now($tz)->endOfDay()->setTimezone(AppTimezone::name());

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
     * Perbandingan antarunit hari ini (paritas dasbor yayasan aplikasi lama), hanya data nyata.
     *
     * @return list<array{code: string, name: string, students: int, classrooms: int, recorded: int, present: int, late: int, absent: int}>
     */
    public function unitComparison(?int $yearId): array
    {
        $date = CarbonImmutable::now(AttendanceClassifier::BUSINESS_TIMEZONE)->toDateString();
        $units = Unit::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']);

        $students = Student::query()->where('status', StudentStatus::Active->value)
            ->selectRaw('unit_id, count(*) as aggregate')->groupBy('unit_id')->pluck('aggregate', 'unit_id');
        $classrooms = Classroom::query()->where('is_active', true)
            ->when($yearId !== null, fn (Builder $q) => $q->where('academic_year_id', $yearId))
            ->selectRaw('unit_id, count(*) as aggregate')->groupBy('unit_id')->pluck('aggregate', 'unit_id');
        $attendance = AttendanceDay::query()
            ->join('students', 'students.id', '=', 'attendance_days.student_id')
            ->whereDate('attendance_days.school_date', $date)
            ->selectRaw('students.unit_id as unit_id, attendance_days.status as status, count(*) as aggregate')
            ->groupBy('students.unit_id', 'attendance_days.status')
            ->get();

        return array_values($units->map(function (Unit $unit) use ($students, $classrooms, $attendance): array {
            $rows = $attendance->where('unit_id', $unit->id);
            $count = fn (string $status): int => (int) $rows->filter(fn ($r): bool => $r->getRawOriginal('status') === $status)->sum('aggregate');

            return [
                'code' => $unit->code,
                'name' => $unit->name,
                'students' => (int) ($students[$unit->id] ?? 0),
                'classrooms' => (int) ($classrooms[$unit->id] ?? 0),
                'recorded' => (int) $rows->sum('aggregate'),
                'present' => $count('present'),
                'late' => $count('late'),
                'absent' => $count('absent'),
            ];
        })->all());
    }

    /**
     * Keamanan akun: login gagal 24 jam terakhir dan login staf terbaru.
     *
     * @return array{failed_logins_24h: int, recent_logins: list<array{name: string, at: string}>}
     */
    public function security(): array
    {
        return [
            'failed_logins_24h' => AuditLog::query()->where('action', 'login_failed')->where('created_at', '>=', now()->subDay())->count(),
            'recent_logins' => array_values(User::query()
                ->whereNotNull('last_login_at')
                ->whereHas('roles', fn (Builder $q) => $q->whereIn('name', array_map(fn (Role $r): string => $r->value, Role::staff())))
                ->orderByDesc('last_login_at')
                ->limit(5)
                ->get(['id', 'name', 'last_login_at'])
                ->map(fn (User $u): array => ['name' => $u->name, 'at' => $u->last_login_at?->toIso8601String() ?? ''])
                ->all()),
        ];
    }

    /**
     * Modul uang yang ditahan selama masa paralel (ADR-021) dan masih nonaktif.
     *
     * @return list<string>
     */
    public function heldModules(): array
    {
        return array_values(array_map(
            fn (Feature $f): string => $f->label(),
            array_filter(Feature::cases(), fn (Feature $f): bool => $f->heldUntilCutover() && ! $this->features->enabled($f)),
        ));
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
            ->whereDate('holiday_date', '>=', $start->toDateString())->whereDate('holiday_date', '<=', $today->toDateString())
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
            ->whereDate('school_date', '>=', $schoolDays[0])->whereDate('school_date', '<=', end($schoolDays))
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

    /**
     * Hal yang menunggu tindakan pengguna ini, untuk lonceng topbar staf (ADR-028).
     * Hanya hitungan nyata yang lebih dari 0; di-cache 60 detik per pengguna & konteks.
     *
     * @return list<array{key: string, label: string, count: int, href: string}>
     */
    public function attention(User $user, ?Unit $unit, ?int $yearId): array
    {
        $cacheKey = sprintf('staff.attention.%d.%s.%s', $user->id, $unit->id ?? 'all', $yearId ?? 'none');

        /** @var list<array{key: string, label: string, count: int, href: string}> */
        return Cache::remember($cacheKey, self::ATTENTION_TTL_SECONDS, function () use ($user, $unit, $yearId): array {
            $homeroomOnly = $this->isHomeroomOnly($user);
            $inUnit = fn (Builder $q) => $unit !== null ? $q->where('unit_id', $unit->id) : $q;
            // Wali kelas hanya diminta menindak siswa rombel yang ia walikan.
            $inHomeroom = fn (Builder $q) => $q->whereHas('enrollments', fn (Builder $e) => $e
                ->where('academic_year_id', $yearId)
                ->whereHas('classroom', fn (Builder $c) => $c->where('homeroom_teacher_id', $user->id)));

            $items = [];

            if ($this->features->enabled(Feature::Attendance) && $user->can(Permission::AttendanceManage->value)) {
                $items[] = [
                    'key' => 'leaves',
                    'label' => 'pengajuan izin menunggu keputusan',
                    'count' => LeaveRequest::query()
                        ->where('status', LeaveRequestStatus::Pending->value)
                        ->whereHas('student', fn (Builder $s) => $homeroomOnly ? $inHomeroom($inUnit($s)) : $inUnit($s))
                        ->count(),
                    'href' => route('admin.attendance.leaves.index', absolute: false),
                ];
            }

            if ($this->features->enabled(Feature::Habits) && $user->can(Permission::HabitsVerify->value)) {
                $items[] = [
                    'key' => 'habits',
                    'label' => 'siswa dengan pembiasaan belum diverifikasi',
                    'count' => HabitLog::query()
                        ->where('verification', 'pending')
                        ->where('done', true)
                        ->whereHas('student', fn (Builder $s) => $homeroomOnly
                            ? $inHomeroom($inUnit($s->where('status', StudentStatus::Active->value)))
                            : $inUnit($s->where('status', StudentStatus::Active->value)))
                        ->distinct()
                        ->count('student_id'),
                    'href' => route('admin.habits.verify.index', absolute: false),
                ];
            }

            if ($this->features->enabled(Feature::Behavior) && $user->can(Permission::ThresholdsManage->value)) {
                $items[] = [
                    'key' => 'followups',
                    'label' => 'tindak lanjut BK masih terbuka',
                    'count' => DisciplineFollowup::query()
                        ->where('status', 'open')
                        ->whereHas('student', $inUnit)
                        ->count(),
                    'href' => route('admin.behavior.discipline.index', absolute: false),
                ];
            }

            if ($this->features->enabled(Feature::Rewards) && $user->can(Permission::RewardsApprove->value)) {
                $items[] = [
                    'key' => 'rewards',
                    'label' => 'pengajuan reward menunggu persetujuan',
                    'count' => RewardRedemption::query()
                        ->where('status', 'pending')
                        ->whereHas('student', $inUnit)
                        ->count(),
                    'href' => route('admin.behavior.rewards.index', absolute: false),
                ];
            }

            return array_values(array_filter($items, fn (array $item): bool => $item['count'] > 0));
        });
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

    /**
     * Dasbor keuangan staf (T-617): kas masuk per periode & metode, piutang per jenis & rombel,
     * tunggakan teratas, pembayaran online belum terekonsiliasi, saldo & top-up dompet, serta per unit.
     *
     * @return array{
     *     scope: 'unit'|'all',
     *     can_view_all_units: bool,
     *     unit_code: ?string,
     *     unit_name: ?string,
     *     billing_enabled: bool,
     *     wallet_enabled: bool,
     *     cash_in: ?array{
     *         today: array{total: int, count: int, by_method: array{cash: int, transfer: int, midtrans: int}},
     *         month: array{total: int, count: int, by_method: array{cash: int, transfer: int, midtrans: int}},
     *         academic_year: array{total: int, count: int, by_method: array{cash: int, transfer: int, midtrans: int}},
     *         pending_transfer_proofs: int,
     *     },
     *     receivables: ?array{
     *         total: int,
     *         overdue: int,
     *         overdue_count: int,
     *         by_fee_type: list<array{fee_type_id: int, name: string, count: int, total: int, overdue: int}>,
     *         by_classroom: list<array{classroom_id: ?int, name: string, student_count: int, total: int, overdue: int}>,
     *         top_arrears: list<array{student_id: int, student_name: string, nis: string, classroom: string, overdue_count: int, overdue_amount: int}>,
     *     },
     *     online_unreconciled: array{available: bool, midtrans_enabled: bool, pending_count: int, pending_amount: int, needs_review_count: int},
     *     wallet: ?array{available: bool, total_balance: int, active_wallets: int, frozen_wallets: int, topup_today: int, topup_month: int},
     *     by_unit: list<array{code: string, name: string, cash_in_month: int, receivables_total: int, receivables_overdue: int, wallet_balance: int}>,
     * }
     */
    public function finance(?Unit $unit, ?int $yearId, bool $billingOn, bool $walletOn, bool $canViewAllUnits): array
    {
        $nowWita = CarbonImmutable::now(AttendanceClassifier::BUSINESS_TIMEZONE);
        $scopeUnit = $canViewAllUnits && $unit === null ? null : $unit;

        return [
            'scope' => $scopeUnit === null ? 'all' : 'unit',
            'can_view_all_units' => $canViewAllUnits,
            'unit_code' => $scopeUnit?->code,
            'unit_name' => $scopeUnit?->name,
            'billing_enabled' => $billingOn,
            'wallet_enabled' => $walletOn,
            'cash_in' => $billingOn ? $this->financeCashIn($scopeUnit, $yearId, $nowWita) : null,
            'receivables' => $billingOn ? $this->financeReceivables($scopeUnit, $yearId, $nowWita) : null,
            'online_unreconciled' => $this->financeOnlineUnreconciled($scopeUnit),
            'wallet' => $walletOn ? $this->financeWallet($scopeUnit, $nowWita) : null,
            'by_unit' => $canViewAllUnits ? $this->financeByUnit($billingOn, $walletOn, $nowWita) : [],
        ];
    }

    /**
     * @return array{
     *     today: array{total: int, count: int, by_method: array{cash: int, transfer: int, midtrans: int}},
     *     month: array{total: int, count: int, by_method: array{cash: int, transfer: int, midtrans: int}},
     *     academic_year: array{total: int, count: int, by_method: array{cash: int, transfer: int, midtrans: int}},
     *     pending_transfer_proofs: int,
     * }
     */
    private function financeCashIn(?Unit $unit, ?int $yearId, CarbonImmutable $nowWita): array
    {
        $year = $yearId !== null ? AcademicYear::query()->find($yearId) : null;
        $yearStart = ($year !== null ? $year->starts_on : $nowWita->startOfYear())->setTimezone(AttendanceClassifier::BUSINESS_TIMEZONE)->startOfDay();
        $yearEnd = ($year !== null ? $year->ends_on : $nowWita->endOfYear())->setTimezone(AttendanceClassifier::BUSINESS_TIMEZONE)->endOfDay();

        $summarize = function (CarbonImmutable $fromWita, CarbonImmutable $toWita) use ($unit): array {
            $rows = Payment::query()
                ->whereNull('payments.reversed_at')
                ->whereBetween('payments.paid_at', [$fromWita->setTimezone(AppTimezone::name()), $toWita->setTimezone(AppTimezone::name())])
                ->when($unit !== null, fn (Builder $q) => $q->whereHas('student', fn (Builder $s) => $s->where('unit_id', $unit->id)))
                ->selectRaw('payments.method as method, count(*) as tx_count, coalesce(sum(payments.amount), 0) as total_amount')
                ->groupBy('payments.method')
                ->get();

            $byMethod = [
                PaymentMethod::Cash->value => 0,
                PaymentMethod::Transfer->value => 0,
                PaymentMethod::Midtrans->value => 0,
            ];
            $total = 0;
            $count = 0;

            foreach ($rows as $row) {
                $methodKey = (string) $row->getRawOriginal('method');
                $amount = (int) $row->getAttribute('total_amount');
                $txCount = (int) $row->getAttribute('tx_count');

                if (array_key_exists($methodKey, $byMethod)) {
                    $byMethod[$methodKey] += $amount;
                }

                $total += $amount;
                $count += $txCount;
            }

            return [
                'total' => $total,
                'count' => $count,
                'by_method' => [
                    'cash' => $byMethod[PaymentMethod::Cash->value],
                    'transfer' => $byMethod[PaymentMethod::Transfer->value],
                    'midtrans' => $byMethod[PaymentMethod::Midtrans->value],
                ],
            ];
        };

        $pendingProofs = TransferProof::query()
            ->where('status', TransferProofStatus::Pending->value)
            ->when($unit !== null, fn (Builder $q) => $q->whereHas('student', fn (Builder $s) => $s->where('unit_id', $unit->id)))
            ->count();

        return [
            'today' => $summarize($nowWita->startOfDay(), $nowWita->endOfDay()),
            'month' => $summarize($nowWita->startOfMonth(), $nowWita->endOfMonth()),
            'academic_year' => $summarize($yearStart, $yearEnd),
            'pending_transfer_proofs' => $pendingProofs,
        ];
    }

    /**
     * @return array{
     *     total: int,
     *     overdue: int,
     *     overdue_count: int,
     *     by_fee_type: list<array{fee_type_id: int, name: string, count: int, total: int, overdue: int}>,
     *     by_classroom: list<array{classroom_id: ?int, name: string, student_count: int, total: int, overdue: int}>,
     *     top_arrears: list<array{student_id: int, student_name: string, nis: string, classroom: string, overdue_count: int, overdue_amount: int}>,
     * }
     */
    private function financeReceivables(?Unit $unit, ?int $yearId, CarbonImmutable $nowWita): array
    {
        $todayDate = $nowWita->toDateString();
        $remainingExpr = 'CASE WHEN (invoices.amount - invoices.adjustment_total - invoices.paid_total) > 0 THEN (invoices.amount - invoices.adjustment_total - invoices.paid_total) ELSE 0 END';

        $baseQuery = fn (): Builder => Invoice::query()
            ->join('students', 'students.id', '=', 'invoices.student_id')
            ->whereIn('invoices.status', [InvoiceStatus::Unpaid->value, InvoiceStatus::Partial->value])
            ->when($unit !== null, fn (Builder $q) => $q->where('students.unit_id', $unit->id));

        /** @var object{total_amount: int|string|null, overdue_amount: int|string|null, overdue_count: int|string|null}|null $summary */
        $summary = $baseQuery()
            ->selectRaw("coalesce(sum({$remainingExpr}), 0) as total_amount")
            ->selectRaw("coalesce(sum(CASE WHEN invoices.due_date < ? THEN {$remainingExpr} ELSE 0 END), 0) as overdue_amount", [$todayDate])
            ->selectRaw('coalesce(sum(CASE WHEN invoices.due_date < ? THEN 1 ELSE 0 END), 0) as overdue_count', [$todayDate])
            ->toBase()
            ->first();

        $byFeeType = array_values($baseQuery()
            ->join('fee_types', 'fee_types.id', '=', 'invoices.fee_type_id')
            ->selectRaw('fee_types.id as fee_type_id, fee_types.name as fee_name, count(*) as invoice_count')
            ->selectRaw("coalesce(sum({$remainingExpr}), 0) as total_amount")
            ->selectRaw("coalesce(sum(CASE WHEN invoices.due_date < ? THEN {$remainingExpr} ELSE 0 END), 0) as overdue_amount", [$todayDate])
            ->groupBy('fee_types.id', 'fee_types.name')
            ->orderByDesc('total_amount')
            ->orderBy('fee_types.name')
            ->limit(8)
            ->toBase()
            ->get()
            ->map(fn (object $row): array => [
                'fee_type_id' => (int) $row->fee_type_id,
                'name' => (string) $row->fee_name,
                'count' => (int) $row->invoice_count,
                'total' => (int) $row->total_amount,
                'overdue' => (int) $row->overdue_amount,
            ])
            ->all());

        $byClassroomQuery = $baseQuery();
        if ($yearId !== null) {
            $byClassroomQuery
                ->leftJoin('enrollments', function ($join) use ($yearId): void {
                    $join->on('enrollments.student_id', '=', 'students.id')
                        ->where('enrollments.academic_year_id', '=', $yearId);
                })
                ->leftJoin('classrooms', 'classrooms.id', '=', 'enrollments.classroom_id');
        } else {
            $byClassroomQuery->leftJoin('classrooms', fn ($join) => $join->whereRaw('1 = 0'));
        }

        $byClassroom = array_values($byClassroomQuery
            ->selectRaw("classrooms.id as classroom_id, coalesce(classrooms.name, 'Tanpa rombel') as classroom_name, count(distinct students.id) as student_count")
            ->selectRaw("coalesce(sum({$remainingExpr}), 0) as total_amount")
            ->selectRaw("coalesce(sum(CASE WHEN invoices.due_date < ? THEN {$remainingExpr} ELSE 0 END), 0) as overdue_amount", [$todayDate])
            ->groupBy('classrooms.id', 'classrooms.name')
            ->orderByDesc('overdue_amount')
            ->orderByDesc('total_amount')
            ->orderBy('classroom_name')
            ->limit(10)
            ->toBase()
            ->get()
            ->map(fn (object $row): array => [
                'classroom_id' => $row->classroom_id !== null ? (int) $row->classroom_id : null,
                'name' => (string) $row->classroom_name,
                'student_count' => (int) $row->student_count,
                'total' => (int) $row->total_amount,
                'overdue' => (int) $row->overdue_amount,
            ])
            ->all());

        $topArrearsRows = $baseQuery()
            ->whereDate('invoices.due_date', '<', $todayDate)
            ->selectRaw("students.id as student_id, students.name as student_name, students.nis as nis, count(*) as overdue_count, coalesce(sum({$remainingExpr}), 0) as overdue_amount")
            ->groupBy('students.id', 'students.name', 'students.nis')
            ->orderByDesc('overdue_amount')
            ->orderBy('students.name')
            ->limit(6)
            ->toBase()
            ->get();

        $topStudentIds = $topArrearsRows->pluck('student_id')->map(fn (mixed $id): int => (int) $id)->all();
        $classroomByStudent = $yearId !== null && $topStudentIds !== []
            ? Enrollment::query()
                ->where('academic_year_id', $yearId)
                ->whereIn('student_id', $topStudentIds)
                ->with('classroom:id,name')
                ->get(['student_id', 'classroom_id'])
                ->mapWithKeys(fn (Enrollment $e): array => [$e->student_id => $e->classroom->name])
            : collect();

        $topArrears = array_values($topArrearsRows->map(fn (object $row): array => [
            'student_id' => (int) $row->student_id,
            'student_name' => (string) $row->student_name,
            'nis' => (string) $row->nis,
            'classroom' => (string) $classroomByStudent->get((int) $row->student_id, '-'),
            'overdue_count' => (int) $row->overdue_count,
            'overdue_amount' => (int) $row->overdue_amount,
        ])->all());

        return [
            'total' => (int) ($summary->total_amount ?? 0),
            'overdue' => (int) ($summary->overdue_amount ?? 0),
            'overdue_count' => (int) ($summary->overdue_count ?? 0),
            'by_fee_type' => $byFeeType,
            'by_classroom' => $byClassroom,
            'top_arrears' => $topArrears,
        ];
    }

    /**
     * @return array{available: bool, midtrans_enabled: bool, pending_count: int, pending_amount: int, needs_review_count: int}
     */
    private function financeOnlineUnreconciled(?Unit $unit): array
    {
        $midtransEnabled = $this->features->enabled(Feature::Midtrans);

        if (! Schema::hasTable('payment_orders')) {
            return [
                'available' => false,
                'midtrans_enabled' => $midtransEnabled,
                'pending_count' => 0,
                'pending_amount' => 0,
                'needs_review_count' => 0,
            ];
        }

        $query = DB::table('payment_orders')
            ->when($unit !== null && Schema::hasTable('payment_order_items'), function ($q) use ($unit): void {
                $q->whereExists(function ($sub) use ($unit): void {
                    $sub->selectRaw('1')
                        ->from('payment_order_items')
                        ->join('invoices', 'invoices.id', '=', 'payment_order_items.invoice_id')
                        ->join('students', 'students.id', '=', 'invoices.student_id')
                        ->whereColumn('payment_order_items.payment_order_id', 'payment_orders.id')
                        ->where('students.unit_id', $unit->id);
                });
            });

        /** @var object{pending_count: int|string|null, pending_amount: int|string|null, needs_review_count: int|string|null}|null $row */
        $row = $query
            ->selectRaw("coalesce(sum(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) as pending_count")
            ->selectRaw("coalesce(sum(CASE WHEN status = 'pending' THEN gross_amount ELSE 0 END), 0) as pending_amount")
            // Order bermasalah ditandai lewat review_reason (T-703), bukan status tersendiri.
            ->selectRaw('coalesce(sum(CASE WHEN review_reason IS NOT NULL AND reviewed_at IS NULL THEN 1 ELSE 0 END), 0) as needs_review_count')
            ->first();

        return [
            'available' => true,
            'midtrans_enabled' => $midtransEnabled,
            'pending_count' => (int) ($row->pending_count ?? 0),
            'pending_amount' => (int) ($row->pending_amount ?? 0),
            'needs_review_count' => (int) ($row->needs_review_count ?? 0),
        ];
    }

    /**
     * @return array{available: bool, total_balance: int, active_wallets: int, frozen_wallets: int, topup_today: int, topup_month: int}
     */
    private function financeWallet(?Unit $unit, CarbonImmutable $nowWita): array
    {
        if (! Schema::hasTable('wallets') || ! Schema::hasTable('wallet_entries')) {
            return [
                'available' => false,
                'total_balance' => 0,
                'active_wallets' => 0,
                'frozen_wallets' => 0,
                'topup_today' => 0,
                'topup_month' => 0,
            ];
        }

        /** @var object{total_balance: int|string|null, active_wallets: int|string|null, frozen_wallets: int|string|null}|null $walletRow */
        $walletRow = DB::table('wallets')
            ->join('students', 'students.id', '=', 'wallets.student_id')
            ->when($unit !== null, fn ($q) => $q->where('students.unit_id', $unit->id))
            ->selectRaw('coalesce(sum(wallets.balance), 0) as total_balance')
            ->selectRaw('coalesce(sum(CASE WHEN wallets.balance > 0 THEN 1 ELSE 0 END), 0) as active_wallets')
            ->selectRaw('coalesce(sum(CASE WHEN wallets.is_frozen = 1 THEN 1 ELSE 0 END), 0) as frozen_wallets')
            ->first();

        /** @var object{topup_today: int|string|null, topup_month: int|string|null}|null $entryRow */
        $entryRow = DB::table('wallet_entries')
            ->join('wallets', 'wallets.id', '=', 'wallet_entries.wallet_id')
            ->join('students', 'students.id', '=', 'wallets.student_id')
            ->where('wallet_entries.type', 'topup')
            ->whereBetween('wallet_entries.created_at', [$nowWita->startOfMonth()->setTimezone(AppTimezone::name()), $nowWita->endOfMonth()->setTimezone(AppTimezone::name())])
            ->when($unit !== null, fn ($q) => $q->where('students.unit_id', $unit->id))
            ->selectRaw('coalesce(sum(CASE WHEN wallet_entries.created_at >= ? AND wallet_entries.created_at <= ? THEN wallet_entries.amount ELSE 0 END), 0) as topup_today', [
                $nowWita->startOfDay()->setTimezone(AppTimezone::name())->toDateTimeString(),
                $nowWita->endOfDay()->setTimezone(AppTimezone::name())->toDateTimeString(),
            ])
            ->selectRaw('coalesce(sum(wallet_entries.amount), 0) as topup_month')
            ->first();

        return [
            'available' => true,
            'total_balance' => (int) ($walletRow->total_balance ?? 0),
            'active_wallets' => (int) ($walletRow->active_wallets ?? 0),
            'frozen_wallets' => (int) ($walletRow->frozen_wallets ?? 0),
            'topup_today' => (int) ($entryRow->topup_today ?? 0),
            'topup_month' => (int) ($entryRow->topup_month ?? 0),
        ];
    }

    /**
     * @return list<array{code: string, name: string, cash_in_month: int, receivables_total: int, receivables_overdue: int, wallet_balance: int}>
     */
    private function financeByUnit(bool $billingOn, bool $walletOn, CarbonImmutable $nowWita): array
    {
        $units = Unit::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']);

        $cashByUnit = collect();
        $receivablesByUnit = collect();
        $walletByUnit = collect();

        if ($billingOn) {
            $cashByUnit = Payment::query()
                ->join('students', 'students.id', '=', 'payments.student_id')
                ->whereNull('payments.reversed_at')
                ->whereBetween('payments.paid_at', [$nowWita->startOfMonth()->setTimezone(AppTimezone::name()), $nowWita->endOfMonth()->setTimezone(AppTimezone::name())])
                ->selectRaw('students.unit_id as unit_id, coalesce(sum(payments.amount), 0) as total_amount')
                ->groupBy('students.unit_id')
                ->pluck('total_amount', 'unit_id');

            $todayDate = $nowWita->toDateString();
            $remainingExpr = 'CASE WHEN (invoices.amount - invoices.adjustment_total - invoices.paid_total) > 0 THEN (invoices.amount - invoices.adjustment_total - invoices.paid_total) ELSE 0 END';

            $receivablesByUnit = Invoice::query()
                ->join('students', 'students.id', '=', 'invoices.student_id')
                ->whereIn('invoices.status', [InvoiceStatus::Unpaid->value, InvoiceStatus::Partial->value])
                ->selectRaw("students.unit_id as unit_id, coalesce(sum({$remainingExpr}), 0) as total_amount, coalesce(sum(CASE WHEN invoices.due_date < ? THEN {$remainingExpr} ELSE 0 END), 0) as overdue_amount", [$todayDate])
                ->groupBy('students.unit_id')
                ->toBase()
                ->get()
                ->keyBy('unit_id');
        }

        if ($walletOn && Schema::hasTable('wallets')) {
            $walletByUnit = DB::table('wallets')
                ->join('students', 'students.id', '=', 'wallets.student_id')
                ->selectRaw('students.unit_id as unit_id, coalesce(sum(wallets.balance), 0) as total_balance')
                ->groupBy('students.unit_id')
                ->pluck('total_balance', 'unit_id');
        }

        return array_values($units->map(function (Unit $unit) use ($cashByUnit, $receivablesByUnit, $walletByUnit): array {
            /** @var object{total_amount: int|string, overdue_amount: int|string}|null $rec */
            $rec = $receivablesByUnit->get($unit->id);

            return [
                'code' => $unit->code,
                'name' => $unit->name,
                'cash_in_month' => (int) ($cashByUnit[$unit->id] ?? 0),
                'receivables_total' => (int) ($rec->total_amount ?? 0),
                'receivables_overdue' => (int) ($rec->overdue_amount ?? 0),
                'wallet_balance' => (int) ($walletByUnit[$unit->id] ?? 0),
            ];
        })->all());
    }
}
