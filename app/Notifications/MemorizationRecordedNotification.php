<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;

final class MemorizationRecordedNotification extends GuardianNotification
{
    public function __construct(
        private readonly int $recordId,
        private readonly int $studentId,
        private readonly string $studentName,
        private readonly string $summaryLabel,
        private readonly float $finalScore,
        private readonly string $predicate,
        private readonly bool $passed,
    ) {
        parent::__construct();
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::Quran;
    }

    public function title(object $notifiable): string
    {
        return 'Setoran Hafalan Ananda';
    }

    public function body(object $notifiable): string
    {
        $status = $this->passed ? 'Lulus' : 'Perlu mengulang';

        return "Setoran hafalan Ananda {$this->studentName} ({$this->summaryLabel}) mendapat nilai {$this->finalScore} (predikat {$this->predicate}, {$status}).";
    }

    public function url(object $notifiable): string
    {
        return "/wali/anak/{$this->studentId}/hafalan";
    }

    public function tag(object $notifiable): string
    {
        return "memorization-record-{$this->recordId}";
    }
}
