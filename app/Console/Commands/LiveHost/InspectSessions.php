<?php

declare(strict_types=1);

namespace App\Console\Commands\LiveHost;

use App\Models\LiveSession;
use Illuminate\Console\Command;

/**
 * Read-only x-ray of a host/account's live sessions on one day, so we can see
 * WHY a schedule day is crowded: which sessions sit on which slot (assignment),
 * whether several are actually the same TikTok live (same source_record_id)
 * split across rows, and which carry proof vs GMV.
 */
class InspectSessions extends Command
{
    /**
     * @var string
     */
    protected $signature = 'livehost:inspect-sessions
        {--date= : Calendar day to inspect (Y-m-d), by scheduled_start_at}
        {--host= : Limit to a live_host_id}
        {--account= : Substring match on the shop or creator account name}';

    /**
     * @var string
     */
    protected $description = 'Dump every live session on a day (per slot, status, proof, GMV lives) to diagnose crowded schedule days.';

    public function handle(): int
    {
        $date = $this->option('date');

        if (! $date) {
            $this->error('Pass --date=YYYY-MM-DD.');

            return self::INVALID;
        }

        $account = $this->option('account');
        $hostId = $this->option('host') ? (int) $this->option('host') : null;

        $sessions = LiveSession::query()
            ->with(['platformAccount', 'liveAccount', 'actualLiveRecords:id,source,source_record_id,live_attributed_gmv_myr'])
            ->withCount('attachments')
            ->whereDate('scheduled_start_at', $date)
            ->when($hostId, fn ($q) => $q->where('live_host_id', $hostId))
            ->when($account, fn ($q) => $q->where(function ($q) use ($account): void {
                $q->whereHas('platformAccount', fn ($q) => $q->where('name', 'like', "%{$account}%"))
                    ->orWhereHas('liveAccount', fn ($q) => $q
                        ->where('nickname', 'like', "%{$account}%")
                        ->orWhere('display_name', 'like', "%{$account}%"));
            }))
            ->orderBy('scheduled_start_at')
            ->orderBy('live_schedule_assignment_id')
            ->get();

        if ($sessions->isEmpty()) {
            $this->warn('No sessions match those filters.');

            return self::SUCCESS;
        }

        $rows = $sessions->map(function (LiveSession $s): array {
            $lives = $s->actualLiveRecords;

            return [
                $s->id,
                $s->live_host_id,
                $s->live_schedule_assignment_id ?? '—',
                $s->time_slot_id ?? '—',
                $s->scheduled_start_at?->format('H:i') ?? '—',
                $s->actual_start_at?->format('H:i') ?? '—',
                $s->status,
                (int) $s->attachments_count,
                $lives->count(),
                $lives->pluck('source_record_id')->filter()->implode(',') ?: '—',
                $s->gmv_source ?? '—',
                $s->liveAccount?->nickname ?: ($s->platformAccount?->name ?? '—'),
            ];
        })->all();

        $this->table(
            ['id', 'host', 'assign', 'slot', 'sched', 'actual', 'status', 'files', 'lives', 'tiktok live id(s)', 'src', 'account'],
            $rows,
        );

        // Same TikTok live appearing on more than one session = a split that
        // should have been multi-linked onto ONE session, not duplicated.
        $liveToSessions = [];
        foreach ($sessions as $s) {
            foreach ($s->actualLiveRecords as $live) {
                if ($live->source_record_id) {
                    $liveToSessions[$live->source_record_id][] = $s->id;
                }
            }
        }
        $splits = array_filter($liveToSessions, fn ($ids) => count(array_unique($ids)) > 1);

        $this->newLine();
        $this->info(sprintf(
            '%d session(s) · %d empty "perlu upload" (ended, 0 files) · %d distinct slot(s).',
            $sessions->count(),
            $sessions->where('status', 'ended')->where('attachments_count', 0)->count(),
            $sessions->pluck('live_schedule_assignment_id')->unique()->count(),
        ));

        if ($splits !== []) {
            $this->warn(sprintf(
                '%d TikTok live(s) are attached to MULTIPLE sessions (should be one): %s',
                count($splits),
                collect($splits)->map(fn ($ids, $live) => "{$live}→sessions[".implode(',', array_unique($ids)).']')->implode('  '),
            ));
        }

        return self::SUCCESS;
    }
}
