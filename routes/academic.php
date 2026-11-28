<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\Permission;
use App\Http\Controllers\Admin\AcademicMasterController;
use App\Http\Controllers\Admin\FacilityController;
use App\Http\Controllers\Admin\LessonScheduleController;
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

// Tingkat Kelas, Rombel (nama kelompok), Jurusan: satu halaman generik.
Route::middleware('permission:'.Permission::ClassroomsManage->value)
    ->prefix('akademik/master/{kind}')
    ->where(['kind' => 'tingkat|rombel|jurusan'])
    ->name('academic-masters.')
    ->controller(AcademicMasterController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::put('{id}', 'update')->whereNumber('id')->name('update');
        Route::delete('{id}', 'destroy')->whereNumber('id')->name('destroy');
    });

// Jam Pelajaran & Jadwal Pelajaran Mingguan (kunci fitur lesson_schedule).
Route::middleware(['permission:'.Permission::ClassroomsManage->value, 'feature:'.Feature::LessonSchedule->value])
    ->prefix('akademik')
    ->controller(LessonScheduleController::class)
    ->group(function (): void {
        Route::get('jam-pelajaran', 'periods')->name('lesson-periods.index');
        Route::post('jam-pelajaran', 'storePeriod')->name('lesson-periods.store');
        Route::put('jam-pelajaran/{period}', 'updatePeriod')->whereNumber('period')->name('lesson-periods.update');
        Route::delete('jam-pelajaran/{period}', 'destroyPeriod')->whereNumber('period')->name('lesson-periods.destroy');

        Route::get('jadwal', 'schedule')->name('lesson-schedules.index');
        Route::post('jadwal', 'plot')->name('lesson-schedules.plot');
        Route::post('jadwal/kosongkan', 'clear')->name('lesson-schedules.clear');
    });
