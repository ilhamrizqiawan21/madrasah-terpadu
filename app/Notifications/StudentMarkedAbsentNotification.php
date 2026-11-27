<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;

final class StudentMarkedAbsentNotification extends GuardianNotification
{
    public function __construct(
        private readonly int $studentId,
        private readonly string $studentName,
        private readonly string $schoolDate,
        private readonly string $absentAfterLabel,
        private readonly string $dateLabel,
    ) {
        parent::__construct();
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::Attendance;
    }

    public function title(object $notifiable): string
    {
        return 'Ananda Tercatat Alpa';
    }

    public function body(object $notifiable): string
    {
        return "Ananda {$this->studentName} belum melakukan presensi hingga batas pukul {$this->absentAfterLabel} WITA ({$this->dateLabel}).";
    }

    public function url(object $notifiable): string
    {
        return "/wali/anak/{$this->studentId}/izin";
    }

    public function tag(object $notifiable): string
    {
        return "attendance-absent-{$this->studentId}-{$this->schoolDate}";
    }
}
