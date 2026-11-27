<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\Permission;
use App\Http\Controllers\Admin\CbtEssayScoreController;
use App\Http\Controllers\Admin\CbtExamController;
use App\Http\Controllers\Admin\CbtMonitorController;
use App\Http\Controllers\Admin\CbtQuestionController;
use App\Http\Controllers\Admin\CbtStudentPinController;
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
        Route::middleware('permission:'.Permission::CbtView->value)->group(function (): void {
            Route::get('mapel', [CbtSubjectController::class, 'index'])->name('subjects.index');
            Route::get('pin-siswa', [CbtStudentPinController::class, 'index'])->name('pins.index');
            Route::post('pin-siswa/{student}/reset', [CbtStudentPinController::class, 'resetStudent'])
                ->whereNumber('student')
                ->name('pins.reset-student');
            Route::post('pin-siswa/rombel/{classroom}/reset', [CbtStudentPinController::class, 'resetClassroom'])
                ->whereNumber('classroom')
                ->name('pins.reset-classroom');

            Route::get('daftar', [CbtExamController::class, 'index'])->name('exams.index');
            Route::post('daftar', [CbtExamController::class, 'store'])->name('exams.store');
            Route::put('daftar/{exam}', [CbtExamController::class, 'update'])->whereNumber('exam')->name('exams.update');
            Route::patch('daftar/{exam}/status', [CbtExamController::class, 'updateStatus'])->whereNumber('exam')->name('exams.status.update');

            Route::get('daftar/{exam}/soal', [CbtQuestionController::class, 'index'])->whereNumber('exam')->name('questions.index');
            Route::post('daftar/{exam}/soal', [CbtQuestionController::class, 'store'])->whereNumber('exam')->name('questions.store');
            Route::post('daftar/{exam}/soal/impor', [CbtQuestionController::class, 'import'])->whereNumber('exam')->name('questions.import');
            Route::put('daftar/{exam}/soal/{question}', [CbtQuestionController::class, 'update'])->whereNumber(['exam', 'question'])->name('questions.update');
            Route::delete('daftar/{exam}/soal/{question}', [CbtQuestionController::class, 'destroy'])->whereNumber(['exam', 'question'])->name('questions.destroy');
            Route::patch('daftar/{exam}/soal/{question}/koreksi-kunci', [CbtQuestionController::class, 'correctKey'])->whereNumber(['exam', 'question'])->name('questions.correct-key');
            Route::get('daftar/{exam}/soal/{question}/gambar', [CbtQuestionController::class, 'image'])->whereNumber(['exam', 'question'])->name('questions.image');

            Route::get('daftar/{exam}/essay', [CbtEssayScoreController::class, 'index'])->whereNumber('exam')->name('essay.index');
            Route::post('daftar/{exam}/essay', [CbtEssayScoreController::class, 'store'])->whereNumber('exam')->name('essay.store');
            Route::post('daftar/{exam}/essay/impor', [CbtEssayScoreController::class, 'import'])->whereNumber('exam')->name('essay.import');

            Route::get('daftar/{exam}/monitor', [CbtMonitorController::class, 'index'])->whereNumber('exam')->name('monitor.index');
            Route::get('daftar/{exam}/monitor/data', [CbtMonitorController::class, 'data'])->whereNumber('exam')->name('monitor.data');
            Route::post('daftar/{exam}/monitor/{session}/buka-kunci', [CbtMonitorController::class, 'unlock'])->whereNumber(['exam', 'session'])->name('monitor.unlock');
            Route::post('daftar/{exam}/monitor/{session}/tambah-waktu', [CbtMonitorController::class, 'extraTime'])->whereNumber(['exam', 'session'])->name('monitor.extra-time');
            Route::post('daftar/{exam}/monitor/{session}/paksa-selesai', [CbtMonitorController::class, 'forceClose'])->whereNumber(['exam', 'session'])->name('monitor.force-close');
        });

        Route::middleware('permission:'.Permission::CbtManage->value)->group(function (): void {
            Route::post('mapel', [CbtSubjectController::class, 'store'])->name('subjects.store');
            Route::put('mapel/{subject}', [CbtSubjectController::class, 'update'])->whereNumber('subject')->name('subjects.update');
            Route::patch('mapel/{subject}/status', [CbtSubjectController::class, 'updateStatus'])->whereNumber('subject')->name('subjects.status.update');

            Route::post('penugasan', [CbtTeachingAssignmentController::class, 'store'])->name('assignments.store');
            Route::delete('penugasan/{assignment}', [CbtTeachingAssignmentController::class, 'destroy'])->whereNumber('assignment')->name('assignments.destroy');
        });
    });
