<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\Permission;
use App\Http\Controllers\Admin\BehaviorRecordController;
use App\Http\Controllers\Admin\DisciplineController;
use App\Http\Controllers\Admin\LateBehaviorController;
use App\Http\Controllers\Admin\PointRuleController;
use App\Http\Controllers\Admin\RewardController;
use Illuminate\Support\Facades\Route;

Route::prefix('perilaku')
    ->name('behavior.')
    ->middleware('feature:'.Feature::Behavior->value)
    ->group(function (): void {
        Route::controller(BehaviorRecordController::class)
            ->group(function (): void {
                Route::get('/', 'index')
                    ->middleware('permission:'.Permission::BehaviorView->value)
                    ->name('index');
                Route::post('/', 'store')
                    ->middleware('permission:'.Permission::BehaviorRecord->value)
                    ->name('store');
                Route::post('{behaviorRecord}/batal', 'void')
                    ->whereNumber('behaviorRecord')
                    ->middleware('permission:'.Permission::BehaviorVoid->value)
                    ->name('void');
                Route::get('{behaviorRecord}/bukti', 'evidence')
                    ->whereNumber('behaviorRecord')
                    ->middleware('permission:'.Permission::BehaviorView->value)
                    ->name('evidence');
            });

        Route::get('keterlambatan', [LateBehaviorController::class, 'index'])
            ->middleware('permission:'.Permission::BehaviorRecord->value)
            ->name('late.index');

        Route::prefix('aturan')
            ->name('rules.')
            ->middleware('permission:'.Permission::PointRulesManage->value)
            ->controller(PointRuleController::class)
            ->group(function (): void {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::put('{pointRule}', 'update')->whereNumber('pointRule')->name('update');
                Route::patch('{pointRule}/status', 'toggle')->whereNumber('pointRule')->name('toggle');
                Route::post('impor', 'import')->name('import');
                Route::get('templat', 'template')->name('template');
            });

        Route::controller(DisciplineController::class)
            ->group(function (): void {
                Route::get('tindak-lanjut', 'index')
                    ->middleware('permission:'.Permission::BehaviorView->value)
                    ->name('discipline.index');
                Route::post('tindak-lanjut/{followup}/selesai', 'completeFollowup')
                    ->whereNumber('followup')
                    ->name('followups.done');
                Route::post('ambang', 'storeThreshold')
                    ->middleware('permission:'.Permission::ThresholdsManage->value)
                    ->name('thresholds.store');
                Route::put('ambang/{threshold}', 'updateThreshold')
                    ->whereNumber('threshold')
                    ->middleware('permission:'.Permission::ThresholdsManage->value)
                    ->name('thresholds.update');
                Route::patch('ambang/{threshold}/status', 'toggleThreshold')
                    ->whereNumber('threshold')
                    ->middleware('permission:'.Permission::ThresholdsManage->value)
                    ->name('thresholds.toggle');
            });

        Route::prefix('reward')
            ->name('rewards.')
            ->middleware('feature:'.Feature::Rewards->value)
            ->controller(RewardController::class)
            ->group(function (): void {
                Route::get('/', 'index')
                    ->middleware('permission:'.Permission::BehaviorView->value)
                    ->name('index');
                Route::post('penawaran', 'storeOffer')
                    ->middleware('permission:'.Permission::RewardsApprove->value)
                    ->name('offers.store');
                Route::put('penawaran/{rewardOffer}', 'updateOffer')
                    ->whereNumber('rewardOffer')
                    ->middleware('permission:'.Permission::RewardsApprove->value)
                    ->name('offers.update');
                Route::patch('penawaran/{rewardOffer}/status', 'toggleOffer')
                    ->whereNumber('rewardOffer')
                    ->middleware('permission:'.Permission::RewardsApprove->value)
                    ->name('offers.toggle');
                Route::post('penukaran', 'requestRedemption')
                    ->name('redemptions.store');
                Route::post('penukaran/{rewardRedemption}/setujui', 'approve')
                    ->whereNumber('rewardRedemption')
                    ->middleware('permission:'.Permission::RewardsApprove->value)
                    ->name('redemptions.approve');
                Route::post('penukaran/{rewardRedemption}/tolak', 'reject')
                    ->whereNumber('rewardRedemption')
                    ->middleware('permission:'.Permission::RewardsApprove->value)
                    ->name('redemptions.reject');
            });
    });
