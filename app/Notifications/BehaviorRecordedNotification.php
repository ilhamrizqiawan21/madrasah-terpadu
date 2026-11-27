<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Enums\PointCategory;

final class BehaviorRecordedNotification extends GuardianNotification
{
    public function __construct(
        private readonly int $recordId,
        private readonly int $studentId,
        private readonly string $studentName,
        private readonly string $categoryValue,
        private readonly string $ruleName,
        private readonly int $points,
        private readonly string $dateLabel,
    ) {
        parent::__construct();
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::Behavior;
    }

    public function title(object $notifiable): string
    {
        $cat = PointCategory::tryFrom($this->categoryValue);

        return match ($cat) {
            PointCategory::Violation => 'Catatan Pelanggaran Ananda',
            PointCategory::Achievement => 'Catatan Prestasi Ananda',
            PointCategory::GoodDeed => 'Catatan Kebaikan Ananda',
            null => 'Catatan Perilaku Ananda',
        };
    }

    public function body(object $notifiable): string
    {
        $cat = PointCategory::tryFrom($this->categoryValue);
        $prefix = $cat === PointCategory::Violation ? 'tercatat melakukan pelanggaran' : 'memperoleh catatan';

        // Nama pencatat disembunyikan dari wali sesuai U-06.
        return "Ananda {$this->studentName} {$prefix}: {$this->ruleName} ({$this->points} poin) pada {$this->dateLabel}.";
    }

    public function url(object $notifiable): string
    {
        return "/wali/anak/{$this->studentId}/perilaku";
    }

    public function tag(object $notifiable): string
    {
        return "behavior-record-{$this->recordId}";
    }
}
