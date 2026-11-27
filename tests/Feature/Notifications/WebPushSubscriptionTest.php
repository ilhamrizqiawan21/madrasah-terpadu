<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

test('tamu tidak dapat menyimpan atau menghapus push subscription', function (): void {
    $this->postJson(route('push.subscriptions.store'), [
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/sample-token',
    ])->assertUnauthorized();

    $this->deleteJson(route('push.subscriptions.destroy'), [
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/sample-token',
    ])->assertUnauthorized();
});

test('pengguna login dapat menyimpan dan menghapus push subscription', function (): void {
    $user = User::factory()->create();
    $endpoint = 'https://fcm.googleapis.com/fcm/send/example-endpoint-123';

    $this->actingAs($user)
        ->postJson(route('push.subscriptions.store'), [
            'endpoint' => $endpoint,
            'keys' => [
                'p256dh' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u-Ts1XbjhazAkj7I99e8QcYP7DkM',
                'auth' => 'tBHItJI5svbpez7KI4CCXg',
            ],
            'content_encoding' => 'aes128gcm',
        ])
        ->assertNoContent();

    expect($user->pushSubscriptions()->where('endpoint', $endpoint)->exists())->toBeTrue();

    $this->actingAs($user)
        ->deleteJson(route('push.subscriptions.destroy'), [
            'endpoint' => $endpoint,
        ])
        ->assertNoContent();

    expect($user->pushSubscriptions()->where('endpoint', $endpoint)->exists())->toBeFalse();
});

test('endpoint tidak valid ditolak', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('push.subscriptions.store'), [
            'endpoint' => 'bukan-url',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('endpoint');
});

test('notifikasi uji terkirim melalui channel WebPush', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    $sample = new class extends BaseNotification
    {
        /**
         * @return list<string>
         */
        public function via(object $notifiable): array
        {
            return [WebPushChannel::class];
        }

        public function toWebPush(object $notifiable, object $notification): WebPushMessage
        {
            return (new WebPushMessage)
                ->title('Uji Notifikasi')
                ->body('Pesan uji Web Push')
                ->tag('test-push')
                ->data(['url' => '/wali']);
        }
    };

    $user->notify($sample);

    Notification::assertSentTo($user, $sample::class, function ($notification, array $channels): bool {
        return in_array(WebPushChannel::class, $channels, true);
    });
});
