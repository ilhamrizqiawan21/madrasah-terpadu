<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\Permission;
use App\Http\Controllers\Admin\AttendanceReportController;
use App\Http\Controllers\Admin\BehaviorReportController;
use App\Http\Controllers\Admin\QuranReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('laporan')
    ->name('reports.')
    ->middleware('permission:'.Permission::ReportsView->value)
    ->group(function (): void {
        Route::middleware('feature:'.Feature::Attendance->value)->group(function (): void {
            Route::get('/', [AttendanceReportController::class, 'index'])->name('index');
            Route::get('presensi/pdf', [AttendanceReportController::class, 'exportPdf'])->name('attendance.pdf');
            Route::get('presensi/excel', [AttendanceReportController::class, 'exportExcel'])->name('attendance.excel');
        });

        Route::middleware('feature:'.Feature::Behavior->value)->group(function (): void {
            Route::get('bk', [BehaviorReportController::class, 'index'])->name('behavior.index');
            Route::get('bk/pdf', [BehaviorReportController::class, 'exportPdf'])->name('behavior.pdf');
            Route::get('bk/excel', [BehaviorReportController::class, 'exportExcel'])->name('behavior.excel');
        });

        Route::middleware('feature:'.Feature::Quran->value)->group(function (): void {
            Route::get('hafalan', [QuranReportController::class, 'index'])->name('quran.index');
            Route::get('hafalan/pdf', [QuranReportController::class, 'exportPdf'])->name('quran.pdf');
            Route::get('hafalan/excel', [QuranReportController::class, 'exportExcel'])->name('quran.excel');
        });
    });
