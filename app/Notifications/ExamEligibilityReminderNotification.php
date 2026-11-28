<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Support\Money;

/**
 * Notifikasi ke wali murid H-7 dan H-1 sebelum periode ujian bila anak belum layak (T-614).
 */
final class ExamEligibilityReminderNotification extends GuardianNotification
{
    public function __construct(
        private readonly int $examPeriodId,
        private readonly int $studentId,
        private readonly string $studentName,
        private readonly string $periodName,
        private readonly int $blockingCount,
        private readonly int $blockingAmount,
        private readonly string $startsOnLabel,
    ) {
        parent::__construct();
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::Billing;
    }

    public function title(object $notifiable): string
    {
        return 'Syarat Administrasi Ujian';
    }

    public function body(object $notifiable): string
    {
        $nominal = Money::format($this->blockingAmount);

        return "Ananda {$this->studentName} memiliki {$this->blockingCount} tagihan syarat {$this->periodName} sebesar {$nominal} yang perlu dilunasi sebelum {$this->startsOnLabel}.";
    }

    public function url(object $notifiable): string
    {
        return '/wali/tagihan';
    }

    public function tag(object $notifiable): string
    {
        return "exam-eligibility-{$this->examPeriodId}-{$this->studentId}";
    }
}
