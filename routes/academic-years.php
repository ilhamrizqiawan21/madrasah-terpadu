<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Http\Controllers\Admin\AcademicYearController;
use Illuminate\Support\Facades\Route;

/*
| Tahun ajaran & semester (T-203). Dimuat di dalam grup /admin pada routes/web.php.
*/
Route::middleware('permission:'.Permission::AcademicYearsManage->value)
    ->prefix('tahun-ajaran')
    ->name('academic-years.')
    ->controller(AcademicYearController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::put('{academicYear}', 'update')->whereNumber('academicYear')->name('update');
        Route::post('{academicYear}/aktifkan', 'activate')->whereNumber('academicYear')->name('activate');
        Route::delete('{academicYear}', 'destroy')->whereNumber('academicYear')->name('destroy');
    });
