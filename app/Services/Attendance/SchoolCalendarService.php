<?php

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Domain\Attendance\AttendanceClassifier;
use App\Models\SchoolHoliday;
use App\Models\Unit;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Kalender sekolah dan hari libur per unit / yayasan (T-505).
 * Dipakai oleh GateScanService dan MarkDailyAbsences.
 */
final class SchoolCalendarService
{
    /**
     * @var list<int>
     */
    public const DEFAULT_WEEKEND_DAYS = [6, 7];

    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @return list<int>
     */
    public function weekendDaysForUnit(Unit $unit): array
    {
        $settings = $unit->attendance_settings;
        $configured = $settings['weekend_days'] ?? null;

        if (! is_array($configured)) {
            return self::DEFAULT_WEEKEND_DAYS;
        }

        $days = array_values(array_unique(array_filter(
            array_map(static fn (mixed $day): int => (int) $day, $configured),
            static fn (int $day): bool => $day >= 1 && $day <= 7,
        )));

        sort($days);

        return $days;
    }

    public function isHoliday(Unit $unit, CarbonImmutable|string $date): bool
    {
        $carbon = $date instanceof CarbonImmutable
            ? $date->setTimezone(AttendanceClassifier::BUSINESS_TIMEZONE)
            : CarbonImmutable::parse($date, AttendanceClassifier::BUSINESS_TIMEZONE);

        if (in_array($carbon->dayOfWeekIso, $this->weekendDaysForUnit($unit), true)) {
            return true;
        }

        return SchoolHoliday::query()
            ->whereDate('holiday_date', $carbon->toDateString())
            ->where('is_day_off', true)
            ->where(function (Builder $query) use ($unit): void {
                $query->whereNull('unit_id')
                    ->orWhere('unit_id', $unit->id);
            })
            ->exists();
    }

    /**
     * @param  array{unit_id: int|null, holiday_date: string, title: string, is_day_off: bool}  $data
     */
    public function createHoliday(array $data, User $actor): SchoolHoliday
    {
        return DB::transaction(function () use ($data, $actor): SchoolHoliday {
            $holiday = SchoolHoliday::query()->create([
                'unit_id' => $data['unit_id'],
                'holiday_date' => $data['holiday_date'],
                'title' => trim($data['title']),
                'is_day_off' => $data['is_day_off'],
                'created_by' => $actor->id,
            ]);

            $this->auditLogger->record(
                action: 'school_holiday_created',
                subject: $holiday,
                after: [
                    'unit_id' => $holiday->unit_id,
                    'holiday_date' => $holiday->holiday_date->toDateString(),
                    'title' => $holiday->title,
                    'is_day_off' => $holiday->is_day_off,
                ],
                userId: $actor->id,
            );

            return $holiday;
        });
    }

    public function deleteHoliday(SchoolHoliday $holiday, User $actor): void
    {
        DB::transaction(function () use ($holiday, $actor): void {
            $before = [
                'unit_id' => $holiday->unit_id,
                'holiday_date' => $holiday->holiday_date->toDateString(),
                'title' => $holiday->title,
                'is_day_off' => $holiday->is_day_off,
            ];

            $holiday->delete();

            $this->auditLogger->record(
                action: 'school_holiday_deleted',
                subject: $holiday,
                before: $before,
                userId: $actor->id,
            );
        });
    }

    /**
     * @param  list<int>  $weekendDays
     */
    public function updateUnitWeekendDays(Unit $unit, array $weekendDays, User $actor): Unit
    {
        $normalized = array_values(array_unique(array_filter(
            $weekendDays,
            static fn (int $day): bool => $day >= 1 && $day <= 7,
        )));
        sort($normalized);

        return DB::transaction(function () use ($unit, $normalized, $actor): Unit {
            /** @var Unit $locked */
            $locked = Unit::query()->whereKey($unit->id)->lockForUpdate()->firstOrFail();
            $before = $this->weekendDaysForUnit($locked);

            $settings = $locked->attendance_settings;
            $settings['weekend_days'] = $normalized;

            $locked->forceFill([
                'attendance_settings' => $settings,
            ])->save();

            $this->auditLogger->record(
                action: 'unit_weekend_days_updated',
                subject: $locked,
                before: ['weekend_days' => $before],
                after: ['weekend_days' => $normalized],
                userId: $actor->id,
            );

            return $locked;
        });
    }
}
