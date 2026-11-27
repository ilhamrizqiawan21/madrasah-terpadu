<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\Permission;
use App\Http\Controllers\Admin\PointRuleController;
use Illuminate\Support\Facades\Route;

Route::prefix('perilaku')
    ->name('behavior.')
    ->middleware('feature:'.Feature::Behavior->value)
    ->group(function (): void {
        Route::inertia('/', 'admin/dashboard')
            ->middleware('permission:'.Permission::BehaviorView->value)
            ->name('index');

        Route::prefix('aturan')
            ->name('rules.')
            ->middleware('permission:'.Permission::PointRulesManage->value)
            ->controller(PointRuleController::class)
            ->group(function (): void {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::put('{pointRule}', 'update')->whereNumber('pointRule')->name('update');
                Route::patch('{pointRule}/status', 'toggle')->whereNumber('pointRule')->name('toggle');
                Route::post('impor', 'import')->name('import');
                Route::get('templat', 'template')->name('template');
            });
    });
