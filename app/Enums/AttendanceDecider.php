<?php

declare(strict_types=1);

namespace App\Enums;

enum AttendanceDecider: string
{
    case Scan = 'scan';
    case Leave = 'leave';
    case System = 'system';
    case Manual = 'manual';
}
