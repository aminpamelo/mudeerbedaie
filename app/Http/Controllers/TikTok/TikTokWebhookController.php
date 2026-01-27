<?php

declare(strict_types=1);

namespace App\Http\Controllers\TikTok;

use App\Http\Controllers\Controller;
use App\Models\PlatformAccount;
use App\Models\TikTokWebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class TikTokWebhookController extends Controller
{
    /**
     * Handle incoming TikTok Shop webhooks.
     */
    public function handle(Request $request): JsonResponse|Response
    {
        Log::info('[TikTok Webhook] Received webhook request', [
            'method' => $request->method(),
            'headers' => $request->headers->all(),
            'body' => $request->all(),
        ]);

        // TikTok sends a verification challenge on webhook setup
        if ($request->has('challenge')) {
            return $this->handleChallenge($request);
        }

        // Verify webhook signature
        if (! $this->verifySignature($request)) {
            Log::warning('[TikTok Webhook] Invalid signature');

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        // Parse webhook payload
        $payload = $request->all();
        $eventType = $payload['type'] ?? $payload['event_type'] ?? 'unknown';
        $eventId = $payload['event_id'] ?? $payload['message_id'] ?? uniqid('tiktok_');

        // Check for duplicate event (idempotency)
        if (TikTokWebhookEvent::isAlreadyProcessed($eventId)) {
            Log::info('[TikTok Webhook] Duplicate event ignored', ['event_id' => $eventId]);

            return response()->json(['status' => 'already_processed']);
        }

        // Find associated platform account if shop_id is provided
        $shopId = $payload['shop_id'] ?? $payload['data']['shop_id'] ?? null;
        $platformAccount = null;

        if ($shopId) {
            $platformAccount = PlatformAccount::where('shop_id', $shopId)->first();

            Log::info('[TikTok Webhook] Looking up account', [
                'shop_id' => $shopId,
                'found' => $platformAccount !== null,
                'account_id' => $platformAccount?->id,
            ]);
        }

        // Store the webhook event
        $webhookEvent = TikTokWebhookEvent::create([
            'platform_account_id' => $platformAccount?->id,
            'event_type' => $eventType,
            'event_id' => $eventId,
            'payload' => $payload,
            'status' => 'pending',
        ]);

        Log::info('[TikTok Webhook] Event stored', [
            'webhook_event_id' => $webhookEvent->id,
            'event_type' => $eventType,
            'event_id' => $eventId,
        ]);

        // Process the event based on type
        try {
            $this->processEvent($webhookEvent, $eventType, $payload);
            $webhookEvent->markAsProcessed();
        } catch (\Exception $e) {
            Log::error('[TikTok Webhook] Processing failed', [
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);
            $webhookEvent->markAsFailed($e->getMessage());
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Handle TikTok's challenge verification for webhook setup.
     */
    private function handleChallenge(Request $request): Response
    {
        $challenge = $request->get('challenge');

        Log::info('[TikTok Webhook] Challenge verification', ['challenge' => $challenge]);

        // Return the challenge as plain text (TikTok requirement)
        return response($challenge, 200)
            ->header('Content-Type', 'text/plain');
    }

    /**
     * Verify webhook signature from TikTok.
     */
    private function verifySignature(Request $request): bool
    {
        $signature = $request->header('X-TikTok-Signature') ?? $request->header('Authorization');

        Log::info('[TikTok Webhook] Signature verification started', [
            'has_signature' => ! empty($signature),
            'sandbox_mode' => config('services.tiktok.sandbox'),
            'has_app_secret' => ! empty(config('services.tiktok.app_secret')),
        ]);

        // During development/testing, you can optionally skip verification
        if (config('services.tiktok.sandbox') && empty($signature)) {
            Log::info('[TikTok Webhook] Sandbox mode - skipping signature verification');

            return true;
        }

        // If no signature is provided but we're not in sandbox mode, still process
        // but log a warning (TikTok webhooks may not always include signature)
        if (empty($signature)) {
            Log::warning('[TikTok Webhook] No signature provided - allowing webhook for now');

            return true;
        }

        $appSecret = config('services.tiktok.app_secret');
        if (empty($appSecret)) {
            Log::warning('[TikTok Webhook] App secret not configured - allowing webhook');

            return true;
        }

        // TikTok uses HMAC-SHA256 for signature verification
        $payload = $request->getContent();
        $timestamp = $request->header('X-TikTok-Timestamp', '');

        $expectedSignature = hash_hmac('sha256', $timestamp.$payload, $appSecret);

        $isValid = hash_equals($expectedSignature, $signature);

        if (! $isValid) {
            Log::warning('[TikTok Webhook] Signature mismatch', [
                'received_signature_length' => strlen($signature),
                'expected_signature_length' => strlen($expectedSignature),
                'timestamp' => $timestamp,
                'payload_length' => strlen($payload),
            ]);
        }

        return $isValid;
    }

    /**
     * Process webhook event based on type.
     */
    private function processEvent(TikTokWebhookEvent $webhookEvent, string $eventType, array $payload): void
    {
        Log::info('[TikTok Webhook] Processing event', [
            'type' => $eventType,
            'payload_keys' => array_keys($payload),
        ]);

        match ($eventType) {
            'ORDER_STATUS_CHANGE', 'order.status_change' => $this->handleOrderStatusChange($webhookEvent, $payload),
            'PRODUCT_STATUS_CHANGE', 'product.status_change' => $this->handleProductStatusChange($webhookEvent, $payload),
            'PACKAGE_UPDATE', 'package.update' => $this->handlePackageUpdate($webhookEvent, $payload),
            'RETURN_REQUEST', 'return.request' => $this->handleReturnRequest($webhookEvent, $payload),
            default => Log::info('[TikTok Webhook] Unhandled event type', ['type' => $eventType]),
        };
    }

    /**
     * Handle order status change events.
     */
    private function handleOrderStatusChange(TikTokWebhookEvent $webhookEvent, array $payload): void
    {
        Log::info('[TikTok Webhook] Order status changed', [
            'order_id' => $payload['data']['order_id'] ?? 'unknown',
            'status' => $payload['data']['status'] ?? 'unknown',
        ]);

        // TODO: Implement order status sync
        // - Update local ProductOrder status
        // - Trigger notifications if needed
    }

    /**
     * Handle product status change events.
     */
    private function handleProductStatusChange(TikTokWebhookEvent $webhookEvent, array $payload): void
    {
        Log::info('[TikTok Webhook] Product status changed', [
            'product_id' => $payload['data']['product_id'] ?? 'unknown',
            'status' => $payload['data']['status'] ?? 'unknown',
        ]);

        // TODO: Implement product status sync
        // - Update product approval status
        // - Notify admin if rejected
    }

    /**
     * Handle package/shipping update events.
     */
    private function handlePackageUpdate(TikTokWebhookEvent $webhookEvent, array $payload): void
    {
        Log::info('[TikTok Webhook] Package update received', [
            'order_id' => $payload['data']['order_id'] ?? 'unknown',
            'tracking_number' => $payload['data']['tracking_number'] ?? 'unknown',
        ]);

        // TODO: Implement shipping tracking sync
    }

    /**
     * Handle return request events.
     */
    private function handleReturnRequest(TikTokWebhookEvent $webhookEvent, array $payload): void
    {
        Log::info('[TikTok Webhook] Return request received', [
            'order_id' => $payload['data']['order_id'] ?? 'unknown',
            'return_id' => $payload['data']['return_id'] ?? 'unknown',
        ]);

        // TODO: Implement return request handling
    }
}
