<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Guardian\AccountController;
use App\Http\Controllers\Guardian\BillingController;
use App\Http\Controllers\Guardian\ChildContextController;
use App\Http\Controllers\Guardian\HistoryController;
use App\Http\Controllers\Guardian\HomeController;
use App\Http\Controllers\Guardian\NotificationInboxController;
use Illuminate\Support\Facades\Route;

/*
| Portal wali murid (T-301+). Dimuat di dalam grup /wali pada routes/web.php.
*/
Route::get('/', HomeController::class)->name('home');
Route::post('anak', ChildContextController::class)->name('child.update');
Route::get('riwayat', HistoryController::class)->name('history');
Route::get('akun', AccountController::class)->name('account');

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
    });
