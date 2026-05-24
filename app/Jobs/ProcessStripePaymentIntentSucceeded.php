<?php

namespace App\Jobs;

use App\Models\ProductOrder;
use App\Models\WebhookEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Safety net for the funnel credit_card flow.
 *
 * The happy path is synchronous: Stripe.js confirms the card, the browser calls
 * confirmStripePayment() on the Livewire component, and the order is marked
 * paid before the customer sees the thank-you page. This job covers the
 * unhappy paths where that callback never fires (closed browser, network
 * blip, 3DS challenge redirect) — Stripe still sends payment_intent.succeeded
 * to our webhook, and this job uses it to mark the order paid.
 */
class ProcessStripePaymentIntentSucceeded implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public WebhookEvent $webhookEvent,
        public array $stripePaymentIntent
    ) {}

    public function handle(): void
    {
        try {
            $paymentIntentId = $this->stripePaymentIntent['id'] ?? null;

            if (! $paymentIntentId) {
                $this->webhookEvent->markAsFailed('Payment intent ID missing from webhook payload');

                return;
            }

            // Locate the funnel order via the metadata we stamped on it during
            // processStripePayment(). We deliberately scope to source='funnel'
            // so this job only acts on funnel-checkout orders — other Stripe
            // flows (subscriptions, manual charges) have their own handlers.
            $order = ProductOrder::query()
                ->where('source', 'funnel')
                ->where('metadata->stripe_payment_intent_id', $paymentIntentId)
                ->first();

            if (! $order) {
                Log::info('payment_intent.succeeded: no matching funnel order, skipping', [
                    'payment_intent_id' => $paymentIntentId,
                    'webhook_event_id' => $this->webhookEvent->id,
                ]);
                $this->webhookEvent->markAsProcessed();

                return;
            }

            if ($order->payment_status === 'paid') {
                // Already marked paid by the synchronous confirmStripePayment
                // call — nothing to do, this is the expected happy-path case.
                Log::info('payment_intent.succeeded: order already paid', [
                    'order_id' => $order->id,
                    'payment_intent_id' => $paymentIntentId,
                ]);
                $this->webhookEvent->markAsProcessed();

                return;
            }

            $order->update([
                'status' => 'confirmed',
                'payment_status' => 'paid',
                'paid_time' => $order->paid_time ?? now(),
            ]);

            $order->addSystemNote('Order marked paid via Stripe webhook (payment_intent.succeeded fallback)');

            Log::info('payment_intent.succeeded: order marked paid by webhook fallback', [
                'order_id' => $order->id,
                'payment_intent_id' => $paymentIntentId,
            ]);

            $this->webhookEvent->markAsProcessed();

        } catch (\Exception $e) {
            Log::error('Failed to process payment_intent.succeeded webhook', [
                'webhook_event_id' => $this->webhookEvent->id,
                'payment_intent_id' => $this->stripePaymentIntent['id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            $this->webhookEvent->markAsFailed($e->getMessage());

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('payment_intent.succeeded job permanently failed', [
            'webhook_event_id' => $this->webhookEvent->id,
            'payment_intent_id' => $this->stripePaymentIntent['id'] ?? null,
            'error' => $exception->getMessage(),
        ]);

        $this->webhookEvent->markAsFailed($exception->getMessage());
    }
}
