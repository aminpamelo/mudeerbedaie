<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Checkout - {{ $funnel->name }}</title>

    {{-- Embed Settings Styles --}}
    <style>
        :root {
            --primary-color: {{ $embedSettings['primary_color'] ?? '#3b82f6' }};
            --border-radius: {{ match($embedSettings['border_radius'] ?? 'xl') {
                'none' => '0',
                'sm' => '0.25rem',
                'md' => '0.375rem',
                'lg' => '0.5rem',
                'xl' => '0.75rem',
                '2xl' => '1rem',
                default => '0.75rem',
            } }};
        }

        body {
            margin: 0;
            padding: 16px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: transparent;
            min-height: 100vh;
        }

        /* Override primary color */
        .bg-blue-600 { background-color: var(--primary-color) !important; }
        .bg-blue-700 { background-color: color-mix(in srgb, var(--primary-color), black 10%) !important; }
        .bg-blue-500 { background-color: var(--primary-color) !important; }
        .border-blue-500 { border-color: var(--primary-color) !important; }
        .bg-blue-50\/50 { background-color: color-mix(in srgb, var(--primary-color), transparent 95%) !important; }
        .ring-blue-500 { --tw-ring-color: var(--primary-color) !important; }
        .text-blue-600 { color: var(--primary-color) !important; }
        .shadow-blue-600\/25 { --tw-shadow-color: color-mix(in srgb, var(--primary-color), transparent 75%) !important; }

        /* Override border radius */
        .rounded-2xl { border-radius: var(--border-radius) !important; }
        .rounded-xl { border-radius: calc(var(--border-radius) * 0.8) !important; }
        .rounded-lg { border-radius: calc(var(--border-radius) * 0.6) !important; }

        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            @if(($embedSettings['theme'] ?? 'light') === 'auto')
            body {
                background-color: #1f2937;
            }
            .bg-white {
                background-color: #374151 !important;
            }
            .bg-gray-50 {
                background-color: #1f2937 !important;
            }
            .text-gray-900 {
                color: #f9fafb !important;
            }
            .text-gray-700 {
                color: #d1d5db !important;
            }
            .text-gray-600 {
                color: #9ca3af !important;
            }
            .text-gray-500 {
                color: #6b7280 !important;
            }
            .border-gray-200 {
                border-color: #4b5563 !important;
            }
            .border-gray-300 {
                border-color: #4b5563 !important;
            }
            @endif
        }

        @if(($embedSettings['theme'] ?? 'light') === 'dark')
        body {
            background-color: #1f2937;
        }
        .bg-white {
            background-color: #374151 !important;
        }
        .bg-gray-50 {
            background-color: #1f2937 !important;
        }
        .text-gray-900 {
            color: #f9fafb !important;
        }
        .text-gray-700 {
            color: #d1d5db !important;
        }
        .text-gray-600 {
            color: #9ca3af !important;
        }
        .text-gray-500 {
            color: #6b7280 !important;
        }
        .border-gray-200 {
            border-color: #4b5563 !important;
        }
        .border-gray-300 {
            border-color: #4b5563 !important;
        }
        @endif
    </style>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    {{-- Facebook Pixel (browser-side) --}}
    @include('funnel.partials.facebook-pixel', [
        'funnel' => $funnel,
        'pageViewEventId' => $pageViewEventId ?? null,
        'viewContentEventId' => null,
        'viewContentData' => null,
        'initiateCheckoutEventId' => $initiateCheckoutEventId ?? null,
        'checkoutData' => $checkoutData ?? null,
        'purchaseEventId' => null,
        'purchaseData' => null,
    ])
</head>
<body>
    @livewire('funnel.embed-checkout-form', [
        'funnel' => $funnel,
        'step' => $step,
        'session' => $session,
        'embedded' => true,
    ])

    @livewireScripts

    <script>
        // Resize observer to notify parent of height changes
        const resizeObserver = new ResizeObserver(entries => {
            for (let entry of entries) {
                const height = entry.target.scrollHeight;
                window.parent.postMessage({
                    type: 'funnel-embed-resize',
                    height: height + 32 // Add padding
                }, '*');
            }
        });

        resizeObserver.observe(document.body);

        // Initial height notification
        window.addEventListener('load', () => {
            window.parent.postMessage({
                type: 'funnel-embed-ready',
                height: document.body.scrollHeight + 32
            }, '*');
        });

        // Listen for messages from parent
        window.addEventListener('message', (event) => {
            if (event.data.type === 'funnel-embed-prefill') {
                // Prefill form data from parent
                const data = event.data.data;
                if (data.email) {
                    Livewire.dispatch('prefill-email', { email: data.email });
                }
                if (data.name) {
                    Livewire.dispatch('prefill-name', { name: data.name });
                }
                if (data.phone) {
                    Livewire.dispatch('prefill-phone', { phone: data.phone });
                }
            }
        });
    </script>
</body>
</html>
