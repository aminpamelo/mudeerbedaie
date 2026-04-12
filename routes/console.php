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

// WhatsApp cost analytics sync - fetches previous day's costs from Meta API
Schedule::job(new \App\Jobs\SyncWhatsAppCostAnalyticsJob)->dailyAt('06:00');

// TikTok token refresh - runs daily to ensure tokens don't expire
Schedule::command(\App\Console\Commands\TikTokRefreshTokens::class)
    ->daily()
    ->at('03:00');

// HR Module - Mark absent employees at end of day
Schedule::command('hr:mark-absent')->dailyAt('23:59');

// HR Module - Generate monthly penalty summary on 1st of each month
Schedule::command('hr:penalty-summary')->monthlyOn(1, '08:00');

// HR Module - Initialize leave balances on Jan 1st each year
Schedule::command('hr:initialize-leave-balances')->yearlyOn(1, 1, '00:01');

// HR Notifications - Clock reminders (every 15 min)
Schedule::command('hr:send-clock-reminders')->everyFifteenMinutes();

// HR Notifications - Late alerts (every 15 min during work hours)
Schedule::command('hr:send-late-alerts')->everyFifteenMinutes()->between('8:00', '18:00');

// HR Notifications - Leave balance check (weekly Monday 8 AM)
Schedule::command('hr:check-leave-balances')->weeklyOn(1, '8:00');

// HR Notifications - Upcoming leave reminders (daily 6 PM)
Schedule::command('hr:send-upcoming-leave-reminders')->dailyAt('18:00');

// HR Notifications - Team leave alerts (daily 8 AM)
Schedule::command('hr:send-team-leave-alerts')->dailyAt('08:00');

// HR Notifications - Pending claims reminder (daily 9 AM)
Schedule::command('hr:remind-pending-claims')->dailyAt('09:00');

// HR Notifications - Expiring claims check (daily 9 AM)
Schedule::command('hr:check-expiring-claims')->dailyAt('09:00');

// HR Notifications - Asset return check (daily 9 AM)
Schedule::command('hr:check-asset-returns')->dailyAt('09:00');

// HR Notifications - Probation endings check (daily 9 AM)
Schedule::command('hr:check-probation-endings')->dailyAt('09:00');

// HR Notifications - Expiring documents check (daily 9 AM)
Schedule::command('hr:check-expiring-documents')->dailyAt('09:00');

// TikTok Shop analytics sync - daily at 4 AM
Schedule::command('tiktok:sync-analytics')->dailyAt('04:00');

// TikTok Shop affiliate sync - daily at 5 AM
Schedule::command('tiktok:sync-affiliates')->dailyAt('05:00');

// TikTok Shop finance sync - daily at 6 AM (after WhatsApp cost sync also at 6 AM, staggered by queue)
Schedule::command('tiktok:sync-finance')->dailyAt('06:00');
