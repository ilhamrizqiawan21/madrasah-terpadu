<?php

declare(strict_types=1);

use App\Jobs\CloseExpiredExamSessions;
use App\Jobs\ExpirePaymentOrders;
use App\Jobs\MarkDailyAbsences;
use App\Jobs\SendExamEligibilityReminders;
use App\Jobs\SendInvoiceDueReminders;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new MarkDailyAbsences)
    ->everyFifteenMinutes()
    ->timezone('Asia/Makassar')
    ->withoutOverlapping();

Schedule::job(new CloseExpiredExamSessions)
    ->everyMinute()
    ->timezone('Asia/Makassar')
    ->withoutOverlapping();

Schedule::job(new SendInvoiceDueReminders)
    ->dailyAt('07:00')
    ->timezone('Asia/Makassar')
    ->withoutOverlapping();

Schedule::job(new SendExamEligibilityReminders)
    ->dailyAt('07:15')
    ->timezone('Asia/Makassar')
    ->withoutOverlapping();

Schedule::command('wallet:verify-balances')
    ->dailyAt('01:00')
    ->timezone('Asia/Makassar')
    ->withoutOverlapping();

Schedule::job(new ExpirePaymentOrders)
    ->everyFifteenMinutes()
    ->timezone('Asia/Makassar')
    ->withoutOverlapping();
