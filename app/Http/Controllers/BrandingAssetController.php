<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Settings\IdentitySettingsService;
use App\Services\Settings\SettingsRepository;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Menyajikan logo/favicon dari disk privat tanpa autentikasi: halaman login
 * dan tab browser membutuhkannya sebelum pengguna masuk.
 */
class BrandingAssetController extends Controller
{
    public function __invoke(string $asset, SettingsRepository $settings): StreamedResponse
    {
        $identity = $settings->identity();
        $path = $asset === 'logo' ? $identity['logo_path'] : $identity['favicon_path'];
        $disk = Storage::disk(IdentitySettingsService::DISK);

        abort_if($path === null || ! $disk->exists($path), 404);

        return $disk->response($path, null, [
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
