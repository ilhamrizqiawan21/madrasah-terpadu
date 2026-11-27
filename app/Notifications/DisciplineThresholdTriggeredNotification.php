<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;

final class DisciplineThresholdTriggeredNotification extends GuardianNotification
{
    public function __construct(
        private readonly int $followupId,
        private readonly int $studentId,
        private readonly string $studentName,
        private readonly int $thresholdPoints,
        private readonly string $actionName,
    ) {
        parent::__construct();
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::Behavior;
    }

    public function title(object $notifiable): string
    {
        return "Tindak Lanjut Kedisiplinan: {$this->actionName}";
    }

    public function body(object $notifiable): string
    {
        return "Poin pelanggaran Ananda {$this->studentName} telah mencapai {$this->thresholdPoints} poin. Tindak lanjut sekolah: {$this->actionName}.";
    }

    public function url(object $notifiable): string
    {
        return "/wali/anak/{$this->studentId}/perilaku";
    }

    public function tag(object $notifiable): string
    {
        return "discipline-followup-{$this->followupId}";
    }
}
