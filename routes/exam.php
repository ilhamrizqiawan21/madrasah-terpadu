<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Http\Controllers\Exam\ExamAuthController;
use App\Http\Controllers\Exam\ExamDashboardController;
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
        });
    });
