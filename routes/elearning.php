<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\Permission;
use App\Http\Controllers\Admin\AssignmentController;
use App\Http\Controllers\Admin\ReportCardController;
use Illuminate\Support\Facades\Route;

/*
| E-Learning hasil duplikasi alur aplikasi lama. Dimuat di dalam grup /admin pada routes/web.php.
*/
Route::middleware(['permission:'.Permission::StudentsView->value, 'feature:'.Feature::Assignments->value])
    ->prefix('e-learning')
    ->controller(AssignmentController::class)
    ->group(function (): void {
        Route::get('tugas', 'index')->name('assignments.index');
        Route::post('tugas', 'store')->name('assignments.store');
        Route::put('tugas/{assignment}', 'update')->whereNumber('assignment')->name('assignments.update');
        Route::delete('tugas/{assignment}', 'destroy')->whereNumber('assignment')->name('assignments.destroy');
        Route::get('tugas/{assignment}/lampiran', 'attachment')->whereNumber('assignment')->name('assignments.attachment');
    });

Route::middleware(['permission:'.Permission::StudentsView->value, 'feature:'.Feature::ReportCards->value])
    ->prefix('e-learning')
    ->controller(ReportCardController::class)
    ->group(function (): void {
        Route::get('e-rapor', 'index')->name('report-cards.index');
        Route::post('e-rapor', 'store')->name('report-cards.store');
        Route::patch('e-rapor/{reportCard}/terbit', 'togglePublish')->whereNumber('reportCard')->name('report-cards.publish');
        Route::delete('e-rapor/{reportCard}', 'destroy')->whereNumber('reportCard')->name('report-cards.destroy');
        Route::get('e-rapor/{reportCard}/unduh', 'download')->whereNumber('reportCard')->name('report-cards.download');
    });
