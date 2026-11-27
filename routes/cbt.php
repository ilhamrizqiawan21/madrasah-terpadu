<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\Permission;
use App\Http\Controllers\Admin\CbtSubjectController;
use App\Http\Controllers\Admin\CbtTeachingAssignmentController;
use Illuminate\Support\Facades\Route;

/*
| Ujian CBT (Fase 15 / T-1501 s.d. T-1510). Dimuat di dalam grup /admin pada routes/web.php.
*/
Route::middleware('feature:'.Feature::Cbt->value)
    ->prefix('ujian')
    ->name('cbt.')
    ->group(function (): void {
        Route::middleware('permission:'.Permission::CbtView->value)
            ->get('mapel', [CbtSubjectController::class, 'index'])
            ->name('subjects.index');

        Route::middleware('permission:'.Permission::CbtManage->value)->group(function (): void {
            Route::post('mapel', [CbtSubjectController::class, 'store'])->name('subjects.store');
            Route::put('mapel/{subject}', [CbtSubjectController::class, 'update'])->whereNumber('subject')->name('subjects.update');
            Route::patch('mapel/{subject}/status', [CbtSubjectController::class, 'updateStatus'])->whereNumber('subject')->name('subjects.status.update');

            Route::post('penugasan', [CbtTeachingAssignmentController::class, 'store'])->name('assignments.store');
            Route::delete('penugasan/{assignment}', [CbtTeachingAssignmentController::class, 'destroy'])->whereNumber('assignment')->name('assignments.destroy');
        });
    });
