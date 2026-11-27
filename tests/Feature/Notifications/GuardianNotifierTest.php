<?php

declare(strict_types=1);

use App\Enums\GuardianRelation;
use App\Enums\NotificationCategory;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\GuardianNotification;
use App\Services\Notifications\GuardianNotifier;
use App\Services\Notifications\NotificationPreferenceService;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use NotificationChannels\WebPush\WebPushChannel;

function makeSampleGuardianNotification(NotificationCategory $category = NotificationCategory::Attendance): GuardianNotification
{
    return new class($category) extends GuardianNotification
    {
        public function __construct(private readonly NotificationCategory $notificationCategory)
        {
            parent::__construct();
        }

        public function category(): NotificationCategory
        {
            return $this->notificationCategory;
        }

        public function title(object $notifiable): string
        {
            return 'Presensi Masuk';
        }

        public function body(object $notifiable): string
        {
            return 'Ananda telah melakukan presensi masuk tepat waktu.';
        }

        public function url(object $notifiable): string
        {
            return '/wali';
        }

        public function tag(object $notifiable): string
        {
            return 'attendance-sample';
        }
    };
}

test('GuardianNotifier mengirim notifikasi ke semua wali aktif siswa dan melewati wali nonaktif', function (): void {
    Notification::fake();

    $student = Student::factory()->create();

    $activeUser1 = User::factory()->create(['is_active' => true]);
    $activeUser2 = User::factory()->create(['is_active' => true]);
    $inactiveUser = User::factory()->create(['is_active' => false]);

    $g1 = Guardian::factory()->create(['user_id' => $activeUser1->id]);
    $g2 = Guardian::factory()->create(['user_id' => $activeUser2->id]);
    $g3 = Guardian::factory()->create(['user_id' => $inactiveUser->id]);

    $student->guardians()->attach($g1->id, ['relation' => GuardianRelation::Father->value, 'is_primary' => true]);
    $student->guardians()->attach($g2->id, ['relation' => GuardianRelation::Mother->value, 'is_primary' => false]);
    $student->guardians()->attach($g3->id, ['relation' => GuardianRelation::Guardian->value, 'is_primary' => false]);

    $notification = makeSampleGuardianNotification();

    app(GuardianNotifier::class)->notifyStudentGuardians($student, $notification);

    Notification::assertSentTo($activeUser1, $notification::class);
    Notification::assertSentTo($activeUser2, $notification::class);
    Notification::assertNotSentTo($inactiveUser, $notification::class);
});

test('rollback transaksi tidak mengirim notifikasi ShouldQueueAfterCommit', function (): void {
    Queue::fake();

    $student = Student::factory()->create();
    $user = User::factory()->create(['is_active' => true]);
    $guardian = Guardian::factory()->create(['user_id' => $user->id]);
    $student->guardians()->attach($guardian->id, ['relation' => GuardianRelation::Father->value, 'is_primary' => true]);

    $notification = makeSampleGuardianNotification();
    expect($notification)->toBeInstanceOf(ShouldQueueAfterCommit::class);
    expect($notification->queue)->toBe('notifications');
    expect($notification->tries)->toBe(3);
    expect($notification->backoff())->toBe([10, 60, 300]);

    try {
        DB::transaction(function () use ($student, $notification): void {
            app(GuardianNotifier::class)->notifyStudentGuardians($student, $notification);

            throw new RuntimeException('Simulasi gagal di tengah transaksi');
        });
    } catch (RuntimeException) {
        // Diharapkan rollback.
    }

    Queue::assertNothingPushed();

    // Saat transaksi berhasil (tidak rollback), job notifikasi didorong ke antrean `notifications`.
    DB::transaction(function () use ($student, $notification): void {
        app(GuardianNotifier::class)->notifyStudentGuardians($student, $notification);
    });

    Queue::assertPushedOn('notifications', SendQueuedNotifications::class);
});

test('GuardianNotification menghormati preferensi push dan selalu menyertakan database', function (): void {
    $user = User::factory()->create(['is_active' => true]);

    app(NotificationPreferenceService::class)->update($user, [
        NotificationCategory::Attendance->value => false,
    ]);

    $attendanceNotif = makeSampleGuardianNotification(NotificationCategory::Attendance);
    $billingNotif = makeSampleGuardianNotification(NotificationCategory::Billing);

    expect($attendanceNotif->via($user))->toBe(['database']);
    expect($billingNotif->via($user))->toBe(['database', WebPushChannel::class]);

    $payload = $attendanceNotif->toArray($user);
    expect($payload)->toMatchArray([
        'title' => 'Presensi Masuk',
        'body' => 'Ananda telah melakukan presensi masuk tepat waktu.',
        'url' => '/wali',
        'category' => 'attendance',
        'tag' => 'attendance-sample',
    ]);

    $webPush = $attendanceNotif->toWebPush($user, $attendanceNotif)->toArray();
    expect($webPush['title'])->toBe('Presensi Masuk')
        ->and($webPush['tag'])->toBe('attendance-sample')
        ->and($webPush['data']['url'])->toBe('/wali');
});

test('WhatsAppChannel terdaftar sebagai kanal no-op dan tidak dipakai di via()', function (): void {
    $channel = app(ChannelManager::class)->driver('whatsapp');
    expect($channel)->toBeInstanceOf(WhatsAppChannel::class);

    $user = User::factory()->create(['is_active' => true]);
    $notification = makeSampleGuardianNotification();

    expect($notification->via($user))->not->toContain('whatsapp')
        ->not->toContain(WhatsAppChannel::class);

    // Panggilan send() harus aman tanpa error (no-op).
    $channel->send($user, $notification);
    expect(true)->toBeTrue();
});
