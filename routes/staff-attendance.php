<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\Permission;
use App\Http\Controllers\Admin\StaffAttendanceController;
use App\Http\Controllers\Admin\StaffLeaveRequestController;
use Illuminate\Support\Facades\Route;

/*
| Absensi & Izin Pegawai (T-1601). Dimuat di dalam grup /admin pada routes/web.php.
*/
Route::middleware('feature:'.Feature::StaffAttendance->value)
    ->prefix('absensi')
    ->group(function (): void {
        Route::middleware('permission:'.Permission::UsersManage->value)
            ->controller(StaffAttendanceController::class)
            ->group(function (): void {
                Route::get('pegawai', 'index')->name('staff-attendance.index');
                Route::post('pegawai', 'store')->name('staff-attendance.store');
            });

        Route::controller(StaffLeaveRequestController::class)->group(function (): void {
            Route::get('izin-pegawai', 'index')->name('staff-leaves.index');
            Route::post('izin-pegawai', 'store')->name('staff-leaves.store');
            Route::patch('izin-pegawai/{leave}/keputusan', 'decide')
                ->whereNumber('leave')
                ->name('staff-leaves.decide');
        });
    });
