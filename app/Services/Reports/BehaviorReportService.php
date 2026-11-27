<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Domain\Attendance\AttendanceClassifier;
use App\Enums\BehaviorRecordStatus;
use App\Enums\PointCategory;
use App\Models\AcademicYear;
use App\Models\BehaviorRecord;
use App\Models\DisciplineFollowup;
use App\Models\Enrollment;
use App\Models\RewardRedemption;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;

class BehaviorReportService
{
    /**
     * @return array{
     *     headers: list<string>,
     *     rows: list<array<string, string|int>>,
     *     summary: array{records: int, positive_points: int, violation_points: int, open_followups: int, vouchers: int}
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

        $summary = [
            'records' => BehaviorRecord::query()
                ->where('status', BehaviorRecordStatus::Active->value)
                ->whereBetween('occurred_on', [$dateFrom, $dateTo])
                ->whereHas('student', $studentFilter)
                ->count(),
            'positive_points' => (int) BehaviorRecord::query()
                ->where('status', BehaviorRecordStatus::Active->value)
                ->whereIn('category', [PointCategory::Achievement->value, PointCategory::GoodDeed->value])
                ->whereBetween('occurred_on', [$dateFrom, $dateTo])
                ->whereHas('student', $studentFilter)
                ->sum('points'),
            'violation_points' => (int) BehaviorRecord::query()
                ->where('status', BehaviorRecordStatus::Active->value)
                ->where('category', PointCategory::Violation->value)
                ->whereBetween('occurred_on', [$dateFrom, $dateTo])
                ->whereHas('student', $studentFilter)
                ->sum('points'),
            'open_followups' => DisciplineFollowup::query()
                ->where('status', 'open')
                ->whereHas('student', $studentFilter)
                ->count(),
            'vouchers' => RewardRedemption::query()
                ->whereBetween('created_at', [$dateFrom.' 00:00:00', $dateTo.' 23:59:59'])
                ->whereHas('student', $studentFilter)
                ->count(),
        ];

        $result = match ($mode) {
            'top_points' => $this->topPoints($studentFilter, $dateFrom, $dateTo, $activeYearId),
            'followups' => $this->openFollowups($studentFilter, $activeYearId),
            'rewards' => $this->rewards($studentFilter, $dateFrom, $dateTo),
            default => $this->records($studentFilter, $dateFrom, $dateTo),
        };

        return [
            'headers' => $result['headers'],
            'rows' => $result['rows'],
            'summary' => $summary,
        ];
    }

    /**
     * @param  \Closure(Builder<Student>): void  $studentFilter
     * @return array{headers: list<string>, rows: list<array<string, string|int>>}
     */
    private function records(\Closure $studentFilter, string $dateFrom, string $dateTo): array
    {
        $items = BehaviorRecord::query()
            ->with(['student:id,nis,name,unit_id', 'student.unit:id,code', 'pointRule:id,name', 'recorder:id,name'])
            ->where('status', BehaviorRecordStatus::Active->value)
            ->whereBetween('occurred_on', [$dateFrom, $dateTo])
            ->whereHas('student', $studentFilter)
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->get();

        $rows = [];
        foreach ($items as $item) {
            $rows[] = [
                'id' => $item->id,
                'date' => $item->occurred_on->format('Y-m-d'),
                'nis' => $item->student->nis,
                'student_name' => $item->student->name,
                'unit_code' => $item->student->unit->code ?? '-',
                'category_code' => $item->category->value,
                'category_label' => $item->category->label(),
                'rule_name' => $item->pointRule->name,
                'points' => $item->points,
                'recorder' => $item->recorder->name,
            ];
        }

        return [
            'headers' => ['Tanggal', 'NIS', 'Siswa', 'Unit', 'Kategori', 'Aturan', 'Poin', 'Pencatat'],
            'rows' => $rows,
        ];
    }

