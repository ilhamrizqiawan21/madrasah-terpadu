<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Navigation;
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
            'branding' => fn (): array => [
                'appName' => (string) config('app.name'),
                'foundationName' => (string) config('sekolah.foundation_name', 'Yayasan Amal Jaya Berkah'),
                'logoUrl' => null,
            ],
            'auth' => [
                'user' => $request->user(),
            ],
            'navigation' => fn (): array => Navigation::forUser($request->user()),
            'schoolContext' => fn (): array => [
                'unit' => (string) $request->session()->get('active_unit', 'MI'),
                'academicYear' => (string) $request->session()->get('active_academic_year', '2026/2027'),
                'units' => ['MI', 'RA', 'TPQ'],
                'academicYears' => ['2026/2027', '2025/2026'],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
