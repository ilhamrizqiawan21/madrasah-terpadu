<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Http\Controllers\Admin\AlumniTracerController;
use App\Http\Controllers\Admin\StudentCardController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\StudentImportController;
use App\Http\Controllers\Admin\StudentMutationController;
use App\Http\Controllers\Admin\StudentProfileController;
use App\Http\Controllers\Admin\StudentPromotionController;
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
            Route::get('{student}', StudentProfileController::class)->whereNumber('student')->name('show');
        });

        Route::middleware('permission:'.Permission::StudentsManage->value)->group(function (): void {
            Route::post('/', 'store')->name('store');
            Route::put('{student}', 'update')->whereNumber('student')->name('update');
            Route::patch('{student}/status', 'updateStatus')->whereNumber('student')->name('status.update');

            Route::post('kartu/cetak', [StudentCardController::class, 'printClassroom'])
                ->name('cards.print-classroom');

            Route::prefix('{student}/kartu')
                ->whereNumber('student')
                ->name('cards.')
                ->controller(StudentCardController::class)
                ->group(function (): void {
                    Route::post('qr', 'issueQr')->name('qr.issue');
                    Route::post('rfid', 'registerRfid')->name('rfid.register');
                    Route::post('cetak', 'printSingle')->name('print');
                    Route::delete('/', 'revoke')->name('revoke');
                });
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

/*
| Kenaikan kelas massal & mutasi siswa (T-212).
*/
Route::middleware('permission:'.Permission::StudentsManage->value)->group(function (): void {
    Route::prefix('siswa/kenaikan-kelas')
        ->name('students.promotions.')
        ->controller(StudentPromotionController::class)
        ->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
        });

    Route::prefix('siswa/mutasi')
        ->name('students.mutations.')
        ->controller(StudentMutationController::class)
        ->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::post('masuk', 'storeIn')->name('store-in');
            Route::post('keluar', 'storeOut')->name('store-out');
        });
});

// Data & Survei Alumni (tracer study, alur aplikasi lama).
Route::middleware('permission:'.Permission::StudentsManage->value)
    ->prefix('alumni')
    ->name('alumni.')
    ->controller(AlumniTracerController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('survei', 'create')->name('create');
        Route::post('survei', 'store')->name('store');
        Route::delete('{response}', 'destroy')->whereNumber('response')->name('destroy');
    });
