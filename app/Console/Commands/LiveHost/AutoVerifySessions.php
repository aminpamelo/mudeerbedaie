<?php

namespace App\Console\Commands\LiveHost;

use App\Services\LiveHost\AutoVerifyService;
use App\Services\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Auto-assign + verify TikTok lives that match the schedule. Scheduled runs are
 * gated by the `livehost.auto_verify_enabled` setting; pass --force to run a
 * one-off regardless (e.g. to catch up a backlog).
 */
class AutoVerifySessions extends Command
{
    protected $signature = 'livehost:auto-verify-sessions
        {--days=2 : Look back this many days of synced lives}
        {--force : Run even when the auto-verify setting is off}';

    protected $description = 'Auto-assign the scheduled host and verify TikTok lives that match the schedule.';

    public function handle(AutoVerifyService $service, SettingsService $settings): int
    {
        $enabled = filter_var($settings->get('livehost.auto_verify_enabled', false), FILTER_VALIDATE_BOOLEAN);

        if (! $enabled && ! $this->option('force')) {
            $this->info('Auto-verify is disabled (livehost.auto_verify_enabled). Skipping.');

            return self::SUCCESS;
        }

        $days = max(1, (int) $this->option('days'));
        $to = CarbonImmutable::now();
        $from = $to->subDays($days)->startOfDay();

        $stats = $service->run($from, $to);

        $this->info(sprintf(
            'Auto-verify: %d sessions verified (%d records) · scanned %d · no-match %d · no-host %d · skipped %d',
            $stats['sessions_verified'],
            $stats['records_linked'],
            $stats['scanned'],
            $stats['no_match'],
            $stats['no_host'],
            $stats['skipped'],
        ));

        return self::SUCCESS;
    }
}
