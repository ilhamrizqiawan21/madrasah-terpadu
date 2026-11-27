<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\StudentImportController;
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

/*
| Impor Excel siswa + wali (T-207). Bisa membuat akun wali, jadi butuh students.manage dan guardians.manage.
| Berkas unggahan dikenal lewat sesi, bukan id di URL.
*/
Route::prefix('siswa/impor')
    ->name('students.import.')
    ->controller(StudentImportController::class)
    ->middleware([
        'permission:'.Permission::StudentsManage->value,
        'permission:'.Permission::GuardiansManage->value,
    ])
    ->group(function (): void {
        Route::get('/', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::delete('/', 'destroy')->name('destroy');
        Route::get('template', 'template')->name('template');
        Route::get('pratinjau', 'preview')->name('preview');
        Route::post('simpan', 'commit')->name('commit');
        Route::get('hasil', 'result')->name('result');
    });
