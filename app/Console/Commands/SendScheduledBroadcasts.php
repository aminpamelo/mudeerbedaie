<?php

namespace App\Console\Commands;

use App\Jobs\SendBroadcastEmail;
use App\Models\Broadcast;
use Illuminate\Console\Command;

class SendScheduledBroadcasts extends Command
{
    protected $signature = 'broadcasts:send-scheduled {--grace=120 : Only send broadcasts overdue by at most this many minutes}';

    protected $description = 'Dispatch CRM email broadcasts whose scheduled time has arrived.';

    public function handle(): int
    {
        $grace = max(1, (int) $this->option('grace'));
        $now = now();
        $cutoff = $now->copy()->subMinutes($grace);

        $due = Broadcast::query()
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', $now)
            ->where('scheduled_at', '>=', $cutoff)
            ->get();

        // Stale backlog (overdue beyond the grace window) is intentionally left
        // untouched so we never silently blast an old campaign.
        $stale = Broadcast::query()
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<', $cutoff)
            ->count();

        if ($stale > 0) {
            $this->warn("Skipping {$stale} stale scheduled broadcast(s) overdue by more than {$grace} min.");
        }

        $dispatched = 0;

        foreach ($due as $broadcast) {
            // Atomically claim so an overlapping run can't double-dispatch.
            $claimed = Broadcast::query()
                ->where('id', $broadcast->id)
                ->where('status', 'scheduled')
                ->update(['status' => 'sending']);

            if ($claimed !== 1) {
                continue;
            }

            SendBroadcastEmail::dispatch($broadcast->fresh());
            $dispatched++;
            $this->info("Dispatched broadcast #{$broadcast->id}: {$broadcast->name}");
        }

        $this->info("Done. Dispatched {$dispatched} scheduled broadcast(s).");

        return self::SUCCESS;
    }
}
