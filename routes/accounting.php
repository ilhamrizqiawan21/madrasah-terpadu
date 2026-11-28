<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\Permission;
use App\Http\Controllers\Admin\Accounting\CashAccountController;
use App\Http\Controllers\Admin\Accounting\CashBookController;
use App\Http\Controllers\Admin\Accounting\ExternalIncomeController;
use Illuminate\Support\Facades\Route;

// Akuntansi & Kas (ADR-030). Dimuat dari routes/wallet.php di dalam grup /admin.
Route::prefix('akuntansi')
    ->middleware(['permission:'.Permission::CashBookManage->value, 'feature:'.Feature::CashBook->value])
    ->group(function (): void {
        Route::controller(CashAccountController::class)->group(function (): void {
            Route::get('pos-akun', 'index')->name('cash-accounts.index');
            Route::post('pos-akun', 'store')->name('cash-accounts.store');
            Route::put('pos-akun/{account}', 'update')->whereNumber('account')->name('cash-accounts.update');
            Route::delete('pos-akun/{account}', 'destroy')->whereNumber('account')->name('cash-accounts.destroy');
            Route::get('pos-akun/pdf', 'exportPdf')->name('cash-accounts.pdf');

            Route::get('alokasi-dana', 'allocations')->name('cash-allocations.index');
            Route::put('alokasi-dana/{account}', 'updateAllocation')->whereNumber('account')->name('cash-allocations.update');
        });

        Route::controller(CashBookController::class)->group(function (): void {
            Route::get('buku-kas', 'index')->name('cash-book.index');
            Route::post('buku-kas/pengeluaran', 'storeExpense')->name('cash-book.expense');
            Route::post('buku-kas/{entry}/koreksi', 'reverse')->whereNumber('entry')->name('cash-book.reverse');
            Route::get('buku-kas/pdf', 'exportPdf')->name('cash-book.pdf');
            Route::get('buku-kas/excel', 'exportExcel')->name('cash-book.excel');
        });

        Route::controller(ExternalIncomeController::class)->group(function (): void {
            Route::get('pemasukan-lain', 'index')->name('external-income.index');
            Route::post('pemasukan-lain', 'store')->name('external-income.store');
            Route::get('pemasukan-lain/pdf', 'exportPdf')->name('external-income.pdf');
            Route::get('pemasukan-lain/excel', 'exportExcel')->name('external-income.excel');
        });
    });
