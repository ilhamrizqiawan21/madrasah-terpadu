<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Student;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memastikan rute `/wali/anak/{student}/...` hanya dapat dibuka oleh wali yang terhubung
 * dengan siswa tersebut. Anak milik wali lain menjawab 404 (bukan 403) agar tidak
 * membocorkan keberadaan data siswa (T-302).
 */
class EnsureGuardianOwnsStudent
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $param = $request->route('student');

        $studentId = match (true) {
            $param instanceof Student => $param->id,
            is_numeric($param) => (int) $param,
            default => null,
        };

        if (
            ! $user instanceof User
            || $studentId === null
            || $user->guardian?->students()->whereKey($studentId)->exists() !== true
        ) {
            abort(404);
        }

        return $next($request);
    }
}
