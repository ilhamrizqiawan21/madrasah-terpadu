<?php

declare(strict_types=1);

namespace App\Support;

final class Branding
{
    /**
     * URL publik logo/favicon. Parameter `v` berubah setiap berkas diganti,
     * sehingga cache browser yang panjang tetap aman.
     */
    public static function assetUrl(string $asset, ?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        return route('branding.show', [
            'asset' => $asset,
            'v' => substr(hash('sha256', $path), 0, 10),
        ], absolute: false);
    }
}
