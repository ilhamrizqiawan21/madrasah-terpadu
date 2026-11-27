<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Http\Controllers\Admin\FeeTypeController;
use Illuminate\Support\Facades\Route;

Route::prefix('keuangan')
    ->middleware('feature:'.Feature::Billing->value)
    ->group(function (): void {
        Route::controller(FeeTypeController::class)->group(function (): void {
            Route::get('jenis-tagihan', 'index')->name('fee-types.index');
            Route::post('jenis-tagihan', 'store')->name('fee-types.store');
            Route::put('jenis-tagihan/{feeType}', 'update')->name('fee-types.update');
            Route::patch('jenis-tagihan/{feeType}/toggle', 'toggle')->name('fee-types.toggle');
            Route::delete('jenis-tagihan/{feeType}', 'destroy')->name('fee-types.destroy');
        });
    });
