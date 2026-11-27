<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\Permission;
use App\Http\Controllers\Admin\MemorizationController;
use App\Http\Controllers\Admin\MemorizationMaterialController;
use App\Http\Controllers\Admin\MemorizationTargetController;
use App\Http\Controllers\Admin\PibController;
use App\Http\Controllers\Admin\RubricController;
use Illuminate\Support\Facades\Route;

Route::prefix('hafalan')
    ->name('quran.')
    ->middleware('feature:'.Feature::Quran->value)
    ->group(function (): void {
        Route::get('/', [MemorizationController::class, 'index'])
            ->middleware('permission:'.Permission::QuranView->value)
            ->name('index');
        Route::post('/', [MemorizationController::class, 'store'])
            ->middleware('permission:'.Permission::QuranRecord->value)
            ->name('store');

        Route::prefix('materi')
            ->name('materials.')
            ->middleware('permission:'.Permission::QuranManage->value)
            ->controller(MemorizationMaterialController::class)
            ->group(function (): void {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::put('{material}', 'update')->whereNumber('material')->name('update');
                Route::patch('{material}/status', 'toggle')->whereNumber('material')->name('toggle');
                Route::delete('{material}', 'destroy')->whereNumber('material')->name('destroy');
            });

        Route::prefix('target')
            ->name('targets.')
            ->middleware('permission:'.Permission::QuranManage->value)
            ->controller(MemorizationTargetController::class)
            ->group(function (): void {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::put('{target}', 'update')->whereNumber('target')->name('update');
                Route::patch('{target}/status', 'toggle')->whereNumber('target')->name('toggle');
            });

        Route::prefix('rubrik')
            ->name('rubrics.')
            ->middleware('permission:'.Permission::QuranManage->value)
            ->controller(RubricController::class)
            ->group(function (): void {
                Route::get('/', 'index')->name('index');
                Route::post('{version}/terbitkan', 'publish')->whereNumber('version')->name('publish');
                Route::post('{rubric}/draf', 'createDraft')->whereNumber('rubric')->name('draft');
            });

        Route::prefix('pib')
            ->name('pib.')
            ->controller(PibController::class)
            ->group(function (): void {
                Route::get('/', 'index')
                    ->middleware('permission:'.Permission::QuranView->value)
                    ->name('index');
                Route::post('bab', 'storeChapter')
                    ->middleware('permission:'.Permission::QuranManage->value)
                    ->name('chapters.store');
                Route::post('subbab', 'storeSubchapter')
                    ->middleware('permission:'.Permission::QuranManage->value)
                    ->name('subchapters.store');
                Route::post('materi', 'storeMaterial')
                    ->middleware('permission:'.Permission::QuranManage->value)
                    ->name('materials.store');
                Route::post('nilai', 'storeScore')
                    ->middleware('permission:'.Permission::QuranRecord->value)
                    ->name('scores.store');
            });
    });
