<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\Permission;
use App\Http\Controllers\Admin\CharacterAssessmentController;
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

Route::middleware(['feature:'.Feature::Attitude->value, 'permission:'.Permission::AttitudeView->value])
    ->prefix('kesiswaan/penilaian-karakter')
    ->name('character.')
    ->controller(CharacterAssessmentController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('{classroom}', 'show')->whereNumber('classroom')->name('show');
        Route::post('{classroom}/hitung', 'compute')->whereNumber('classroom')->name('compute');
        Route::post('{classroom}/ajukan', 'submit')->whereNumber('classroom')->name('submit');
        Route::post('{classroom}/setujui', 'approve')->whereNumber('classroom')->name('approve');
        Route::get('{classroom}/pdf', 'pdf')->whereNumber('classroom')->name('pdf');
        Route::get('{classroom}/excel', 'excel')->whereNumber('classroom')->name('excel');
        Route::post('{classroom}/buka-kunci', 'reopen')->whereNumber('classroom')->name('reopen');
        Route::patch('nilai/{assessment}', 'correct')->whereNumber('assessment')->name('correct');
    });
