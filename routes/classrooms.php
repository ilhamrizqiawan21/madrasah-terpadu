<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Http\Controllers\Admin\ClassroomController;
use Illuminate\Support\Facades\Route;

/*
| Rombel (T-204). Dimuat di dalam grup /admin pada routes/web.php.
*/
Route::middleware('permission:'.Permission::ClassroomsManage->value)
    ->prefix('rombel')
    ->name('classrooms.')
    ->controller(ClassroomController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::post('salin', 'copy')->name('copy');
        Route::put('{classroom}', 'update')->whereNumber('classroom')->name('update');
        Route::patch('{classroom}/status', 'updateStatus')->whereNumber('classroom')->name('status.update');
    });
