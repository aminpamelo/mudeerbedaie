<?php

namespace App\Console\Commands;

use App\Jobs\SendCekbotBroadcastJob;
use App\Models\CekbotBroadcast;
use Illuminate\Console\Command;

class CekbotRunBroadcasts extends Command
{
    protected $signature = 'cekbot:run-broadcasts';

    protected $description = 'Dispatch Cekbot broadcasts whose scheduled time has arrived.';

    public function handle(): int
    {
        $due = CekbotBroadcast::query()
            ->where('status', CekbotBroadcast::STATUS_SCHEDULED)
            ->where('scheduled_at', '<=', now())
            ->get();

        foreach ($due as $broadcast) {
            // Flip status first so the next run does not re-dispatch it.
            $broadcast->update(['status' => CekbotBroadcast::STATUS_SENDING]);
            SendCekbotBroadcastJob::dispatch($broadcast->id);
            $this->info("Dispatched broadcast #{$broadcast->id} ({$broadcast->name}).");
        }

        $this->info("Dispatched {$due->count()} due broadcast(s).");

        return self::SUCCESS;
    }
}
