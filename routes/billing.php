<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\Permission;
use App\Http\Controllers\Admin\BillingRunController;
use App\Http\Controllers\Admin\CashierController;
use App\Http\Controllers\Admin\ExamDispensationController;
use App\Http\Controllers\Admin\ExamEligibilityController;
use App\Http\Controllers\Admin\ExamPeriodController;
use App\Http\Controllers\Admin\FeeTypeController;
use App\Http\Controllers\Admin\InvoiceAdjustmentController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\OpeningBalanceController;
use App\Http\Controllers\Admin\StudentDiscountController;
use App\Http\Controllers\Admin\TransferProofController;
use App\Http\Controllers\Billing\ReceiptController;
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

    Route::controller(StudentDiscountController::class)
        ->middleware(['permission:'.Permission::FeeTypesManage->value, 'feature:'.Feature::Billing->value])
        ->group(function (): void {
            Route::get('diskon', 'index')->name('discounts.index');
            Route::post('diskon', 'store')->name('discounts.store');
            Route::put('diskon/{studentDiscount}', 'update')->name('discounts.update');
            Route::patch('diskon/{studentDiscount}/toggle', 'toggle')->name('discounts.toggle');
        });

    Route::controller(BillingRunController::class)
        ->middleware(['permission:'.Permission::InvoicesIssue->value, 'feature:'.Feature::Billing->value])
        ->group(function (): void {
            Route::get('terbitkan', 'create')->name('billing-runs.create');
            Route::post('terbitkan/pratinjau', 'preview')->name('billing-runs.preview');
            Route::post('terbitkan', 'store')->name('billing-runs.store');
        });

    Route::controller(OpeningBalanceController::class)
        ->middleware(['permission:'.Permission::InvoicesIssue->value, 'feature:'.Feature::Billing->value])
        ->group(function (): void {
            Route::get('saldo-awal', 'index')->name('opening-balances.index');
            Route::get('saldo-awal/template', 'template')->name('opening-balances.template');
            Route::post('saldo-awal/pratinjau', 'preview')->name('opening-balances.preview');
            Route::post('saldo-awal', 'store')->name('opening-balances.store');
        });

    Route::controller(InvoiceController::class)
        ->middleware(['permission:'.Permission::InvoicesView->value, 'feature:'.Feature::Billing->value])
        ->group(function (): void {
            Route::get('tagihan', 'index')->name('invoices.index');
            Route::get('tagihan/{invoice}', 'show')->name('invoices.show');
            Route::get('siswa/{student}/tagihan', 'studentCard')->name('students.invoices');
        });

    Route::controller(InvoiceAdjustmentController::class)
        ->middleware(['permission:'.Permission::InvoicesAdjust->value, 'feature:'.Feature::Billing->value])
        ->group(function (): void {
            Route::post('tagihan/{invoice}/penyesuaian', 'store')->name('invoices.adjust');
            Route::post('tagihan/{invoice}/batalkan', 'cancel')->name('invoices.cancel');
        });

    Route::controller(CashierController::class)
        ->middleware(['permission:'.Permission::PaymentsReceive->value, 'feature:'.Feature::Billing->value])
        ->group(function (): void {
            Route::get('kasir', 'index')->name('cashier.index');
            Route::post('kasir', 'store')->name('cashier.store');
        });

    Route::post('pembayaran/{payment}/balik', [CashierController::class, 'reverse'])
        ->middleware(['permission:'.Permission::PaymentsReverse->value, 'feature:'.Feature::Billing->value])
        ->name('payments.reverse');

    Route::get('pembayaran/{payment}/kuitansi', [ReceiptController::class, 'staff'])
        ->middleware(['permission:'.Permission::InvoicesView->value.'|'.Permission::PaymentsReceive->value, 'feature:'.Feature::Billing->value])
        ->name('payments.receipt');

    Route::controller(TransferProofController::class)
        ->middleware(['permission:'.Permission::PaymentsReconcile->value, 'feature:'.Feature::Billing->value])
        ->group(function (): void {
            Route::get('bukti-transfer', 'index')->name('transfer-proofs.index');
            Route::get('bukti-transfer/{transferProof}/berkas', 'file')->name('transfer-proofs.file');
            Route::post('bukti-transfer/{transferProof}/setujui', 'approve')->name('transfer-proofs.approve');
            Route::post('bukti-transfer/{transferProof}/tolak', 'reject')->name('transfer-proofs.reject');
        });

    Route::controller(ExamPeriodController::class)
        ->middleware(['permission:'.Permission::InvoicesView->value, 'feature:'.Feature::ExamEligibility->value])
        ->group(function (): void {
            Route::get('kelayakan-ujian', 'index')->name('exam-periods.index');
        });

    Route::controller(ExamPeriodController::class)
        ->middleware(['permission:'.Permission::InvoicesIssue->value, 'feature:'.Feature::ExamEligibility->value])
        ->group(function (): void {
            Route::post('kelayakan-ujian', 'store')->name('exam-periods.store');
            Route::put('kelayakan-ujian/{examPeriod}', 'update')->name('exam-periods.update');
            Route::patch('kelayakan-ujian/{examPeriod}/aktifkan', 'activate')->name('exam-periods.activate');
            Route::patch('kelayakan-ujian/{examPeriod}/tutup', 'close')->name('exam-periods.close');
        });

    Route::controller(ExamDispensationController::class)
        ->middleware(['feature:'.Feature::ExamEligibility->value])
        ->group(function (): void {
            Route::post('kelayakan-ujian/{examPeriod}/dispensasi', 'store')->name('exam-periods.dispensations.store');
            Route::post('kelayakan-ujian/dispensasi/{override}/cabut', 'revoke')->name('exam-periods.dispensations.revoke');
        });

    Route::controller(ExamEligibilityController::class)
        ->middleware(['feature:'.Feature::ExamEligibility->value])
        ->group(function (): void {
            Route::get('kelayakan-ujian/{examPeriod}/daftar', 'index')->name('exam-periods.eligibility.index');
            Route::get('kelayakan-ujian/{examPeriod}/ekspor-pdf', 'exportPdf')->name('exam-periods.eligibility.pdf');
            Route::get('kelayakan-ujian/{examPeriod}/ekspor-excel', 'exportExcel')->name('exam-periods.eligibility.excel');
        });
});
