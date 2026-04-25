<?php

namespace App\Console\Commands;

use App\Models\SessionReplacementRequest;
use App\Notifications\ReplacementResolvedNotification;
use Illuminate\Console\Command;

class ExpireReplacementRequestsCommand extends Command
{
    protected $signature = 'replacements:expire';

    protected $description = 'Expire pending session replacement requests whose expires_at has passed.';

    public function handle(): int
    {
        $expired = 0;

        SessionReplacementRequest::query()
            ->where('status', SessionReplacementRequest::STATUS_PENDING)
            ->where('expires_at', '<=', now())
            ->with('originalHost')
            ->each(function (SessionReplacementRequest $req) use (&$expired): void {
                $req->update(['status' => SessionReplacementRequest::STATUS_EXPIRED]);
                $req->originalHost?->notify(
                    new ReplacementResolvedNotification($req, ReplacementResolvedNotification::RESOLUTION_EXPIRED)
                );
                $expired++;
            });

        $this->info("Expired {$expired} replacement request(s).");

        return self::SUCCESS;
    }
}
