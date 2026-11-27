<?php

declare(strict_types=1);

namespace App\Http\Controllers\Exam;

use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Exam\ExamLoginRequest;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ExamAuthController extends Controller
{
    public function create(): Response|RedirectResponse
    {
        if (Auth::guard('student')->check()) {
            return redirect()->route('exam.index');
        }

        return Inertia::render('exam/login');
    }

    public function store(ExamLoginRequest $request): RedirectResponse
    {
        $nis = (string) $request->validated('nis');
        $pin = (string) $request->validated('pin');
        $throttleKey = 'exam-login:'.mb_strtolower($nis);

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'nis' => "Terlalu banyak percobaan masuk untuk NIS ini. Coba lagi dalam {$seconds} detik.",
            ]);
        }

        $student = Student::query()
            ->with('examPin')
            ->where('nis', $nis)
            ->where('status', StudentStatus::Active)
            ->first();

        if (
            $student === null
            || $student->examPin === null
            || ! Hash::check($pin, $student->examPin->pin_hash)
        ) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'nis' => 'NIS atau PIN ujian tidak sesuai.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        Auth::guard('student')->login($student);
        $request->session()->regenerate();

        return redirect()->route('exam.index');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('student')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('exam.login');
    }
}
