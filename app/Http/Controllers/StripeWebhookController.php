<?php

namespace App\Http\Controllers;

use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    public function __construct(private StripeService $stripeService) {}

    public function handle(Request $request): Response
    {
        $signature = $request->header('Stripe-Signature');

        if (! $signature) {
            Log::warning('Stripe webhook received without signature');

            return response('Missing signature', 400);
        }

        try {
            // Get raw payload for signature verification
            $payload = $request->getContent();

            // Handle the webhook using StripeService
            $this->stripeService->handleWebhook($payload, $signature);

            Log::info('Stripe webhook processed successfully', [
                'event_type' => json_decode($payload, true)['type'] ?? 'unknown',
            ]);

            return response('OK', 200);

        } catch (\Exception $e) {
            Log::error('Stripe webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response('Webhook handling failed', 400);
        }
    }
}
