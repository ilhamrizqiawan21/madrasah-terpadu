<?php

declare(strict_types=1);

namespace App\Enums;

enum LeaveType: string
{
    case Sick = 'sick';
    case Excused = 'excused';

    public function label(): string
    {
        return match ($this) {
            self::Sick => 'Sakit',
            self::Excused => 'Izin',
        };
    }

    public function attendanceStatus(): AttendanceStatus
    {
        return match ($this) {
            self::Sick => AttendanceStatus::Sick,
            self::Excused => AttendanceStatus::Excused,
        };
    }
}
