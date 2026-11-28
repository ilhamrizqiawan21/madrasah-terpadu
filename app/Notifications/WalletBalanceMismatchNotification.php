<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Peringatan ke admin saat verifikasi harian mendeteksi selisih cache saldo dompet vs ledger (T-802).
 */
final class WalletBalanceMismatchNotification extends Notification
{
    use Queueable;

    /**
     * @param  list<array{wallet_id: int, student_id: int, cached_balance: int, ledger_sum: int}>  $mismatches
     */
    public function __construct(
        private readonly array $mismatches,
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
            'title' => 'Selisih Saldo Dompet Kantin',
            'body' => count($this->mismatches).' dompet memiliki selisih antara saldo cache dan jumlah ledger.',
            'mismatches' => $this->mismatches,
        ];
    }
}
