<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Domain\Attendance\AttendanceClassifier;
use App\Enums\AttendanceStatus;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\AttendanceDay;
use App\Models\Classroom;
use App\Models\Enrollment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AttendanceReportService
{
    /**
     * @return array{
     *     headers: list<string>,
     *     rows: list<array<string, string|int>>,
     *     summary: array{present: int, late: int, sick: int, excused: int, absent: int, total: int}
     * }
     */
    public function build(
        string $mode,
        ?int $unitId,
        ?int $classroomId,
        string $date,
        string $month,
    ): array {
        $activeYearId = AcademicYear::query()->where('is_active', true)->value('id');

        return match ($mode) {
            'monthly' => $this->monthlyByClassroom($unitId, $classroomId, $month, $activeYearId),
            'student' => $this->monthlyByStudent($unitId, $classroomId, $month, $activeYearId),
            default => $this->daily($unitId, $classroomId, $date, $activeYearId),
        };
    }

    /**
     * @return array{
     *     headers: list<string>,
     *     rows: list<array<string, string|int>>,
     *     summary: array{present: int, late: int, sick: int, excused: int, absent: int, total: int}
     * }
     */
    private function daily(?int $unitId, ?int $classroomId, string $date, ?int $activeYearId): array
    {
        $enrollments = $this->enrolledStudents($unitId, $classroomId, $activeYearId);
        $studentIds = $enrollments->pluck('student_id')->all();

        $days = $studentIds === []
            ? collect()
            : AttendanceDay::query()
                ->whereIn('student_id', $studentIds)
                ->whereDate('school_date', $date)
                ->get()
                ->keyBy('student_id');

        $summary = ['present' => 0, 'late' => 0, 'sick' => 0, 'excused' => 0, 'absent' => 0, 'total' => $enrollments->count()];
        $rows = [];

        foreach ($enrollments as $enrollment) {
            /** @var AttendanceDay|null $day */
            $day = $days->get($enrollment->student_id);
            $status = $day?->status;

            $summary = $this->incrementSummary($summary, $status);

            $rows[] = [
                'id' => $enrollment->student_id,
                'nis' => $enrollment->student->nis,
                'student_name' => $enrollment->student->name,
                'unit_code' => $enrollment->classroom->unit->code ?? '-',
                'classroom_name' => $enrollment->classroom->name,
                'status_code' => $status !== null ? $status->value : 'not_yet',
                'status_label' => $status !== null ? $status->label() : 'Belum tercatat',
                'checked_in' => $day?->checked_in_at?->setTimezone(AttendanceClassifier::BUSINESS_TIMEZONE)->format('H.i') ?? '-',
                'checked_out' => $day?->checked_out_at?->setTimezone(AttendanceClassifier::BUSINESS_TIMEZONE)->format('H.i') ?? '-',
            ];
        }

        return [
            'headers' => ['NIS', 'Nama siswa', 'Unit', 'Rombel', 'Status', 'Masuk', 'Pulang'],
            'rows' => $rows,
            'summary' => $summary,
        ];
    }

    /**
     * @return array{
     *     headers: list<string>,
     *     rows: list<array<string, string|int>>,
     *     summary: array{present: int, late: int, sick: int, excused: int, absent: int, total: int}
     * }
     */
    private function monthlyByClassroom(?int $unitId, ?int $classroomId, string $month, ?int $activeYearId): array
    {
        [$start, $end] = $this->monthRange($month);

        $classrooms = Classroom::query()
            ->with('unit:id,code')
            ->where('is_active', true)
            ->when($activeYearId !== null, fn (Builder $q) => $q->where('academic_year_id', $activeYearId))
            ->when($unitId !== null, fn (Builder $q) => $q->where('unit_id', $unitId))
            ->when($classroomId !== null, fn (Builder $q) => $q->whereKey($classroomId))
            ->orderBy('grade')
            ->orderBy('name')
            ->get(['id', 'unit_id', 'grade', 'name']);

        $enrollments = $this->enrolledStudents($unitId, $classroomId, $activeYearId)->groupBy('classroom_id');
        $allStudentIds = $enrollments->flatten(1)->pluck('student_id')->all();

        $daysByStudent = $allStudentIds === []
            ? collect()
            : AttendanceDay::query()
                ->whereIn('student_id', $allStudentIds)
                ->whereBetween('school_date', [$start, $end])
                ->get()
                ->groupBy('student_id');

        $summary = ['present' => 0, 'late' => 0, 'sick' => 0, 'excused' => 0, 'absent' => 0, 'total' => 0];
        $rows = [];

        foreach ($classrooms as $classroom) {
            $classEnrollments = $enrollments->get($classroom->id, collect());
            $counts = ['present' => 0, 'late' => 0, 'sick' => 0, 'excused' => 0, 'absent' => 0];

            foreach ($classEnrollments as $enrollment) {
                foreach ($daysByStudent->get($enrollment->student_id, collect()) as $day) {
                    $counts[$day->status->value]++;
                    $summary = $this->incrementSummary($summary, $day->status);
                }
            }

            $recordedTotal = array_sum($counts);
            $summary['total'] += $recordedTotal;
            $presentPct = $recordedTotal > 0
                ? (int) round((($counts['present'] + $counts['late']) / $recordedTotal) * 100)
                : 0;

            $rows[] = [
                'id' => $classroom->id,
                'unit_code' => $classroom->unit->code ?? '-',
                'classroom_name' => $classroom->name,
                'student_count' => $classEnrollments->count(),
                'present' => $counts['present'],
                'late' => $counts['late'],
                'sick' => $counts['sick'],
                'excused' => $counts['excused'],
                'absent' => $counts['absent'],
                'attendance_pct' => $presentPct.'%',
            ];
        }

        return [
            'headers' => ['Unit', 'Rombel', 'Siswa', 'Hadir', 'Terlambat', 'Sakit', 'Izin', 'Alpa', '% Hadir'],
            'rows' => $rows,
            'summary' => $summary,
        ];
    }

    /**
     * @return array{
     *     headers: list<string>,
     *     rows: list<array<string, string|int>>,
     *     summary: array{present: int, late: int, sick: int, excused: int, absent: int, total: int}
     * }
     */
    private function monthlyByStudent(?int $unitId, ?int $classroomId, string $month, ?int $activeYearId): array
    {
        [$start, $end] = $this->monthRange($month);
        $enrollments = $this->enrolledStudents($unitId, $classroomId, $activeYearId);
        $studentIds = $enrollments->pluck('student_id')->all();

        $daysByStudent = $studentIds === []
            ? collect()
            : AttendanceDay::query()
                ->whereIn('student_id', $studentIds)
                ->whereBetween('school_date', [$start, $end])
                ->get()
                ->groupBy('student_id');

        $summary = ['present' => 0, 'late' => 0, 'sick' => 0, 'excused' => 0, 'absent' => 0, 'total' => 0];
        $rows = [];

        foreach ($enrollments as $enrollment) {
            $counts = [
                AttendanceStatus::Present->value => 0,
                AttendanceStatus::Late->value => 0,
                AttendanceStatus::Sick->value => 0,
                AttendanceStatus::Excused->value => 0,
                AttendanceStatus::Absent->value => 0,
            ];

            foreach ($daysByStudent->get($enrollment->student_id, collect()) as $day) {
                $counts[$day->status->value]++;
                $summary = $this->incrementSummary($summary, $day->status);
            }

            $recordedTotal = array_sum($counts);
            $summary['total'] += $recordedTotal;
            $presentPct = $recordedTotal > 0
                ? (int) round((($counts['present'] + $counts['late']) / $recordedTotal) * 100)
                : 0;

            $rows[] = [
                'id' => $enrollment->student_id,
                'nis' => $enrollment->student->nis,
                'student_name' => $enrollment->student->name,
                'unit_code' => $enrollment->classroom->unit->code ?? '-',
                'classroom_name' => $enrollment->classroom->name,
                'present' => $counts['present'],
                'late' => $counts['late'],
                'sick' => $counts['sick'],
                'excused' => $counts['excused'],
                'absent' => $counts['absent'],
                'attendance_pct' => $presentPct.'%',
            ];
        }

        return [
            'headers' => ['NIS', 'Nama siswa', 'Unit', 'Rombel', 'Hadir', 'Terlambat', 'Sakit', 'Izin', 'Alpa', '% Hadir'],
            'rows' => $rows,
            'summary' => $summary,
        ];
    }

    /**
     * @return Collection<int, Enrollment>
     */
    private function enrolledStudents(?int $unitId, ?int $classroomId, ?int $activeYearId): Collection
    {
        return Enrollment::query()
            ->with(['student:id,nis,name,status', 'classroom:id,unit_id,name', 'classroom.unit:id,code'])
            ->whereHas('student', fn (Builder $q) => $q->where('status', StudentStatus::Active->value))
            ->when($activeYearId !== null, fn (Builder $q) => $q->where('academic_year_id', $activeYearId))
            ->when($classroomId !== null, fn (Builder $q) => $q->where('classroom_id', $classroomId))
            ->when($unitId !== null, fn (Builder $q) => $q->whereHas('classroom', fn (Builder $c) => $c->where('unit_id', $unitId)))
            ->orderBy('classroom_id')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function monthRange(string $month): array
    {
        $start = CarbonImmutable::createFromFormat('Y-m', $month, AttendanceClassifier::BUSINESS_TIMEZONE)
            ?->startOfMonth() ?? CarbonImmutable::now(AttendanceClassifier::BUSINESS_TIMEZONE)->startOfMonth();

        return [$start->toDateString(), $start->endOfMonth()->toDateString()];
    }

    /**
     * @param  array{present: int, late: int, sick: int, excused: int, absent: int, total: int}  $summary
     * @return array{present: int, late: int, sick: int, excused: int, absent: int, total: int}
     */
    private function incrementSummary(array $summary, ?AttendanceStatus $status): array
    {
        match ($status) {
            AttendanceStatus::Present => $summary['present']++,
            AttendanceStatus::Late => $summary['late']++,
            AttendanceStatus::Sick => $summary['sick']++,
            AttendanceStatus::Excused => $summary['excused']++,
            AttendanceStatus::Absent => $summary['absent']++,
            null => null,
        };

        return $summary;
    }
}
