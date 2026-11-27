<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\StudentStatus;
use App\Models\Student;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnsureActiveStudent
{
    public function handle(Request $request, Closure $next): Response
    {
        $student = Auth::guard('student')->user();

        if ($student instanceof Student && $student->status !== StudentStatus::Active) {
            Auth::guard('student')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                abort(403, 'Status siswa tidak aktif.');
            }

            return redirect()
                ->route('exam.login')
                ->withErrors(['nis' => 'NIS atau PIN ujian tidak sesuai.']);
        }

        return $next($request);
    }
}
