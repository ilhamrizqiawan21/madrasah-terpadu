<?php

declare(strict_types=1);

namespace App\Services\Habits;

use App\Enums\HabitAspect;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\HabitItem;
use App\Models\HabitLog;
use App\Models\SchoolHoliday;
use Carbon\CarbonImmutable;

class HabitRecapService
{
    /**
     * @return array{
     *     effective_days: int,
     *     spiritual_items: int,
     *     social_items: int,
     *     rows: list<array{
     *         student_id: int,
     *         student_name: string,
     *         student_nis: string,
     *         spiritual_verified: int,
     *         spiritual_expected: int,
     *         spiritual_pct: float,
     *         social_verified: int,
     *         social_expected: int,
     *         social_pct: float,
     *     }>
     * }
     */
    public function recapForClassroom(Classroom $classroom, string $period, string $month): array
    {
        $timezone = config('sekolah.timezone', 'Asia/Makassar');
        $tz = is_string($timezone) ? $timezone : 'Asia/Makassar';

        if ($period === 'semester') {
            $year = $classroom->academicYear;
            $from = $year->starts_on->startOfDay();
            $to = $year->ends_on->startOfDay();
        } else {
            $from = CarbonImmutable::parse($month.'-01', $tz)->startOfDay();
            $to = $from->endOfMonth()->startOfDay();
        }

        $today = CarbonImmutable::now($tz)->startOfDay();
        if ($to->greaterThan($today)) {
            $to = $today;
        }
        if ($to->lessThan($from)) {
            $to = $from;
        }

        $effectiveDays = $this->countEffectiveDays($from, $to, $classroom->unit_id);

        $activeItems = HabitItem::query()
            ->active()
            ->forUnit($classroom->unit_id)
            ->get(['id', 'aspect']);

        $spiritualItemIds = $activeItems
            ->filter(fn (HabitItem $i): bool => $i->aspect === HabitAspect::Spiritual)
            ->pluck('id')
            ->all();
        $socialItemIds = $activeItems
            ->filter(fn (HabitItem $i): bool => $i->aspect === HabitAspect::Social)
            ->pluck('id')
            ->all();

        $spiritualExpected = $effectiveDays * count($spiritualItemIds);
        $socialExpected = $effectiveDays * count($socialItemIds);

        $enrollments = Enrollment::query()
            ->with('student:id,nis,name')
            ->where('classroom_id', $classroom->id)
            ->where('academic_year_id', $classroom->academic_year_id)
            ->get();

        $studentIds = $enrollments->pluck('student_id')->all();
        $verifiedLogs = $studentIds !== []
            ? HabitLog::query()
                ->whereIn('student_id', $studentIds)
                ->whereBetween('log_date', [$from->toDateString(), $to->toDateString()])
                ->where('done', true)
                ->where('verification', 'verified')
                ->get(['student_id', 'habit_item_id'])
            : collect();

        $spiritualCounts = [];
        $socialCounts = [];

        foreach ($verifiedLogs as $log) {
            if (in_array($log->habit_item_id, $spiritualItemIds, true)) {
                $spiritualCounts[$log->student_id] = ($spiritualCounts[$log->student_id] ?? 0) + 1;
            } elseif (in_array($log->habit_item_id, $socialItemIds, true)) {
                $socialCounts[$log->student_id] = ($socialCounts[$log->student_id] ?? 0) + 1;
            }
        }

        $rows = $enrollments
            ->sortBy(fn (Enrollment $e): string => $e->student->name)
            ->map(function (Enrollment $e) use (
                $spiritualCounts,
                $socialCounts,
                $spiritualExpected,
                $socialExpected,
            ): array {
                $spVerified = $spiritualCounts[$e->student_id] ?? 0;
                $soVerified = $socialCounts[$e->student_id] ?? 0;

                return [
                    'student_id' => $e->student_id,
                    'student_name' => $e->student->name,
                    'student_nis' => $e->student->nis,
                    'spiritual_verified' => $spVerified,
                    'spiritual_expected' => $spiritualExpected,
                    'spiritual_pct' => $spiritualExpected > 0
                        ? min(100.0, round(($spVerified / $spiritualExpected) * 100, 1))
                        : 0.0,
                    'social_verified' => $soVerified,
                    'social_expected' => $socialExpected,
                    'social_pct' => $socialExpected > 0
                        ? min(100.0, round(($soVerified / $socialExpected) * 100, 1))
                        : 0.0,
                ];
            })
            ->values()
            ->all();

        return [
            'effective_days' => $effectiveDays,
            'spiritual_items' => count($spiritualItemIds),
            'social_items' => count($socialItemIds),
            'rows' => array_values($rows),
        ];
    }

    private function countEffectiveDays(CarbonImmutable $from, CarbonImmutable $to, int $unitId): int
    {
        $holidayDates = SchoolHoliday::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->where(function ($q) use ($unitId): void {
                $q->where('unit_id', $unitId)->orWhereNull('unit_id');
            })
            ->pluck('date')
            ->map(static fn (mixed $d): string => $d instanceof CarbonImmutable ? $d->toDateString() : (string) $d)
            ->all();

        $days = 0;
        $cursor = $from;
        while ($cursor->lessThanOrEqualTo($to)) {
            if (! in_array($cursor->toDateString(), $holidayDates, true)) {
                $days++;
            }
            $cursor = $cursor->addDay();
        }

        return max(1, $days);
    }
}
