<?php

declare(strict_types=1);

test('manifest PWA disajikan dengan start_url /wali dan ikon 192/512', function (): void {
    $response = $this->get(route('pwa.manifest'));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json; charset=utf-8')
        ->assertJsonPath('start_url', '/wali')
        ->assertJsonPath('display', 'standalone')
        ->assertJsonCount(2, 'icons');
});

test('service worker disajikan tanpa cache', function (): void {
    $response = $this->get(route('pwa.sw'));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/javascript; charset=utf-8')
        ->assertSee('OFFLINE_URL');

    $cacheControl = (string) $response->headers->get('Cache-Control');
    expect($cacheControl)->toContain('no-cache')
        ->and($cacheControl)->toContain('no-store');
});
