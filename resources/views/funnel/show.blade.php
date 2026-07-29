<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $metaTitle }} | {{ config('app.name') }}</title>
    <meta name="description" content="{{ $metaDescription }}">

    <!-- Open Graph -->
    <meta property="og:title" content="{{ $metaTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    @if($ogImage)
        <meta property="og:image" content="{{ $ogImage }}">
    @endif
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">

    <!-- Favicon -->
    @if($funnel->settings['favicon'] ?? null)
        <link rel="icon" href="{{ $funnel->settings['favicon'] }}">
    @endif

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Styles -->
    @vite(['resources/css/app.css'])

    @include('funnel.partials.checkout-styles')

    <!-- Custom CSS -->
    @if($customCss)
        <style>{!! $customCss !!}</style>
    @endif

    <!-- Tracking Codes (Head) -->
    @if($funnel->settings['tracking_codes']['head'] ?? null)
        {!! $funnel->settings['tracking_codes']['head'] !!}
    @endif

    <!-- Facebook Pixel -->
    @include('funnel.partials.facebook-pixel')

    <!-- Google (GA4 + Google Ads) -->
    @include('funnel.partials.google-tracking')
</head>
<body>
    <!-- Progress Bar (if enabled) -->
    @if($step->settings['show_progress'] ?? false)
        @php
            $totalSteps = $funnel->steps()->where('is_active', true)->count();
            $currentStepOrder = $step->sort_order + 1;
            $progressPercent = ($currentStepOrder / $totalSteps) * 100;
        @endphp
        <div class="funnel-progress" style="position: fixed; top: 0; left: 0; right: 0; z-index: 1000;">
            <div style="height: 4px; background: #e5e7eb;">
                <div style="height: 100%; width: {{ $progressPercent }}%; background: linear-gradient(to right, #3b82f6, #8b5cf6); transition: width 0.3s ease;"></div>
            </div>
        </div>
    @endif

    <!-- Main Content -->
    <main class="funnel-content">
        @if($renderedContent)
            {!! $renderedContent !!}
        @else
            <!-- Fallback content when no Puck content -->
            <div style="min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 40px;">
                <div style="text-align: center; max-width: 600px;">
                    <h1 style="font-size: 2rem; color: #111827; margin-bottom: 16px;">{{ $step->name }}</h1>
                    <p style="color: #6b7280;">This page is being built. Check back soon!</p>
                </div>
            </div>
        @endif
    </main>

    <!-- Livewire Checkout Component (for checkout steps, or when a [checkout_form] tag / Checkout Form block is present) -->
    @php
        $hasCheckoutMarker = $renderedContent && str_contains((string) $renderedContent, 'id="funnel-checkout-form"');
    @endphp
    @if($step->type === 'checkout' || $hasCheckoutMarker)
        <div id="funnel-checkout-container" style="max-width: 1200px; margin: 0 auto; padding: 32px 16px;">
            @livewire('funnel.checkout-form', [
                'funnel' => $funnel,
                'step' => $step,
                'session' => $session,
            ])
        </div>

        {{-- Move checkout form into the Puck placeholder if it exists --}}
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const placeholder = document.getElementById('funnel-checkout-form');
                const checkoutContainer = document.getElementById('funnel-checkout-container');
                if (placeholder && checkoutContainer) {
                    placeholder.appendChild(checkoutContainer);
                    checkoutContainer.style.padding = '0';
                    checkoutContainer.style.maxWidth = '100%';
                }
            });
        </script>
    @endif

    <!-- Exit Intent Popup (if enabled) -->
    @if($step->settings['exit_popup'] ?? false)
        <div id="exit-popup" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
            <div style="background: white; border-radius: 16px; padding: 40px; max-width: 500px; margin: 20px; text-align: center;">
                <h2 style="font-size: 24px; font-weight: 700; color: #111827; margin-bottom: 12px;">Wait! Don't Leave Yet!</h2>
                <p style="color: #6b7280; margin-bottom: 24px;">{{ $step->settings['exit_popup_message'] ?? 'You might miss out on this special offer.' }}</p>
                <button onclick="document.getElementById('exit-popup').style.display='none'" style="padding: 12px 32px; background: #3b82f6; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">Stay on Page</button>
                <div style="margin-top: 16px;">
                    <a href="#" onclick="document.getElementById('exit-popup').style.display='none'; return false;" style="color: #6b7280; font-size: 14px;">No thanks, I'll pass</a>
                </div>
            </div>
        </div>
    @endif

    <!-- If a post-purchase page loads inside the checkout frame, promote it to the top window -->
    <script>(function(){try{if(window.top!==window.self){var p=new URLSearchParams(window.location.search);if(p.has('order')||p.has('complete')){window.top.location.replace(window.location.href);}}}catch(e){}})();</script>

    <!-- Scripts -->
    @vite(['resources/js/app.js'])

    <!-- Funnel Scripts -->
    <script>
        // Configuration
        window.funnelConfig = {
            funnelId: {{ $funnel->id }},
            funnelUuid: '{{ $funnel->uuid }}',
            funnelSlug: '{{ $funnel->slug }}',
            stepId: {{ $step->id }},
            stepSlug: '{{ $step->slug }}',
            stepType: '{{ $step->type }}',
            sessionUuid: '{{ $session->uuid }}',
            isCustomDomain: {{ !empty($isCustomDomain) ? 'true' : 'false' }},
            nextStepUrl: '{{ $nextStep ? (!empty($isCustomDomain) ? "/{$nextStep->slug}" : "/f/{$funnel->slug}/{$nextStep->slug}") : '' }}',
            csrfToken: '{{ csrf_token() }}',
        };

        // Track time on page
        let pageStartTime = Date.now();
        let timeOnPage = 0;

        setInterval(() => {
            timeOnPage = Math.floor((Date.now() - pageStartTime) / 1000);
        }, 1000);

        // Send time on page when leaving
        window.addEventListener('beforeunload', () => {
            navigator.sendBeacon('/api/funnel-events', JSON.stringify({
                type: 'time_on_page',
                funnel_id: window.funnelConfig.funnelId,
                step_id: window.funnelConfig.stepId,
                session_uuid: window.funnelConfig.sessionUuid,
                data: { seconds: timeOnPage }
            }));
        });

        // Handle funnel actions
        document.addEventListener('click', (e) => {
            const action = e.target.closest('[data-funnel-action]');
            if (!action) return;

            const actionType = action.dataset.funnelAction;

            switch (actionType) {
                case 'next':
                    e.preventDefault();
                    if (window.funnelConfig.nextStepUrl) {
                        window.location.href = window.funnelConfig.nextStepUrl;
                    }
                    break;

                case 'add-to-cart':
                    e.preventDefault();
                    const productId = action.dataset.productId;
                    addToCart(productId);
                    break;

                case 'checkout':
                    e.preventDefault();
                    goToCheckout();
                    break;
            }
        });

        // Handle opt-in forms
        document.querySelectorAll('.funnel-optin-form').forEach(form => {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(form);

                form.classList.add('loading');

                try {
                    const optinUrl = window.funnelConfig.isCustomDomain ? '/optin' : `/f/${window.funnelConfig.funnelSlug}/optin`;
                    const response = await fetch(optinUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': window.funnelConfig.csrfToken,
                        },
                        body: JSON.stringify({
                            email: formData.get('email'),
                            name: formData.get('name'),
                            phone: formData.get('phone'),
                            step_id: window.funnelConfig.stepId,
                        }),
                    });

                    const data = await response.json();

                    if (data.success && data.redirect_url) {
                        window.location.href = data.redirect_url;
                    }
                } catch (error) {
                    console.error('Opt-in error:', error);
                    form.classList.remove('loading');
                }
            });
        });

        // Countdown timer
        document.querySelectorAll('.puck-countdown').forEach(countdown => {
            const endDate = new Date(countdown.dataset.endDate);

            function updateCountdown() {
                const now = new Date();
                const diff = endDate - now;

                if (diff <= 0) {
                    countdown.innerHTML = '<div style="text-align: center; color: inherit;">Offer Expired</div>';
                    return;
                }

                const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((diff % (1000 * 60)) / 1000);

                const daysEl = countdown.querySelector('.countdown-days');
                const hoursEl = countdown.querySelector('.countdown-hours');
                const minutesEl = countdown.querySelector('.countdown-minutes');
                const secondsEl = countdown.querySelector('.countdown-seconds');

                if (daysEl) daysEl.textContent = String(days).padStart(2, '0');
                if (hoursEl) hoursEl.textContent = String(hours).padStart(2, '0');
                if (minutesEl) minutesEl.textContent = String(minutes).padStart(2, '0');
                if (secondsEl) secondsEl.textContent = String(seconds).padStart(2, '0');
            }

            updateCountdown();
            setInterval(updateCountdown, 1000);
        });

        // Exit intent popup
        @if($step->settings['exit_popup'] ?? false)
        let exitPopupShown = false;
        document.addEventListener('mouseout', (e) => {
            if (e.clientY < 0 && !exitPopupShown) {
                document.getElementById('exit-popup').style.display = 'flex';
                exitPopupShown = true;
            }
        });
        @endif

        // Timer redirect (if enabled)
        @if($step->settings['timer_enabled'] ?? false)
        setTimeout(() => {
            if (window.funnelConfig.nextStepUrl) {
                window.location.href = window.funnelConfig.nextStepUrl;
            }
        }, {{ ($step->settings['timer_duration'] ?? 30) * 1000 }});
        @endif

        // Helper functions
        async function addToCart(productId) {
            try {
                const response = await fetch('/api/funnel-cart/add', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': window.funnelConfig.csrfToken,
                    },
                    body: JSON.stringify({
                        product_id: productId,
                        funnel_id: window.funnelConfig.funnelId,
                        session_uuid: window.funnelConfig.sessionUuid,
                    }),
                });

                const data = await response.json();
                if (data.success) {
                    // Show success notification or update cart count
                    console.log('Added to cart');
                }
            } catch (error) {
                console.error('Add to cart error:', error);
            }
        }

        function goToCheckout() {
            // Find checkout step
            window.location.href = window.funnelConfig.isCustomDomain ? '/checkout' : `/f/${window.funnelConfig.funnelSlug}/checkout`;
        }

        // Track ANY link clicks on thank you pages
        @if($step->type === 'thankyou')
        let tyClickTracked = false; // Track only once per page session

        document.addEventListener('click', (e) => {
            // Find closest anchor tag (covers clicks on child elements like SVGs, spans, etc.)
            const link = e.target.closest('a[href]');
            if (!link) return;

            // Skip empty hrefs, javascript: links, and anchor-only links
            const href = link.getAttribute('href');
            if (!href || href === '#' || href.startsWith('javascript:')) return;

            // Only track once per page session (first click counts)
            if (tyClickTracked) return;
            tyClickTracked = true;

            const sessionUuid = link.dataset.sessionUuid || window.funnelConfig.sessionUuid;
            const stepId = link.dataset.stepId || window.funnelConfig.stepId;

            // Send tracking event via beacon with proper JSON content type
            const data = JSON.stringify({
                session_uuid: sessionUuid,
                step_id: stepId,
                button_url: href,
                button_text: link.textContent?.trim().substring(0, 100) || '',
            });
            const blob = new Blob([data], { type: 'application/json' });
            navigator.sendBeacon('/api/funnel-events/button-click', blob);
        });
        @endif
    </script>

    <!-- Custom JS -->
    @if($customJs)
        <script>{!! $customJs !!}</script>
    @endif

    <!-- Tracking Codes (Body) -->
    @if($funnel->settings['tracking_codes']['body'] ?? null)
        {!! $funnel->settings['tracking_codes']['body'] !!}
    @endif
</body>
</html>
