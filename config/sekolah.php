<?php

declare(strict_types=1);

/*
| Domain configuration. Values here are defaults; anything a school admin
| must change at runtime lives in the `settings` table (docs/SCHEMA.md) and
| overrides these. Never read env() outside config files.
*/

return [

    'foundation_name' => 'Yayasan Amal Jaya Berkah',

    // Aplikasi lama yang masih memegang keuangan selama masa paralel (ADR-021); kosong = tanpa tautan.
    'legacy_finance_url' => env('LEGACY_FINANCE_URL', ''),

    'security' => [
        // Wajib 2FA untuk Admin & Bendahara (T-104). Dimatikan sementara atas
        // keputusan pemilik (ADR-025); nyalakan sebelum produksi.
        'require_two_factor' => (bool) env('REQUIRE_TWO_FACTOR', false),
        // Fitur 2FA secara keseluruhan (ADR-026). false = tanpa tantangan kode saat login
        // dan tanpa menu 2FA di Keamanan, termasuk untuk akun yang sudah pernah mengaktifkannya.
        'two_factor_enabled' => (bool) env('TWO_FACTOR_ENABLED', false),
    ],

    'attendance' => [
        // Default gate times per unit, overridable in Pengaturan > Presensi.
        'defaults' => [
            'check_in_opens_at' => '05:30',
            'late_after' => '07:00',
            'check_out_opens_at' => '11:00',
            'absent_after' => '09:00',
        ],
        // A second scan of the same student within this window is ignored.
        'duplicate_scan_window_seconds' => 120,
        // How many days back a guardian may submit a leave request.
        'leave_backdate_days' => 3,
    ],

    'wallet' => [
        // Upper bound a guardian may set as a daily spending limit (rupiah).
        'max_daily_limit' => 100_000,
        'default_daily_limit' => 20_000,
        'min_top_up' => 10_000,
        'max_top_up' => 1_000_000,
        // A cashier may reverse their own purchase within this window.
        'cashier_reversal_window_minutes' => 15,
    ],

    'habits' => [
        'guardian_backdate_days' => 3,
    ],

    'payments' => [
        // Pending Snap orders expire after this many hours.
        'order_expiry_hours' => 24,
        // ADR-009 (menunggu U-05): kelebihan bayar online saat tagihan sudah lunas di kasir masuk
        // saldo kantin siswa. false = hanya diberi tanda tinjau, pengembalian diputuskan bendahara.
        'overpayment_to_wallet' => (bool) env('PAYMENTS_OVERPAYMENT_TO_WALLET', true),
        'enabled_payments' => [
            'other_qris', 'gopay', 'shopeepay',
            'bca_va', 'bni_va', 'bri_va', 'permata_va', 'echannel',
        ],
    ],

    'notifications' => [
        // Categories a guardian can toggle. `billing` and `account` are always on.
        'categories' => ['attendance', 'canteen', 'behavior', 'habit', 'quran', 'billing', 'account'],
        'always_on' => ['billing', 'account'],
    ],

];
