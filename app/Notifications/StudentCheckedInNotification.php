<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\AttendanceStatus;
use App\Enums\NotificationCategory;

final class StudentCheckedInNotification extends GuardianNotification
{
    public function __construct(
        private readonly int $studentId,
        private readonly string $studentName,
        private readonly string $schoolDate,
        private readonly string $timeLabel,
        private readonly AttendanceStatus $status,
    ) {
        parent::__construct();
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::Attendance;
    }

    public function title(object $notifiable): string
    {
        return $this->status === AttendanceStatus::Late
            ? 'Ananda Tiba Terlambat'
            : 'Ananda Tiba di Sekolah';
    }

    public function body(object $notifiable): string
    {
        $statusNote = $this->status === AttendanceStatus::Late ? ' (terlambat)' : '';

        return "Ananda {$this->studentName} tiba di sekolah pukul {$this->timeLabel} WITA{$statusNote}.";
    }

    public function url(object $notifiable): string
    {
        return '/wali';
    }

    public function tag(object $notifiable): string
    {
        return "attendance-in-{$this->studentId}-{$this->schoolDate}";
    }
}
