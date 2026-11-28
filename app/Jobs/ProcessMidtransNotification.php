<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\PaymentNotification;
use App\Services\Payments\MidtransNotificationProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Memproses satu notifikasi webhook Midtrans di antrean `payments` (T-703, PAYMENTS §4).
 * Diulang bila Midtrans tidak dapat dihubungi; pemroses idempoten.
 */
final class ProcessMidtransNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [30, 60, 300, 900];

    public function __construct(public readonly int $notificationId)
    {
        $this->onQueue('payments');
    }

    public function handle(MidtransNotificationProcessor $processor): void
    {
        $notification = PaymentNotification::query()->find($this->notificationId);

        if ($notification === null) {
            return;
        }

        $processor->process($notification);
    }
}
