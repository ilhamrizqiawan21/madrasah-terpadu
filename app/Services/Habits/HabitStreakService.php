<?php

declare(strict_types=1);

namespace App\Services\Habits;

use App\Enums\PointAccount;
use App\Enums\SettingKey;
use App\Models\AcademicYear;
use App\Models\HabitLog;
use App\Models\PointEntry;
use App\Models\Student;
use App\Models\User;
use App\Services\Behavior\PointLedgerService;
use App\Services\Settings\SettingsRepository;
use Carbon\CarbonImmutable;

class HabitStreakService
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly PointLedgerService $ledger,
    ) {}

    /**
     * @return array{enabled: bool, days: int, points: int}
     */
    public function config(): array
    {
        $enabled = (bool) $this->settings->get(SettingKey::HabitStreakEnabled, false);
        $days = max(1, (int) $this->settings->get(SettingKey::HabitStreakDays, 7));
        $points = max(1, (int) $this->settings->get(SettingKey::HabitStreakPoints, 5));

        return [
            'enabled' => $enabled,
            'days' => $days,
            'points' => $points,
        ];
    }

    public function updateConfig(User $actor, bool $enabled, int $days, int $points): void
    {
        $this->settings->setMany([
            SettingKey::HabitStreakEnabled->value => $enabled,
            SettingKey::HabitStreakDays->value => max(1, $days),
            SettingKey::HabitStreakPoints->value => max(1, $points),
        ], $actor);
    }

    public function evaluateAfterVerification(
        HabitLog $verifiedLog,
        AcademicYear $academicYear,
    ): ?PointEntry {
        $config = $this->config();
        if (! $config['enabled'] || ! $verifiedLog->done || $verifiedLog->verification !== 'verified') {
            return null;
        }

        $days = $config['days'];
        $points = $config['points'];
        $endDate = $verifiedLog->log_date->startOfDay();
        $startDate = $endDate->subDays($days - 1);

        $verifiedDates = HabitLog::query()
            ->where('student_id', $verifiedLog->student_id)
            ->where('habit_item_id', $verifiedLog->habit_item_id)
            ->where('done', true)
            ->where('verification', 'verified')
            ->whereDate('log_date', '>=', $startDate->toDateString())
            ->whereDate('log_date', '<=', $endDate->toDateString())
            ->pluck('log_date')
            ->map(static fn (mixed $d): string => $d instanceof CarbonImmutable
                ? $d->toDateString()
                : CarbonImmutable::parse((string) $d)->toDateString())
            ->unique()
            ->all();

        for ($i = 0; $i < $days; $i++) {
            $check = $startDate->addDays($i)->toDateString();
            if (! in_array($check, $verifiedDates, true)) {
                return null;
            }
        }

        // Prevent overlapping streak awards within the same streak window
        $recentKeys = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $endDate->subDays($i)->toDateString();
            $recentKeys[] = "habit-streak:{$verifiedLog->student_id}:{$verifiedLog->habit_item_id}:{$academicYear->id}:{$d}";
        }

        $alreadyAwardedRecently = PointEntry::query()
            ->whereIn('idempotency_key', $recentKeys)
            ->exists();

        if ($alreadyAwardedRecently) {
            return null;
        }

        /** @var Student $student */
        $student = $verifiedLog->student;

        return $this->ledger->post(
            student: $student,
            academicYear: $academicYear,
            account: PointAccount::Merit,
            delta: $points,
            source: $verifiedLog,
            idempotencyKey: "habit-streak:{$verifiedLog->student_id}:{$verifiedLog->habit_item_id}:{$academicYear->id}:{$endDate->toDateString()}",
        );
    }
}
