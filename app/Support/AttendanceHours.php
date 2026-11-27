<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Jam presensi per unit (PRD §4.2), disimpan di `units.attendance_settings` dalam format H:i WITA.
 * Urutan wajib: jam masuk dibuka < batas terlambat < batas alpa, dan jam pulang minimal > batas terlambat.
 */
final class AttendanceHours
{
    public const KEYS = ['check_in_opens_at', 'late_after', 'absent_after', 'check_out_opens_at'];

    /**
     * @return array{check_in_opens_at: string, late_after: string, absent_after: string, check_out_opens_at: string}
     */
    public static function defaults(): array
    {
        /** @var array<string, string> $configured */
        $configured = (array) config('sekolah.attendance.defaults', []);

        return [
            'check_in_opens_at' => (string) ($configured['check_in_opens_at'] ?? '05:30'),
            'late_after' => (string) ($configured['late_after'] ?? '07:00'),
            'absent_after' => (string) ($configured['absent_after'] ?? '09:00'),
            'check_out_opens_at' => (string) ($configured['check_out_opens_at'] ?? '11:00'),
        ];
    }
}
