<?php

declare(strict_types=1);

namespace App\Console\Commands\LiveHost;

use App\Models\LiveSession;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Collapse duplicate live sessions on the same slot + host + calendar day.
 *
 * Repeated "Go Live" taps used to create a fresh LiveSession each time (fixed
 * in GoLiveController), leaving a pile of empty "PERLU UPLOAD" sessions next to
 * the real one. This command keeps the richest session per slot/day (the one
 * with proof attachments, then GMV lives, then a verified/locked one) and
 * deletes the empty extras. Anything carrying data or in a locked payroll
 * period is never touched — it's reported for manual review instead.
 *
 * Safe by default: prints a dry-run report; pass --force to actually delete.
 */
class DedupeLiveSessions extends Command
{
    /**
     * @var string
     */
    protected $signature = 'livehost:dedupe-sessions
        {--host= : Limit to a single live_host_id}
        {--force : Delete the empty duplicates (default is a dry-run report)}';

    /**
     * @var string
     */
    protected $description = 'Detect duplicate live sessions on the same slot+host+day (repeated Go Live taps) and delete the empty extras, keeping the richest one.';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $hostId = $this->option('host') ? (int) $this->option('host') : null;

        if (! $force) {
            $this->warn('DRY RUN — no rows will be deleted. Pass --force to apply.');
        }

        // Candidate universe: sessions tied to a slot (Go Live, the assignment
        // observer, and auto-verify all link one). Group by slot + host + day.
        $groups = LiveSession::query()
            ->whereNotNull('live_schedule_assignment_id')
            ->when($hostId, fn ($q) => $q->where('live_host_id', $hostId))
            ->withCount(['attachments', 'actualLiveRecords'])
            ->get()
            ->groupBy(fn (LiveSession $s): string => implode('|', [
                $s->live_schedule_assignment_id,
                $s->live_host_id,
                $s->scheduled_start_at?->toDateString() ?? 'no-date',
            ]))
            ->filter(fn (Collection $group): bool => $group->count() > 1);

        if ($groups->isEmpty()) {
            $this->info('No duplicate sessions found. 🎉');

            return self::SUCCESS;
        }

        $deleted = 0;
        $conflicts = 0;
        $rows = [];

        foreach ($groups as $key => $group) {
            // Keeper = richest: most attachments, then most GMV lives, then a
            // verified/locked one, then the earliest id. Everything after it is
            // an extra that may be removable.
            $ranked = $group->sortByDesc(fn (LiveSession $s): array => [
                (int) $s->attachments_count,
                (int) $s->actual_live_records_count,
                ($s->gmv_locked_at !== null || $s->verified_at !== null) ? 1 : 0,
                -$s->id,
            ])->values();

            $keeper = $ranked->first();

            foreach ($ranked->slice(1) as $extra) {
                $safe = (int) $extra->attachments_count === 0
                    && (int) $extra->actual_live_records_count === 0
                    && $extra->gmv_locked_at === null
                    && $extra->verified_at === null
                    && $extra->status !== 'live'
                    && ! $extra->isInLockedPayrollPeriod();

                $rows[] = [
                    $key,
                    $keeper->id,
                    $extra->id,
                    $extra->status,
                    (int) $extra->attachments_count,
                    (int) $extra->actual_live_records_count,
                    $safe ? ($force ? 'deleted' : 'would delete') : 'KEPT (has data)',
                ];

                if (! $safe) {
                    $conflicts++;

                    continue;
                }

                $deleted++;

                if ($force) {
                    $extra->delete();
                }
            }
        }

        $this->table(
            ['slot|host|date', 'keep', 'extra', 'status', 'files', 'lives', 'action'],
            $rows,
        );

        $this->newLine();
        $this->info(sprintf(
            '%d duplicate group(s) · %s %d empty extra(s)%s.',
            $groups->count(),
            $force ? 'deleted' : 'would delete',
            $deleted,
            $conflicts > 0 ? " · {$conflicts} kept for manual review (has files/GMV)" : '',
        ));

        return self::SUCCESS;
    }
}
