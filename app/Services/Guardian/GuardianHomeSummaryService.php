<?php

declare(strict_types=1);

namespace App\Services\Guardian;

use App\Domain\Attendance\AttendanceClassifier;
use App\Domain\Billing\InvoiceStatusResolver;
use App\Domain\Exam\ExamEligibilityResolver;
use App\Enums\AttendanceStatus;
use App\Enums\BehaviorRecordStatus;
use App\Enums\ExamPeriodStatus;
use App\Enums\ExamResultStatus;
use App\Enums\ExamStatus;
use App\Enums\Feature;
use App\Enums\InvoiceStatus;
use App\Enums\LeaveRequestStatus;
use App\Enums\PointAccount;
use App\Models\AcademicYear;
use App\Models\AttendanceDay;
use App\Models\BehaviorRecord;
use App\Models\Exam;
use App\Models\ExamEligibilityOverride;
use App\Models\ExamPeriod;
use App\Models\ExamResult;
use App\Models\Invoice;
use App\Models\LeaveRequest;
use App\Models\PointEntry;
use App\Models\SchoolHoliday;
use App\Models\Student;
use App\Services\Attendance\SchoolCalendarService;
use App\Services\Features\FeatureFlags;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

/**
 * Menyusun ringkasan Beranda Wali per anak berdasarkan urgensi (T-303, DESIGN §3 Wali).
 *
 * Bagian modul yang dimatikan bernilai `null`; bagian tanpa data juga `null` agar
 * tidak tampil sebagai kartu kosong. `cards` dipertahankan untuk kartu ringkas
 * berurutan prioritas. Di-cache 60 detik per anak.
 *
 * @phpstan-type HomeSummary array{
 *     student: array{id: int, nis: string, name: string, nickname: ?string, unit: string, classroom: ?string, status_label: string, photo_url: ?string},
 *     today: ?array{date: string, off_day: ?string, status: ?string, status_label: ?string, checked_in_at: ?string, checked_out_at: ?string, late_after: ?string, href: string},
 *     week: list<array{date: string, status: ?string, status_label: string}>,
 *     leave: ?array{id: int, status: string, status_label: string, type_label: string, starts_on: string, ends_on: string, decision_note: ?string},
 *     behavior: ?array{merit: int, violation: int, latest: ?array{title: string, category: string, category_label: string, points: int, occurred_on: string}, href: ?string},
 *     exams: ?array{upcoming: list<array{id: int, title: string, subject: string, status: string, opens_at: string, closes_at: string}>, latest_result: ?array{title: string, subject: string, score: int, computed_at: string}, href: string},
 *     exam_eligibility: ?array{period_name: string, delivery_label: string, starts_on: string, total_amount: int, invoices: list<array{id: int, description: string, remaining: int, due_date: string}>, href: string},
 *     cards: list<array{key: string, priority: int, title: string, summary: string, tone: string, href: ?string}>
 * }
 */
final class GuardianHomeSummaryService
{
    public const CACHE_TTL_SECONDS = 60;

    private const WEEK_SCHOOL_DAYS = 5;

    private const LEAVE_RECENT_DAYS = 7;

    private const EXAM_LOOKAHEAD_DAYS = 7;

    public function __construct(
        private readonly FeatureFlags $features,
        private readonly SchoolCalendarService $calendar,
        private readonly ExamEligibilityResolver $eligibilityResolver,
    ) {}

    public static function cacheKey(int $studentId): string
    {
        return "guardian.home.student.{$studentId}";
    }

    public function invalidate(int $studentId): void
    {
        Cache::forget(self::cacheKey($studentId));
    }

    /**
     * @return HomeSummary
     */
    public function forStudent(Student $student): array
    {
        /** @var HomeSummary */
        return Cache::remember(
            self::cacheKey($student->id),
            self::CACHE_TTL_SECONDS,
            fn (): array => $this->build($student),
        );
    }

