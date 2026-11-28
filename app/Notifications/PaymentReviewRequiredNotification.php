<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\PaymentReviewReason;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;

/**
 * Peringatan ke bendahara: order Midtrans perlu ditinjau manusia (PAYMENTS §4, ADR-009, T-707).
 */
final class PaymentReviewRequiredNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(
        private readonly string $orderId,
        private readonly PaymentReviewReason $reason,
        private readonly string $studentName,
        private readonly int $amount,
        private readonly string $detail,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Pembayaran online perlu ditinjau: '.$this->reason->label(),
            'body' => "Order {$this->orderId} ({$this->studentName}, ".Money::format($this->amount)."). {$this->detail}",
            'order_id' => $this->orderId,
            'reason' => $this->reason->value,
        ];
    }
}
