<?php

declare(strict_types=1);

namespace App\Services\Character;

use App\Domain\Attendance\AttendanceClassifier;
use App\Domain\Character\ComponentMeasure;
use App\Enums\AttendanceStatus;
use App\Enums\BehaviorRecordStatus;
use App\Enums\CharacterSource;
use App\Enums\HabitAspect;
use App\Enums\PointCategory;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\AttendanceDay;
use App\Models\BehaviorRecord;
use App\Models\CharacterComponent;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\HabitItem;
use App\Models\HabitLog;
use App\Models\SchoolHoliday;
use App\Models\Unit;
use App\Services\Attendance\SchoolCalendarService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Mengumpulkan angka sumber penilaian karakter untuk satu rombel satu semester (ADR-029).
 *
 * Rentang per siswa = sejak masuk rombel (atau awal semester) sampai akhir semester atau hari ini.
 * Hari pembiasaan = semua hari kecuali libur sekolah (mutabaah juga dikerjakan di rumah);
 * hari presensi = hari sekolah (tanpa akhir pekan unit dan libur).
 * Poin dihitung dari catatan perilaku aktif semester ini, bukan saldo ledger (penukaran reward
 * dan poin streak tidak memengaruhi).
 */
final class CharacterInputService
{
    public function __construct(private readonly SchoolCalendarService $calendar) {}

    /**
     * @return array<int, array{student_id: int, name: string, nis: string, nickname: string|null, from: string, to: string, habit_days: int, school_days: int, habit_log_days: int, verified: array<int, int>, attendance: array<string, int>, violation_points: int, merit_points: int}>
     */
    public function forClassroom(Classroom $classroom, AcademicYear $year): array
    {
        $tz = AttendanceClassifier::BUSINESS_TIMEZONE;
        $today = CarbonImmutable::now($tz)->startOfDay();
        $semesterStart = CarbonImmutable::parse($year->starts_on->toDateString(), $tz);
        $semesterEnd = CarbonImmutable::parse($year->ends_on->toDateString(), $tz);
        $end = $semesterEnd->lessThan($today) ? $semesterEnd : $today;

        /** @var Collection<int, Enrollment> $enrollments */
        $enrollments = Enrollment::query()
            ->with('student:id,name,nis,nickname,status')
            ->where('classroom_id', $classroom->id)
            ->where('academic_year_id', $year->id)
            ->whereHas('student', fn ($q) => $q->where('status', StudentStatus::Active->value))
            ->get();

        if ($enrollments->isEmpty()) {
            return [];
        }

        $unit = $classroom->unit;
        $holidays = $this->holidays($unit, $semesterStart, $end);
        $weekend = $this->calendar->weekendDaysForUnit($unit);
        $studentIds = $enrollments->pluck('student_id')->all();
        $from = $semesterStart->toDateString();
        $to = $end->toDateString();

        $verified = HabitLog::query()
            ->whereIn('student_id', $studentIds)
            ->whereDate('log_date', '>=', $from)->whereDate('log_date', '<=', $to)
            ->where('done', true)
            ->where('verification', 'verified')
            ->select('student_id', 'habit_item_id', DB::raw('count(*) as aggregate'))
            ->groupBy('student_id', 'habit_item_id')
            ->get();

        $logDays = HabitLog::query()
            ->whereIn('student_id', $studentIds)
            ->whereDate('log_date', '>=', $from)->whereDate('log_date', '<=', $to)
            ->select('student_id', 'log_date')
            ->distinct()
            ->get();

        $attendance = AttendanceDay::query()
            ->whereIn('student_id', $studentIds)
            ->whereDate('school_date', '>=', $from)->whereDate('school_date', '<=', $to)
            ->select('student_id', 'status', DB::raw('count(*) as aggregate'))
            ->groupBy('student_id', 'status')
            ->get();

        $points = BehaviorRecord::query()
            ->whereIn('student_id', $studentIds)
            ->where('academic_year_id', $year->id)
            ->where('status', BehaviorRecordStatus::Active->value)
            ->select('student_id', 'category', DB::raw('sum(points) as aggregate'))
            ->groupBy('student_id', 'category')
            ->get();

        $result = [];

        foreach ($enrollments as $enrollment) {
            $joined = CarbonImmutable::parse($enrollment->created_at->toDateString(), $tz);
            $start = $joined->greaterThan($semesterStart) ? $joined : $semesterStart;
            $sid = $enrollment->student_id;

            [$habitDays, $schoolDays] = $this->countDays($start, $end, $holidays, $weekend);

            $result[$sid] = [
                'student_id' => $sid,
                'name' => $enrollment->student->name,
                'nis' => $enrollment->student->nis,
                'nickname' => $enrollment->student->nickname,
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
                'habit_days' => $habitDays,
                'school_days' => $schoolDays,
                'habit_log_days' => $logDays->where('student_id', $sid)
                    ->filter(fn (HabitLog $log): bool => $log->log_date->toDateString() >= $start->toDateString())
                    ->count(),
                'verified' => $verified->where('student_id', $sid)
                    ->mapWithKeys(fn (HabitLog $row): array => [$row->habit_item_id => (int) $row->getAttribute('aggregate')])
                    ->all(),
                'attendance' => $attendance->where('student_id', $sid)
                    ->mapWithKeys(fn (AttendanceDay $row): array => [$row->status->value => (int) $row->getAttribute('aggregate')])
                    ->all(),
                'violation_points' => (int) $points->where('student_id', $sid)
                    ->filter(fn (BehaviorRecord $row): bool => $row->category === PointCategory::Violation)
                    ->sum(fn (BehaviorRecord $row): int => (int) $row->getAttribute('aggregate')),
                'merit_points' => (int) $points->where('student_id', $sid)
                    ->filter(fn (BehaviorRecord $row): bool => $row->category !== PointCategory::Violation)
                    ->sum(fn (BehaviorRecord $row): int => (int) $row->getAttribute('aggregate')),
            ];
        }

        return $result;
    }

