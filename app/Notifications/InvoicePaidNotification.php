<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Support\Money;

final class InvoicePaidNotification extends GuardianNotification
{
    public function __construct(
        private readonly int $paymentId,
        private readonly string $studentName,
        private readonly int $amount,
        private readonly string $receiptNumber,
    ) {
        parent::__construct();
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::Billing;
    }

    public function title(object $notifiable): string
    {
        return 'Pembayaran Tagihan Diterima';
    }

    public function body(object $notifiable): string
    {
        $nominal = Money::format($this->amount);

        return "Pembayaran ananda {$this->studentName} sebesar {$nominal} ({$this->receiptNumber}) telah diterima.";
    }

    public function url(object $notifiable): string
    {
        return '/wali';
    }

    public function tag(object $notifiable): string
    {
        return "invoice-paid-{$this->paymentId}";
    }
}
