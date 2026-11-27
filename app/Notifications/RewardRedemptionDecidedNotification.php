<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;

final class RewardRedemptionDecidedNotification extends GuardianNotification
{
    public function __construct(
        private readonly int $redemptionId,
        private readonly int $studentId,
        private readonly string $studentName,
        private readonly string $offerName,
        private readonly int $voucherAmount,
        private readonly bool $approved,
        private readonly ?string $note = null,
    ) {
        parent::__construct();
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::Behavior;
    }

    public function title(object $notifiable): string
    {
        return $this->approved ? 'Penukaran Reward Disetujui' : 'Penukaran Reward Ditolak';
    }

    public function body(object $notifiable): string
    {
        if ($this->approved) {
            $nominal = number_format($this->voucherAmount, 0, ',', '.');

            return "Penukaran reward {$this->offerName} untuk Ananda {$this->studentName} disetujui. Voucher kantin senilai Rp {$nominal} telah diterbitkan.";
        }

        $reason = $this->note !== null && $this->note !== '' ? " Alasan: {$this->note}." : '';

        return "Pengajuan reward {$this->offerName} untuk Ananda {$this->studentName} belum dapat disetujui.{$reason}";
    }

    public function url(object $notifiable): string
    {
        return "/wali/anak/{$this->studentId}/perilaku";
    }

    public function tag(object $notifiable): string
    {
        return "reward-decided-{$this->redemptionId}";
    }
}
