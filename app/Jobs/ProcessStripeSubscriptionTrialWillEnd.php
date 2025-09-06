<?php

namespace App\Jobs;

use App\Models\WebhookEvent;
use App\Services\StripeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessStripeSubscriptionTrialWillEnd implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public WebhookEvent $webhookEvent,
        public array $stripeSubscription
    ) {}

    public function handle(StripeService $stripeService): void
    {
        try {
            // Extract subscription ID - handle both object and array formats
            $subscriptionId = $this->stripeSubscription['id'] ?? $this->stripeSubscription['object']->id ?? null;

            if (! $subscriptionId) {
                Log::error('Subscription ID not found in webhook data', [
                    'webhook_event_id' => $this->webhookEvent->id,
                    'subscription_data_keys' => array_keys($this->stripeSubscription),
                ]);
                $this->webhookEvent->markAsFailed('Subscription ID not found in webhook data');

                return;
            }

            Log::info('Processing subscription trial will end webhook', [
                'webhook_event_id' => $this->webhookEvent->id,
                'stripe_subscription_id' => $subscriptionId,
            ]);

            // TODO: Implement trial will end logic
            $this->webhookEvent->markAsProcessed();

        } catch (\Exception $e) {
            Log::error('Failed to process subscription trial will end webhook', [
                'webhook_event_id' => $this->webhookEvent->id,
                'error' => $e->getMessage(),
            ]);

            $this->webhookEvent->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->webhookEvent->markAsFailed($exception->getMessage());
    }
}