    /**
     * @return HomeSummary
     */
    private function build(Student $student): array
    {
        $activeYearId = AcademicYear::query()->where('is_active', true)->value('id');
        $student->load([
            'unit',
            'enrollments' => fn ($q) => $activeYearId !== null
                ? $q->where('academic_year_id', $activeYearId)->with('classroom')
                : $q->whereRaw('1 = 0'),
        ]);

        $enrollment = $student->enrollments->first();
        $now = CarbonImmutable::now(AttendanceClassifier::BUSINESS_TIMEZONE);
        $attendanceOn = $this->features->enabled(Feature::Attendance);

        $todayRecord = $attendanceOn ? $this->todayRecord($student, $now) : null;

        return [
            'student' => [
                'id' => $student->id,
                'nis' => $student->nis,
                'name' => $student->name,
                'nickname' => $student->nickname,
                'unit' => $student->unit->code,
                'classroom' => $enrollment?->classroom->name,
                'status_label' => $student->status->label(),
                'photo_url' => $student->photo_path !== null
                    ? route('guardian.students.photo', ['student' => $student->id])
                    : null,
            ],
            'today' => $attendanceOn ? $this->today($student, $todayRecord, $now) : null,
            'week' => $attendanceOn ? $this->week($student, $now) : [],
            'leave' => $attendanceOn ? $this->leave($student, $now) : null,
            'behavior' => $this->features->enabled(Feature::Behavior) && is_int($activeYearId)
                ? $this->behavior($student, $activeYearId)
                : null,
            'exams' => $this->features->enabled(Feature::Cbt)
                ? $this->exams($student, $enrollment?->classroom_id, $now)
                : null,
            'exam_eligibility' => $this->features->enabled(Feature::ExamEligibility)
                ? $this->examEligibility($student)
                : null,
            'cards' => $todayRecord !== null ? [$this->attendanceCard($student, $todayRecord)] : [],
        ];
    }

    private function todayRecord(Student $student, CarbonImmutable $now): ?AttendanceDay
    {
        return AttendanceDay::query()
            ->where('student_id', $student->id)
            ->whereDate('school_date', $now->toDateString())
            ->first();
    }

    /**
     * @return array{date: string, off_day: ?string, status: ?string, status_label: ?string, checked_in_at: ?string, checked_out_at: ?string, late_after: ?string, href: string}
     */
    private function today(Student $student, ?AttendanceDay $record, CarbonImmutable $now): array
    {
        $tz = AttendanceClassifier::BUSINESS_TIMEZONE;
        $offDay = null;

        if ($record === null && $this->calendar->isHoliday($student->unit, $now)) {
            $offDay = in_array($now->dayOfWeekIso, $this->calendar->weekendDaysForUnit($student->unit), true)
                ? 'Akhir pekan, tidak ada kegiatan sekolah.'
                : 'Hari ini libur sekolah.';
        }

        $lateAfter = $student->unit->attendance_settings['late_after'] ?? null;

        return [
            'date' => $now->toDateString(),
            'off_day' => $offDay,
            'status' => $record?->status->value,
            'status_label' => $record?->status->label(),
            'checked_in_at' => $record?->checked_in_at?->setTimezone($tz)->format('H.i'),
            'checked_out_at' => $record?->checked_out_at?->setTimezone($tz)->format('H.i'),
            'late_after' => is_string($lateAfter) ? str_replace(':', '.', substr($lateAfter, 0, 5)) : null,
            'href' => route('guardian.students.attendance.index', ['student' => $student->id]),
        ];
    }

    /**
     * Status lima hari sekolah terakhir sampai hari ini, melewati akhir pekan dan libur unit.
     *
     * @return list<array{date: string, status: ?string, status_label: string}>
     */
    private function week(Student $student, CarbonImmutable $now): array
    {
        $unit = $student->unit;
        $weekend = $this->calendar->weekendDaysForUnit($unit);
        $start = $now->subDays(14);

        $holidays = SchoolHoliday::query()
            ->whereDate('holiday_date', '>=', $start->toDateString())->whereDate('holiday_date', '<=', $now->toDateString())
            ->where('is_day_off', true)
            ->where(fn (Builder $q) => $q->whereNull('unit_id')->orWhere('unit_id', $unit->id))
            ->pluck('holiday_date')
            ->map(fn (mixed $value): string => substr((string) $value, 0, 10))
            ->all();

        $days = [];

        for ($day = $now; $day->greaterThanOrEqualTo($start) && count($days) < self::WEEK_SCHOOL_DAYS; $day = $day->subDay()) {
            if (! in_array($day->dayOfWeekIso, $weekend, true) && ! in_array($day->toDateString(), $holidays, true)) {
                $days[] = $day->toDateString();
            }
        }

        if ($days === []) {
            return [];
        }

        $days = array_reverse($days);

        $records = AttendanceDay::query()
            ->where('student_id', $student->id)
            ->whereDate('school_date', '>=', $days[0])->whereDate('school_date', '<=', end($days))
            ->get(['school_date', 'status'])
            ->mapWithKeys(fn (AttendanceDay $d): array => [$d->school_date->toDateString() => $d->status]);

        return array_map(function (string $date) use ($records): array {
            /** @var AttendanceStatus|null $status */
            $status = $records->get($date);

            return [
                'date' => $date,
                'status' => $status?->value,
                'status_label' => $status?->label() ?? 'Belum tercatat',
            ];
        }, $days);
    }