    /**
     * Ubah satu komponen konfigurasi menjadi ukuran untuk satu siswa.
     *
     * @param  array{habit_days: int, school_days: int, habit_log_days: int, verified: array<int, int>, attendance: array<string, int>, violation_points: int, merit_points: int}  $student
     * @param  Collection<int, HabitItem>  $items  item pembiasaan aktif unit
     */
    public function measure(CharacterComponent $component, array $student, Collection $items, ?int $observationLevel): ComponentMeasure
    {
        $habitCoverage = $student['habit_days'] > 0
            ? min(100.0, round($student['habit_log_days'] * 100 / $student['habit_days'], 1))
            : 0.0;

        return match ($component->source) {
            CharacterSource::HabitsAspect => $this->habitMeasure(
                $component,
                array_values(array_map('intval', $items->filter(fn (HabitItem $i): bool => $i->aspect->value === ($component->params['aspect'] ?? ''))->pluck('id')->all())),
                $student,
                $habitCoverage,
                ($component->params['aspect'] ?? '') === HabitAspect::Spiritual->value ? 'pembiasaan ibadah' : 'pembiasaan sosial',
            ),
            CharacterSource::HabitsItems => $this->habitMeasure(
                $component,
                $this->itemIds($component),
                $student,
                $habitCoverage,
                $this->itemPhrase($items, $this->itemIds($component)),
            ),
            CharacterSource::AttendanceOnTime => $this->attendanceMeasure($component, $student),
            CharacterSource::ViolationPoints => new ComponentMeasure($component->source, $component->weight, $component->params, 'ketaatan pada tata tertib', (float) $student['violation_points']),
            CharacterSource::MeritPoints => new ComponentMeasure($component->source, $component->weight, $component->params, 'perbuatan baik dan prestasi', (float) $student['merit_points']),
            // Capaian hafalan menyusul setelah modul hafalan (P-14) selesai: dianggap belum ada data.
            CharacterSource::Memorization => new ComponentMeasure($component->source, $component->weight, $component->params, 'hafalan', null),
            CharacterSource::TeacherObservation => new ComponentMeasure($component->source, $component->weight, $component->params, 'sikap sehari-hari di kelas', $observationLevel !== null ? (float) $observationLevel : null),
        };
    }

