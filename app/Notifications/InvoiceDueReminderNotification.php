<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Support\Money;

final class InvoiceDueReminderNotification extends GuardianNotification
{
    public function __construct(
        private readonly int $invoiceId,
        private readonly string $studentName,
        private readonly string $description,
        private readonly int $remaining,
        private readonly string $dueDateLabel,
        private readonly string $kind,
    ) {
        parent::__construct();
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::Billing;
    }

    public function title(object $notifiable): string
    {
        return $this->kind === 'overdue'
            ? 'Tagihan Melewati Jatuh Tempo'
            : 'Pengingat Tagihan Jatuh Tempo';
    }

    public function body(object $notifiable): string
    {
        $nominal = Money::format($this->remaining);

        if ($this->kind === 'overdue') {
            return "Tagihan {$this->description} untuk {$this->studentName} sebesar {$nominal} telah melewati jatuh tempo ({$this->dueDateLabel}).";
        }

        return "Tagihan {$this->description} untuk {$this->studentName} sebesar {$nominal} akan jatuh tempo pada {$this->dueDateLabel}.";
    }

    public function url(object $notifiable): string
    {
        return '/wali/tagihan';
    }

    public function tag(object $notifiable): string
    {
        return "invoice-reminder-{$this->kind}-{$this->invoiceId}";
    }
}
