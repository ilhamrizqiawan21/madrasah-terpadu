<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Menolak konfigurasi Midtrans yang tidak konsisten sebelum rilis (T-706, PAYMENTS §2).
 *
 * Aplikasi lama tertangkap memakai kunci sandbox di produksi; perintah ini dijalankan saat deploy
 * agar kesalahan yang sama berhenti sebelum wali membayar.
 */
final class CheckPaymentConfig extends Command
{
    protected $signature = 'payments:check-config';

    protected $description = 'Periksa kesesuaian mode Midtrans (sandbox/produksi), kunci, dan APP_URL.';

    public function handle(): int
    {
        $isProduction = (bool) config('services.midtrans.is_production');
        $serverKey = (string) config('services.midtrans.server_key');
        $clientKey = (string) config('services.midtrans.client_key');
        $appUrl = (string) config('app.url');

        $problems = [];

        if ($serverKey === '') {
            $problems[] = 'MIDTRANS_SERVER_KEY kosong.';
        }

        if ($clientKey === '') {
            $problems[] = 'MIDTRANS_CLIENT_KEY kosong.';
        }

        $serverIsSandbox = str_starts_with($serverKey, 'SB-Mid-server-');
        $serverIsProduction = str_starts_with($serverKey, 'Mid-server-');
        $clientIsSandbox = str_starts_with($clientKey, 'SB-Mid-client-');
        $clientIsProduction = str_starts_with($clientKey, 'Mid-client-');

        if ($isProduction) {
            if ($serverKey !== '' && ! $serverIsProduction) {
                $problems[] = 'Mode produksi menyala, tetapi Server Key bukan kunci produksi (harus berawalan Mid-server-).';
            }

            if ($clientKey !== '' && ! $clientIsProduction) {
                $problems[] = 'Mode produksi menyala, tetapi Client Key bukan kunci produksi (harus berawalan Mid-client-).';
            }

            if (! str_starts_with($appUrl, 'https://')) {
                $problems[] = 'APP_URL harus HTTPS di mode produksi agar URL notifikasi dan halaman selesai aman.';
            }
        } else {
            if ($serverKey !== '' && ! $serverIsSandbox) {
                $problems[] = 'Mode sandbox menyala, tetapi Server Key bukan kunci sandbox (harus berawalan SB-Mid-server-).';
            }

            if ($clientKey !== '' && ! $clientIsSandbox) {
                $problems[] = 'Mode sandbox menyala, tetapi Client Key bukan kunci sandbox (harus berawalan SB-Mid-client-).';
            }
        }

        if ($problems !== []) {
            foreach ($problems as $problem) {
                $this->components->error($problem);
            }

            return self::FAILURE;
        }

        // Jangan pernah mencetak kunci; cukup mode dan URL notifikasi.
        $this->components->info(sprintf(
            'Konfigurasi Midtrans konsisten: mode %s, notifikasi ke %s/webhooks/midtrans.',
            $isProduction ? 'produksi' : 'sandbox',
            rtrim($appUrl, '/'),
        ));

        return self::SUCCESS;
    }
}
