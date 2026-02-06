{{-- Facebook Pixel Browser-Side Tracking --}}
@php
    use App\Services\Funnel\FacebookPixelService;

    $pixelService = app(FacebookPixelService::class);
    $pixelSettings = $pixelService->getPixelSettings($funnel);
    $pixelEnabled = $pixelService->isEnabled($funnel);
@endphp

@if($pixelEnabled)
{{-- Facebook Pixel Base Code --}}
<script>
{!! $pixelService->getPixelInitCode($funnel) !!}
</script>
<noscript>
    <img height="1" width="1" style="display:none"
         src="https://www.facebook.com/tr?id={{ $pixelSettings['pixel_id'] }}&ev=PageView&noscript=1"/>
</noscript>

{{-- Track PageView with event ID for deduplication --}}
@if($pixelService->isEventEnabled($funnel, 'page_view'))
<script>
fbq('track', 'PageView', {}, { eventID: '{{ $pageViewEventId ?? '' }}' });
</script>
@endif

{{-- Track ViewContent for product/landing pages --}}
@if(isset($viewContentData) && $pixelService->isEventEnabled($funnel, 'view_content'))
<script>
fbq('track', 'ViewContent', {
    content_type: 'product',
    content_ids: {!! json_encode($viewContentData['content_ids'] ?? []) !!},
    content_name: '{{ $viewContentData['content_name'] ?? $step->name }}',
    value: {{ $viewContentData['value'] ?? 0 }},
    currency: '{{ $viewContentData['currency'] ?? 'MYR' }}'
}, { eventID: '{{ $viewContentEventId ?? '' }}' });
</script>
@endif

{{-- Track InitiateCheckout for checkout pages --}}
@if($step->type === 'checkout' && $pixelService->isEventEnabled($funnel, 'initiate_checkout'))
<script>
fbq('track', 'InitiateCheckout', {
    content_type: 'product',
    @if(isset($checkoutData))
    content_ids: {!! json_encode($checkoutData['content_ids'] ?? []) !!},
    value: {{ $checkoutData['value'] ?? 0 }},
    num_items: {{ $checkoutData['num_items'] ?? 1 }},
    @endif
    currency: '{{ $checkoutData['currency'] ?? 'MYR' }}'
}, { eventID: '{{ $initiateCheckoutEventId ?? '' }}' });
</script>
@endif

{{-- Track Purchase for thank you pages --}}
@if($step->type === 'thankyou' && isset($purchaseData) && $pixelService->isEventEnabled($funnel, 'purchase'))
<script>
fbq('track', 'Purchase', {
    content_type: 'product',
    content_ids: {!! json_encode($purchaseData['content_ids'] ?? []) !!},
    contents: {!! json_encode($purchaseData['contents'] ?? []) !!},
    value: {{ $purchaseData['value'] ?? 0 }},
    currency: '{{ $purchaseData['currency'] ?? 'MYR' }}',
    num_items: {{ $purchaseData['num_items'] ?? 1 }}
}, { eventID: '{{ $purchaseEventId ?? '' }}' });
</script>
@endif

{{-- Helper functions for dynamic event tracking --}}
<script>
window.FunnelPixel = {
    pixelId: '{{ $pixelSettings['pixel_id'] }}',

    // Track AddToCart event
    trackAddToCart: function(productId, productName, price, quantity) {
        if (typeof fbq === 'undefined') return;

        var eventId = this.generateEventId();
        fbq('track', 'AddToCart', {
            content_type: 'product',
            content_ids: [String(productId)],
            content_name: productName,
            contents: [{
                id: String(productId),
                quantity: quantity || 1,
                item_price: price
            }],
            value: price * (quantity || 1),
            currency: 'MYR'
        }, { eventID: eventId });

        // Also send to server for deduplication
        this.sendServerEvent('AddToCart', {
            product_id: productId,
            product_name: productName,
            price: price,
            quantity: quantity || 1,
            event_id: eventId
        });

        return eventId;
    },

    // Track Lead event (opt-in form)
    trackLead: function(email, name, value) {
        if (typeof fbq === 'undefined') return;

        var eventId = this.generateEventId();
        var params = {
            content_name: '{{ $funnel->name }} - Lead',
            currency: 'MYR'
        };

        if (value) {
            params.value = value;
        }

        fbq('track', 'Lead', params, { eventID: eventId });

        // Also send to server for deduplication
        this.sendServerEvent('Lead', {
            email: email,
            name: name,
            value: value,
            event_id: eventId
        });

        return eventId;
    },

    // Track custom event
    trackCustom: function(eventName, params) {
        if (typeof fbq === 'undefined') return;

        var eventId = this.generateEventId();
        fbq('trackCustom', eventName, params || {}, { eventID: eventId });

        return eventId;
    },

    // Generate unique event ID
    generateEventId: function() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            var r = Math.random() * 16 | 0;
            var v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    },

    // Send event to server for Conversions API
    sendServerEvent: function(eventName, data) {
        fetch('/api/v1/funnel/{{ $funnel->uuid }}/pixel-event', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.funnelConfig?.csrfToken || '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                event_name: eventName,
                event_id: data.event_id,
                data: data,
                session_uuid: window.funnelConfig?.sessionUuid || '{{ $session->uuid ?? '' }}'
            })
        }).catch(function(error) {
            console.error('Pixel server event failed:', error);
        });
    }
};

// Auto-track AddToCart button clicks
document.addEventListener('click', function(e) {
    var addToCartBtn = e.target.closest('[data-funnel-action="add-to-cart"]');
    if (addToCartBtn) {
        var productId = addToCartBtn.dataset.productId;
        var productName = addToCartBtn.dataset.productName || 'Product';
        var productPrice = parseFloat(addToCartBtn.dataset.productPrice) || 0;
        var quantity = parseInt(addToCartBtn.dataset.quantity) || 1;

        window.FunnelPixel.trackAddToCart(productId, productName, productPrice, quantity);
    }
});

// Auto-track opt-in form submissions
document.addEventListener('submit', function(e) {
    var optinForm = e.target.closest('.funnel-optin-form');
    if (optinForm) {
        var email = optinForm.querySelector('[name="email"]')?.value || '';
        var name = optinForm.querySelector('[name="name"]')?.value || '';

        window.FunnelPixel.trackLead(email, name);
    }
});
</script>
@endif
