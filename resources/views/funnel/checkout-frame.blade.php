<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>Checkout — {{ $funnel->name }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    {{-- The exact stylesheet the published funnel page uses, so the framed form
         looks identical to the inline checkout on a normal (Puck) funnel step. --}}
    @include('funnel.partials.checkout-styles')

    <style>
        /* The frame sits inside the sales page — stay transparent so it blends in. */
        html, body { background: transparent !important; }
        .funnel-checkout { padding-top: 8px; }
    </style>

    <script>
        window.funnelConfig = {
            funnelId: {{ $funnel->id }},
            funnelUuid: @json($funnel->uuid),
            funnelSlug: @json($funnel->slug),
            stepId: {{ $step->id }},
            stepSlug: @json($step->slug),
            stepType: @json($step->type),
            sessionUuid: @json($session->uuid),
            csrfToken: @json(csrf_token()),
            isFrame: true,
        };
    </script>
</head>
<body>
    <div class="funnel-checkout">
        @livewire('funnel.checkout-form', [
            'funnel' => $funnel,
            'step' => $step,
            'session' => $session,
        ])
    </div>

    @livewireScripts

    <script>
        (function () {
            // Report our height to the parent page so the iframe can auto-size.
            function postHeight() {
                window.parent.postMessage({
                    type: 'funnel-checkout-frame-resize',
                    height: document.body.scrollHeight,
                }, '*');
            }

            if ('ResizeObserver' in window) {
                new ResizeObserver(postHeight).observe(document.body);
            }
            window.addEventListener('load', postHeight);
            window.addEventListener('resize', postHeight);

            // The checkout form redirects via Livewire ($this->redirect) — inside a
            // frame that would only navigate the frame. Bubble the redirect up so the
            // TOP window moves to the next funnel step / thank-you / payment gateway.
            document.addEventListener('livewire:init', function () {
                Livewire.hook('commit', function (payload) {
                    if (!payload || typeof payload.succeed !== 'function') {
                        return;
                    }
                    payload.succeed(function (response) {
                        var effect = response && response.effect;
                        var url = effect && (effect.redirect || effect.redirectUsingNavigate);
                        if (url) {
                            window.parent.postMessage({
                                type: 'funnel-checkout-frame-redirect',
                                url: url,
                            }, '*');
                        }
                    });
                });
            });
        })();
    </script>
</body>
</html>
