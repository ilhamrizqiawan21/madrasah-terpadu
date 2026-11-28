<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Ringkasan selisih rekonsiliasi harian Midtrans untuk bendahara (T-705, PAYMENTS §5).
 */
final class PaymentReconciliationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<array{order_id: string, before: string, after: string, result: string}>  $discrepancies
     * @param  list<string>  $failed
     */
    public function __construct(
        private readonly string $date,
        private readonly array $discrepancies,
        private readonly array $failed,
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
        $parts = [];
        if ($this->discrepancies !== []) {
            $parts[] = count($this->discrepancies).' order berbeda dengan Midtrans';
        }
        if ($this->failed !== []) {
            $parts[] = count($this->failed).' order gagal dicek';
        }

        return [
            'title' => "Rekonsiliasi pembayaran online {$this->date}",
            'body' => implode(', ', $parts).'. Buka Keuangan > Pembayaran online untuk meninjau.',
            'discrepancies' => $this->discrepancies,
            'failed' => $this->failed,
        ];
    }
}
