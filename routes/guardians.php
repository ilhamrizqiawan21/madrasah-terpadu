<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Http\Controllers\Admin\GuardianController;
use Illuminate\Support\Facades\Route;

/*
| Wali + hubungan anak (T-206). Dimuat di dalam grup /admin pada routes/web.php.
| Semua aksi: guardians.manage. Tidak ada rute hapus: akun wali dinonaktifkan.
*/
Route::prefix('wali')
    ->name('guardians.')
    ->controller(GuardianController::class)
    ->middleware('permission:'.Permission::GuardiansManage->value)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('cari-siswa', 'searchStudents')->name('students.search');
        Route::post('/', 'store')->name('store');
        Route::put('{guardian}', 'update')->whereNumber('guardian')->name('update');
        Route::patch('{guardian}/status', 'updateStatus')->whereNumber('guardian')->name('status.update');
        Route::post('{guardian}/reset-kata-sandi', 'resetPassword')->whereNumber('guardian')->name('password.reset');
    });
