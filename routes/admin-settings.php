<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Http\Controllers\Admin\AppearanceSettingController;
use App\Http\Controllers\Admin\FeatureFlagController;
use App\Http\Controllers\Admin\IdentitySettingController;
use App\Http\Controllers\Admin\UnitController;
use Illuminate\Support\Facades\Route;

/*
| Pengaturan sekolah (Fase 2). Dimuat di dalam grup /admin pada routes/web.php.
*/
Route::middleware('permission:'.Permission::SettingsManage->value)
    ->prefix('pengaturan')
    ->name('settings.')
    ->group(function (): void {
        Route::redirect('/', '/admin/pengaturan/identitas')->name('index');

        Route::get('identitas', [IdentitySettingController::class, 'edit'])->name('identity.edit');
        Route::put('identitas', [IdentitySettingController::class, 'update'])->name('identity.update');

        Route::get('tampilan', [AppearanceSettingController::class, 'edit'])->name('appearance.edit');
        // POST (bukan PUT) karena form mengirim berkas sebagai multipart.
        Route::post('tampilan', [AppearanceSettingController::class, 'update'])->name('appearance.update');

        Route::get('kunci-fitur', [FeatureFlagController::class, 'edit'])->name('features.edit');
        Route::put('kunci-fitur', [FeatureFlagController::class, 'update'])->name('features.update');
    });

Route::middleware('permission:'.Permission::UnitsManage->value)
    ->prefix('pengaturan/unit')
    ->name('settings.units.')
    ->controller(UnitController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::put('{unit}', 'update')->whereNumber('unit')->name('update');
        Route::patch('{unit}/status', 'updateStatus')->whereNumber('unit')->name('status.update');
    });
