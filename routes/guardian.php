<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Billing\ReceiptController;
use App\Http\Controllers\Guardian\AccountController;
use App\Http\Controllers\Guardian\AssignmentController;
use App\Http\Controllers\Guardian\AttendanceController;
use App\Http\Controllers\Guardian\BehaviorController;
use App\Http\Controllers\Guardian\BillingController;
use App\Http\Controllers\Guardian\CharacterController;
use App\Http\Controllers\Guardian\ChildContextController;
use App\Http\Controllers\Guardian\ExamResultController;
use App\Http\Controllers\Guardian\HabitController;
use App\Http\Controllers\Guardian\HistoryController;
use App\Http\Controllers\Guardian\HomeController;
use App\Http\Controllers\Guardian\LeaveRequestController;
use App\Http\Controllers\Guardian\MemorizationController;
use App\Http\Controllers\Guardian\NotificationInboxController;
use App\Http\Controllers\Guardian\NotificationPreferenceController;
use App\Http\Controllers\Guardian\PaymentController;
use App\Http\Controllers\Guardian\ReportCardController;
use App\Http\Controllers\Guardian\TransferProofController;
use App\Http\Controllers\Guardian\WalletController;
use Illuminate\Support\Facades\Route;

/*
| Portal wali murid (T-301+). Dimuat di dalam grup /wali pada routes/web.php.
*/
Route::get('/', HomeController::class)->name('home');
Route::post('anak', ChildContextController::class)->name('child.update');
Route::get('riwayat', HistoryController::class)->name('history');
Route::get('akun', AccountController::class)->name('account');
Route::put('akun/notifikasi', [NotificationPreferenceController::class, 'update'])->name('notifications.preferences.update');

Route::prefix('notifikasi')
    ->name('notifications.')
    ->controller(NotificationInboxController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('baca-semua', 'markAllRead')->name('read-all');
        Route::patch('{notification}/baca', 'markRead')->name('read');
    });

Route::get('tagihan', BillingController::class)
    ->middleware('feature:'.Feature::Billing->value)
    ->name('billing');

// Pembayaran online Midtrans (T-702). URL `selesai` juga dipakai sebagai callback finish Snap.
Route::prefix('pembayaran/{paymentOrder}')
    ->middleware('feature:'.Feature::Midtrans->value)
    ->controller(PaymentController::class)
    ->group(function (): void {
        Route::get('/', 'show')->name('payments.show');
        Route::get('selesai', 'finish')->name('payments.finish');
    });

Route::prefix('anak/{student}')
    ->whereNumber('student')
    ->middleware('guardian.owns-student')
    ->group(function (): void {
        Route::get('foto', [StudentController::class, 'photo'])->name('students.photo');

        Route::middleware('feature:'.Feature::Attendance->value)->group(function (): void {
            Route::get('presensi', [AttendanceController::class, 'index'])->name('students.attendance.index');
            Route::get('izin', [LeaveRequestController::class, 'create'])->name('students.leave.create');
            Route::post('izin', [LeaveRequestController::class, 'store'])->name('students.leave.store');
            Route::get('izin/{leaveRequest}/lampiran', [LeaveRequestController::class, 'attachment'])
                ->whereNumber('leaveRequest')
                ->name('students.leave.attachment');
        });

        Route::middleware('feature:'.Feature::Billing->value)->group(function (): void {
            Route::get('pembayaran/{payment}/kuitansi', [ReceiptController::class, 'guardian'])
                ->whereNumber('payment')
                ->name('students.payments.receipt');
            Route::post('tagihan/{invoice}/bukti-transfer', [TransferProofController::class, 'store'])
                ->whereNumber('invoice')
                ->name('students.invoices.transfer-proof.store');
            Route::get('bukti-transfer/{transferProof}/berkas', [TransferProofController::class, 'file'])
                ->whereNumber('transferProof')
                ->name('students.transfer-proofs.file');
            Route::post('pembayaran', [PaymentController::class, 'store'])
                ->middleware(['feature:'.Feature::Midtrans->value, 'throttle:10,1'])
                ->name('students.payments.store');
        });

        Route::middleware('feature:'.Feature::Cbt->value)->group(function (): void {
            Route::get('ujian', ExamResultController::class)->name('students.exams.index');
        });

        Route::middleware('feature:'.Feature::Behavior->value)->group(function (): void {
            Route::get('perilaku', [BehaviorController::class, 'index'])->name('students.behavior.index');
            Route::post('perilaku/tukar', [BehaviorController::class, 'requestRedemption'])
                ->middleware('feature:'.Feature::Rewards->value)
                ->name('students.behavior.redeem');
        });

        Route::middleware('feature:'.Feature::Habits->value)->group(function (): void {
            Route::get('pembiasaan', [HabitController::class, 'index'])->name('students.habits.index');
            Route::post('pembiasaan/centang', [HabitController::class, 'toggle'])->name('students.habits.toggle');
        });

        Route::middleware('feature:'.Feature::Wallet->value)->group(function (): void {
            Route::get('dompet', [WalletController::class, 'index'])->name('students.wallet.index');
            Route::patch('dompet/limit', [WalletController::class, 'updateLimit'])->name('students.wallet.limit');
            Route::patch('dompet/beku', [WalletController::class, 'toggleFreeze'])->name('students.wallet.freeze');
            Route::post('dompet/isi-saldo', [PaymentController::class, 'topUp'])
                ->middleware(['feature:'.Feature::Midtrans->value, 'throttle:10,1'])
                ->name('students.wallet.topup');
        });

        Route::middleware('feature:'.Feature::Attitude->value)->group(function (): void {
            Route::get('karakter', [CharacterController::class, 'index'])->name('students.character.index');
            Route::get('karakter/{review}/pdf', [CharacterController::class, 'pdf'])
                ->whereNumber('review')
                ->name('students.character.pdf');
        });

        Route::middleware('feature:'.Feature::Assignments->value)->group(function (): void {
            Route::get('tugas', [AssignmentController::class, 'index'])->name('students.assignments.index');
            Route::get('tugas/{assignment}/lampiran', [AssignmentController::class, 'attachment'])
                ->whereNumber('assignment')
                ->name('students.assignments.attachment');
        });

        Route::middleware('feature:'.Feature::ReportCards->value)->group(function (): void {
            Route::get('rapor', [ReportCardController::class, 'index'])->name('students.report-cards.index');
            Route::get('rapor/{reportCard}/unduh', [ReportCardController::class, 'download'])
                ->whereNumber('reportCard')
                ->middleware('signed')
                ->name('students.report-cards.download');
        });

        Route::middleware('feature:'.Feature::Quran->value)->group(function (): void {
            Route::get('hafalan', MemorizationController::class)->name('students.quran.index');
        });
    });
