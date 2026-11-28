<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\Permission;
use App\Http\Controllers\Admin\CanteenReportController;
use App\Http\Controllers\Admin\WalletController;
use Illuminate\Support\Facades\Route;

Route::prefix('kantin')->group(function (): void {
    Route::controller(WalletController::class)
        ->middleware(['permission:'.Permission::WalletManage->value, 'feature:'.Feature::Wallet->value])
        ->group(function (): void {
            Route::get('dompet', 'index')->name('wallets.index');
        });

    Route::controller(WalletController::class)
        ->middleware(['permission:'.Permission::WalletTopup->value, 'feature:'.Feature::Wallet->value])
        ->group(function (): void {
            Route::post('dompet/isi-saldo', 'topUp')->name('wallets.topup');
            Route::get('dompet/kuitansi/{entry}', 'receipt')->name('wallets.receipt');
        });
});

Route::prefix('laporan/kantin')
    ->name('reports.canteen.')
    ->controller(CanteenReportController::class)
    ->middleware(['permission:'.Permission::ReportsView->value, 'feature:'.Feature::Wallet->value])
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('pdf', 'exportPdf')->name('pdf');
        Route::get('excel', 'exportExcel')->name('excel');
    });
