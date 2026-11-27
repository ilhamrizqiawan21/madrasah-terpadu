<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAppearanceSettingsRequest;
use App\Models\User;
use App\Services\Settings\IdentitySettingsService;
use App\Services\Settings\SettingsRepository;
use App\Support\Branding;
use App\Support\ThemeColor;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengaturan > Tampilan (logo, favicon, dan warna utama, T-201/T-208).
 */
class AppearanceSettingController extends Controller
{
    public function edit(SettingsRepository $settings): Response
    {
        $identity = $settings->identity();

        return Inertia::render('admin/settings/appearance', [
            'appearance' => [
                'logo_url' => Branding::assetUrl('logo', $identity['logo_path']),
                'favicon_url' => Branding::assetUrl('favicon', $identity['favicon_path']),
                'primary_color' => $identity['primary_color'],
                'default_color' => ThemeColor::DEFAULT_HEX,
            ],
        ]);
    }

    public function update(UpdateAppearanceSettingsRequest $request, IdentitySettingsService $service): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $service->updateAssets(
            logo: $request->uploadedFile('logo'),
            favicon: $request->uploadedFile('favicon'),
            removeLogo: $request->boolean('remove_logo'),
            removeFavicon: $request->boolean('remove_favicon'),
            actor: $actor,
            primaryColor: $request->string('primary_color')->toString() ?: null,
            resetColor: $request->boolean('reset_color'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tampilan berhasil disimpan.']);

        return back();
    }
}
