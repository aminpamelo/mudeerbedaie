<?php

declare(strict_types=1);

namespace App\Console\Commands\LiveHost;

use App\Models\LiveSession;
use App\Services\LiveHost\AutoVerifyService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Bulk un-verify: reset every verified live session in a date range back to
 * pending (detach its lives, clear the locked GMV + verification stamps) — a
 * clean slate to re-verify from scratch. A session is in range if its scheduled
 * start OR its actual live time falls inside it (so drifted rows are still
 * caught).
 *
 * Dry-run by default. Skips sessions in a locked payroll period. By default it
 * resets human-verified sessions too (pass --keep-human to preserve those).
 */
class UnverifyRange extends Command
{
    protected $signature = 'livehost:unverify-range
        {--from= : Sessions from this date (Y-m-d)}
        {--until= : ...until this date (Y-m-d)}
        {--keep-human : Preserve sessions a human verified/edited}
        {--apply : Actually reset them to pending (default is a read-only count)}';

    protected $description = 'Reset all verified sessions in a date range back to pending (bulk un-verify).';

    public function handle(AutoVerifyService $service): int
    {
        $from = $this->option('from');
        $until = $this->option('until');
        if (! $from || ! $until) {
            $this->error('Pass both --from and --until (Y-m-d).');

            return self::INVALID;
        }

        $fromAt = CarbonImmutable::parse($from)->startOfDay();
        $toAt = CarbonImmutable::parse($until)->endOfDay();
        $apply = (bool) $this->option('apply');
        $keepHuman = (bool) $this->option('keep-human');

        $sessions = LiveSession::query()
            ->where('verification_status', 'verified')
            ->where(function ($q) use ($fromAt, $toAt) {
                $q->whereBetween('scheduled_start_at', [$fromAt, $toAt])
                    ->orWhereBetween('actual_start_at', [$fromAt, $toAt])
                    ->orWhereBetween('actual_end_at', [$fromAt, $toAt]);
            })
            ->orderBy('scheduled_start_at')
            ->get();

        $reset = 0;
        $skippedHuman = 0;
        $skippedLocked = 0;
        $gmvCleared = 0.0;

        foreach ($sessions as $session) {
            if ($keepHuman && $service->hasVerificationHistory($session)) {
                $skippedHuman++;

                continue;
            }
            if ($service->isPayrollLocked($session)) {
                $skippedLocked++;

                continue;
            }

            $gmvCleared += (float) $session->gmv_amount;

            if ($apply) {
                $service->revertToPending($session);
            }
            $reset++;
        }

        if (! $apply) {
            $this->warn('DRY RUN — no rows changed. Pass --apply to reset.');
        }

        $this->info(sprintf(
            '%s %d verified session(s) in %s … %s (RM %s GMV) · %d skipped (payroll-locked)%s',
            $apply ? 'Reset' : 'Would reset',
            $reset,
            $from,
            $until,
            number_format($gmvCleared, 2),
            $skippedLocked,
            $keepHuman ? " · {$skippedHuman} skipped (human-verified)" : '',
        ));

        return self::SUCCESS;
    }
}
