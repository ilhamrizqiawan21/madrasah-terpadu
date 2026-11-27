<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\MemorizationRecord;
use App\Models\PibScore;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class QuranReportService
{
    /**
     * @return array{
     *     headers: list<string>,
     *     rows: list<array<string, string|int|float>>,
     *     summary: array{deposits: int, passed: int, avg_score: float, pib_scores: int, avg_pib: float}
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

        $records = MemorizationRecord::query()
            ->with(['student:id,nis,name,unit_id', 'student.unit:id,code', 'teacher:id,name'])
            ->whereBetween('recorded_on', [$dateFrom, $dateTo])
            ->whereHas('student', $studentFilter)
            ->orderByDesc('recorded_on')
            ->orderByDesc('id')
            ->get();

        $pibScores = PibScore::query()
            ->with(['student:id,nis,name,unit_id', 'material:id,pib_subchapter_id,name', 'material.subchapter:id,pib_chapter_id,name', 'material.subchapter.chapter:id,name'])
            ->when($activeYearId !== null, fn (Builder $q) => $q->where('academic_year_id', $activeYearId))
            ->whereHas('student', $studentFilter)
            ->get();

        $deposits = $records->count();
        $passed = $records->where('passed', true)->count();
        $avgScore = $deposits > 0 ? round((float) $records->avg('final_score'), 1) : 0.0;
        $pibCount = $pibScores->count();
        $avgPib = $pibCount > 0 ? round((float) $pibScores->avg('score'), 1) : 0.0;

        $summary = [
            'deposits' => $deposits,
            'passed' => $passed,
            'avg_score' => $avgScore,
            'pib_scores' => $pibCount,
            'avg_pib' => $avgPib,
        ];

        $result = match ($mode) {
            'teacher' => $this->byTeacher($records),
            'pib' => $this->byPib($pibScores),
            default => $this->byClassroom($unitId, $classroomId, $activeYearId, $records, $pibScores),
        };

        return [
            'headers' => $result['headers'],
            'rows' => $result['rows'],
            'summary' => $summary,
        ];
    }

    /**
     * @param  Collection<int, MemorizationRecord>  $records
     * @param  Collection<int, PibScore>  $pibScores
     * @return array{headers: list<string>, rows: list<array<string, string|int|float>>}
     */
    private function byClassroom(
        ?int $unitId,
        ?int $classroomId,
        ?int $activeYearId,
        Collection $records,
        Collection $pibScores,
    ): array {
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

        $recordsByStudent = $records->groupBy('student_id');
        $pibByStudent = $pibScores->groupBy('student_id');

        $rows = [];
        foreach ($classrooms as $classroom) {
            $studentIds = $enrollmentsByClass->get($classroom->id, collect())->pluck('student_id');
            $classRecords = $studentIds->flatMap(fn (int $id) => $recordsByStudent->get($id, collect()));
            $classPibs = $studentIds->flatMap(fn (int $id) => $pibByStudent->get($id, collect()));

            $count = $classRecords->count();
            $passedCount = $classRecords->where('passed', true)->count();
            $avgMem = $count > 0 ? round((float) $classRecords->avg('final_score'), 1) : 0.0;
            $pibCount = $classPibs->count();
            $avgPib = $pibCount > 0 ? round((float) $classPibs->avg('score'), 1) : 0.0;

            $rows[] = [
                'id' => $classroom->id,
                'unit_code' => $classroom->unit->code ?? '-',
                'classroom_name' => $classroom->name,
                'student_count' => $studentIds->count(),
                'deposits' => $count,
                'passed' => $passedCount,
                'avg_score' => $avgMem,
                'pib_count' => $pibCount,
                'avg_pib' => $avgPib,
            ];
        }

        return [
            'headers' => ['Unit', 'Rombel', 'Siswa', 'Setoran', 'Lulus', 'Rata-rata Hafalan', 'Nilai PIB', 'Rata-rata PIB'],
            'rows' => $rows,
        ];
    }

    /**
     * @param  Collection<int, MemorizationRecord>  $records
     * @return array{headers: list<string>, rows: list<array<string, string|int|float>>}
     */
    private function byTeacher(Collection $records): array
    {
        $grouped = $records->groupBy('teacher_id');
        $rows = [];

        foreach ($grouped as $teacherId => $items) {
            /** @var MemorizationRecord $first */
            $first = $items->first();
            $count = $items->count();

            $rows[] = [
                'id' => (int) $teacherId,
                'teacher_name' => $first->teacher->name,
                'student_count' => $items->pluck('student_id')->unique()->count(),
                'deposits' => $count,
                'passed' => $items->where('passed', true)->count(),
                'avg_score' => $count > 0 ? round((float) $items->avg('final_score'), 1) : 0.0,
            ];
        }

        usort($rows, fn (array $a, array $b): int => $b['deposits'] <=> $a['deposits']);

        return [
            'headers' => ['Guru', 'Siswa Unik', 'Jumlah Setoran', 'Lulus', 'Rata-rata Nilai'],
            'rows' => $rows,
        ];
    }

    /**
     * @param  Collection<int, PibScore>  $pibScores
     * @return array{headers: list<string>, rows: list<array<string, string|int|float>>}
     */
    private function byPib(Collection $pibScores): array
    {
        $grouped = $pibScores->groupBy('pib_material_id');
        $rows = [];

        foreach ($grouped as $materialId => $items) {
            /** @var PibScore $first */
            $first = $items->first();
            $count = $items->count();

            $rows[] = [
                'id' => (int) $materialId,
                'chapter' => $first->material->subchapter->chapter->name ?? '-',
                'subchapter' => $first->material->subchapter->name ?? '-',
                'material' => $first->material->name ?? '-',
                'student_count' => $count,
                'avg_mistakes' => $count > 0 ? round((float) $items->avg('mistakes'), 1) : 0.0,
                'avg_score' => $count > 0 ? round((float) $items->avg('score'), 1) : 0.0,
            ];
        }

        return [
            'headers' => ['Bab', 'Subbab', 'Materi / Komponen', 'Siswa Dinilai', 'Rata-rata Salah', 'Rata-rata Nilai'],
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
