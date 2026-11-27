<?php

declare(strict_types=1);

namespace App\Domain\Attendance;

use App\Enums\AttendanceStatus;
use App\Enums\GateScanResult;
use Carbon\CarbonImmutable;

/**
 * Pengklasifikasi scan gerbang murni tanpa ketergantungan database (T-502).
 * Seluruh perbandingan jam memakai zona waktu bisnis WITA (Asia/Makassar).
 */
final class AttendanceClassifier
{
    public const BUSINESS_TIMEZONE = 'Asia/Makassar';

    /**
     * @param  array{check_in_opens_at: string, late_after: string, absent_after: string, check_out_opens_at: string}  $unitSchedule
     */
    public function classify(
        CarbonImmutable $scannedAt,
        array $unitSchedule,
        ?CarbonImmutable $lastScanAt,
        bool $hasCheckedInToday,
        bool $hasCheckedOutToday,
        int $duplicateWindowSeconds = 120,
        bool $isHoliday = false,
    ): AttendanceClassification {
        $localTime = $scannedAt->setTimezone(self::BUSINESS_TIMEZONE);

        if ($isHoliday) {
            return new AttendanceClassification(
                result: GateScanResult::Holiday,
                direction: 'in',
                shouldUpdateAttendanceDay: false,
                dayStatus: null,
                isCheckIn: false,
                isCheckOut: false,
            );
        }

        if ($lastScanAt !== null && $localTime->diffInSeconds($lastScanAt->setTimezone(self::BUSINESS_TIMEZONE), absolute: true) < $duplicateWindowSeconds) {
            return new AttendanceClassification(
                result: GateScanResult::Duplicate,
                direction: $hasCheckedInToday ? 'out' : 'in',
                shouldUpdateAttendanceDay: false,
                dayStatus: null,
                isCheckIn: false,
                isCheckOut: false,
            );
        }

        $timeString = $localTime->format('H:i:s');
        $checkInOpens = $this->normalizeTime($unitSchedule['check_in_opens_at']);
        $lateAfter = $this->normalizeTime($unitSchedule['late_after']);
        $checkOutOpens = $this->normalizeTime($unitSchedule['check_out_opens_at']);

        if (! $hasCheckedInToday) {
            if ($timeString < $checkInOpens) {
                return new AttendanceClassification(
                    result: GateScanResult::OutsideHours,
                    direction: 'in',
                    shouldUpdateAttendanceDay: false,
                    dayStatus: null,
                    isCheckIn: false,
                    isCheckOut: false,
                );
            }

            if ($timeString <= $lateAfter) {
                return new AttendanceClassification(
                    result: GateScanResult::OnTime,
                    direction: 'in',
                    shouldUpdateAttendanceDay: true,
                    dayStatus: AttendanceStatus::Present,
                    isCheckIn: true,
                    isCheckOut: false,
                );
            }

            return new AttendanceClassification(
                result: GateScanResult::Late,
                direction: 'in',
                shouldUpdateAttendanceDay: true,
                dayStatus: AttendanceStatus::Late,
                isCheckIn: true,
                isCheckOut: false,
            );
        }

        if ($timeString < $checkOutOpens) {
            return new AttendanceClassification(
                result: GateScanResult::OutsideHours,
                direction: 'out',
                shouldUpdateAttendanceDay: false,
                dayStatus: null,
                isCheckIn: false,
                isCheckOut: false,
            );
        }

        if ($hasCheckedOutToday) {
            return new AttendanceClassification(
                result: GateScanResult::Duplicate,
                direction: 'out',
                shouldUpdateAttendanceDay: false,
                dayStatus: null,
                isCheckIn: false,
                isCheckOut: false,
            );
        }

        return new AttendanceClassification(
            result: GateScanResult::CheckOut,
            direction: 'out',
            shouldUpdateAttendanceDay: true,
            dayStatus: null,
            isCheckIn: false,
            isCheckOut: true,
        );
    }

    private function normalizeTime(string $time): string
    {
        return strlen($time) === 5 ? $time.':00' : $time;
    }
}