    /**
     * @return list<int>
     */
    private function itemIds(CharacterComponent $component): array
    {
        return array_values(array_map('intval', (array) ($component->params['item_ids'] ?? [])));
    }

    /**
     * @param  list<int>  $itemIds
     * @param  array{habit_days: int, verified: array<int, int>}  $student
     */
    private function habitMeasure(CharacterComponent $component, array $itemIds, array $student, float $coverage, string $phrase): ComponentMeasure
    {
        $expected = $student['habit_days'] * count($itemIds);

        if ($expected === 0) {
            return new ComponentMeasure($component->source, $component->weight, $component->params, $phrase, null, $coverage);
        }

        $done = array_sum(array_intersect_key($student['verified'], array_flip($itemIds)));

        return new ComponentMeasure($component->source, $component->weight, $component->params, $phrase, min(100.0, round($done * 100 / $expected, 1)), $coverage);
    }

    /**
     * @param  array{school_days: int, attendance: array<string, int>}  $student
     */
    private function attendanceMeasure(CharacterComponent $component, array $student): ComponentMeasure
    {
        $counts = $student['attendance'];
        $recorded = array_sum($counts);
        $excused = ($counts[AttendanceStatus::Excused->value] ?? 0) + ($counts[AttendanceStatus::Sick->value] ?? 0);
        $base = $recorded - $excused;
        $coverage = $student['school_days'] > 0 ? min(100.0, round($recorded * 100 / $student['school_days'], 1)) : 0.0;
        $value = $base > 0 ? round(($counts[AttendanceStatus::Present->value] ?? 0) * 100 / $base, 1) : null;

        return new ComponentMeasure($component->source, $component->weight, $component->params, 'kehadiran tepat waktu', $value, $coverage);
    }

    /**
     * @param  Collection<int, HabitItem>  $items
     * @param  list<int>  $ids
     */
    private function itemPhrase(Collection $items, array $ids): string
    {
        $names = $items->whereIn('id', $ids)->pluck('name')->map(fn (string $n): string => mb_strtolower($n))->values()->all();

        return match (count($names)) {
            0 => 'pembiasaan terpilih',
            1 => $names[0],
            2 => $names[0].' dan '.$names[1],
            default => $names[0].', '.$names[1].', dan lainnya',
        };
    }

    /**
     * @return list<string>
     */
    private function holidays(Unit $unit, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return array_values(SchoolHoliday::query()
            ->whereDate('holiday_date', '>=', $from->toDateString())->whereDate('holiday_date', '<=', $to->toDateString())
            ->where('is_day_off', true)
            ->where(fn ($q) => $q->whereNull('unit_id')->orWhere('unit_id', $unit->id))
            ->pluck('holiday_date')
            ->map(fn (mixed $date): string => substr((string) $date, 0, 10))
            ->all());
    }

    /**
     * @param  list<string>  $holidays
     * @param  list<int>  $weekend
     * @return array{0: int, 1: int} [hari pembiasaan, hari sekolah]
     */
    private function countDays(CarbonImmutable $from, CarbonImmutable $to, array $holidays, array $weekend): array
    {
        $habitDays = 0;
        $schoolDays = 0;

        for ($day = $from; $day->lessThanOrEqualTo($to); $day = $day->addDay()) {
            if (in_array($day->toDateString(), $holidays, true)) {
                continue;
            }

            $habitDays++;

            if (! in_array($day->dayOfWeekIso, $weekend, true)) {
                $schoolDays++;
            }
        }

        return [$habitDays, $schoolDays];
    }
}
