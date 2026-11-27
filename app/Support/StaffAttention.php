<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Role;
use App\Models\Unit;
use App\Models\User;
use App\Services\Dashboard\StaffDashboardService;
use Illuminate\Http\Request;

/**
 * Prop bersama `attention` untuk lonceng topbar staf; kosong untuk tamu, wali, dan petugas kiosk.
 */
final class StaffAttention
{
    /**
     * @return list<array{key: string, label: string, count: int, href: string}>
     */
    public static function forRequest(Request $request): array
    {
        $user = $request->user('web');

        if (! $user instanceof User || ! $user->hasAnyRole(array_map(fn (Role $role): string => $role->value, Role::adminArea()))) {
            return [];
        }

        $context = SchoolContext::resolve($request->session());
        $unit = $context['unit'] !== null ? Unit::query()->where('code', $context['unit'])->first() : null;

        return app(StaffDashboardService::class)->attention($user, $unit, $context['academicYearId']);
    }
}
