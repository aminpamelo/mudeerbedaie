<?php

namespace App\Services\Funnel;

use App\Models\Funnel;
use App\Models\FunnelSession;
use App\Models\ProductOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FacebookPixelService
{
    protected const API_VERSION = 'v21.0';

    protected const API_BASE_URL = 'https://graph.facebook.com';

    /**
     * Standard Facebook Pixel events.
     */
    public const EVENT_PAGE_VIEW = 'PageView';

    public const EVENT_VIEW_CONTENT = 'ViewContent';

    public const EVENT_ADD_TO_CART = 'AddToCart';

    public const EVENT_INITIATE_CHECKOUT = 'InitiateCheckout';

    public const EVENT_PURCHASE = 'Purchase';

    public const EVENT_LEAD = 'Lead';

    public const EVENT_COMPLETE_REGISTRATION = 'CompleteRegistration';

    /**
     * Get pixel settings from funnel.
     */
    public function getPixelSettings(Funnel $funnel): array
    {
        $settings = $funnel->settings['pixel_settings']['facebook'] ?? [];

        return [
            'enabled' => $settings['enabled'] ?? false,
            'pixel_id' => $settings['pixel_id'] ?? '',
            'access_token' => $settings['access_token'] ?? '',
            'test_event_code' => $settings['test_event_code'] ?? '',
            'events' => $settings['events'] ?? [
                'page_view' => true,
                'view_content' => true,
                'add_to_cart' => true,
                'initiate_checkout' => true,
                'purchase' => true,
                'lead' => true,
            ],
        ];
    }

    /**
     * Check if pixel is enabled for a funnel.
     */
    public function isEnabled(Funnel $funnel): bool
    {
        $settings = $this->getPixelSettings($funnel);

        return $settings['enabled'] && ! empty($settings['pixel_id']);
    }

    /**
     * Check if Conversions API is enabled (has access token).
     */
    public function isConversionsApiEnabled(Funnel $funnel): bool
    {
        $settings = $this->getPixelSettings($funnel);

        return $this->isEnabled($funnel) && ! empty($settings['access_token']);
    }

    /**
     * Check if a specific event is enabled.
     */
    public function isEventEnabled(Funnel $funnel, string $eventKey): bool
    {
        $settings = $this->getPixelSettings($funnel);

        return $settings['events'][$eventKey] ?? true;
    }

    /**
     * Generate a unique event ID for deduplication.
     */
    public function generateEventId(): string
    {
        return Str::uuid()->toString();
    }

    /**
     * Send event to Facebook Conversions API (server-side).
     */
    public function sendServerEvent(
        Funnel $funnel,
        string $eventName,
        array $userData = [],
        array $customData = [],
        ?string $eventId = null,
        ?string $eventSourceUrl = null
    ): bool {
        if (! $this->isConversionsApiEnabled($funnel)) {
            return false;
        }

        $settings = $this->getPixelSettings($funnel);
        $pixelId = trim($settings['pixel_id'] ?? '');
        $accessToken = trim($settings['access_token'] ?? '');
        $testEventCode = trim($settings['test_event_code'] ?? '');

        $eventId = $eventId ?? $this->generateEventId();

        $eventData = [
            'event_name' => $eventName,
            'event_time' => now()->timestamp,
            'event_id' => $eventId,
            'action_source' => 'website',
            'user_data' => $this->prepareUserData($userData),
        ];

        if ($eventSourceUrl) {
            $eventData['event_source_url'] = $eventSourceUrl;
        }

        if (! empty($customData)) {
            $eventData['custom_data'] = $customData;
        }

        $payload = [
            'data' => json_encode([$eventData]),
            'access_token' => $accessToken,
        ];

        if (! empty($testEventCode)) {
            $payload['test_event_code'] = $testEventCode;
        }

        try {
            $response = Http::asForm()->post(
                self::API_BASE_URL.'/'.self::API_VERSION."/{$pixelId}/events",
                $payload
            );

            if ($response->successful()) {
                Log::info('Facebook Conversions API event sent', [
                    'funnel_id' => $funnel->id,
                    'event_name' => $eventName,
                    'event_id' => $eventId,
                    'response' => $response->json(),
                ]);

                return true;
            }

            Log::error('Facebook Conversions API error', [
                'funnel_id' => $funnel->id,
                'event_name' => $eventName,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return false;

        } catch (\Exception $e) {
            Log::error('Facebook Conversions API exception', [
                'funnel_id' => $funnel->id,
                'event_name' => $eventName,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Prepare user data for Conversions API (hash PII).
     */
    protected function prepareUserData(array $userData): array
    {
        $prepared = [];

        // Hash email if provided
        if (! empty($userData['email'])) {
            $prepared['em'] = [hash('sha256', strtolower(trim($userData['email'])))];
        }

        // Hash phone if provided (remove non-digits first)
        if (! empty($userData['phone'])) {
            $phone = preg_replace('/[^0-9]/', '', $userData['phone']);
            $prepared['ph'] = [hash('sha256', $phone)];
        }

        // Hash first name if provided
        if (! empty($userData['first_name'])) {
            $prepared['fn'] = [hash('sha256', strtolower(trim($userData['first_name'])))];
        }

        // Hash last name if provided
        if (! empty($userData['last_name'])) {
            $prepared['ln'] = [hash('sha256', strtolower(trim($userData['last_name'])))];
        }

        // Hash city if provided
        if (! empty($userData['city'])) {
            $prepared['ct'] = [hash('sha256', strtolower(str_replace(' ', '', $userData['city'])))];
        }

        // Hash state if provided
        if (! empty($userData['state'])) {
            $prepared['st'] = [hash('sha256', strtolower(str_replace(' ', '', $userData['state'])))];
        }

        // Hash zip/postal code if provided
        if (! empty($userData['zip'])) {
            $prepared['zp'] = [hash('sha256', strtolower(str_replace(' ', '', $userData['zip'])))];
        }

        // Country code (2-letter, lowercase)
        if (! empty($userData['country'])) {
            $prepared['country'] = [hash('sha256', strtolower($userData['country']))];
        }

        // External ID (not hashed - customer ID from your system)
        if (! empty($userData['external_id'])) {
            $prepared['external_id'] = [$userData['external_id']];
        }

        // Client IP address (not hashed)
        if (! empty($userData['client_ip_address'])) {
            $prepared['client_ip_address'] = $userData['client_ip_address'];
        }

        // Client user agent (not hashed)
        if (! empty($userData['client_user_agent'])) {
            $prepared['client_user_agent'] = $userData['client_user_agent'];
        }

        // Facebook click ID from cookie (not hashed)
        if (! empty($userData['fbc'])) {
            $prepared['fbc'] = $userData['fbc'];
        }

        // Facebook browser ID from cookie (not hashed)
        if (! empty($userData['fbp'])) {
            $prepared['fbp'] = $userData['fbp'];
        }

        return $prepared;
    }

    /**
     * Extract user data from request and session.
     */
    public function extractUserDataFromRequest(Request $request, ?FunnelSession $session = null): array
    {
        $userData = [
            'client_ip_address' => $request->ip(),
            'client_user_agent' => $request->userAgent(),
            'fbc' => $request->cookie('_fbc'),
            'fbp' => $request->cookie('_fbp'),
        ];

        if ($session) {
            if ($session->email) {
                $userData['email'] = $session->email;
            }
            if ($session->phone) {
                $userData['phone'] = $session->phone;
            }
            $userData['external_id'] = $session->uuid;
        }

        return $userData;
    }

    /**
     * Track PageView event.
     */
    public function trackPageView(
        Funnel $funnel,
        Request $request,
        ?FunnelSession $session = null,
        ?string $eventId = null
    ): ?string {
        if (! $this->isEnabled($funnel) || ! $this->isEventEnabled($funnel, 'page_view')) {
            return null;
        }

        $eventId = $eventId ?? $this->generateEventId();
        $userData = $this->extractUserDataFromRequest($request, $session);

        $this->sendServerEvent(
            $funnel,
            self::EVENT_PAGE_VIEW,
            $userData,
            [],
            $eventId,
            $request->fullUrl()
        );

        return $eventId;
    }

    /**
     * Track ViewContent event.
     */
    public function trackViewContent(
        Funnel $funnel,
        Request $request,
        array $contentData,
        ?FunnelSession $session = null,
        ?string $eventId = null
    ): ?string {
        if (! $this->isEnabled($funnel) || ! $this->isEventEnabled($funnel, 'view_content')) {
            return null;
        }

        $eventId = $eventId ?? $this->generateEventId();
        $userData = $this->extractUserDataFromRequest($request, $session);

        $customData = [
            'content_type' => 'product',
            'currency' => $contentData['currency'] ?? 'MYR',
        ];

        if (! empty($contentData['content_ids'])) {
            $customData['content_ids'] = $contentData['content_ids'];
        }

        if (! empty($contentData['content_name'])) {
            $customData['content_name'] = $contentData['content_name'];
        }

        if (isset($contentData['value'])) {
            $customData['value'] = $contentData['value'];
        }

        $this->sendServerEvent(
            $funnel,
            self::EVENT_VIEW_CONTENT,
            $userData,
            $customData,
            $eventId,
            $request->fullUrl()
        );

        return $eventId;
    }

    /**
     * Track AddToCart event.
     */
    public function trackAddToCart(
        Funnel $funnel,
        Request $request,
        array $cartData,
        ?FunnelSession $session = null,
        ?string $eventId = null
    ): ?string {
        if (! $this->isEnabled($funnel) || ! $this->isEventEnabled($funnel, 'add_to_cart')) {
            return null;
        }

        $eventId = $eventId ?? $this->generateEventId();
        $userData = $this->extractUserDataFromRequest($request, $session);

        $customData = [
            'content_type' => 'product',
            'currency' => $cartData['currency'] ?? 'MYR',
            'value' => $cartData['value'] ?? 0,
        ];

        if (! empty($cartData['content_ids'])) {
            $customData['content_ids'] = $cartData['content_ids'];
        }

        if (! empty($cartData['contents'])) {
            $customData['contents'] = $cartData['contents'];
        }

        $this->sendServerEvent(
            $funnel,
            self::EVENT_ADD_TO_CART,
            $userData,
            $customData,
            $eventId,
            $request->fullUrl()
        );

        return $eventId;
    }

    /**
     * Track InitiateCheckout event.
     */
    public function trackInitiateCheckout(
        Funnel $funnel,
        Request $request,
        array $checkoutData,
        ?FunnelSession $session = null,
        ?string $eventId = null
    ): ?string {
        if (! $this->isEnabled($funnel) || ! $this->isEventEnabled($funnel, 'initiate_checkout')) {
            return null;
        }

        $eventId = $eventId ?? $this->generateEventId();
        $userData = $this->extractUserDataFromRequest($request, $session);

        $customData = [
            'content_type' => 'product',
            'currency' => $checkoutData['currency'] ?? 'MYR',
            'value' => $checkoutData['value'] ?? 0,
            'num_items' => $checkoutData['num_items'] ?? 1,
        ];

        if (! empty($checkoutData['content_ids'])) {
            $customData['content_ids'] = $checkoutData['content_ids'];
        }

        if (! empty($checkoutData['contents'])) {
            $customData['contents'] = $checkoutData['contents'];
        }

        $this->sendServerEvent(
            $funnel,
            self::EVENT_INITIATE_CHECKOUT,
            $userData,
            $customData,
            $eventId,
            $request->fullUrl()
        );

        return $eventId;
    }

    /**
     * Track Purchase event.
     */
    public function trackPurchase(
        Funnel $funnel,
        ProductOrder $order,
        ?FunnelSession $session = null,
        ?string $eventId = null,
        ?string $eventSourceUrl = null
    ): ?string {
        if (! $this->isEnabled($funnel) || ! $this->isEventEnabled($funnel, 'purchase')) {
            return null;
        }

        $eventId = $eventId ?? $this->generateEventId();

        // Build user data from order
        $userData = [
            'email' => $order->email,
            'phone' => $order->customer_phone,
            'external_id' => $session?->uuid ?? $order->order_number,
        ];

        // Add name if available
        if ($order->customer_name) {
            $nameParts = explode(' ', $order->customer_name, 2);
            $userData['first_name'] = $nameParts[0] ?? '';
            $userData['last_name'] = $nameParts[1] ?? '';
        }

        // Add billing address data if available
        if (is_array($order->billing_address)) {
            $userData['city'] = $order->billing_address['city'] ?? '';
            $userData['state'] = $order->billing_address['state'] ?? '';
            $userData['zip'] = $order->billing_address['postcode'] ?? $order->billing_address['zip'] ?? '';
            $userData['country'] = $order->billing_address['country'] ?? 'MY';
        }

        // Build content data
        $contents = [];
        $contentIds = [];

        foreach ($order->items as $item) {
            $contentIds[] = (string) ($item->product_id ?? $item->id);
            $contents[] = [
                'id' => (string) ($item->product_id ?? $item->id),
                'quantity' => $item->quantity,
                'item_price' => (float) $item->unit_price,
            ];
        }

        $customData = [
            'content_type' => 'product',
            'content_ids' => $contentIds,
            'contents' => $contents,
            'currency' => strtoupper($order->currency ?? 'MYR'),
            'value' => (float) $order->total_amount,
            'num_items' => $order->items->sum('quantity'),
        ];

        $this->sendServerEvent(
            $funnel,
            self::EVENT_PURCHASE,
            $userData,
            $customData,
            $eventId,
            $eventSourceUrl ?? url("/f/{$funnel->slug}")
        );

        // Store event ID in order metadata for client-side deduplication
        $metadata = $order->metadata ?? [];
        $metadata['fb_purchase_event_id'] = $eventId;
        $order->update(['metadata' => $metadata]);

        return $eventId;
    }

    /**
     * Track Lead event (opt-in form submission).
     */
    public function trackLead(
        Funnel $funnel,
        Request $request,
        array $leadData,
        ?FunnelSession $session = null,
        ?string $eventId = null
    ): ?string {
        if (! $this->isEnabled($funnel) || ! $this->isEventEnabled($funnel, 'lead')) {
            return null;
        }

        $eventId = $eventId ?? $this->generateEventId();

        $userData = $this->extractUserDataFromRequest($request, $session);

        // Override with form data
        if (! empty($leadData['email'])) {
            $userData['email'] = $leadData['email'];
        }
        if (! empty($leadData['phone'])) {
            $userData['phone'] = $leadData['phone'];
        }
        if (! empty($leadData['name'])) {
            $nameParts = explode(' ', $leadData['name'], 2);
            $userData['first_name'] = $nameParts[0] ?? '';
            $userData['last_name'] = $nameParts[1] ?? '';
        }

        $customData = [
            'currency' => $leadData['currency'] ?? 'MYR',
        ];

        if (isset($leadData['value'])) {
            $customData['value'] = $leadData['value'];
        }

        if (! empty($leadData['content_name'])) {
            $customData['content_name'] = $leadData['content_name'];
        }

        $this->sendServerEvent(
            $funnel,
            self::EVENT_LEAD,
            $userData,
            $customData,
            $eventId,
            $request->fullUrl()
        );

        return $eventId;
    }

    /**
     * Generate browser-side pixel initialization code.
     */
    public function getPixelInitCode(Funnel $funnel): string
    {
        if (! $this->isEnabled($funnel)) {
            return '';
        }

        $settings = $this->getPixelSettings($funnel);
        $pixelId = $settings['pixel_id'];

        return <<<JS
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '{$pixelId}');
JS;
    }

    /**
     * Generate browser-side event tracking code.
     */
    public function getEventTrackCode(string $eventName, array $params = [], ?string $eventId = null): string
    {
        $paramsJson = ! empty($params) ? json_encode($params) : '{}';
        $options = $eventId ? json_encode(['eventID' => $eventId]) : '{}';

        return "fbq('track', '{$eventName}', {$paramsJson}, {$options});";
    }

    /**
     * Test the Conversions API connection.
     */
    public function testConnection(Funnel $funnel): array
    {
        $settings = $this->getPixelSettings($funnel);

        // Trim any whitespace from pixel ID and access token
        $pixelId = trim($settings['pixel_id'] ?? '');
        $accessToken = trim($settings['access_token'] ?? '');

        if (empty($pixelId)) {
            return [
                'success' => false,
                'message' => 'Pixel ID is not configured',
            ];
        }

        if (empty($accessToken)) {
            return [
                'success' => false,
                'message' => 'Access token is not configured for Conversions API',
            ];
        }

        // Send a test event
        $testEventId = $this->generateEventId();
        $testEventCode = trim($settings['test_event_code'] ?? '') ?: 'TEST'.Str::random(5);

        // Facebook Conversions API requires sufficient user data parameters
        // We use hashed test data to pass validation while keeping it clearly identifiable as a test
        $testEmail = hash('sha256', 'test@example.com');
        $testIp = request()->ip() ?? '127.0.0.1';

        $eventData = [
            'event_name' => 'PageView',
            'event_time' => now()->timestamp,
            'event_id' => $testEventId,
            'action_source' => 'website',
            'event_source_url' => url('/'),
            'user_data' => [
                'em' => [$testEmail], // Hashed email (required for matching)
                'client_ip_address' => $testIp,
                'client_user_agent' => request()->userAgent() ?? 'Facebook Pixel Test/1.0',
            ],
        ];

        $payload = [
            'data' => json_encode([$eventData]),
            'access_token' => $accessToken,
            'test_event_code' => $testEventCode,
        ];

        $url = self::API_BASE_URL.'/'.self::API_VERSION."/{$pixelId}/events";

        Log::info('Facebook Pixel test connection attempt', [
            'funnel_id' => $funnel->id,
            'pixel_id' => $pixelId,
            'url' => $url,
            'event_id' => $testEventId,
        ]);

        try {
            $response = Http::asForm()->post($url, $payload);

            $responseData = $response->json();

            Log::info('Facebook Pixel test connection response', [
                'funnel_id' => $funnel->id,
                'status' => $response->status(),
                'response' => $responseData,
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Connection successful! Events received: '.($responseData['events_received'] ?? 0),
                    'response' => $responseData,
                ];
            }

            $errorMessage = $responseData['error']['message'] ?? 'Unknown error';
            $errorCode = $responseData['error']['code'] ?? null;
            $errorType = $responseData['error']['type'] ?? null;
            $errorSubcode = $responseData['error']['error_subcode'] ?? null;

            // Provide more specific error messages
            if ($errorCode === 190) {
                $errorMessage = 'Invalid or expired access token. Please generate a new token in Facebook Events Manager.';
            } elseif ($errorCode === 100 && $errorSubcode === 2804050) {
                // Insufficient user data - this shouldn't happen with our updated test, but handle it anyway
                $errorMessage = 'Connection successful but insufficient user data. Events will work with real visitor data.';

                // Return success since the credentials are valid
                return [
                    'success' => true,
                    'message' => 'Credentials verified! Server-side tracking is ready.',
                    'warning' => 'Test event had insufficient user data, but real events will include visitor information.',
                ];
            } elseif ($errorCode === 100 && str_contains($errorMessage, 'Invalid parameter')) {
                $errorMessage = 'Invalid Pixel ID or the access token does not have permission for this Pixel.';
            } elseif ($errorCode === 100 && str_contains($errorMessage, 'access_token')) {
                $errorMessage = 'Invalid access token format. Please copy the full token from Facebook Events Manager.';
            }

            return [
                'success' => false,
                'message' => $errorMessage,
                'error_code' => $errorCode,
                'error_type' => $errorType,
            ];

        } catch (\Exception $e) {
            Log::error('Facebook Pixel test connection exception', [
                'funnel_id' => $funnel->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Connection failed: '.$e->getMessage(),
            ];
        }
    }
}
