<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\User;

/**
 * Notifikasi uji dari admin untuk dukungan teknis wali (T-405).
 * Memakai kategori `account` (terkunci selalu aktif) agar terkirim ke kotak masuk
 * sekaligus Web Push meski kategori lain dimatikan wali.
 */
final class AdminTestNotification extends GuardianNotification
{
    public function __construct(private readonly string $sentAtLabel)
    {
        parent::__construct();
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::Account;
    }

    public function title(object $notifiable): string
    {
        return 'Notifikasi Uji Sistem';
    }

    public function body(object $notifiable): string
    {
        $recipientName = $notifiable instanceof User ? $notifiable->name : 'Wali';

        return "Notifikasi uji berhasil dikirim untuk {$recipientName} pukul {$this->sentAtLabel} WITA.";
    }

    public function url(object $notifiable): string
    {
        return '/wali/notifikasi';
    }

    public function tag(object $notifiable): string
    {
        return 'admin-test-notification';
    }
}
