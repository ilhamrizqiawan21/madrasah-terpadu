<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Controllers\Admin\SchoolContextController;
use App\Http\Controllers\BrandingAssetController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::get('branding/{asset}', BrandingAssetController::class)
    ->whereIn('asset', ['logo', 'favicon'])
    ->name('branding.show');

Route::middleware(['auth'])->group(function (): void {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::prefix('admin')
        ->name('admin.')
        ->middleware('role:'.implode('|', [
            Role::Admin->value,
            Role::Treasurer->value,
            Role::Teacher->value,
            Role::Counselor->value,
            Role::UnitHead->value,
        ]))
        ->group(function (): void {
            Route::inertia('/', 'admin/dashboard')->name('dashboard');
            Route::post('context', SchoolContextController::class)->name('context.update');

            Route::inertia('siswa', 'admin/dashboard')
                ->middleware('permission:'.Permission::StudentsView->value)
                ->name('students.index');

            Route::inertia('presensi', 'admin/dashboard')
                ->middleware('permission:'.Permission::AttendanceView->value)
                ->name('attendance.index');

            Route::inertia('tagihan', 'admin/dashboard')
                ->middleware('permission:'.Permission::InvoicesView->value)
                ->name('invoices.index');

            Route::inertia('perilaku', 'admin/dashboard')
                ->middleware('permission:'.Permission::BehaviorView->value)
                ->name('behavior.index');

            Route::inertia('hafalan', 'admin/dashboard')
                ->middleware('permission:'.Permission::QuranView->value)
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
