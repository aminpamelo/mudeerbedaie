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
            'Auto-verify: %d sessions verified (%d records) · scanned %d · no-match %d · no-host %d · unsettled %d · skipped %d',
            $stats['sessions_verified'],
            $stats['records_linked'],
            $stats['scanned'],
            $stats['no_match'],
            $stats['no_host'],
            $stats['unsettled'],
            $stats['skipped'],
        ));

        $refresh = $service->refresh($from, $to);
        $rstats = $refresh['stats'];

        $this->info(sprintf(
            'Refresh: %d GMV updated · %d segments added · %d drift fixed · %d flagged · scanned %d',
            $rstats['gmv_updated'],
            $rstats['segments_added'],
            $rstats['drift_fixed'],
            $rstats['drift_flagged'],
            $rstats['refresh_scanned'],
        ));

        $this->reportFindings($refresh['findings']);

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, mixed>>  $findings
     */
    private function reportFindings(array $findings): void
    {
        if ($findings === []) {
            return;
        }

        $this->newLine();
        $this->line('Integrity findings:');
        foreach ($findings as $f) {
            $this->line(match ($f['type']) {
                'moved' => sprintf('  ✓ moved session %d → %d (live %d) — off its slot, relinked to the correct one.', $f['session_id'], $f['target_session_id'], $f['live_id']),
                'conflict' => sprintf('  ! session %d drifted; the right slot (session %d) is occupied — needs a manual look.', $f['session_id'], $f['suggested_session_id']),
                'orphaned' => sprintf('  ! session %d: live %d no longer matches any hosted slot — schedule edited/removed.', $f['session_id'], $f['live_id']),
                'shrink' => sprintf('  ! session %d would drop live(s) %s on refresh — left as-is for review.', $f['session_id'], implode(',', $f['would_drop'])),
                default => '  ? '.json_encode($f),
            });
        }
    }
}
