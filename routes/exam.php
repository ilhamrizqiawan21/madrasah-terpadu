<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Http\Controllers\Exam\ExamAuthController;
use App\Http\Controllers\Exam\ExamDashboardController;
use App\Http\Controllers\Exam\ExamQuestionImageController;
use App\Http\Controllers\Exam\ExamSessionController;
use Illuminate\Support\Facades\Route;

/*
| Area Ujian CBT Siswa (T-1502 s.d. T-1505). Guard `student` terpisah dari `web`.
*/
Route::middleware('feature:'.Feature::Cbt->value)
    ->prefix('ujian')
    ->name('exam.')
    ->group(function (): void {
        Route::get('masuk', [ExamAuthController::class, 'create'])->name('login');
        Route::post('masuk', [ExamAuthController::class, 'store'])->name('login.store');

        Route::middleware(['auth:student', 'exam.active'])->group(function (): void {
            Route::get('/', ExamDashboardController::class)->name('index');
            Route::post('keluar', [ExamAuthController::class, 'destroy'])->name('logout');
            Route::get('{exam}/soal/{question}/gambar', ExamQuestionImageController::class)
                ->whereNumber(['exam', 'question'])
                ->name('questions.image');

            Route::post('{exam}/mulai', [ExamSessionController::class, 'start'])
                ->whereNumber('exam')
                ->name('sessions.start');
            Route::get('{exam}/kerjakan', [ExamSessionController::class, 'show'])
                ->whereNumber('exam')
                ->name('sessions.show');
            Route::post('{exam}/jawaban', [ExamSessionController::class, 'syncAnswers'])
                ->whereNumber('exam')
                ->name('sessions.answers');
            Route::post('{exam}/pelanggaran', [ExamSessionController::class, 'recordViolation'])
                ->whereNumber('exam')
                ->name('sessions.violations');
            Route::post('{exam}/selesai', [ExamSessionController::class, 'submit'])
                ->whereNumber('exam')
                ->name('sessions.submit');
        });
    });
