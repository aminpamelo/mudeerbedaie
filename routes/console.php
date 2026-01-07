<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule automatic generation of subscription orders
Schedule::command('subscriptions:generate-orders')->daily()->at('01:00');

// Schedule class notification jobs
Schedule::command('notifications:schedule --days=7')->dailyAt('00:30');
Schedule::command('notifications:process --limit=50')->everyFiveMinutes();
