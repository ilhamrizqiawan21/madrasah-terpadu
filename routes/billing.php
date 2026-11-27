<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\Permission;
use App\Http\Controllers\Admin\BillingRunController;
use App\Http\Controllers\Admin\FeeTypeController;
use App\Http\Controllers\Admin\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::prefix('keuangan')->group(function (): void {
    Route::controller(FeeTypeController::class)
        ->middleware(['permission:'.Permission::FeeTypesManage->value, 'feature:'.Feature::Billing->value])
        ->group(function (): void {
            Route::get('jenis-tagihan', 'index')->name('fee-types.index');
            Route::post('jenis-tagihan', 'store')->name('fee-types.store');
            Route::put('jenis-tagihan/{feeType}', 'update')->name('fee-types.update');
            Route::patch('jenis-tagihan/{feeType}/toggle', 'toggle')->name('fee-types.toggle');
            Route::delete('jenis-tagihan/{feeType}', 'destroy')->name('fee-types.destroy');
        });

    Route::controller(BillingRunController::class)
        ->middleware(['permission:'.Permission::InvoicesIssue->value, 'feature:'.Feature::Billing->value])
        ->group(function (): void {
            Route::get('terbitkan', 'create')->name('billing-runs.create');
            Route::post('terbitkan/pratinjau', 'preview')->name('billing-runs.preview');
            Route::post('terbitkan', 'store')->name('billing-runs.store');
        });

    Route::controller(InvoiceController::class)
        ->middleware(['permission:'.Permission::InvoicesView->value, 'feature:'.Feature::Billing->value])
        ->group(function (): void {
            Route::get('tagihan', 'index')->name('invoices.index');
            Route::get('tagihan/{invoice}', 'show')->name('invoices.show');
            Route::get('siswa/{student}/tagihan', 'studentCard')->name('students.invoices');
        });
});
