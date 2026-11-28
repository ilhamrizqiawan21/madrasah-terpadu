<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Support\Money;

/**
 * Notifikasi ke wali murid setiap transaksi jajan di POS kantin (T-803, PRD §4.6).
 */
final class CanteenPurchaseMadeNotification extends GuardianNotification
{
    public function __construct(
        private readonly int $transactionId,
        private readonly string $studentName,
        private readonly int $total,
        private readonly int $balanceAfter,
    ) {
        parent::__construct();
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::Canteen;
    }

    public function title(object $notifiable): string
    {
        return 'Jajan Kantin';
    }

    public function body(object $notifiable): string
    {
        $totalText = Money::format($this->total);
        $balanceText = Money::format($this->balanceAfter);

        return "Ananda {$this->studentName} jajan {$totalText} di Kantin. Sisa saldo {$balanceText}.";
    }

    public function url(object $notifiable): string
    {
        return '/wali/riwayat';
    }

    public function tag(object $notifiable): string
    {
        return "canteen-purchase-{$this->transactionId}";
    }
}
