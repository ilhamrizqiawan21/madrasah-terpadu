<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Http\Controllers\Admin\FacilityController;
use Illuminate\Support\Facades\Route;

/*
| Master Akademik hasil duplikasi alur aplikasi lama. Dimuat di dalam grup /admin pada routes/web.php.
*/
Route::middleware('permission:'.Permission::ClassroomsManage->value)
    ->prefix('akademik')
    ->controller(FacilityController::class)
    ->group(function (): void {
        Route::get('gedung', 'buildings')->name('buildings.index');
        Route::post('gedung', 'storeBuilding')->name('buildings.store');
        Route::put('gedung/{building}', 'updateBuilding')->whereNumber('building')->name('buildings.update');
        Route::delete('gedung/{building}', 'destroyBuilding')->whereNumber('building')->name('buildings.destroy');

        Route::get('ruangan', 'rooms')->name('rooms.index');
        Route::post('ruangan', 'storeRoom')->name('rooms.store');
        Route::put('ruangan/{room}', 'updateRoom')->whereNumber('room')->name('rooms.update');
        Route::delete('ruangan/{room}', 'destroyRoom')->whereNumber('room')->name('rooms.destroy');
    });
