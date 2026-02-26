<x-mail::message>
@if($emailNumber === 1)
# Did you forget something?

Hi there,

We noticed you left some items in your cart. Don't worry, we've saved them for you!

@elseif($emailNumber === 2)
# Your cart is still waiting

Hi there,

Just a friendly reminder that you have items waiting in your cart. They're selling fast, and we'd hate for you to miss out!

@else
# Last chance to complete your order

Hi there,

This is your final reminder. Your cart items are about to expire. Complete your purchase now before it's too late!

@endif

## Your Cart Items

@foreach($items as $item)
- **{{ $item['name'] ?? 'Product' }}** - RM {{ number_format($item['price'] ?? 0, 2) }}
@endforeach

---

**Cart Total: {{ $total }}**

<x-mail::button :url="$recoveryUrl" color="primary">
Complete Your Purchase
</x-mail::button>

If you have any questions about your order, feel free to reply to this email.

Thanks,<br>
{{ $funnelName }}

<x-mail::subcopy>
If you no longer wish to receive these emails, you can safely ignore this message. Your cart will expire in {{ 72 - ($cart->getAbandonmentAge() ?? 0) }} hours.
</x-mail::subcopy>
</x-mail::message>
