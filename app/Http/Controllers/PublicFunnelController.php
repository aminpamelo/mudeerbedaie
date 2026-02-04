<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\ResolvesAffiliateId;
use App\Models\Funnel;
use App\Models\FunnelAffiliate;
use App\Models\FunnelSession;
use App\Models\FunnelStep;
use App\Services\Funnel\FacebookPixelService;
use App\Services\Funnel\PuckRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PublicFunnelController extends Controller
{
    use ResolvesAffiliateId;

    public function __construct(
        protected PuckRenderer $renderer,
        protected FacebookPixelService $pixelService
    ) {}

    /**
     * Show funnel landing page with affiliate ref code (path-based).
     */
    public function showWithRef(Request $request, string $slug, string $refCode): View
    {
        $this->storeAffiliateRef($request, $slug, $refCode);

        return $this->show($request, $slug);
    }

    /**
     * Show specific funnel step with affiliate ref code (path-based).
     */
    public function showStepWithRef(Request $request, string $slug, string $stepSlug, string $refCode): View
    {
        $this->storeAffiliateRef($request, $slug, $refCode);

        return $this->showStep($request, $slug, $stepSlug);
    }

    /**
     * Store affiliate ref code in cookie and session.
     */
    protected function storeAffiliateRef(Request $request, string $slug, string $refCode): void
    {
        $affiliate = FunnelAffiliate::where('ref_code', $refCode)->active()->first();
        if (! $affiliate) {
            return;
        }

        $funnel = Funnel::where('slug', $slug)->first();
        if (! $funnel) {
            return;
        }

        // Verify affiliate has joined this funnel
        if (! $affiliate->funnels()->where('funnels.id', $funnel->id)->wherePivot('status', 'approved')->exists()) {
            return;
        }

        $cookieKey = "affiliate_ref_{$funnel->id}";
        cookie()->queue($cookieKey, $affiliate->id, 60 * 24 * 30);
        session([$cookieKey => $affiliate->id]);
    }

    /**
     * Show funnel landing page (first step).
     */
    public function show(Request $request, string $slug): View
    {
        $funnel = Funnel::where('slug', $slug)
            ->where('status', 'published')
            ->with(['steps' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->firstOrFail();

        // Get the first step
        $step = $funnel->steps->first();

        if (! $step) {
            abort(404, 'Funnel has no active steps');
        }

        return $this->renderStep($request, $funnel, $step);
    }

    /**
     * Show specific funnel step.
     */
    public function showStep(Request $request, string $slug, string $stepSlug): View
    {
        $funnel = Funnel::where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        $step = $funnel->steps()
            ->where('slug', $stepSlug)
            ->where('is_active', true)
            ->firstOrFail();

        return $this->renderStep($request, $funnel, $step);
    }

    /**
     * Render a funnel step.
     */
    protected function renderStep(Request $request, Funnel $funnel, FunnelStep $step): View
    {
        // Get or create session
        $session = $this->getOrCreateSession($request, $funnel);

        // Track page view
        $this->trackPageView($session, $step);

        // Get published content
        $content = $step->publishedContent;

        if (! $content) {
            // Fallback to draft content if no published version
            $content = $step->draftContent;
        }

        // Render Puck content to HTML
        $renderedContent = null;
        if ($content && ! empty($content->content)) {
            $context = [
                'funnel_uuid' => $funnel->uuid,
                'step_id' => $step->id,
                'session_uuid' => $session->uuid,
            ];
            $renderedContent = $this->renderer->render($content->content, $context);

            // Replace content placeholders
            $orderNumber = $request->query('order');
            if ($orderNumber) {
                $renderedContent = new \Illuminate\Support\HtmlString(
                    str_replace('[ORDER_NUMBER]', e($orderNumber), (string) $renderedContent)
                );
            }
        }

        // Get products for this step
        $products = $step->products()->with(['package.items'])->where('is_active', true)->orderBy('sort_order')->get();

        // Get order bumps for this step
        $orderBumps = $step->orderBumps()->where('is_active', true)->orderBy('sort_order')->get();

        // Get next step for navigation
        $nextStep = $step->next_step_id
            ? $funnel->steps()->find($step->next_step_id)
            : $funnel->steps()->where('sort_order', '>', $step->sort_order)->first();

        // Track Facebook Pixel events (server-side) and get event IDs for client-side
        $pixelData = $this->trackPixelEvents($request, $funnel, $step, $session, $products);

        return view('funnel.show', [
            'funnel' => $funnel,
            'step' => $step,
            'content' => $content,
            'renderedContent' => $renderedContent,
            'products' => $products,
            'orderBumps' => $orderBumps,
            'nextStep' => $nextStep,
            'session' => $session,
            'customCss' => $content?->custom_css,
            'customJs' => $content?->custom_js,
            'metaTitle' => $content?->meta_title ?: $step->name,
            'metaDescription' => $content?->meta_description ?: $funnel->description,
            'ogImage' => $content?->og_image ?: $funnel->thumbnail,
            // Pixel tracking data
            'pageViewEventId' => $pixelData['pageViewEventId'] ?? null,
            'viewContentEventId' => $pixelData['viewContentEventId'] ?? null,
            'viewContentData' => $pixelData['viewContentData'] ?? null,
            'initiateCheckoutEventId' => $pixelData['initiateCheckoutEventId'] ?? null,
            'checkoutData' => $pixelData['checkoutData'] ?? null,
            'purchaseEventId' => $pixelData['purchaseEventId'] ?? null,
            'purchaseData' => $pixelData['purchaseData'] ?? null,
        ]);
    }

    /**
     * Get or create a funnel session.
     */
    protected function getOrCreateSession(Request $request, Funnel $funnel): FunnelSession
    {
        $sessionKey = "funnel_session_{$funnel->id}";
        $sessionUuid = $request->cookie($sessionKey) ?: session($sessionKey);
        $isNewSession = false;
        $isNewVisitorToday = false;

        if ($sessionUuid) {
            $session = FunnelSession::where('uuid', $sessionUuid)->first();
            if ($session) {
                // Check if this is the first visit today (for daily unique visitor tracking)
                $lastVisitDate = $session->last_activity_at?->toDateString();
                $today = now()->toDateString();

                if ($lastVisitDate !== $today) {
                    $isNewVisitorToday = true;
                }

                $session->updateActivity();

                // Track daily unique visitor at funnel level
                if ($isNewVisitorToday) {
                    $funnelAnalytics = \App\Models\FunnelAnalytics::getOrCreateForToday($funnel->id);
                    $funnelAnalytics->incrementVisitors();
                }

                return $session;
            }
        }

        // Resolve affiliate ID from query param or cookie
        $affiliateId = $this->resolveAffiliateId($request, $funnel);

        // Create new session
        $session = FunnelSession::create([
            'funnel_id' => $funnel->id,
            'visitor_id' => $this->getVisitorId($request),
            'affiliate_id' => $affiliateId,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referrer' => $request->header('referer'),
            'utm_source' => $request->input('utm_source'),
            'utm_medium' => $request->input('utm_medium'),
            'utm_campaign' => $request->input('utm_campaign'),
            'utm_term' => $request->input('utm_term'),
            'utm_content' => $request->input('utm_content'),
            'entry_url' => $request->fullUrl(),
        ]);
        $isNewSession = true;

        // Track new unique visitor in analytics (funnel level)
        $funnelAnalytics = \App\Models\FunnelAnalytics::getOrCreateForToday($funnel->id);
        $funnelAnalytics->incrementVisitors();

        // Store session UUID in cookie (30 days) and session
        cookie()->queue($sessionKey, $session->uuid, 60 * 24 * 30);
        session([$sessionKey => $session->uuid]);

        return $session;
    }

    /**
     * Get or create visitor ID.
     */
    protected function getVisitorId(Request $request): string
    {
        $visitorId = $request->cookie('funnel_visitor_id') ?: session('funnel_visitor_id');

        if (! $visitorId) {
            $visitorId = Str::uuid()->toString();
            cookie()->queue('funnel_visitor_id', $visitorId, 60 * 24 * 365);
            session(['funnel_visitor_id' => $visitorId]);
        }

        return $visitorId;
    }

    /**
     * Track page view event.
     */
    protected function trackPageView(FunnelSession $session, FunnelStep $step): void
    {
        $session->trackEvent('page_view', [
            'step_id' => $step->id,
            'step_name' => $step->name,
            'step_type' => $step->type,
        ]);

        // Check if this is the first visit to this step today
        $today = now()->toDateString();
        $visitedStepsToday = session("funnel_{$session->funnel_id}_visited_steps_{$today}", []);
        $isFirstStepVisitToday = ! in_array($step->id, $visitedStepsToday);

        // Update current step
        $session->update(['current_step_id' => $step->id]);

        // Update analytics
        $this->updateAnalytics($session->funnel_id, $step->id, $isFirstStepVisitToday);

        // Mark this step as visited today
        if ($isFirstStepVisitToday) {
            $visitedStepsToday[] = $step->id;
            session(["funnel_{$session->funnel_id}_visited_steps_{$today}" => $visitedStepsToday]);
        }
    }

    /**
     * Update funnel analytics.
     */
    protected function updateAnalytics(int $funnelId, int $stepId, bool $isFirstStepVisitToday = false): void
    {
        // Update funnel-level analytics (pageviews only, visitors tracked in getOrCreateSession)
        $funnelAnalytics = \App\Models\FunnelAnalytics::getOrCreateForToday($funnelId);
        $funnelAnalytics->incrementPageviews();

        // Update step-level analytics
        $stepAnalytics = \App\Models\FunnelAnalytics::getOrCreateForToday($funnelId, $stepId);
        $stepAnalytics->incrementPageviews();

        // Track step-level unique visitor (first visit to this step today)
        if ($isFirstStepVisitToday) {
            $stepAnalytics->incrementVisitors();
        }
    }

    /**
     * Track Facebook Pixel events and return event IDs for browser-side deduplication.
     */
    protected function trackPixelEvents(
        \Illuminate\Http\Request $request,
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

        // Track PageView (always)
        $data['pageViewEventId'] = $this->pixelService->trackPageView($funnel, $request, $session);

        // Track ViewContent for landing/sales pages with products
        if (in_array($step->type, ['landing', 'sales', 'optin']) && $products->isNotEmpty()) {
            $contentIds = $products->pluck('product_id')->filter()->map(fn ($id) => (string) $id)->toArray();
            $totalValue = $products->sum('funnel_price');

            $data['viewContentData'] = [
                'content_ids' => $contentIds,
                'content_name' => $step->name,
                'value' => $totalValue,
                'currency' => 'MYR',
            ];

            $data['viewContentEventId'] = $this->pixelService->trackViewContent(
                $funnel,
                $request,
                $data['viewContentData'],
                $session
            );
        }

        // Track InitiateCheckout for checkout pages
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

        // Track Purchase for thank you pages (get data from session's completed order)
        if ($step->type === 'thankyou') {
            $funnelOrder = $session->orders()->with('productOrder.items')->latest()->first();

            if ($funnelOrder && $funnelOrder->productOrder) {
                $order = $funnelOrder->productOrder;

                // Check if we already have an event ID from server-side tracking
                $purchaseEventId = $order->metadata['fb_purchase_event_id'] ?? null;

                if ($purchaseEventId) {
                    $contentIds = $order->items->pluck('product_id')->filter()->map(fn ($id) => (string) $id)->toArray();

                    $data['purchaseEventId'] = $purchaseEventId;
                    $data['purchaseData'] = [
                        'content_ids' => $contentIds,
                        'contents' => $order->items->map(fn ($item) => [
                            'id' => (string) ($item->product_id ?? $item->id),
                            'quantity' => $item->quantity,
                            'item_price' => (float) $item->unit_price,
                        ])->toArray(),
                        'value' => (float) $order->total_amount,
                        'currency' => strtoupper($order->currency ?? 'MYR'),
                        'num_items' => $order->items->sum('quantity'),
                    ];
                }
            }
        }

        return $data;
    }

    /**
     * Handle opt-in form submission.
     */
    public function submitOptin(Request $request, string $slug): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'step_id' => ['required', 'integer'],
        ]);

        $funnel = Funnel::where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        $step = $funnel->steps()->findOrFail($request->input('step_id'));

        // Get session
        $sessionKey = "funnel_session_{$funnel->id}";
        $sessionUuid = $request->cookie($sessionKey) ?: session($sessionKey);
        $session = $sessionUuid ? FunnelSession::where('uuid', $sessionUuid)->first() : null;

        if ($session) {
            // Update session with contact info
            $session->update([
                'email' => $request->input('email'),
                'phone' => $request->input('phone'),
            ]);

            // Track opt-in event
            $session->trackEvent('optin', [
                'step_id' => $step->id,
                'email' => $request->input('email'),
            ]);

            // Track Facebook Pixel Lead event
            $this->pixelService->trackLead($funnel, $request, [
                'email' => $request->input('email'),
                'name' => $request->input('name'),
                'phone' => $request->input('phone'),
                'content_name' => $funnel->name.' - '.$step->name,
            ], $session);
        }

        // Get next step URL
        $nextStep = $step->next_step_id
            ? $funnel->steps()->find($step->next_step_id)
            : $funnel->steps()->where('sort_order', '>', $step->sort_order)->first();

        $redirectUrl = $nextStep
            ? "/f/{$funnel->slug}/{$nextStep->slug}"
            : "/f/{$funnel->slug}";

        return response()->json([
            'success' => true,
            'redirect_url' => $redirectUrl,
        ]);
    }

    /**
     * Recover abandoned cart.
     */
    public function recoverCart(Request $request, string $slug, string $sessionUuid): View
    {
        $funnel = Funnel::where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        $session = FunnelSession::where('uuid', $sessionUuid)
            ->where('funnel_id', $funnel->id)
            ->firstOrFail();

        // Get the cart
        $cart = $session->cart;

        if (! $cart) {
            // No cart found, redirect to funnel
            return redirect("/f/{$funnel->slug}");
        }

        // Track recovery click
        $session->trackEvent('cart_recovery_click', [
            'cart_id' => $cart->id,
        ]);

        // Get checkout step
        $checkoutStep = $funnel->steps()
            ->where('type', 'checkout')
            ->first();

        if ($checkoutStep) {
            // Store session in cookie and redirect to checkout
            $sessionKey = "funnel_session_{$funnel->id}";
            cookie()->queue($sessionKey, $session->uuid, 60 * 24 * 30);

            return redirect("/f/{$funnel->slug}/{$checkoutStep->slug}");
        }

        return redirect("/f/{$funnel->slug}");
    }
}
