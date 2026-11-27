<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Http\Controllers\Admin\AppearanceSettingController;
use App\Http\Controllers\Admin\IdentitySettingController;
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
    });
