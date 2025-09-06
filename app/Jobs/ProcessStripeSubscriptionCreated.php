<?php

namespace App\Jobs;

use App\Models\WebhookEvent;
use App\Services\StripeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessStripeSubscriptionCreated implements ShouldQueue
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
            Log::info('Processing subscription created webhook', [
                'webhook_event_id' => $this->webhookEvent->id,
                'stripe_subscription_id' => $this->stripeSubscription['id'],
            ]);

            // TODO: Implement subscription created logic
            $this->webhookEvent->markAsProcessed();

        } catch (\Exception $e) {
            Log::error('Failed to process subscription created webhook', [
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
