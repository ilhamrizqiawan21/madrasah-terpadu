<?php

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Enums\GateScanResult;
use App\Models\AttendanceDay;
use App\Models\GateScan;
use App\Models\Student;

final readonly class GateScanOutcome
{
    public function __construct(
        public bool $accepted,
        public string $code,
        public string $message,
        public ?Student $student = null,
        public ?GateScan $scan = null,
        public ?AttendanceDay $attendanceDay = null,
        public ?GateScanResult $result = null,
        public ?string $timeLabel = null,
    ) {}
}
