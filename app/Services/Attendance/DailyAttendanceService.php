<?php

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Domain\Attendance\AttendanceClassifier;
use App\Enums\AttendanceDecider;
use App\Enums\AttendanceStatus;
use App\Enums\StudentStatus;
use App\Models\AttendanceDay;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Guardian\GuardianHomeSummaryService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rekap presensi harian/bulanan staf & koreksi manual oleh wali kelas/admin (T-508).
 */
final class DailyAttendanceService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly GuardianHomeSummaryService $homeCache,
    ) {}

    public function correct(
        Student $student,
        string $schoolDate,
        AttendanceStatus $status,
        string $reason,
        User $actor,
    ): AttendanceDay {
        return DB::transaction(function () use ($student, $schoolDate, $status, $reason, $actor): AttendanceDay {
            Student::query()->whereKey($student->id)->lockForUpdate()->firstOrFail();

            /** @var AttendanceDay|null $existing */
            $existing = AttendanceDay::query()
                ->where('student_id', $student->id)
                ->whereDate('school_date', $schoolDate)
                ->lockForUpdate()
                ->first();

            $before = [
                'status' => $existing?->status->value ?? 'not_yet',
                'decided_by' => $existing?->decided_by->value,
            ];

            if ($existing === null) {
                $day = AttendanceDay::query()->create([
                    'student_id' => $student->id,
                    'school_date' => $schoolDate,
                    'status' => $status,
                    'checked_in_at' => null,
                    'checked_out_at' => null,
                    'leave_request_id' => null,
                    'decided_by' => AttendanceDecider::Manual,
                ]);
            } else {
                $existing->forceFill([
                    'status' => $status,
                    'decided_by' => AttendanceDecider::Manual,
                ])->save();
                $day = $existing;
            }

            $this->homeCache->invalidate($student->id);

            $this->auditLogger->record(
                action: 'attendance_day_corrected',
                subject: $day,
                before: $before,
                after: [
                    'student_id' => $student->id,
                    'school_date' => $schoolDate,
                    'status' => $status->value,
                    'reason' => trim($reason),
                ],
                userId: $actor->id,
            );

            return $day;
        });
    }

    /**
     * @return array{
     *     counts: array{present: int, late: int, excused: int, sick: int, absent: int, not_yet: int},
     *     rows: list<array{student_id: int, nis: string, name: string, status: string, status_label: string, checked_in_at: ?string, checked_out_at: ?string, decided_by: ?string}>
     * }
     */
    public function dailyForClassroom(Classroom $classroom, string $schoolDate): array
    {
        $students = $this->activeStudentsInClassroom($classroom);
        $studentIds = $students->modelKeys();

        /** @var \Illuminate\Support\Collection<int, AttendanceDay> $daysByStudent */
        $daysByStudent = AttendanceDay::query()
            ->whereIn('student_id', $studentIds)
            ->whereDate('school_date', $schoolDate)
            ->get()
            ->keyBy('student_id');

        $counts = [
            'present' => 0,
            'late' => 0,
            'excused' => 0,
            'sick' => 0,
            'absent' => 0,
            'not_yet' => 0,
        ];

        $rows = [];
        $tz = AttendanceClassifier::BUSINESS_TIMEZONE;

        foreach ($students as $student) {
            /** @var AttendanceDay|null $day */
            $day = $daysByStudent->get($student->id);

            if ($day === null) {
                $counts['not_yet']++;
                $status = 'not_yet';
                $statusLabel = 'Belum tap';
            } else {
                $counts[$day->status->value]++;
                $status = $day->status->value;
                $statusLabel = $day->status->label();
            }

            $rows[] = [
                'student_id' => $student->id,
                'nis' => $student->nis,
                'name' => $student->name,
                'status' => $status,
                'status_label' => $statusLabel,
                'checked_in_at' => $day?->checked_in_at?->setTimezone($tz)->format('H.i'),
                'checked_out_at' => $day?->checked_out_at?->setTimezone($tz)->format('H.i'),
                'decided_by' => $day?->decided_by->value,
            ];
        }

        return [
            'counts' => $counts,
            'rows' => $rows,
        ];
    }

    /**
     * @return list<array{student_id: int, nis: string, name: string, present: int, late: int, excused: int, sick: int, absent: int}>
     */
    public function monthlyForClassroom(Classroom $classroom, string $monthYyyyMm): array
    {
        $students = $this->activeStudentsInClassroom($classroom);
        $studentIds = $students->modelKeys();

        $start = CarbonImmutable::parse("{$monthYyyyMm}-01", AttendanceClassifier::BUSINESS_TIMEZONE)->startOfMonth()->toDateString();
        $end = CarbonImmutable::parse("{$monthYyyyMm}-01", AttendanceClassifier::BUSINESS_TIMEZONE)->endOfMonth()->toDateString();

        $days = AttendanceDay::query()
            ->whereIn('student_id', $studentIds)
            ->whereDate('school_date', '>=', $start)
            ->whereDate('school_date', '<=', $end)
            ->get()
            ->groupBy('student_id');

        $rows = [];

        foreach ($students as $student) {
            /** @var \Illuminate\Support\Collection<int, AttendanceDay> $studentDays */
            $studentDays = $days->get($student->id, collect());

            $counts = [
                'present' => 0,
                'late' => 0,
                'excused' => 0,
                'sick' => 0,
                'absent' => 0,
            ];

            foreach ($studentDays as $day) {
                $counts[$day->status->value]++;
            }

            $rows[] = [
                'student_id' => $student->id,
                'nis' => $student->nis,
                'name' => $student->name,
                ...$counts,
            ];
        }

        return $rows;
    }

    /**
     * @return Collection<int, Student>
     */
    private function activeStudentsInClassroom(Classroom $classroom): Collection
    {
        return Student::query()
            ->where('status', StudentStatus::Active->value)
            ->whereHas('enrollments', fn ($q) => $q
                ->where('academic_year_id', $classroom->academic_year_id)
                ->where('classroom_id', $classroom->id))
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }
}
