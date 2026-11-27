<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;

/**
 * Kanal WhatsApp cadangan no-op (ADR-005, T-404).
 *
 * Terdaftar di ChannelManager sebagai titik ekstensi masa depan bila yayasan
 * suatu saat memilih gateway WhatsApp. Tidak pernah dimasukkan ke `via()`.
 */
final class WhatsAppChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        // Sengaja kosong (no-op) sampai ADR baru mengaktifkan gateway WhatsApp.
    }
}
