<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use App\Enums\FeeRecurrence;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Perhitungan murni periode tagihan, jatuh tempo, dan deskripsi tagihan (T-602).
 */
final class BillingPeriod
{
    private const MONTHS_ID = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    /**
     * @return list<CarbonImmutable>
     */
    public static function expandMonthly(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $start = $from->startOfMonth();
        $end = $to->startOfMonth();

        if ($end->lessThan($start)) {
            throw new InvalidArgumentException('Bulan akhir harus sama atau setelah bulan awal.');
        }

        $periods = [];
        $cursor = $start;
        $guard = 0;

        while ($cursor->lessThanOrEqualTo($end)) {
            $periods[] = $cursor;
            $cursor = $cursor->addMonthNoOverflow();
            $guard++;

            if ($guard > 24) {
                throw new InvalidArgumentException('Rentang bulan penerbitan maksimal 24 bulan.');
            }
        }

        return $periods;
    }

    public static function dueDateForPeriod(
        CarbonImmutable $period,
        ?int $dueDay,
        ?CarbonImmutable $fixedDueDate,
    ): CarbonImmutable {
        if ($fixedDueDate !== null) {
            return $fixedDueDate;
        }

        $day = max(1, min(31, $dueDay ?? 10));
        $clampedDay = min($day, $period->daysInMonth);

        return $period->setDay($clampedDay);
    }

    public static function descriptionFor(
        string $feeTypeName,
        FeeRecurrence $recurrence,
        CarbonImmutable $period,
        ?string $academicYearName = null,
    ): string {
        if ($recurrence === FeeRecurrence::Once) {
            return $academicYearName !== null && $academicYearName !== ''
                ? "{$feeTypeName} — {$academicYearName}"
                : $feeTypeName;
        }

        $monthName = self::MONTHS_ID[$period->month] ?? $period->format('F');

        return "{$feeTypeName} — {$monthName} {$period->year}";
    }
}
