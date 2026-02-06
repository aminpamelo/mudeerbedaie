# Funnel Payment Integration Plan

## Current State Analysis

### Existing Funnel Checkout Flow
1. **checkout-form.blade.php** - Livewire Volt component handling the 3-step checkout (Cart → Information → Payment)
2. **FunnelCheckoutService.php** - Service handling Stripe payments only
3. Payment methods displayed: Credit Card, Debit Card, FPX, GrabPay, Boost (UI only, not fully functional)

### Current Issues
1. FPX selection exists in UI but only triggers Stripe FPX (not Bayarcash)
2. No actual payment processing happens - orders are created as "pending" and browser event dispatched
3. Bayarcash integration (completed for cart checkout) is not connected to funnel checkout
4. No way to configure which payment methods are available for funnels

## Integration Goals
1. Connect Bayarcash FPX to funnel checkout
2. Handle payment redirects properly for FPX
3. Update Bayarcash webhook handler to support funnel orders
4. Show only configured/enabled payment methods
5. Proper post-payment flow (redirect to thank you page or next step)

---

## Implementation Plan

### Phase 1: Update Funnel Checkout Form for Bayarcash FPX

**File:** `resources/views/livewire/funnel/checkout-form.blade.php`

**Changes:**
1. Add methods to check if payment providers are enabled:
   - `isBayarcashEnabled()` - check if Bayarcash is configured
   - `isStripeEnabled()` - check if Stripe is configured

2. Update payment method rendering to only show available methods:
   - Show FPX only if Bayarcash is enabled
   - Show Credit/Debit Card only if Stripe is enabled
   - Show GrabPay/Boost if Stripe supports them

3. Update `processOrder()` method:
   - For FPX payments: redirect to Bayarcash payment page
   - For Card payments: redirect to Stripe (existing behavior)

4. Handle payment redirect URLs:
   - Return URL: `/f/{slug}?payment_status=success&order={order_number}`
   - Callback URL: `/bayarcash/callback` (existing)

### Phase 2: Update FunnelCheckoutService for Multi-Provider Support

**File:** `app/Services/Funnel/FunnelCheckoutService.php`

**Changes:**
1. Inject `BayarcashService` alongside `StripeService`
2. Add method `createBayarcashPayment()` for FPX payments
3. Add method `getAvailablePaymentMethods()` returning enabled methods
4. Update `createCheckout()` to handle payment method selection

### Phase 3: Update Bayarcash Webhook Controller

**File:** `app/Http/Controllers/BayarcashWebhookController.php`

**Changes:**
1. After payment success, detect if order is from funnel (check metadata)
2. If funnel order:
   - Update `FunnelOrder` status
   - Mark `FunnelSession` as converted
   - Update funnel analytics
   - Track `payment_completed` event
3. Update return URL handling to redirect to funnel thank you page

### Phase 4: Funnel Payment Return Handler

**File:** Create `app/Http/Controllers/FunnelPaymentController.php`

**Endpoints:**
- `GET /f/{slug}/payment/return` - Handle payment return from any provider
- Verify payment status
- Redirect to appropriate next step or thank you page

### Phase 5: Update Models (if needed)

**ProductOrder model:**
- Ensure `metadata` field can store `funnel_id`, `step_id`, `session_uuid`
- Already has `bayarcash_transaction_id`, `bayarcash_response` fields

---

## Detailed Implementation

### Step 1: Update checkout-form.blade.php

```php
// Add these methods to the component class:

private function isBayarcashEnabled(): bool
{
    return app(SettingsService::class)->isBayarcashEnabled();
}

private function isStripeEnabled(): bool
{
    return app(SettingsService::class)->isStripeConfigured();
}

public function getAvailablePaymentMethods(): array
{
    $methods = [];

    if ($this->isStripeEnabled()) {
        $methods[] = ['id' => 'credit_card', 'name' => 'Credit Card', 'description' => 'Visa, Mastercard'];
        $methods[] = ['id' => 'debit_card', 'name' => 'Debit Card', 'description' => 'Visa, Mastercard'];
    }

    if ($this->isBayarcashEnabled()) {
        $methods[] = ['id' => 'fpx', 'name' => 'FPX Online Banking', 'description' => 'Malaysian Banks'];
    }

    return $methods;
}

// Update processOrder() to route to correct payment provider:
public function processOrder(): void
{
    // ... validation ...

    // Create ProductOrder first (pending status)

    if ($this->paymentMethod === 'fpx' && $this->isBayarcashEnabled()) {
        $this->processBayarcashPayment($productOrder);
    } else {
        $this->processStripePayment($productOrder);
    }
}

private function processBayarcashPayment(ProductOrder $order): void
{
    $bayarcashService = app(BayarcashService::class);

    $paymentIntent = $bayarcashService->createPaymentIntent([
        'order_number' => $order->order_number,
        'amount' => $order->total_amount,
        'payer_name' => $this->customerData['name'],
        'payer_email' => $this->customerData['email'],
        'payer_phone' => $this->customerData['phone'] ?? '',
    ]);

    // Redirect to Bayarcash payment page
    $this->redirect($paymentIntent->url);
}
```

