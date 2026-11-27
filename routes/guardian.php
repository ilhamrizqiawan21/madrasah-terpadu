<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Guardian\AccountController;
use App\Http\Controllers\Guardian\AttendanceController;
use App\Http\Controllers\Guardian\BehaviorController;
use App\Http\Controllers\Guardian\BillingController;
use App\Http\Controllers\Guardian\ChildContextController;
use App\Http\Controllers\Guardian\ExamResultController;
use App\Http\Controllers\Guardian\HistoryController;
use App\Http\Controllers\Guardian\HomeController;
use App\Http\Controllers\Guardian\LeaveRequestController;
use App\Http\Controllers\Guardian\NotificationInboxController;
use App\Http\Controllers\Guardian\NotificationPreferenceController;
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

        Route::middleware('feature:'.Feature::Cbt->value)->group(function (): void {
            Route::get('ujian', ExamResultController::class)->name('students.exams.index');
        });

        Route::middleware('feature:'.Feature::Behavior->value)->group(function (): void {
            Route::get('perilaku', [BehaviorController::class, 'index'])->name('students.behavior.index');
            Route::post('perilaku/tukar', [BehaviorController::class, 'requestRedemption'])
                ->middleware('feature:'.Feature::Rewards->value)
                ->name('students.behavior.redeem');
        });
    });
