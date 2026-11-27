<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\Permission;
use App\Http\Controllers\Admin\AttendanceReportController;
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
    });
