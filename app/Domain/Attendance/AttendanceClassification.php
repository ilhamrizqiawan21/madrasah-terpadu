<?php

declare(strict_types=1);

namespace App\Domain\Attendance;

use App\Enums\AttendanceStatus;
use App\Enums\GateScanResult;

final readonly class AttendanceClassification
{
    public function __construct(
        public GateScanResult $result,
        public string $direction,
        public bool $shouldUpdateAttendanceDay,
        public ?AttendanceStatus $dayStatus,
        public bool $isCheckIn,
        public bool $isCheckOut,
    ) {}
}
