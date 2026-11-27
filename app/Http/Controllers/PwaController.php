<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Settings\SettingsRepository;
use App\Support\Branding;
use App\Support\ThemeColor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Menyajikan manifest PWA dinamis dan service worker tanpa cache (T-304).
 */
class PwaController extends Controller
{
    public function manifest(SettingsRepository $settings): JsonResponse
    {
        $identity = $settings->identity();
        $appName = $identity['app_name'];
        $themeColor = $identity['primary_color'] ?? ThemeColor::DEFAULT_HEX;
        $iconSrc = Branding::assetUrl('logo', $identity['logo_path']) ?? '/apple-touch-icon.png';

        return response()->json([
            'name' => $appName,
            'short_name' => $appName,
            'lang' => 'id',
            'start_url' => '/wali',
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#F4F1EA',
            'theme_color' => $themeColor,
            'icons' => [
                [
                    'src' => $iconSrc,
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => $iconSrc,
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any maskable',
                ],
            ],
        ], 200, [
            'Content-Type' => 'application/manifest+json; charset=utf-8',
            'Cache-Control' => 'no-cache',
        ]);
    }

    public function serviceWorker(): Response
    {
        $path = public_path('sw.js');
        $content = is_file($path) ? (string) file_get_contents($path) : '';

        return response($content, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Service-Worker-Allowed' => '/',
        ]);
    }
}
