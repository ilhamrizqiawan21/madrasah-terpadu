<?php

declare(strict_types=1);

use App\Jobs\CloseExpiredExamSessions;
use App\Jobs\MarkDailyAbsences;
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
