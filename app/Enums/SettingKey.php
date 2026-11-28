<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Kunci yang boleh disimpan di tabel `settings`. Kunci di luar daftar ini ditolak
 * agar tabel tidak menjadi tempat sampah konfigurasi.
 */
enum SettingKey: string
{
    case AppName = 'identity.app_name';
    case FoundationName = 'identity.foundation_name';
    case Address = 'identity.address';
    case City = 'identity.city';
    case Phone = 'identity.phone';
    case Email = 'identity.email';
    case LogoPath = 'identity.logo_path';
    case FaviconPath = 'identity.favicon_path';
    case PrimaryColor = 'appearance.primary_color';
    case HabitStreakEnabled = 'habits.streak_enabled';
    case HabitStreakDays = 'habits.streak_days';
    case HabitStreakPoints = 'habits.streak_points';

    public function label(): string
    {
        return match ($this) {
            self::AppName => 'Nama aplikasi',
            self::FoundationName => 'Nama yayasan',
            self::Address => 'Alamat',
            self::City => 'Kota/kabupaten',
            self::Phone => 'Telepon',
            self::Email => 'Email',
            self::LogoPath => 'Logo',
            self::FaviconPath => 'Favicon',
            self::PrimaryColor => 'Warna utama',
            self::HabitStreakEnabled => 'Aktifkan poin streak mutabaah',
            self::HabitStreakDays => 'Jumlah hari streak mutabaah',
            self::HabitStreakPoints => 'Poin streak mutabaah',
        };
    }
}
