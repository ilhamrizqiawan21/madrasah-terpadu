<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\Permission;
use App\Http\Controllers\Admin\ComingSoonController;
use App\Http\Controllers\Admin\MemorizationMaterialController;
use Illuminate\Support\Facades\Route;

Route::prefix('hafalan')
    ->name('quran.')
    ->middleware('feature:'.Feature::Quran->value)
    ->group(function (): void {
        Route::get('/', ComingSoonController::class)
            ->defaults('module', 'setoran-hafalan')
            ->middleware('permission:'.Permission::QuranView->value)
            ->name('index');

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
    });
