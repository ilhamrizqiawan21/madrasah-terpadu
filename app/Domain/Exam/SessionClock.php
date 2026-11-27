<?php

declare(strict_types=1);

namespace App\Domain\Exam;

use Carbon\CarbonImmutable;

/**
 * Perhitungan batas waktu sesi ujian di server (ADR-020).
 */
final class SessionClock
{
    public const GRACE_SECONDS = 120;

    public static function computeDeadline(
        CarbonImmutable $startedAt,
        int $durationMinutes,
        CarbonImmutable $examClosesAt,
        int $extraMinutes = 0,
    ): CarbonImmutable {
        $durationEnd = $startedAt->addMinutes($durationMinutes + $extraMinutes);
        $windowEnd = $examClosesAt->addMinutes($extraMinutes);

        return $durationEnd->lessThan($windowEnd) ? $durationEnd : $windowEnd;
    }

    public static function isWithinGrace(
        CarbonImmutable $now,
        CarbonImmutable $deadlineAt,
        int $extraMinutes = 0,
    ): bool {
        $effectiveDeadline = $deadlineAt->addMinutes($extraMinutes)->addSeconds(self::GRACE_SECONDS);

        return $now->lessThanOrEqualTo($effectiveDeadline);
    }

    public static function remainingSeconds(
        CarbonImmutable $now,
        CarbonImmutable $deadlineAt,
        int $extraMinutes = 0,
    ): int {
        $effectiveDeadline = $deadlineAt->addMinutes($extraMinutes);

        if ($now->greaterThanOrEqualTo($effectiveDeadline)) {
            return 0;
        }

        return (int) $now->diffInSeconds($effectiveDeadline);
    }
}