    /**
     * Pengajuan izin yang masih menunggu, atau yang baru diputuskan dalam 7 hari terakhir.
     *
     * @return array{id: int, status: string, status_label: string, type_label: string, starts_on: string, ends_on: string, decision_note: ?string}|null
     */
    private function leave(Student $student, CarbonImmutable $now): ?array
    {
        $leave = LeaveRequest::query()
            ->where('student_id', $student->id)
            ->where(fn (Builder $q) => $q
                ->where('status', LeaveRequestStatus::Pending->value)
                ->orWhere('decided_at', '>=', $now->subDays(self::LEAVE_RECENT_DAYS)->utc()))
            ->orderByRaw('case when status = ? then 0 else 1 end', [LeaveRequestStatus::Pending->value])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if ($leave === null) {
            return null;
        }

        return [
            'id' => $leave->id,
            'status' => $leave->status->value,
            'status_label' => $leave->status->label(),
            'type_label' => $leave->type->label(),
            'starts_on' => $leave->starts_on->toDateString(),
            'ends_on' => $leave->ends_on->toDateString(),
            'decision_note' => $leave->decision_note,
        ];
    }

    /**
     * @return array{merit: int, violation: int, latest: ?array{title: string, category: string, category_label: string, points: int, occurred_on: string}, href: ?string}|null
     */
    private function behavior(Student $student, int $yearId): ?array
    {
        $balances = PointEntry::query()
            ->where('student_id', $student->id)
            ->where('academic_year_id', $yearId)
            ->orderByDesc('id')
            ->get(['id', 'account', 'balance_after'])
            ->unique(fn (PointEntry $entry): string => $entry->account->value)
            ->mapWithKeys(fn (PointEntry $entry): array => [$entry->account->value => (int) $entry->balance_after]);

        /** @var BehaviorRecord|null $latest */
        $latest = BehaviorRecord::query()
            ->with('pointRule:id,name')
            ->where('student_id', $student->id)
            ->where('academic_year_id', $yearId)
            ->where('status', '!=', BehaviorRecordStatus::Voided->value)
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->first();

        if ($balances->isEmpty() && $latest === null) {
            return null;
        }

        return [
            'merit' => (int) $balances->get(PointAccount::Merit->value, 0),
            'violation' => (int) $balances->get(PointAccount::Violation->value, 0),
            'latest' => $latest === null ? null : [
                'title' => $latest->pointRule->name,
                'category' => $latest->category->value,
                'category_label' => $latest->category->label(),
                'points' => $latest->points,
                'occurred_on' => $latest->occurred_on->toDateString(),
            ],
            'href' => Route::has('guardian.students.behavior.index')
                ? route('guardian.students.behavior.index', ['student' => $student->id])
                : null,
        ];
    }

    /**
     * Ujian rombel anak yang berlangsung atau dibuka 7 hari ke depan, dan nilai akhir terbaru yang diizinkan sekolah.
     *
     * @return array{upcoming: list<array{id: int, title: string, subject: string, status: string, opens_at: string, closes_at: string}>, latest_result: ?array{title: string, subject: string, score: int, computed_at: string}, href: string}|null
     */
    private function exams(Student $student, ?int $classroomId, CarbonImmutable $now): ?array
    {
        $upcoming = [];

        if ($classroomId !== null) {
            $upcoming = array_values(Exam::query()
                ->with('subject:id,name')
                ->whereIn('status', [ExamStatus::Scheduled->value, ExamStatus::Running->value])
                ->where('closes_at', '>=', $now->utc())
                ->where('opens_at', '<=', $now->addDays(self::EXAM_LOOKAHEAD_DAYS)->utc())
                ->orderBy('opens_at')
                ->orderBy('id')
                ->limit(30)
                ->get()
                ->filter(fn (Exam $exam): bool => in_array($classroomId, array_map('intval', $exam->classroom_ids ?? []), true))
                ->take(3)
                ->map(fn (Exam $exam): array => [
                    'id' => $exam->id,
                    'title' => $exam->title,
                    'subject' => $exam->subject->name,
                    'status' => $exam->status->value,
                    'opens_at' => $exam->opens_at->toIso8601String(),
                    'closes_at' => $exam->closes_at->toIso8601String(),
                ])
                ->all());
        }

        /** @var ExamResult|null $result */
        $result = ExamResult::query()
            ->with('session.exam.subject')
            ->where('status', ExamResultStatus::Final->value)
            ->whereHas('session', fn (Builder $q) => $q
                ->where('student_id', $student->id)
                ->whereHas('exam', fn (Builder $e) => $e->where('show_result_to_guardian', true)))
            ->orderByDesc('computed_at')
            ->orderByDesc('id')
            ->first();

        if ($upcoming === [] && $result === null) {
            return null;
        }

        return [
            'upcoming' => $upcoming,
            'latest_result' => $result === null ? null : [
                'title' => $result->session->exam->title,
                'subject' => $result->session->exam->subject->name,
                'score' => $result->score,
                'computed_at' => $result->computed_at->toIso8601String(),
            ],
            'href' => route('guardian.students.exams.index', ['student' => $student->id]),
        ];
    }

