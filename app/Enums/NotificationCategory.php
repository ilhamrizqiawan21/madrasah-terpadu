<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationCategory: string
{
    case Attendance = 'attendance';
    case Canteen = 'canteen';
    case Behavior = 'behavior';
    case Habit = 'habit';
    case Quran = 'quran';
    case Billing = 'billing';
    case Account = 'account';

    public function label(): string
    {
        return match ($this) {
            self::Attendance => 'Presensi',
            self::Canteen => 'Kantin',
            self::Behavior => 'Perilaku',
            self::Habit => 'Pembiasaan',
            self::Quran => 'Hafalan',
            self::Billing => 'Tagihan & lunas',
            self::Account => 'Keamanan akun',
        };
    }

    public function isLocked(): bool
    {
        /** @var list<string> $alwaysOn */
        $alwaysOn = config('sekolah.notifications.always_on', ['billing', 'account']);

        return in_array($this->value, $alwaysOn, true);
    }
}
