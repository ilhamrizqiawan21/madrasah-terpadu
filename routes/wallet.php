<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\Permission;
use App\Http\Controllers\Admin\CanteenCatalogController;
use App\Http\Controllers\Admin\CanteenReportController;
use App\Http\Controllers\Admin\WalletController;
use App\Http\Controllers\Admin\WalletTopupHistoryController;
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

// Riwayat Isi Saldo: lihat & rekap (petugas isi saldo), batalkan setoran tunai (bendahara).
Route::prefix('kantin/riwayat-isi-saldo')
    ->controller(WalletTopupHistoryController::class)
    ->middleware('feature:'.Feature::Wallet->value)
    ->group(function (): void {
        Route::middleware('permission:'.Permission::WalletTopup->value.'|'.Permission::WalletManage->value)->group(function (): void {
            Route::get('/', 'index')->name('wallet-topups.index');
            Route::get('pdf', 'exportPdf')->name('wallet-topups.pdf');
            Route::get('excel', 'exportExcel')->name('wallet-topups.excel');
        });
        Route::post('{entry}/batal', 'cancel')->whereNumber('entry')
            ->middleware('permission:'.Permission::WalletManage->value)->name('wallet-topups.cancel');
    });

// Kategori Menu & Daftar Menu kantin.
Route::prefix('kantin')
    ->controller(CanteenCatalogController::class)
    ->middleware(['permission:'.Permission::WalletManage->value, 'feature:'.Feature::Wallet->value])
    ->group(function (): void {
        Route::get('kategori-menu', 'categories')->name('canteen-categories.index');
        Route::post('kategori-menu', 'storeCategory')->name('canteen-categories.store');
        Route::put('kategori-menu/{category}', 'updateCategory')->whereNumber('category')->name('canteen-categories.update');
        Route::delete('kategori-menu/{category}', 'destroyCategory')->whereNumber('category')->name('canteen-categories.destroy');

        Route::get('menu', 'products')->name('canteen-products.index');
        Route::post('menu', 'storeProduct')->name('canteen-products.store');
        Route::put('menu/{product}', 'updateProduct')->whereNumber('product')->name('canteen-products.update');
        Route::delete('menu/{product}', 'destroyProduct')->whereNumber('product')->name('canteen-products.destroy');
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

require __DIR__.'/accounting.php';
