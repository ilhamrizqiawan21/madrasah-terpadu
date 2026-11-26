<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;

it('memakai zona waktu WITA dan bahasa Indonesia', function (): void {
    expect(config('app.timezone'))->toBe('Asia/Makassar')
        ->and(config('app.locale'))->toBe('id');
});

it('mengaktifkan mode ketat model di luar produksi', function (): void {
    expect(Model::preventsLazyLoading())->toBeTrue();
});

it('menjaga batas dompet tetap masuk akal', function (): void {
    $wallet = config('sekolah.wallet');

    expect($wallet['default_daily_limit'])->toBeLessThanOrEqual($wallet['max_daily_limit'])
        ->and($wallet['min_top_up'])->toBeLessThan($wallet['max_top_up']);
});

it('selalu mengaktifkan notifikasi tagihan dan akun', function (): void {
    expect(config('sekolah.notifications.always_on'))
        ->toBe(['billing', 'account'])
        ->each->toBeIn(config('sekolah.notifications.categories'));
});

it('memakai mode sandbox Midtrans secara default', function (): void {
    expect(config('services.midtrans.is_production'))->toBeFalse();
});
