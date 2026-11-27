<?php

declare(strict_types=1);

test('halaman offline statis tersedia dalam Bahasa Indonesia tanpa CDN eksternal', function (): void {
    $path = public_path('offline.html');

    expect(is_file($path))->toBeTrue();

    $html = (string) file_get_contents($path);

    expect($html)->toContain('Tidak ada koneksi internet')
        ->and($html)->toContain('Coba lagi')
        ->and($html)->not->toContain('https://')
        ->and($html)->not->toContain('http://');
});
