<?php

use App\Http\Middleware\EnsureActiveStudent;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureFeatureEnabled;
use App\Http\Middleware\EnsureGuardianOwnsStudent;
use App\Http\Middleware\EnsureTwoFactorForPrivilegedRoles;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Midtrans tidak membawa token CSRF; keasliannya diperiksa lewat signature (PAYMENTS §4).
        $middleware->validateCsrfTokens(except: ['webhooks/midtrans']);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'feature' => EnsureFeatureEnabled::class,
            'guardian.owns-student' => EnsureGuardianOwnsStudent::class,
            'exam.active' => EnsureActiveStudent::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request): string {
            if ($request->is('ujian') || $request->is('ujian/*')) {
                return route('exam.login');
            }

            return route('login');
        });

        $middleware->web(append: [
            SecurityHeaders::class,
            HandleAppearance::class,
            EnsureActiveUser::class,
            EnsureTwoFactorForPrivilegedRoles::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Halaman galat berbahasa Indonesia untuk permintaan halaman (bukan JSON). Galat 500 tetap
        // memakai halaman debug pengembang saat APP_DEBUG=true.
        $exceptions->respond(function (SymfonyResponse $response, Throwable $e, Request $request): SymfonyResponse {
            $status = $response->getStatusCode();
            $handled = [403, 404, 419, 429, 500, 503];

            if ($request->expectsJson() || ! in_array($status, $handled, true) || ($status === 500 && config('app.debug'))) {
                return $response;
            }

            return Inertia::render('error', ['status' => $status])
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();
