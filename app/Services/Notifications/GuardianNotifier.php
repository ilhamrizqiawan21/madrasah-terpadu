<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * Pengirim tunggal notifikasi domain ke wali murid (T-404, ARCHITECTURE §5).
 *
 * Semua wali aktif yang terhubung ke siswa akan menerima notifikasi.
 * Notifikasi yang dikirim wajib memakai `ShouldQueueAfterCommit` (mis. turunan
 * `GuardianNotification`) agar transaksi yang rollback tidak mengirim pesan.
 */
final class GuardianNotifier
{
    public function notifyStudentGuardians(Student $student, Notification $notification): void
    {
        $guardians = $student->guardians()
            ->with('user')
            ->get();

        $users = $guardians
            ->map(static fn (Guardian $guardian): User => $guardian->user)
            ->filter(static fn (User $user): bool => $user->is_active)
            ->unique('id')
            ->values();

        if ($users->isEmpty()) {
            return;
        }

        DB::afterCommit(static function () use ($users, $notification): void {
            NotificationFacade::send($users, $notification);
        });
    }

    public function notifyUser(User $user, Notification $notification): void
    {
        if (! $user->is_active) {
            return;
        }

        DB::afterCommit(static function () use ($user, $notification): void {
            $user->notify($notification);
        });
    }
}
