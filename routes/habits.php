<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\Permission;
use App\Http\Controllers\Admin\HabitItemController;
use Illuminate\Support\Facades\Route;

Route::prefix('pembiasaan')
    ->name('habits.')
    ->middleware('feature:'.Feature::Habits->value)
    ->group(function (): void {
        Route::prefix('item')
            ->name('items.')
            ->middleware('permission:'.Permission::HabitsManage->value)
            ->controller(HabitItemController::class)
            ->group(function (): void {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::put('{habitItem}', 'update')->whereNumber('habitItem')->name('update');
                Route::patch('{habitItem}/status', 'toggle')->whereNumber('habitItem')->name('toggle');
                Route::post('{habitItem}/urutan', 'move')->whereNumber('habitItem')->name('move');
                Route::delete('{habitItem}', 'destroy')->whereNumber('habitItem')->name('destroy');
            });
    });
