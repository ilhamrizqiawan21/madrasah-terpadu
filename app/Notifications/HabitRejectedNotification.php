<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;

final class HabitRejectedNotification extends GuardianNotification
{
    public function __construct(
        private readonly int $logId,
        private readonly int $studentId,
        private readonly string $studentName,
        private readonly string $itemName,
        private readonly string $logDate,
        private readonly string $dateLabel,
        private readonly string $note,
    ) {
        parent::__construct();
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::Habit;
    }

    public function title(object $notifiable): string
    {
        return 'Catatan Pembiasaan Ditolak';
    }

    public function body(object $notifiable): string
    {
        return "Catatan {$this->itemName} untuk Ananda {$this->studentName} ({$this->dateLabel}) ditolak wali kelas. Catatan: {$this->note}.";
    }

    public function url(object $notifiable): string
    {
        return "/wali/anak/{$this->studentId}/pembiasaan?date={$this->logDate}";
    }

    public function tag(object $notifiable): string
    {
        return "habit-rejected-{$this->logId}";
    }
}
