<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;

final class StudentCheckedOutNotification extends GuardianNotification
{
    public function __construct(
        private readonly int $studentId,
        private readonly string $studentName,
        private readonly string $schoolDate,
        private readonly string $timeLabel,
    ) {
        parent::__construct();
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::Attendance;
    }

    public function title(object $notifiable): string
    {
        return 'Ananda Pulang dari Sekolah';
    }

    public function body(object $notifiable): string
    {
        return "Ananda {$this->studentName} tercatat pulang pukul {$this->timeLabel} WITA.";
    }

    public function url(object $notifiable): string
    {
        return '/wali';
    }

    public function tag(object $notifiable): string
    {
        return "attendance-out-{$this->studentId}-{$this->schoolDate}";
    }
}
