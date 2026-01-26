<?php

namespace App\Jobs\Funnel;

use App\Mail\Funnel\CartAbandonmentMail;
use App\Models\FunnelCart;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ProcessCartAbandonment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 300; // 5 minutes

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting cart abandonment processing');

        // Get carts that have been abandoned for at least 30 minutes
        // and are still recoverable
        $abandonedCarts = FunnelCart::query()
            ->recoverable()
            ->where('updated_at', '<=', now()->subMinutes(30))
            ->with(['session.funnel', 'step'])
            ->get();

        Log::info('Found abandoned carts', ['count' => $abandonedCarts->count()]);

        foreach ($abandonedCarts as $cart) {
            $this->processCart($cart);
        }

        // Mark carts older than 72 hours as expired
        $this->expireOldCarts();
    }

    /**
     * Process a single abandoned cart.
     */
    protected function processCart(FunnelCart $cart): void
    {
        if (! $cart->canSendRecoveryEmail()) {
            Log::debug('Cart cannot receive recovery email', [
                'cart_id' => $cart->id,
                'reason' => $this->getSkipReason($cart),
            ]);

            return;
        }

        // Determine which email to send based on recovery_emails_sent count
        $emailNumber = $cart->recovery_emails_sent + 1;
        $hoursAbandoned = $cart->getAbandonmentAge();

        // Email sequence timing:
        // 1st email: After 30 minutes
        // 2nd email: After 24 hours
        // 3rd email: After 48 hours
        $shouldSend = match ($emailNumber) {
            1 => $hoursAbandoned >= 0.5,  // 30 minutes
            2 => $hoursAbandoned >= 24,   // 24 hours
            3 => $hoursAbandoned >= 48,   // 48 hours
            default => false,
        };

        if (! $shouldSend) {
            return;
        }

        try {
            // Mark abandoned_at if not set
            if (! $cart->abandoned_at) {
                $cart->update(['abandoned_at' => now()]);
            }

            // Send recovery email
            Mail::to($cart->email)
                ->queue(new CartAbandonmentMail($cart, $emailNumber));

            // Mark as sent
            $cart->markAsSent();

            // Track event
            $cart->session?->trackEvent('recovery_email_sent', [
                'cart_id' => $cart->id,
                'email_number' => $emailNumber,
            ]);

            Log::info('Cart abandonment email queued', [
                'cart_id' => $cart->id,
                'email' => $cart->email,
                'email_number' => $emailNumber,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send cart abandonment email', [
                'cart_id' => $cart->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Expire carts older than 72 hours.
     */
    protected function expireOldCarts(): void
    {
        $expiredCount = FunnelCart::query()
            ->whereIn('recovery_status', ['pending', 'sent'])
            ->where('abandoned_at', '<=', now()->subHours(72))
            ->update(['recovery_status' => 'expired']);

        if ($expiredCount > 0) {
            Log::info('Expired old abandoned carts', ['count' => $expiredCount]);
        }
    }

    /**
     * Get reason why cart is being skipped.
     */
    protected function getSkipReason(FunnelCart $cart): string
    {
        if ($cart->isRecovered()) {
            return 'already_recovered';
        }

        if ($cart->isExpired()) {
            return 'expired';
        }

        if ($cart->recovery_emails_sent >= 3) {
            return 'max_emails_sent';
        }

        if (! $cart->email) {
            return 'no_email';
        }

        return 'unknown';
    }
}
