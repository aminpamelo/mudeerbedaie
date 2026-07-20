<?php

declare(strict_types=1);

namespace App\Console\Commands\LiveHost;

use App\Models\LiveSession;
use Illuminate\Console\Command;

/**
 * Fix live sessions whose scheduled_start_at was stamped with the wrong date.
 *
 * A batch import/seeder stamped many imported TikTok sessions with the moment it
 * ran (e.g. 2026-07-16 23:03) instead of the live's real time, so they all pile
 * onto one day in the host's Pocket schedule. This resets scheduled_start_at to
 * the session's real start (actual_start_at — itself derived from the linked
 * TikTok live's launched_time at verify), so each session shows on its true day.
 *
 * Payroll-safe: host payroll/commission buckets by actual_end_at, never
 * scheduled_start_at — so this moves no GMV and touches no locked payroll run.
 * It DOES re-bucket the session in analytics that group by scheduled_start_at,
 * which is the point: those reports currently show these lives on the wrong day,
 * out of sync with the (actual_end_at-based) payroll.
 *
 * Dry-run by default; pass --force to write.
 */
class RedateSessions extends Command
{
    /**
     * @var string
     */
    protected $signature = 'livehost:redate-sessions
        {--host= : Limit to a live_host_id}
        {--account= : Substring match on the shop or creator account name}
        {--force : Apply the fix (default is a dry-run report)}';

    /**
     * @var string
     */
    protected $description = 'Reset scheduled_start_at to the real live time (actual_start_at) for sessions stamped on the wrong day.';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $account = $this->option('account');
        $hostId = $this->option('host') ? (int) $this->option('host') : null;

        if (! $force) {
            $this->warn('DRY RUN — no rows will change. Pass --force to apply.');
        }

        $sessions = LiveSession::query()
            ->with(['platformAccount', 'liveAccount'])
            ->whereNotNull('actual_start_at')
            // Only rows whose scheduled day differs from the real live day. DATE()
            // works on both MySQL and SQLite (both columns share one representation).
            ->whereRaw('DATE(scheduled_start_at) <> DATE(actual_start_at)')
            ->when($hostId, fn ($q) => $q->where('live_host_id', $hostId))
            ->when($account, fn ($q) => $q->where(function ($q) use ($account): void {
                $q->whereHas('platformAccount', fn ($q) => $q->where('name', 'like', "%{$account}%"))
                    ->orWhereHas('liveAccount', fn ($q) => $q
                        ->where('nickname', 'like', "%{$account}%")
                        ->orWhere('display_name', 'like', "%{$account}%"));
            }))
            ->orderBy('actual_start_at')
            ->get();

        if ($sessions->isEmpty()) {
            $this->info('No mis-dated sessions found. 🎉');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($sessions as $s) {
            $rows[] = [
                $s->id,
                $s->live_host_id,
                $s->scheduled_start_at->format('Y-m-d H:i'),
                $s->actual_start_at->format('Y-m-d H:i'),
                $s->liveAccount?->nickname ?: ($s->platformAccount?->name ?? '—'),
                $force ? 'redated' : 'would redate',
            ];

            if ($force) {
                $s->update(['scheduled_start_at' => $s->actual_start_at]);
            }
        }

        $this->table(
            ['id', 'host', 'old scheduled', 'new scheduled (=actual)', 'account', 'action'],
            $rows,
        );

        $byDate = $sessions
            ->groupBy(fn (LiveSession $s): string => $s->actual_start_at->toDateString())
            ->map->count()
            ->sortKeys();

        $this->newLine();
        $this->info(sprintf(
            '%s %d session(s) to their real live date.',
            $force ? 'Redated' : 'Would redate',
            $sessions->count(),
        ));
        $this->line('Spread across: '.$byDate->map(fn (int $n, string $d): string => "{$d}={$n}")->implode('  '));

        return self::SUCCESS;
    }
}
