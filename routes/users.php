<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\Admin\StaffUserController;
use App\Services\Users\StaffUserService;
use Illuminate\Support\Facades\Route;

/*
| Pengguna staf (T-114). Dimuat di dalam grup /admin pada routes/web.php.
| {staffUser} hanya menemukan akun staf: akun wali murni → 404 (dikelola di modul wali).
*/
Route::bind('staffUser', fn (string $value) => StaffUserService::staffQuery()
    ->whereKey((int) $value)
    ->firstOrFail());

Route::middleware('permission:'.Permission::UsersManage->value)
    ->prefix('pengguna')
    ->name('users.')
    ->controller(StaffUserController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::put('{staffUser}', 'update')->whereNumber('staffUser')->name('update');
        Route::patch('{staffUser}/status', 'updateStatus')->whereNumber('staffUser')->name('status.update');
        Route::post('{staffUser}/reset-kata-sandi', 'resetPassword')->whereNumber('staffUser')->name('password.reset');
        Route::delete('{staffUser}', 'destroy')->whereNumber('staffUser')->name('destroy');
        Route::post('hapus-terpilih', 'bulkDestroy')->name('bulk-destroy');
        Route::get('{staffUser}/foto', 'photo')->whereNumber('staffUser')->name('photo');
    });

// Peran & Hak Akses: izin per peran (alur aplikasi lama, kolom Lihat/Kelola).
Route::middleware('permission:'.Permission::UsersManage->value)
    ->prefix('hak-akses')
    ->name('roles.')
    ->controller(RolePermissionController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::put('{role}', 'update')->where('role', '[a-z_]+')->name('update');
    });
