<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Guardian\AccountController;
use App\Http\Controllers\Guardian\BillingController;
use App\Http\Controllers\Guardian\ChildContextController;
use App\Http\Controllers\Guardian\HistoryController;
use App\Http\Controllers\Guardian\HomeController;
use Illuminate\Support\Facades\Route;

/*
| Portal wali murid (T-301+). Dimuat di dalam grup /wali pada routes/web.php.
*/
Route::get('/', HomeController::class)->name('home');
Route::post('anak', ChildContextController::class)->name('child.update');
Route::get('riwayat', HistoryController::class)->name('history');
Route::get('akun', AccountController::class)->name('account');

Route::get('tagihan', BillingController::class)
    ->middleware('feature:'.Feature::Billing->value)
    ->name('billing');

Route::get('anak/{student}/foto', [StudentController::class, 'photo'])
    ->whereNumber('student')
    ->name('students.photo');
