<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\Permission;
use App\Http\Controllers\Admin\SchoolCalendarController;
use Illuminate\Support\Facades\Route;

/*
| Modul Presensi staf & kalender sekolah (T-505+).
| Dimuat di dalam grup /admin pada routes/web.php.
*/
Route::prefix('presensi')
    ->name('attendance.')
    ->middleware('feature:'.Feature::Attendance->value)
    ->group(function (): void {
        Route::inertia('/', 'admin/dashboard')
            ->middleware('permission:'.Permission::AttendanceView->value)
            ->name('index');

        Route::prefix('kalender')
            ->name('calendar.')
            ->controller(SchoolCalendarController::class)
            ->group(function (): void {
                Route::get('/', 'index')
                    ->middleware('permission:'.Permission::AttendanceView->value)
                    ->name('index');

                Route::middleware('permission:'.Permission::AttendanceManage->value)->group(function (): void {
                    Route::post('/', 'store')->name('store');
                    Route::put('akhir-pekan', 'updateWeekend')->name('weekend.update');
                    Route::delete('{holiday}', 'destroy')->whereNumber('holiday')->name('destroy');
                });
            });
    });
