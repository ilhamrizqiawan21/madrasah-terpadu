<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Http\Controllers\Admin\StudentController;
use Illuminate\Support\Facades\Route;

/*
| Siswa + enrollment (T-205). Dimuat di dalam grup /admin pada routes/web.php.
| Lihat: students.view; tulis: students.manage. Tidak ada rute hapus: siswa dinonaktifkan.
*/
Route::prefix('siswa')
    ->name('students.')
    ->controller(StudentController::class)
    ->group(function (): void {
        Route::middleware('permission:'.Permission::StudentsView->value)->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('{student}/foto', 'photo')->whereNumber('student')->name('photo');
        });

        Route::middleware('permission:'.Permission::StudentsManage->value)->group(function (): void {
            Route::post('/', 'store')->name('store');
            Route::put('{student}', 'update')->whereNumber('student')->name('update');
            Route::patch('{student}/status', 'updateStatus')->whereNumber('student')->name('status.update');
        });
    });
