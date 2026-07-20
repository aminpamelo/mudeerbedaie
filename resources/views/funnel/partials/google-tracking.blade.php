{{-- Google tracking (gtag.js) — Google Analytics 4 + Google Ads conversions --}}
@php
    use App\Services\Funnel\GoogleTrackingService;

    $googleService = app(GoogleTrackingService::class);
    $googleEnabled = $googleService->isEnabled($funnel);
@endphp

@if($googleEnabled)
{{-- gtag.js base loader + config --}}
<script async src="https://www.googletagmanager.com/gtag/js?id={{ $googleService->getLoaderId($funnel) }}"></script>
<script>
{!! $googleService->getGtagInitCode($funnel) !!}
</script>

{{-- view_item on landing / sales pages --}}
@if(isset($viewContentData) && $googleService->isEventEnabled($funnel, 'view_item'))
<script>
gtag('event', 'view_item', {
    currency: '{{ $viewContentData['currency'] ?? 'MYR' }}',
    value: {{ $viewContentData['value'] ?? 0 }},
    items: {!! json_encode(collect($viewContentData['content_ids'] ?? [])->map(fn ($id) => ['item_id' => (string) $id])->values()) !!}
});
</script>
@endif

{{-- begin_checkout on checkout pages --}}
@if(isset($checkoutData) && $step->type === 'checkout' && $googleService->isEventEnabled($funnel, 'begin_checkout'))
<script>
gtag('event', 'begin_checkout', {
    currency: '{{ $checkoutData['currency'] ?? 'MYR' }}',
    value: {{ $checkoutData['value'] ?? 0 }},
    items: {!! json_encode(collect($checkoutData['content_ids'] ?? [])->map(fn ($id) => ['item_id' => (string) $id])->values()) !!}
});
</script>
@endif

{{-- purchase on thank-you pages: GA4 purchase + Google Ads conversion --}}
@if(isset($purchaseData) && $step->type === 'thankyou' && $googleService->isEventEnabled($funnel, 'purchase'))
<script>
@if($googleService->hasGa4($funnel))
gtag('event', 'purchase', {
    transaction_id: '{{ $purchaseData['transaction_id'] ?? '' }}',
    currency: '{{ $purchaseData['currency'] ?? 'MYR' }}',
    value: {{ $purchaseData['value'] ?? 0 }},
    items: {!! json_encode(collect($purchaseData['contents'] ?? [])->map(fn ($c) => [
        'item_id' => (string) ($c['id'] ?? ''),
        'quantity' => $c['quantity'] ?? 1,
        'price' => $c['item_price'] ?? 0,
    ])->values()) !!}
});
@endif
@if($googleService->adsPurchaseSendTo($funnel))
gtag('event', 'conversion', {
    send_to: '{{ $googleService->adsPurchaseSendTo($funnel) }}',
    value: {{ $purchaseData['value'] ?? 0 }},
    currency: '{{ $purchaseData['currency'] ?? 'MYR' }}',
    transaction_id: '{{ $purchaseData['transaction_id'] ?? '' }}'
});
@endif
</script>
@endif

{{-- Helper for dynamic events fired from checkout JS (add_to_cart, begin_checkout) --}}
<script>
window.FunnelGoogle = {
    enabled: true,
    addToCartEnabled: {{ $googleService->isEventEnabled($funnel, 'add_to_cart') ? 'true' : 'false' }},

    trackAddToCart: function(productId, productName, price, quantity) {
        if (typeof gtag === 'undefined' || !this.addToCartEnabled) return;
        gtag('event', 'add_to_cart', {
            currency: 'MYR',
            value: price * (quantity || 1),
            items: [{ item_id: String(productId), item_name: productName, quantity: quantity || 1, price: price }]
        });
    },

    trackBeginCheckout: function(value, items) {
        if (typeof gtag === 'undefined') return;
        gtag('event', 'begin_checkout', { currency: 'MYR', value: value || 0, items: items || [] });
    }
};
</script>
@endif
