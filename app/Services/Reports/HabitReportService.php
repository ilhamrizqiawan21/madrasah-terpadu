<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\HabitItem;
use App\Models\HabitLog;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class HabitReportService
{
    /**
     * @return array{
     *     headers: list<string>,
     *     rows: list<array<string, string|int|float>>,
     *     summary: array{total_logs: int, verified_logs: int, active_items: int, active_students: int}
     * }
     */
    public function build(
        string $mode,
        ?int $unitId,
        ?int $classroomId,
        string $dateFrom,
        string $dateTo,
    ): array {
        $activeYearId = AcademicYear::query()->where('is_active', true)->value('id');
        $studentFilter = $this->studentScope($unitId, $classroomId, $activeYearId);

        $activeStudents = Student::query()
            ->where('status', StudentStatus::Active->value)
            ->tap($studentFilter)
            ->count();

        $logs = HabitLog::query()
            ->with(['item:id,name,aspect', 'student:id,unit_id'])
            ->where('done', true)
            ->whereBetween('log_date', [$dateFrom, $dateTo])
            ->whereHas('student', $studentFilter)
            ->get();

        $items = HabitItem::query()
            ->where('is_active', true)
            ->when($unitId !== null, fn (Builder $q) => $q->where(fn (Builder $inner) => $inner->whereNull('unit_id')->orWhere('unit_id', $unitId)))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name', 'aspect']);

        $summary = [
            'total_logs' => $logs->count(),
            'verified_logs' => $logs->where('verification', 'verified')->count(),
            'active_items' => $items->count(),
            'active_students' => $activeStudents,
        ];

        $result = $mode === 'classroom'
            ? $this->byClassroom($unitId, $classroomId, $activeYearId, $logs)
            : $this->byItem($items, $logs, $activeStudents);

        return [
            'headers' => $result['headers'],
            'rows' => $result['rows'],
            'summary' => $summary,
        ];
    }

    /**
     * @param  Collection<int, HabitItem>  $items
     * @param  Collection<int, HabitLog>  $logs
     * @return array{headers: list<string>, rows: list<array<string, string|int|float>>}
     */
    private function byItem(Collection $items, Collection $logs, int $activeStudents): array
    {
        $logsByItem = $logs->groupBy('habit_item_id');
        $rows = [];

        foreach ($items as $item) {
            $itemLogs = $logsByItem->get($item->id, collect());
            $uniqueStudents = $itemLogs->pluck('student_id')->unique()->count();
            $verified = $itemLogs->where('verification', 'verified')->count();
            $pct = $activeStudents > 0 ? round(($uniqueStudents / $activeStudents) * 100, 1) : 0.0;

            $rows[] = [
                'id' => $item->id,
                'item_name' => $item->name,
                'category' => $item->aspect->label(),
                'total_done' => $itemLogs->count(),
                'verified' => $verified,
                'unique_students' => $uniqueStudents,
                'coverage_pct' => $pct.'%',
            ];
        }

        return [
            'headers' => ['Item Pembiasaan', 'Kategori', 'Total Ceklis', 'Terverifikasi', 'Siswa Melaksanakan', 'Cakupan Siswa'],
            'rows' => $rows,
        ];
    }

    /**
     * @param  Collection<int, HabitLog>  $logs
     * @return array{headers: list<string>, rows: list<array<string, string|int|float>>}
     */
    private function byClassroom(?int $unitId, ?int $classroomId, ?int $activeYearId, Collection $logs): array
    {
        $classrooms = Classroom::query()
            ->with('unit:id,code')
            ->where('is_active', true)
            ->when($activeYearId !== null, fn (Builder $q) => $q->where('academic_year_id', $activeYearId))
            ->when($unitId !== null, fn (Builder $q) => $q->where('unit_id', $unitId))
            ->when($classroomId !== null, fn (Builder $q) => $q->whereKey($classroomId))
            ->orderBy('grade')
            ->orderBy('name')
            ->get(['id', 'unit_id', 'name']);

        $enrollmentsByClass = Enrollment::query()
            ->whereHas('student', fn (Builder $q) => $q->where('status', StudentStatus::Active->value))
            ->when($activeYearId !== null, fn (Builder $q) => $q->where('academic_year_id', $activeYearId))
            ->whereIn('classroom_id', $classrooms->pluck('id'))
            ->get(['student_id', 'classroom_id'])
            ->groupBy('classroom_id');

        $logsByStudent = $logs->groupBy('student_id');
        $rows = [];

        foreach ($classrooms as $classroom) {
            $studentIds = $enrollmentsByClass->get($classroom->id, collect())->pluck('student_id');
            $classLogs = $studentIds->flatMap(fn (int $id) => $logsByStudent->get($id, collect()));
            $studentCount = $studentIds->count();
            $totalDone = $classLogs->count();
            $verified = $classLogs->where('verification', 'verified')->count();
            $avgPerStudent = $studentCount > 0 ? round($totalDone / $studentCount, 1) : 0.0;

            $rows[] = [
                'id' => $classroom->id,
                'unit_code' => $classroom->unit->code ?? '-',
                'classroom_name' => $classroom->name,
                'student_count' => $studentCount,
                'total_done' => $totalDone,
                'verified' => $verified,
                'avg_per_student' => $avgPerStudent,
            ];
        }

        return [
            'headers' => ['Unit', 'Rombel', 'Siswa', 'Total Ceklis', 'Terverifikasi', 'Rata-rata per Siswa'],
            'rows' => $rows,
        ];
    }

    /**
     * @return \Closure(Builder<Student>): void
     */
    private function studentScope(?int $unitId, ?int $classroomId, ?int $activeYearId): \Closure
    {
        return function (Builder $q) use ($unitId, $classroomId, $activeYearId): void {
            $q->when($unitId !== null, fn (Builder $s) => $s->where('unit_id', $unitId))
                ->when($classroomId !== null, function (Builder $s) use ($classroomId, $activeYearId): void {
                    $s->whereHas('enrollments', fn (Builder $e) => $e
                        ->where('classroom_id', $classroomId)
                        ->when($activeYearId !== null, fn (Builder $y) => $y->where('academic_year_id', $activeYearId)));
                });
        };
    }
}
