<?php

namespace App\Jobs;

use App\Models\WebhookEvent;
use App\Services\StripeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessStripeInvoicePaymentSucceeded implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $maxExceptions = 2;

    public int $timeout = 120;

    public function __construct(
        public WebhookEvent $webhookEvent,
        public array $stripeInvoice
    ) {}

    public function handle(StripeService $stripeService): void
    {
        try {
            // Extract invoice ID - handle both object and array formats
            $invoiceId = $this->stripeInvoice['id'] ?? $this->stripeInvoice['object']->id ?? null;

            if (! $invoiceId) {
                Log::error('Invoice ID not found in webhook data', [
                    'webhook_event_id' => $this->webhookEvent->id,
                    'invoice_data_keys' => array_keys($this->stripeInvoice),
                ]);
                $this->webhookEvent->markAsFailed('Invoice ID not found in webhook data');

                return;
            }

            Log::info('Processing invoice payment succeeded webhook', [
                'webhook_event_id' => $this->webhookEvent->id,
                'stripe_invoice_id' => $invoiceId,
            ]);

            // If webhook data doesn't contain full invoice (common with Stripe CLI),
            // fetch complete invoice from Stripe API
            $fullInvoiceData = $this->stripeInvoice;
            if (! isset($this->stripeInvoice['lines']) && $invoiceId) {
                Log::info('Fetching full invoice data from Stripe API', [
                    'invoice_id' => $invoiceId,
                ]);

                try {
                    $stripeInvoice = $stripeService->getStripe()->invoices->retrieve(
                        $invoiceId,
                        ['expand' => ['lines']]
                    );
                    $fullInvoiceData = $stripeInvoice->toArray();
                } catch (\Exception $e) {
                    Log::warning('Could not fetch full invoice from Stripe', [
                        'invoice_id' => $invoiceId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Create order from Stripe invoice
            $order = $stripeService->createOrderFromStripeInvoice($fullInvoiceData);

            if ($order) {
                $order->markAsPaid();

                // Update next payment date for the enrollment
                if ($order->enrollment && $fullInvoiceData['period_end']) {
                    $nextPaymentDate = \Carbon\Carbon::createFromTimestamp($fullInvoiceData['period_end'])->addDay();
                    $order->enrollment->updateNextPaymentDate($nextPaymentDate);

                    Log::info('Updated next payment date for enrollment', [
                        'enrollment_id' => $order->enrollment->id,
                        'next_payment_date' => $nextPaymentDate->toDateTimeString(),
                    ]);
                }

                Log::info('Order created and marked as paid', [
                    'order_id' => $order->id,
                    'webhook_event_id' => $this->webhookEvent->id,
                ]);
            }

            // Mark webhook event as processed
            $this->webhookEvent->markAsProcessed();

        } catch (\Exception $e) {
            Log::error('Failed to process invoice payment succeeded webhook', [
                'webhook_event_id' => $this->webhookEvent->id,
                'stripe_invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Mark webhook as failed and increment retry count
            $this->webhookEvent->markAsFailed($e->getMessage());

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Invoice payment succeeded webhook job permanently failed', [
            'webhook_event_id' => $this->webhookEvent->id,
            'stripe_invoice_id' => $this->stripeInvoice['id'],
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        $this->webhookEvent->markAsFailed($exception->getMessage());
    }
}
