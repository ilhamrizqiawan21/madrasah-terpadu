<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        $cameraPolicy = $request->is('gerbang', 'gerbang/*', 'kantin', 'kantin/*')
            ? 'camera=(self)'
            : 'camera=()';
        $response->headers->set('Permissions-Policy', "{$cameraPolicy}, microphone=(), geolocation=()");

        $response->headers->set('Content-Security-Policy', $this->buildCsp());

        return $response;
    }

    private function buildCsp(): string
    {
        $isLocal = app()->isLocal();
        $viteHttp = $isLocal ? ' http://localhost:5173 http://127.0.0.1:5173' : '';
        $viteWs = $isLocal ? ' ws://localhost:5173 ws://127.0.0.1:5173' : '';

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' https://app.midtrans.com https://app.sandbox.midtrans.com{$viteHttp}",
            "style-src 'self' 'unsafe-inline'{$viteHttp}",
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            "connect-src 'self' https://api.midtrans.com https://api.sandbox.midtrans.com{$viteHttp}{$viteWs}",
            "frame-src 'self' https://app.midtrans.com https://app.sandbox.midtrans.com",
            "object-src 'none'",
            "base-uri 'self'",
            "frame-ancestors 'none'",
        ]);
    }
}
