<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Controllers\Admin\SchoolContextController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\BrandingAssetController;
use App\Http\Controllers\DashboardController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

// Tidak ada halaman depan publik: tamu berakhir di login, pengguna di beranda sesuai peran.
Route::get('/', fn (): RedirectResponse => redirect()->route('dashboard'))->name('home');

Route::get('branding/{asset}', BrandingAssetController::class)
    ->whereIn('asset', ['logo', 'favicon'])
    ->name('branding.show');

Route::middleware(['auth'])->group(function (): void {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::prefix('admin')
        ->name('admin.')
        ->middleware('role:'.implode('|', array_map(fn (Role $role): string => $role->value, Role::adminArea())))
        ->group(function (): void {
            Route::inertia('/', 'admin/dashboard')->name('dashboard');
            Route::post('context', SchoolContextController::class)->name('context.update');

            require __DIR__.'/students.php';
            require __DIR__.'/guardians.php';

            Route::inertia('presensi', 'admin/dashboard')
                ->middleware(['permission:'.Permission::AttendanceView->value, 'feature:'.Feature::Attendance->value])
                ->name('attendance.index');

            Route::inertia('tagihan', 'admin/dashboard')
                ->middleware(['permission:'.Permission::InvoicesView->value, 'feature:'.Feature::Billing->value])
                ->name('invoices.index');

            Route::inertia('perilaku', 'admin/dashboard')
                ->middleware(['permission:'.Permission::BehaviorView->value, 'feature:'.Feature::Behavior->value])
                ->name('behavior.index');

            Route::inertia('hafalan', 'admin/dashboard')
                ->middleware(['permission:'.Permission::QuranView->value, 'feature:'.Feature::Quran->value])
                ->name('quran.index');

            Route::inertia('laporan', 'admin/dashboard')
                ->middleware('permission:'.Permission::ReportsView->value)
                ->name('reports.index');

            require __DIR__.'/users.php';
            require __DIR__.'/admin-settings.php';
            require __DIR__.'/academic-years.php';
            require __DIR__.'/classrooms.php';
        });

    Route::prefix('wali')
        ->name('guardian.')
        ->middleware('role:'.Role::Guardian->value)
        ->group(function (): void {
            Route::inertia('/', 'guardian/home')->name('home');
            Route::get('anak/{student}/foto', [StudentController::class, 'photo'])->whereNumber('student')->name('students.photo');
        });

    Route::prefix('gerbang')
        ->name('gate.')
        ->middleware('role:'.implode('|', [Role::GateOfficer->value, Role::Admin->value]))
        ->group(function (): void {
            Route::inertia('/', 'gate/index')->name('index');
        });

    Route::prefix('kantin')
        ->name('canteen.')
        ->middleware('role:'.implode('|', [
            Role::CanteenCashier->value,
            Role::Treasurer->value,
            Role::Admin->value,
        ]))
        ->group(function (): void {
            Route::inertia('/', 'canteen/index')->name('index');
        });
});

require __DIR__.'/settings.php';
