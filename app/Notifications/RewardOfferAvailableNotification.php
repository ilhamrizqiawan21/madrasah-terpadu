<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;

final class RewardOfferAvailableNotification extends GuardianNotification
{
    public function __construct(
        private readonly int $studentId,
        private readonly string $studentName,
        private readonly int $offerId,
        private readonly string $offerName,
        private readonly int $costPoints,
    ) {
        parent::__construct();
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::Behavior;
    }

    public function title(object $notifiable): string
    {
        return 'Penawaran Reward Tersedia';
    }

    public function body(object $notifiable): string
    {
        return "Poin kebaikan Ananda {$this->studentName} telah mencukupi untuk menukar reward {$this->offerName} ({$this->costPoints} poin).";
    }

    public function url(object $notifiable): string
    {
        return "/wali/anak/{$this->studentId}/perilaku";
    }

    public function tag(object $notifiable): string
    {
        return "reward-offer-available-{$this->studentId}-{$this->offerId}";
    }
}
