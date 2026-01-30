<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\ResolvesAffiliateId;
use App\Models\Funnel;
use App\Models\FunnelSession;
use App\Models\FunnelStep;
use App\Services\Funnel\FacebookPixelService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FunnelEmbedController extends Controller
{
    use ResolvesAffiliateId;

    public function __construct(
        protected FacebookPixelService $pixelService
    ) {}

    /**
     * Serve the embed.js script
     */
    public function script()
    {
        $content = view('funnel.embed-script')->render();

        return response($content, 200)
            ->header('Content-Type', 'application/javascript')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * Display the embedded checkout form
     */
    public function show(Request $request, string $embedKey)
    {
        $funnel = Funnel::where('embed_key', $embedKey)
            ->where('embed_enabled', true)
            ->where('status', 'published')
            ->firstOrFail();

        // Get the checkout step
        $step = $funnel->steps()
            ->where('type', 'checkout')
            ->where('is_active', true)
            ->first();

        if (! $step) {
            // Fallback to first active step with products
            $step = $funnel->steps()
                ->where('is_active', true)
                ->whereHas('products')
                ->first();
        }

        if (! $step) {
            abort(404, 'No checkout step found');
        }

        // Create or retrieve session
        $sessionUuid = $request->query('session_uuid') ?? $request->cookie('funnel_session_'.$funnel->id);
        $visitorId = $request->cookie('funnel_visitor_id') ?? Str::uuid()->toString();
        $session = null;

        if ($sessionUuid) {
            $session = FunnelSession::where('uuid', $sessionUuid)
                ->where('funnel_id', $funnel->id)
                ->first();
        }

        if (! $session) {
            $affiliateId = $this->resolveAffiliateId($request, $funnel);

            $session = FunnelSession::create([
                'funnel_id' => $funnel->id,
                'uuid' => Str::uuid()->toString(),
                'visitor_id' => $visitorId,
                'affiliate_id' => $affiliateId,
                'entry_step_id' => $step->id,
                'current_step_id' => $step->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'utm_source' => $request->query('utm_source'),
                'utm_medium' => $request->query('utm_medium'),
                'utm_campaign' => $request->query('utm_campaign'),
                'utm_term' => $request->query('utm_term'),
                'utm_content' => $request->query('utm_content'),
                'status' => 'active',
                'started_at' => now(),
                'last_activity_at' => now(),
                'metadata' => [
                    'embedded' => true,
                    'embed_origin' => $request->query('origin'),
                    'referrer' => $request->query('referrer') ?? $request->header('Referer'),
                ],
            ]);

            // Track entry event
            $session->trackEvent('session_started', [
                'embedded' => true,
                'origin' => $request->query('origin'),
            ], $step);
        }

        // Get embed settings
        $embedSettings = $funnel->embed_settings ?? [];

        // Get products for this step (for pixel tracking)
        $products = $step->products()->where('is_active', true)->orderBy('sort_order')->get();

        // Track Facebook Pixel events (server-side)
        $pixelData = $this->trackPixelEvents($request, $funnel, $step, $session, $products);

        return view('funnel.embed', [
            'funnel' => $funnel,
            'step' => $step,
            'session' => $session,
            'embedSettings' => $embedSettings,
            // Pixel tracking data
            'pageViewEventId' => $pixelData['pageViewEventId'] ?? null,
            'initiateCheckoutEventId' => $pixelData['initiateCheckoutEventId'] ?? null,
            'checkoutData' => $pixelData['checkoutData'] ?? null,
        ])->withCookie(
            cookie('funnel_session_'.$funnel->id, $session->uuid, 60 * 24 * 7) // 7 days
        )->withCookie(
            cookie('funnel_visitor_id', $visitorId, 60 * 24 * 365) // 1 year
        );
    }

    /**
     * Generate embed code for a funnel
     */
    public function generateEmbedCode(Funnel $funnel)
    {
        // Generate embed key if not exists
        if (! $funnel->embed_key) {
            $funnel->update([
                'embed_key' => Str::random(32),
            ]);
        }

        $embedUrl = route('funnel.embed', ['embedKey' => $funnel->embed_key]);
        $scriptUrl = route('funnel.embed.script');

        $iframeCode = <<<HTML
<!-- Funnel Checkout Embed -->
<iframe
    src="{$embedUrl}"
    width="100%"
    height="800"
    frameborder="0"
    allow="payment"
    style="border: none; max-width: 500px; margin: 0 auto; display: block;"
></iframe>
HTML;

        $scriptCode = <<<HTML
<!-- Funnel Checkout Widget -->
<div id="funnel-checkout-{$funnel->embed_key}"></div>
<script src="{$scriptUrl}" data-funnel-key="{$funnel->embed_key}"></script>
HTML;

        return response()->json([
            'embed_key' => $funnel->embed_key,
            'embed_url' => $embedUrl,
            'script_url' => $scriptUrl,
            'codes' => [
                'iframe' => $iframeCode,
                'script' => $scriptCode,
            ],
        ]);
    }

    /**
     * Toggle embed enabled status
     */
    public function toggleEmbed(Request $request, Funnel $funnel)
    {
        $validated = $request->validate([
            'enabled' => 'required|boolean',
        ]);

        // Generate embed key if enabling and not exists
        if ($validated['enabled'] && ! $funnel->embed_key) {
            $funnel->embed_key = Str::random(32);
        }

        $funnel->update([
            'embed_enabled' => $validated['enabled'],
            'embed_key' => $funnel->embed_key,
        ]);

        return response()->json([
            'success' => true,
            'embed_enabled' => $funnel->embed_enabled,
            'embed_key' => $funnel->embed_key,
        ]);
    }

    /**
     * Update embed settings
     */
    public function updateSettings(Request $request, Funnel $funnel)
    {
        $validated = $request->validate([
            'allowed_domains' => 'nullable|array',
            'allowed_domains.*' => 'string',
            'theme' => 'nullable|in:light,dark,auto',
            'primary_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'border_radius' => 'nullable|in:none,sm,md,lg,xl,2xl',
            'show_powered_by' => 'nullable|boolean',
        ]);

        $funnel->update([
            'embed_settings' => $validated,
        ]);

        return response()->json([
            'success' => true,
            'embed_settings' => $funnel->embed_settings,
        ]);
    }

    /**
     * Regenerate embed key
     */
    public function regenerateKey(Funnel $funnel)
    {
        $funnel->update([
            'embed_key' => Str::random(32),
        ]);

        return response()->json([
            'success' => true,
            'embed_key' => $funnel->embed_key,
        ]);
    }

    /**
     * Track Facebook Pixel events for embedded funnel.
     */
    protected function trackPixelEvents(
        Request $request,
        Funnel $funnel,
        FunnelStep $step,
        FunnelSession $session,
        $products
    ): array {
        $data = [];

        // Skip if pixel is not enabled
        if (! $this->pixelService->isEnabled($funnel)) {
            return $data;
        }

        // Track PageView
        $data['pageViewEventId'] = $this->pixelService->trackPageView($funnel, $request, $session);

        // Track InitiateCheckout for checkout pages (embedded is typically checkout)
        if ($step->type === 'checkout') {
            $contentIds = $products->pluck('product_id')->filter()->map(fn ($id) => (string) $id)->toArray();
            $totalValue = $products->sum('funnel_price');

            $data['checkoutData'] = [
                'content_ids' => $contentIds,
                'value' => $totalValue,
                'currency' => 'MYR',
                'num_items' => $products->count(),
            ];

            $data['initiateCheckoutEventId'] = $this->pixelService->trackInitiateCheckout(
                $funnel,
                $request,
                $data['checkoutData'],
                $session
            );
        }

        return $data;
    }
}
