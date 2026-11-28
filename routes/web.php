<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\Role;
use App\Http\Controllers\Admin\ComingSoonController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\SchoolContextController;
use App\Http\Controllers\BrandingAssetController;
use App\Http\Controllers\Canteen\CanteenPosController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Gate\GateController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\PwaController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

// Tidak ada halaman depan publik: tamu berakhir di login, pengguna di beranda sesuai peran.
Route::get('/', fn (): RedirectResponse => redirect()->route('dashboard'))->name('home');

Route::get('branding/{asset}', BrandingAssetController::class)
    ->whereIn('asset', ['logo', 'favicon'])
    ->name('branding.show');

Route::get('manifest.webmanifest', [PwaController::class, 'manifest'])->name('pwa.manifest');
Route::get('sw.js', [PwaController::class, 'serviceWorker'])->name('pwa.sw');

Route::middleware(['auth:web'])->group(function (): void {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('throttle:30,1')->group(function (): void {
        Route::post('push/subscriptions', [PushSubscriptionController::class, 'store'])->name('push.subscriptions.store');
        Route::delete('push/subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push.subscriptions.destroy');
    });

    Route::prefix('admin')
        ->name('admin.')
        ->middleware('role:'.implode('|', array_map(fn (Role $role): string => $role->value, Role::adminArea())))
        ->group(function (): void {
            Route::get('/', AdminDashboardController::class)->name('dashboard');
            Route::post('context', SchoolContextController::class)->name('context.update');

            require __DIR__.'/students.php';
            require __DIR__.'/guardians.php';
            require __DIR__.'/attendance.php';

            // Modul belum dibangun: nama rute lama dipertahankan, isinya halaman "sedang disiapkan" (ADR-028).
            Route::get('segera/{module}', ComingSoonController::class)
                ->where('module', '[a-z0-9-]+')
                ->name('soon');

            require __DIR__.'/behavior.php';
            require __DIR__.'/habits.php';
            require __DIR__.'/quran.php';
            require __DIR__.'/reports.php';

            require __DIR__.'/users.php';
            require __DIR__.'/admin-settings.php';
            require __DIR__.'/academic-years.php';
            require __DIR__.'/classrooms.php';
            require __DIR__.'/cbt.php';
            require __DIR__.'/character.php';
            require __DIR__.'/billing.php';
            require __DIR__.'/wallet.php';
        });

    Route::prefix('wali')
        ->name('guardian.')
        ->middleware('role:'.Role::Guardian->value)
        ->group(function (): void {
            require __DIR__.'/guardian.php';
        });

    Route::prefix('gerbang')
        ->name('gate.')
        ->middleware([
            'role:'.implode('|', [Role::GateOfficer->value, Role::Admin->value]),
            'feature:'.Feature::Attendance->value,
        ])
        ->controller(GateController::class)
        ->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::post('scan', 'scan')
                ->middleware('throttle:300,1')
                ->name('scan');
        });

    Route::prefix('kantin')
        ->name('canteen.')
        ->controller(CanteenPosController::class)
        ->middleware('role:'.implode('|', [
            Role::CanteenCashier->value,
            Role::Treasurer->value,
            Role::Admin->value,
        ]))
        ->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::post('scan', 'lookup')->middleware('throttle:120,1')->name('lookup');
            Route::post('transaksi', 'purchase')->middleware('throttle:120,1')->name('purchase');
            Route::post('transaksi/{transaction}/balik', 'reverse')->name('reverse');
        });
});

require __DIR__.'/exam.php';
require __DIR__.'/settings.php';
