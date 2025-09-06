<?php

namespace App\Jobs;

use App\Models\WebhookEvent;
use App\Services\StripeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessStripeCustomerUpdated implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public WebhookEvent $webhookEvent,
        public array $stripeCustomer
    ) {}

    public function handle(StripeService $stripeService): void
    {
        try {
            // Extract customer ID - should now be properly converted by StripeService
            $customerId = $this->stripeCustomer['id'] ?? null;

            if (! $customerId) {
                Log::error('Customer ID not found in webhook data', [
                    'webhook_event_id' => $this->webhookEvent->id,
                    'customer_data_keys' => array_keys($this->stripeCustomer),
                ]);
                $this->webhookEvent->markAsFailed('Customer ID not found in webhook data');

                return;
            }

            Log::info('Processing customer updated webhook', [
                'webhook_event_id' => $this->webhookEvent->id,
                'stripe_customer_id' => $customerId,
            ]);

            // TODO: Implement customer update logic
            // For now, just mark as processed
            $this->webhookEvent->markAsProcessed();

        } catch (\Exception $e) {
            Log::error('Failed to process customer updated webhook', [
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
