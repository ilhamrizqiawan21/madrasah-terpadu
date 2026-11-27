<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateIdentitySettingsRequest;
use App\Models\User;
use App\Services\Settings\IdentitySettingsService;
use App\Services\Settings\SettingsRepository;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class IdentitySettingController extends Controller
{
    public function edit(SettingsRepository $settings): Response
    {
        $identity = $settings->identity();

        return Inertia::render('admin/settings/identity', [
            'identity' => [
                'app_name' => $identity['app_name'],
                'foundation_name' => $identity['foundation_name'],
                'address' => $identity['address'],
                'phone' => $identity['phone'],
                'email' => $identity['email'],
            ],
        ]);
    }

    public function update(UpdateIdentitySettingsRequest $request, IdentitySettingsService $service): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $service->updateIdentity($request->identityData(), $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Identitas sekolah berhasil disimpan.']);

        return back();
    }
}
