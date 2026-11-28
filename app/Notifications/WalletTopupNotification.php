<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Support\Money;

/**
 * Notifikasi ke wali murid saat isi saldo dompet kantin berhasil (T-804).
 */
final class WalletTopupNotification extends GuardianNotification
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
        return 'Isi Saldo Dompet Kantin';
    }

    public function body(object $notifiable): string
    {
        $amountText = Money::format($this->amount);
        $balanceText = Money::format($this->balanceAfter);

        return "Saldo kantin {$this->studentName} bertambah {$amountText}. Saldo sekarang {$balanceText}.";
    }

    public function url(object $notifiable): string
    {
        return '/wali/dompet';
    }

    public function tag(object $notifiable): string
    {
        return "wallet-topup-{$this->entryId}";
    }
}
