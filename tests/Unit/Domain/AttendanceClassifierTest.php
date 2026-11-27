<?php

declare(strict_types=1);

use App\Domain\Attendance\AttendanceClassifier;
use App\Enums\AttendanceStatus;
use App\Enums\GateScanResult;
use Carbon\CarbonImmutable;

$schedule = [
    'check_in_opens_at' => '05:30',
    'late_after' => '07:00',
    'absent_after' => '09:00',
    'check_out_opens_at' => '11:00',
];

test('AttendanceClassifier mengklasifikasi scan gerbang dengan benar berdasarkan jadwal WITA', function (
    string $time,
    ?string $lastScanTime,
    bool $checkedIn,
    bool $checkedOut,
    bool $isHoliday,
    GateScanResult $expectedResult,
    string $expectedDirection,
    bool $expectedShouldUpdate,
    ?AttendanceStatus $expectedDayStatus,
) use ($schedule): void {
    $classifier = new AttendanceClassifier;
    $tz = AttendanceClassifier::BUSINESS_TIMEZONE;

    $scannedAt = CarbonImmutable::parse("2026-11-27 {$time}", $tz);
    $lastScanAt = $lastScanTime !== null ? CarbonImmutable::parse("2026-11-27 {$lastScanTime}", $tz) : null;

    $classification = $classifier->classify(
        scannedAt: $scannedAt,
        unitSchedule: $schedule,
        lastScanAt: $lastScanAt,
        hasCheckedInToday: $checkedIn,
        hasCheckedOutToday: $checkedOut,
        duplicateWindowSeconds: 120,
        isHoliday: $isHoliday,
    );

    expect($classification->result)->toBe($expectedResult)
        ->and($classification->direction)->toBe($expectedDirection)
        ->and($classification->shouldUpdateAttendanceDay)->toBe($expectedShouldUpdate)
        ->and($classification->dayStatus)->toBe($expectedDayStatus);
})->with([
    'sebelum jam buka' => ['05:29:59', null, false, false, false, GateScanResult::OutsideHours, 'in', false, null],
    'tepat jam buka' => ['05:30:00', null, false, false, false, GateScanResult::OnTime, 'in', true, AttendanceStatus::Present],
    'tepat batas masuk' => ['07:00:00', null, false, false, false, GateScanResult::OnTime, 'in', true, AttendanceStatus::Present],
    '1 detik lewat batas masuk' => ['07:00:01', null, false, false, false, GateScanResult::Late, 'in', true, AttendanceStatus::Late],
    'scan duplikat < 120 detik setelah masuk' => ['06:55:30', '06:54:00', true, false, false, GateScanResult::Duplicate, 'out', false, null],
    'scan setelah 120 detik tapi sebelum jam pulang' => ['10:59:59', '06:50:00', true, false, false, GateScanResult::OutsideHours, 'out', false, null],
    'tepat jam pulang' => ['11:00:00', '06:50:00', true, false, false, GateScanResult::CheckOut, 'out', true, null],
    'scan lagi setelah sudah pulang' => ['11:05:00', '11:00:00', true, true, false, GateScanResult::Duplicate, 'out', false, null],
    'hari libur' => ['06:45:00', null, false, false, true, GateScanResult::Holiday, 'in', false, null],
]);
