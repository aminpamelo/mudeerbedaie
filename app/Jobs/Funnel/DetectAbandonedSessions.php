<?php

namespace App\Jobs\Funnel;

use App\Models\FunnelSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DetectAbandonedSessions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting abandoned session detection');

        // Find sessions that have been inactive for 30 minutes
        // and have cart data but haven't converted
        $abandonedSessions = FunnelSession::query()
            ->active()
            ->where('last_activity_at', '<=', now()->subMinutes(30))
            ->whereHas('cart', function ($query) {
                $query->where('total_amount', '>', 0)
                    ->where('recovery_status', 'pending')
                    ->whereNull('abandoned_at');
            })
            ->with(['cart', 'funnel'])
            ->get();

        Log::info('Found potentially abandoned sessions', [
            'count' => $abandonedSessions->count(),
        ]);

        foreach ($abandonedSessions as $session) {
            $this->processSession($session);
        }

        // Mark old active sessions as abandoned
        $this->markOldSessionsAbandoned();
    }

    /**
     * Process a single potentially abandoned session.
     */
    protected function processSession(FunnelSession $session): void
    {
        // Mark the cart as abandoned
        if ($session->cart && ! $session->cart->abandoned_at) {
            $session->cart->update([
                'abandoned_at' => now(),
            ]);

            // Track abandonment event
            $session->trackEvent('cart_abandoned', [
                'cart_id' => $session->cart->id,
                'total' => $session->cart->total_amount,
                'items_count' => $session->cart->getItemCount(),
            ]);

            Log::info('Session cart marked as abandoned', [
                'session_id' => $session->id,
                'cart_id' => $session->cart->id,
                'email' => $session->email,
            ]);
        }
    }

    /**
     * Mark sessions inactive for more than 24 hours as abandoned.
     */
    protected function markOldSessionsAbandoned(): void
    {
        $abandonedCount = FunnelSession::query()
            ->active()
            ->where('last_activity_at', '<=', now()->subHours(24))
            ->update(['status' => 'abandoned']);

        if ($abandonedCount > 0) {
            Log::info('Marked old sessions as abandoned', [
                'count' => $abandonedCount,
            ]);
        }
    }
}
