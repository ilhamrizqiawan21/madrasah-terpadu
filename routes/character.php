<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\Permission;
use App\Http\Controllers\Admin\CharacterSettingController;
use Illuminate\Support\Facades\Route;

/*
| Penilaian karakter (Fase 12, ADR-029). Dimuat di dalam grup /admin pada routes/web.php.
*/
Route::middleware(['feature:'.Feature::Attitude->value, 'permission:'.Permission::SettingsManage->value])
    ->prefix('pengaturan/penilaian-karakter')
    ->name('settings.character.')
    ->controller(CharacterSettingController::class)
    ->group(function (): void {
        Route::get('/', 'edit')->name('edit');
        Route::post('{unit}/kurikulum', 'applyCurriculum')->whereNumber('unit')->name('curriculum');
        Route::put('{unit}/skala', 'updateScale')->whereNumber('unit')->name('scale');
        Route::post('{unit}/dimensi', 'storeDimension')->whereNumber('unit')->name('dimensions.store');
        Route::put('{unit}/dimensi/{dimension}', 'updateDimension')->whereNumber(['unit', 'dimension'])->name('dimensions.update');
    });
