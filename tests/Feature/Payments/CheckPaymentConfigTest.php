<?php

declare(strict_types=1);

/**
 * @param  array<string, mixed>  $overrides
 */
function configureMidtrans(array $overrides): void
{
    config(array_merge([
        'services.midtrans.is_production' => false,
        'services.midtrans.server_key' => 'SB-Mid-server-abc',
        'services.midtrans.client_key' => 'SB-Mid-client-abc',
        'app.url' => 'http://localhost',
    ], $overrides));
}

it('lulus untuk sandbox dengan kunci sandbox tanpa mencetak kunci', function (): void {
    configureMidtrans([]);

    $this->artisan('payments:check-config')
        ->expectsOutputToContain('mode sandbox')
        ->doesntExpectOutputToContain('SB-Mid-server-abc')
        ->assertSuccessful();
});

it('lulus untuk produksi dengan kunci produksi dan APP_URL HTTPS', function (): void {
    configureMidtrans([
        'services.midtrans.is_production' => true,
        'services.midtrans.server_key' => 'Mid-server-xyz',
        'services.midtrans.client_key' => 'Mid-client-xyz',
        'app.url' => 'https://madrasah.example',
    ]);

    $this->artisan('payments:check-config')
        ->expectsOutputToContain('mode produksi')
        ->assertSuccessful();
});

it('gagal bila mode produksi memakai kunci sandbox', function (): void {
    configureMidtrans([
        'services.midtrans.is_production' => true,
        'app.url' => 'https://madrasah.example',
    ]);

    $this->artisan('payments:check-config')
        ->expectsOutputToContain('Server Key bukan kunci produksi')
        ->assertFailed();
});

it('gagal bila mode sandbox memakai kunci produksi', function (): void {
    configureMidtrans([
        'services.midtrans.server_key' => 'Mid-server-xyz',
        'services.midtrans.client_key' => 'Mid-client-xyz',
    ]);

    $this->artisan('payments:check-config')
        ->expectsOutputToContain('Server Key bukan kunci sandbox')
        ->assertFailed();
});

it('gagal bila kunci kosong', function (): void {
    configureMidtrans([
        'services.midtrans.server_key' => '',
        'services.midtrans.client_key' => '',
    ]);

    $this->artisan('payments:check-config')
        ->expectsOutputToContain('MIDTRANS_SERVER_KEY kosong')
        ->assertFailed();
});

it('gagal bila APP_URL bukan HTTPS di mode produksi', function (): void {
    configureMidtrans([
        'services.midtrans.is_production' => true,
        'services.midtrans.server_key' => 'Mid-server-xyz',
        'services.midtrans.client_key' => 'Mid-client-xyz',
        'app.url' => 'http://madrasah.example',
    ]);

    $this->artisan('payments:check-config')
        ->expectsOutputToContain('APP_URL harus HTTPS')
        ->assertFailed();
});