    /**
     * @param  \Closure(Builder<Student>): void  $studentFilter
     * @return array{headers: list<string>, rows: list<array<string, string|int>>}
     */
    private function topPoints(\Closure $studentFilter, string $dateFrom, string $dateTo, ?int $activeYearId): array
    {
        $items = BehaviorRecord::query()
            ->with(['student:id,nis,name,unit_id', 'student.unit:id,code'])
            ->where('status', BehaviorRecordStatus::Active->value)
            ->whereBetween('occurred_on', [$dateFrom, $dateTo])
            ->whereHas('student', $studentFilter)
            ->get();

        $grouped = [];
        foreach ($items as $item) {
            $sid = $item->student_id;
            if (! isset($grouped[$sid])) {
                $grouped[$sid] = [
                    'id' => $sid,
                    'nis' => $item->student->nis,
                    'student_name' => $item->student->name,
                    'unit_code' => $item->student->unit->code ?? '-',
                    'positive' => 0,
                    'violation' => 0,
                    'record_count' => 0,
                ];
            }
            if ($item->category !== PointCategory::Violation) {
                $grouped[$sid]['positive'] += $item->points;
            } else {
                $grouped[$sid]['violation'] += $item->points;
            }
            $grouped[$sid]['record_count']++;
        }

        $rows = array_values($grouped);
        usort($rows, fn (array $a, array $b): int => ($b['violation'] <=> $a['violation']) ?: ($b['positive'] <=> $a['positive']));

        // Attach classroom label if enrolled
        if ($rows !== [] && $activeYearId !== null) {
            $classroomsByStudent = Enrollment::query()
                ->with('classroom:id,name')
                ->where('academic_year_id', $activeYearId)
                ->whereIn('student_id', array_column($rows, 'id'))
                ->get()
                ->keyBy('student_id');

            foreach ($rows as $idx => $row) {
                $rows[$idx]['classroom_name'] = $classroomsByStudent->get($row['id'])?->classroom->name ?? '-';
            }
        } else {
            foreach ($rows as $idx => $row) {
                $rows[$idx]['classroom_name'] = '-';
            }
        }

        return [
            'headers' => ['NIS', 'Siswa', 'Unit', 'Rombel', 'Poin Pelanggaran', 'Poin Kebaikan', 'Jumlah Catatan'],
            'rows' => $rows,
        ];
    }

    /**
     * @param  \Closure(Builder<Student>): void  $studentFilter
     * @return array{headers: list<string>, rows: list<array<string, string|int>>}
     */
    private function openFollowups(\Closure $studentFilter, ?int $activeYearId): array
    {
        $items = DisciplineFollowup::query()
            ->with(['student:id,nis,name,unit_id', 'student.unit:id,code', 'threshold:id,action_name,points'])
            ->where('status', 'open')
            ->whereHas('student', $studentFilter)
            ->orderByDesc('triggered_at')
            ->orderByDesc('id')
            ->get();

        $classroomsByStudent = ($items->isNotEmpty() && $activeYearId !== null)
            ? Enrollment::query()
                ->with('classroom:id,name')
                ->where('academic_year_id', $activeYearId)
                ->whereIn('student_id', $items->pluck('student_id'))
                ->get()
                ->keyBy('student_id')
            : collect();

        $rows = [];
        foreach ($items as $item) {
            $rows[] = [
                'id' => $item->id,
                'triggered_at' => $item->triggered_at->setTimezone(AttendanceClassifier::BUSINESS_TIMEZONE)->format('Y-m-d H.i'),
                'nis' => $item->student->nis,
                'student_name' => $item->student->name,
                'unit_code' => $item->student->unit->code ?? '-',
                'classroom_name' => $classroomsByStudent->get($item->student_id)?->classroom->name ?? '-',
                'action_title' => $item->threshold->action_name ?? '-',
                'threshold_points' => $item->threshold->points ?? 0,
            ];
        }

        return [
            'headers' => ['Dipicu', 'NIS', 'Siswa', 'Unit', 'Rombel', 'Tindakan', 'Ambang Poin'],
            'rows' => $rows,
        ];
    }

    /**
     * @param  \Closure(Builder<Student>): void  $studentFilter
     * @return array{headers: list<string>, rows: list<array<string, string|int>>}
     */
    private function rewards(\Closure $studentFilter, string $dateFrom, string $dateTo): array
    {
        $items = RewardRedemption::query()
            ->with(['student:id,nis,name,unit_id', 'student.unit:id,code', 'offer:id,name', 'voucher:id,reward_redemption_id,code,status'])
            ->whereBetween('created_at', [$dateFrom.' 00:00:00', $dateTo.' 23:59:59'])
            ->whereHas('student', $studentFilter)
            ->orderByDesc('id')
            ->get();

        $rows = [];
        foreach ($items as $item) {
            $rows[] = [
                'id' => $item->id,
                'nis' => $item->student->nis,
                'student_name' => $item->student->name,
                'unit_code' => $item->student->unit->code ?? '-',
                'offer_name' => $item->offer->name ?? '-',
                'cost_points' => $item->cost_points,
                'voucher_amount' => $item->voucher_amount,
                'status' => $item->status,
                'voucher_code' => $item->voucher->code ?? '-',
            ];
        }

        return [
            'headers' => ['NIS', 'Siswa', 'Unit', 'Reward', 'Biaya Poin', 'Nominal Voucher', 'Status', 'Kode Voucher'],
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
