<?php

use App\Jobs\GenerateDailyAnalyticsJob;
use App\Jobs\GenerateMonthlyAnalyticsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new GenerateDailyAnalyticsJob(now()->yesterday()))->dailyAt('00:05');
Schedule::job(new GenerateMonthlyAnalyticsJob(now()->subMonth()->year, now()->subMonth()->month))->monthlyOn(1, '00:15');
Schedule::command('products:sync')->hourly();
