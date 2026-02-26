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

// Funnel automation jobs
Schedule::job(new \App\Jobs\Funnel\DetectAbandonedSessions)->everyFifteenMinutes();
Schedule::job(new \App\Jobs\Funnel\ProcessCartAbandonment)->everyThirtyMinutes();
Schedule::job(new \App\Jobs\Funnel\UpdateFunnelAnalytics)->dailyAt('02:00');

// TikTok Shop order sync - runs every 15 minutes for active accounts with auto-sync enabled
Schedule::command('tiktok:sync-orders --all --queue --days=1')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// TikTok token refresh - runs daily to ensure tokens don't expire
Schedule::command(\App\Console\Commands\TikTokRefreshTokens::class)
    ->daily()
    ->at('03:00');