    /**
     * Kartu ringkas presensi (T-303) tetap dikirim untuk klien lama dan urutan prioritas.
     *
     * @return array{key: string, priority: int, title: string, summary: string, tone: string, href: ?string}
     */
    private function attendanceCard(Student $student, AttendanceDay $record): array
    {
        $tz = AttendanceClassifier::BUSINESS_TIMEZONE;
        $parts = [$record->status->label()];

        if ($record->checked_in_at !== null) {
            $parts[] = 'Masuk '.$record->checked_in_at->setTimezone($tz)->format('H.i');
        }

        if ($record->checked_out_at !== null) {
            $parts[] = 'Pulang '.$record->checked_out_at->setTimezone($tz)->format('H.i');
        }

        return [
            'key' => 'attendance_today',
            'priority' => 1,
            'title' => 'Presensi hari ini',
            'summary' => implode(' · ', $parts),
            'tone' => match ($record->status) {
                AttendanceStatus::Present => 'success',
                AttendanceStatus::Late, AttendanceStatus::Excused, AttendanceStatus::Sick => 'warning',
                AttendanceStatus::Absent => 'danger',
            },
            'href' => route('guardian.students.attendance.index', ['student' => $student->id]),
        ];
    }

    /**
     * @return ?array{period_name: string, delivery_label: string, starts_on: string, total_amount: int, invoices: list<array{id: int, description: string, remaining: int, due_date: string}>, href: string}
     */
    private function examEligibility(Student $student): ?array
    {
        $periods = ExamPeriod::query()
            ->with('requirements')
            ->where('unit_id', $student->unit_id)
            ->where('status', ExamPeriodStatus::Active->value)
            ->orderBy('starts_on')
            ->get();

        if ($periods->isEmpty()) {
            return null;
        }

        $feeTypeIds = $periods->flatMap(fn (ExamPeriod $p) => $p->requirements->pluck('fee_type_id'))->unique()->values()->all();
        if ($feeTypeIds === []) {
            return null;
        }

        $invoices = Invoice::query()
            ->where('student_id', $student->id)
            ->whereIn('fee_type_id', $feeTypeIds)
            ->where('status', '!=', InvoiceStatus::Cancelled->value)
            ->orderBy('due_date')
            ->get();

        $activeOverridePeriodIds = ExamEligibilityOverride::query()
            ->whereIn('exam_period_id', $periods->pluck('id')->all())
            ->where('student_id', $student->id)
            ->whereNull('revoked_at')
            ->pluck('exam_period_id')
            ->flip();

        foreach ($periods as $period) {
            if ($activeOverridePeriodIds->has($period->id)) {
                continue;
            }

            $eval = $this->eligibilityResolver->evaluateModels($period->requirements, $invoices);
            if ($eval['eligible']) {
                continue;
            }

            $blockingSet = array_flip($eval['blocking_invoice_ids']);
            $blockingInvoices = [];
            $totalAmount = 0;

            foreach ($invoices as $inv) {
                if (! isset($blockingSet[$inv->id])) {
                    continue;
                }

                $remaining = InvoiceStatusResolver::remaining($inv->amount, $inv->adjustment_total, $inv->paid_total);
                if ($remaining <= 0) {
                    continue;
                }

                $totalAmount += $remaining;
                $blockingInvoices[] = [
                    'id' => $inv->id,
                    'description' => $inv->description,
                    'remaining' => $remaining,
                    'due_date' => $inv->due_date->toDateString(),
                ];
            }

            if ($blockingInvoices === []) {
                continue;
            }

            return [
                'period_name' => $period->name,
                'delivery_label' => $period->delivery->label(),
                'starts_on' => $period->starts_on->toDateString(),
                'total_amount' => $totalAmount,
                'invoices' => $blockingInvoices,
                'href' => route('guardian.billing', absolute: false),
            ];
        }

        return null;
    }
}
