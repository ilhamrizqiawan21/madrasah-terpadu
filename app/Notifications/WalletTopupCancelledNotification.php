<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Support\Money;

/**
 * Notifikasi ke wali bila isi saldo tunai dibatalkan bendahara (mis. salah siswa/nominal).
 */
final class WalletTopupCancelledNotification extends GuardianNotification
{
    public function __construct(
        private readonly int $entryId,
        private readonly string $studentName,
        private readonly int $amount,
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
        return 'Isi Saldo Dibatalkan';
    }

    public function body(object $notifiable): string
    {
        $amountText = Money::format($this->amount);
        $balanceText = Money::format($this->balanceAfter);

        return "Isi saldo kantin {$this->studentName} sebesar {$amountText} dibatalkan oleh sekolah. Saldo sekarang {$balanceText}.";
    }

    public function url(object $notifiable): string
    {
        return '/wali/dompet';
    }

    public function tag(object $notifiable): string
    {
        return "wallet-topup-cancelled-{$this->entryId}";
    }
}
