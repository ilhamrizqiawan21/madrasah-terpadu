<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

final class EnsureTwoFactorForPrivilegedRoles
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('sekolah.security.require_two_factor')) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        if (! $user->hasAnyRole([Role::Admin->value, Role::Treasurer->value])) {
            return $next($request);
        }

        if ($user->two_factor_confirmed_at !== null) {
            return $next($request);
        }

        if ($request->routeIs(
            'security.edit',
            'user-password.update',
            'password.confirm',
            'password.confirmation',
            'two-factor.*',
            'logout',
        )) {
            return $next($request);
        }

        Inertia::flash('toast', [
            'type' => 'warning',
            'message' => 'Autentikasi dua faktor wajib diaktifkan untuk akun Admin dan Bendahara.',
        ]);

        return redirect()->route('security.edit');
    }
}
