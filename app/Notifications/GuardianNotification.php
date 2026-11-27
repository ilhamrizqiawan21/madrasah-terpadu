<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\User;
use App\Services\Notifications\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Kelas dasar semua notifikasi domain ke wali murid (T-404, ARCHITECTURE §5).
 *
 * Selalu antre di queue `notifications` setelah transaksi DB commit
 * (`ShouldQueueAfterCommit`). Kanal `database` selalu aktif untuk kotak masuk;
 * kanal `WebPushChannel` mengikuti preferensi kategori wali (`billing` &
 * `account` terkunci aktif). Payload hanya berisi ringkasan tanpa data sensitif.
 */
abstract class GuardianNotification extends Notification implements ShouldQueue, ShouldQueueAfterCommit
{
    use Queueable;

    public int $tries = 3;

    public function __construct()
    {
        $this->onQueue('notifications');
        $this->afterCommit();
    }

    abstract public function category(): NotificationCategory;

    abstract public function title(object $notifiable): string;

    abstract public function body(object $notifiable): string;

    abstract public function url(object $notifiable): string;

    abstract public function tag(object $notifiable): string;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (
            $notifiable instanceof User
            && app(NotificationPreferenceService::class)->isPushEnabled($notifiable, $this->category())
        ) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    /**
     * @return array{title: string, body: string, url: string, category: string, tag: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title($notifiable),
            'body' => $this->body($notifiable),
            'url' => $this->url($notifiable),
            'category' => $this->category()->value,
            'tag' => $this->tag($notifiable),
        ];
    }

    public function toWebPush(object $notifiable, object $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title($notifiable))
            ->body($this->body($notifiable))
            ->tag($this->tag($notifiable))
            ->data([
                'url' => $this->url($notifiable),
                'category' => $this->category()->value,
            ]);
    }
}
