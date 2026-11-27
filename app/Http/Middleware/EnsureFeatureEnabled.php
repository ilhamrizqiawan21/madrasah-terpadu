<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Feature;
use App\Services\Features\FeatureFlags;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `feature:<key>` — modul nonaktif diperlakukan seolah tidak ada (404), bukan 403,
 * agar tidak menjadi navigasi mati (ADR-022). Pasang setelah middleware izin.
 */
final class EnsureFeatureEnabled
{
    public function __construct(private readonly FeatureFlags $flags) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        abort_unless($this->flags->enabled(Feature::from($feature)), 404);

        return $next($request);
    }
}