### Step 2: Update BayarcashService Return URL

The return URL should include funnel context:

```php
private function getReturnUrl(string $orderNumber): string
{
    // Check if this is a funnel order and include funnel slug in return URL
    return url("/bayarcash/return?order={$orderNumber}");
}
```

### Step 3: Update BayarcashWebhookController Return Method

```php
public function return(Request $request): RedirectResponse
{
    // ... existing verification ...

    $order = $this->findOrder($orderNumber);

    // Check if this is a funnel order
    $metadata = $order->metadata ?? [];
    if (isset($metadata['funnel_id'])) {
        $funnel = Funnel::find($metadata['funnel_id']);

        if ($status === '3') {
            // Success - redirect to thank you or next step
            $nextStep = $funnel?->steps()
                ->where('type', 'thankyou')
                ->first();

            if ($nextStep) {
                return redirect("/f/{$funnel->slug}/{$nextStep->slug}?order={$orderNumber}")
                    ->with('success', 'Payment successful!');
            }

            return redirect("/f/{$funnel->slug}?complete=1&order={$orderNumber}")
                ->with('success', 'Payment successful!');
        } elseif ($status === '2') {
            // Failed - return to checkout
            return redirect("/f/{$funnel->slug}/" . ($metadata['step_slug'] ?? ''))
                ->with('error', 'Payment was unsuccessful. Please try again.');
        }
    }

    // Fall back to existing non-funnel behavior
    // ...
}
```

### Step 4: Update Callback Handler for Funnel Analytics

```php
public function callback(Request $request): Response
{
    // ... existing logic ...

    // After successful payment processing, update funnel data
    if ($status === '3') {
        $this->updateFunnelConversion($order);
    }
}

private function updateFunnelConversion($order): void
{
    $metadata = $order->metadata ?? [];

    if (!isset($metadata['funnel_id'])) {
        return;
    }

    // Find FunnelOrder
    $funnelOrder = FunnelOrder::where('product_order_id', $order->id)->first();

    if ($funnelOrder && $funnelOrder->session) {
        // Mark session as converted
        $funnelOrder->session->markAsConverted();

        // Track payment event
        $funnelOrder->session->trackEvent('payment_completed', [
            'order_id' => $order->id,
            'amount' => $order->total_amount,
            'payment_method' => 'fpx',
        ]);

        // Update analytics
        $analytics = FunnelAnalytics::getOrCreateForToday(
            $funnelOrder->funnel_id,
            $funnelOrder->step_id
        );
        $analytics->incrementConversions($funnelOrder->funnel_revenue);
    }

    // Mark cart as recovered
    if (isset($metadata['session_uuid'])) {
        $session = FunnelSession::where('uuid', $metadata['session_uuid'])->first();
        if ($session && $session->cart) {
            $session->cart->markAsRecovered($order);
        }
    }
}
```

---

## Files to Modify

1. **resources/views/livewire/funnel/checkout-form.blade.php**
   - Add payment provider checks
   - Update payment method rendering
   - Add Bayarcash payment processing

2. **app/Http/Controllers/BayarcashWebhookController.php**
   - Update return method for funnel redirects
   - Add funnel conversion tracking in callback

3. **app/Services/Funnel/FunnelCheckoutService.php** (optional enhancement)
   - Add Bayarcash support
   - Add `getAvailablePaymentMethods()` method

---

## Testing Checklist

1. [ ] Verify Bayarcash FPX option shows only when Bayarcash is enabled
2. [ ] Verify Stripe card options show only when Stripe is enabled
3. [ ] Test FPX payment flow:
   - [ ] Order created with pending status
   - [ ] Redirect to Bayarcash payment page works
   - [ ] Return to funnel thank you page after success
   - [ ] Return to checkout with error on failure
4. [ ] Test callback updates funnel analytics correctly
5. [ ] Test cart is marked as recovered after payment
6. [ ] Test session is marked as converted

---

## Estimated Changes Summary

| File | Type | Description |
|------|------|-------------|
| `checkout-form.blade.php` | Modify | Add payment provider logic, Bayarcash integration |
| `BayarcashWebhookController.php` | Modify | Add funnel-aware return/callback handling |
| `FunnelCheckoutService.php` | Modify (optional) | Add multi-provider support |

---

## Notes

- The existing Bayarcash integration for cart checkout is reusable
- Funnel orders use the same `ProductOrder` model, so webhook handling is similar
- The `metadata` field on `ProductOrder` tracks funnel context (funnel_id, step_id, session_uuid)
- Funnel analytics are tracked via `FunnelOrder` and `FunnelAnalytics` models
