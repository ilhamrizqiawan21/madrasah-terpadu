<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Services\Settings\SettingsRepository;
use App\Support\Branding;
use App\Support\GuardianContext;
use App\Support\Navigation;
use App\Support\SchoolContext;
use App\Support\StaffAttention;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'branding' => function (): array {
                $identity = app(SettingsRepository::class)->identity();

                return [
                    'appName' => $identity['app_name'],
                    'foundationName' => $identity['foundation_name'],
                    'logoUrl' => Branding::assetUrl('logo', $identity['logo_path']),
                    'primaryColor' => $identity['primary_color'],
                ];
            },
            'auth' => [
                'user' => $request->user('web'),
            ],
            'navigation' => fn (): array => Navigation::forUser($request->user('web')),
            // Hanya staf yang boleh masuk grup /admin yang memakai pemilih unit/tahun ajaran.
            'schoolContext' => fn (): ?array => $request->user('web')?->hasAnyRole(array_map(fn (Role $role): string => $role->value, Role::adminArea()))
                ? SchoolContext::resolve($request->session())
                : null,
            'attention' => fn (): array => StaffAttention::forRequest($request),
            'guardianContext' => fn (): ?array => $request->user('web')?->hasRole(Role::Guardian->value)
                ? GuardianContext::resolve($request->user('web'), $request->session())
                : null,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
