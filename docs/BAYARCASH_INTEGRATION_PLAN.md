# Bayarcash Payment Gateway Integration Plan

## Overview

This document outlines the detailed plan to integrate Bayarcash Payment Gateway into the Mudeer Bedaie system as an alternative payment option alongside the existing Stripe integration.

## Bayarcash Features

### Supported Payment Methods
- **FPX Online Banking** (B2C and B2B)
- **FPX Direct Debit** (recurring payments)
- **DuitNow** (QR and Online Banking)
- **Credit/Debit Cards**
- **E-Wallets**: Boost, GrabPay, ShopeePay, Touch 'n Go
- **International**: Alipay, WeChat Pay
- **BNPL**: Shopee PayLater, Grab PayLater

### Environments
- **Sandbox**: `console.bayarcash-sandbox.com`
- **Production**: `console.bayar.cash`

---

## Phase 1: Foundation Setup

### 1.1 Install Bayarcash PHP SDK

```bash
composer require webimpian/bayarcash-php-sdk
```

### 1.2 Environment Configuration

Add to `.env.example`:
```env
# Bayarcash Configuration
BAYARCASH_API_TOKEN=
BAYARCASH_API_SECRET_KEY=
BAYARCASH_PORTAL_KEY=
BAYARCASH_SANDBOX=true
```

### 1.3 Create Configuration File

**File**: `config/bayarcash.php`

```php
<?php

return [
    'api_token' => env('BAYARCASH_API_TOKEN'),
    'api_secret_key' => env('BAYARCASH_API_SECRET_KEY'),
    'portal_key' => env('BAYARCASH_PORTAL_KEY'),
    'sandbox' => env('BAYARCASH_SANDBOX', true),
    'api_version' => 'v3',
    'callback_url' => env('APP_URL') . '/bayarcash/callback',
    'return_url' => env('APP_URL') . '/bayarcash/return',
];
```

### 1.4 Database Migration

**File**: `database/migrations/xxxx_add_bayarcash_fields_to_orders.php`

```php
Schema::table('product_orders', function (Blueprint $table) {
    $table->string('bayarcash_transaction_id')->nullable()->after('stripe_payment_intent_id');
    $table->string('bayarcash_payment_channel')->nullable()->after('bayarcash_transaction_id');
    $table->json('bayarcash_response')->nullable()->after('bayarcash_payment_channel');
});

Schema::table('orders', function (Blueprint $table) {
    $table->string('bayarcash_transaction_id')->nullable();
    $table->string('bayarcash_payment_channel')->nullable();
    $table->json('bayarcash_response')->nullable();
});
```

---

## Phase 2: Core Service Implementation

### 2.1 BayarcashService

**File**: `app/Services/BayarcashService.php`

```php
<?php

namespace App\Services;

use Webimpian\BayarcashSdk\Bayarcash;
use App\Models\ProductOrder;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

class BayarcashService
{
    private Bayarcash $bayarcash;
    private SettingsService $settingsService;

    public function __construct(SettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
        $this->initializeBayarcash();
    }

    private function initializeBayarcash(): void
    {
        $apiToken = $this->settingsService->get('bayarcash_api_token');

        if (!$apiToken) {
            throw new \Exception('Bayarcash API token not configured.');
        }

        $this->bayarcash = new Bayarcash($apiToken);

        // Set sandbox mode based on settings
        if ($this->settingsService->get('bayarcash_sandbox', true)) {
            $this->bayarcash->useSandbox();
        }

        // Set API version
        $this->bayarcash->setApiVersion('v3');
    }

    public function isConfigured(): bool
    {
        return !empty($this->settingsService->get('bayarcash_api_token'))
            && !empty($this->settingsService->get('bayarcash_api_secret_key'))
            && !empty($this->settingsService->get('bayarcash_portal_key'));
    }

    public function getPortals(): array
    {
        return $this->bayarcash->getPortals();
    }

    public function getAvailableChannels(): array
    {
        $portalKey = $this->settingsService->get('bayarcash_portal_key');
        return $this->bayarcash->getChannels($portalKey);
    }

    public function getFpxBanks(): array
    {
        return $this->bayarcash->fpxBanksList();
    }

    public function createPaymentIntent(array $data): object
    {
        $secretKey = $this->settingsService->get('bayarcash_api_secret_key');
        $portalKey = $this->settingsService->get('bayarcash_portal_key');

        $paymentData = [
            'portal_key' => $portalKey,
            'order_number' => $data['order_number'],
            'amount' => $data['amount'], // In cents/sen
            'payer_name' => $data['payer_name'],
            'payer_email' => $data['payer_email'],
            'payer_telephone_number' => $data['payer_phone'] ?? '',
            'payment_channel' => $data['payment_channel'] ?? Bayarcash::FPX,
            'callback_url' => $this->getCallbackUrl(),
            'return_url' => $this->getReturnUrl($data['order_number']),
        ];

        // Add bank code for FPX payments
        if (isset($data['bank_code'])) {
            $paymentData['buyer_bank_code'] = $data['bank_code'];
        }

        // Generate checksum for security
        $checksum = $this->bayarcash->createPaymentIntenChecksumValue($secretKey, $paymentData);
        $paymentData['checksum'] = $checksum;

        return $this->bayarcash->createPaymentIntent($paymentData);
    }

    public function verifyCallback(array $data): bool
    {
        $secretKey = $this->settingsService->get('bayarcash_api_secret_key');
        return $this->bayarcash->verifyTransactionCallbackData($data, $secretKey);
    }

    public function verifyReturnData(array $data): bool
    {
        $secretKey = $this->settingsService->get('bayarcash_api_secret_key');
        return $this->bayarcash->verifyReturnUrlCallbackData($data, $secretKey);
    }

    public function getTransaction(string $transactionId): object
    {
        return $this->bayarcash->getTransaction($transactionId);
    }

    public function getTransactionByOrderNumber(string $orderNumber): object
    {
        return $this->bayarcash->getTransactionByOrderNumber($orderNumber);
    }

    private function getCallbackUrl(): string
    {
        return url('/bayarcash/callback');
    }

    private function getReturnUrl(string $orderNumber): string
    {
        return url("/bayarcash/return?order={$orderNumber}");
    }
}
```

### 2.2 Webhook Controller

**File**: `app/Http/Controllers/BayarcashWebhookController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\ProductOrder;
use App\Models\Order;
use App\Services\BayarcashService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BayarcashWebhookController extends Controller
{
    public function __construct(private BayarcashService $bayarcashService) {}

    public function callback(Request $request)
    {
        $data = $request->all();

        Log::info('Bayarcash callback received', $data);

        // Verify callback authenticity
        if (!$this->bayarcashService->verifyCallback($data)) {
            Log::warning('Bayarcash callback verification failed', $data);
            return response('Invalid callback', 400);
        }

        // Process based on status
        $status = $data['status'] ?? null;
        $orderNumber = $data['order_number'] ?? null;
        $transactionId = $data['transaction_id'] ?? null;

        if (!$orderNumber) {
            return response('Missing order number', 400);
        }

        // Find the order (check both ProductOrder and Order tables)
        $order = ProductOrder::where('order_number', $orderNumber)->first()
            ?? Order::where('order_number', $orderNumber)->first();

        if (!$order) {
            Log::error('Order not found for Bayarcash callback', ['order_number' => $orderNumber]);
            return response('Order not found', 404);
        }

        // Update order based on payment status
        // Status codes: 1 = Pending, 2 = Unsuccessful, 3 = Successful
        switch ($status) {
            case '3': // Successful
                $this->handleSuccessfulPayment($order, $data);
                break;
            case '2': // Failed
                $this->handleFailedPayment($order, $data);
                break;
            default:
                $this->handlePendingPayment($order, $data);
        }

        return response('OK', 200);
    }

    public function return(Request $request)
    {
        $data = $request->all();
        $orderNumber = $request->query('order');

        // Verify return data
        if (!$this->bayarcashService->verifyReturnData($data)) {
            return redirect()->route('cart.checkout')
                ->with('error', 'Payment verification failed. Please try again.');
        }

        $status = $data['status'] ?? null;

        if ($status === '3') {
            return redirect()->route('orders.confirmation', ['order' => $orderNumber])
                ->with('success', 'Payment successful!');
        } else {
            return redirect()->route('cart.checkout')
                ->with('error', 'Payment was not successful. Please try again.');
        }
    }

    private function handleSuccessfulPayment($order, array $data): void
    {
        $order->update([
            'status' => 'paid',
            'payment_status' => 'paid',
            'bayarcash_transaction_id' => $data['transaction_id'] ?? null,
            'bayarcash_payment_channel' => $data['payment_channel'] ?? null,
            'bayarcash_response' => $data,
            'paid_at' => now(),
        ]);

        // Dispatch any post-payment jobs (email confirmation, etc.)
        // event(new PaymentSuccessful($order));

        Log::info('Bayarcash payment successful', [
            'order_id' => $order->id,
            'transaction_id' => $data['transaction_id'] ?? null,
        ]);
    }

    private function handleFailedPayment($order, array $data): void
    {
        $order->update([
            'payment_status' => 'failed',
            'bayarcash_response' => $data,
        ]);

        Log::warning('Bayarcash payment failed', [
            'order_id' => $order->id,
            'data' => $data,
        ]);
    }

    private function handlePendingPayment($order, array $data): void
    {
        $order->update([
            'payment_status' => 'pending',
            'bayarcash_transaction_id' => $data['transaction_id'] ?? null,
            'bayarcash_response' => $data,
        ]);

        Log::info('Bayarcash payment pending', [
            'order_id' => $order->id,
            'data' => $data,
        ]);
    }
}
```

### 2.3 Routes Configuration

Add to `routes/web.php`:

```php
// Bayarcash webhook routes - no auth middleware
Route::post('bayarcash/callback', [App\Http\Controllers\BayarcashWebhookController::class, 'callback'])
    ->name('bayarcash.callback')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::get('bayarcash/return', [App\Http\Controllers\BayarcashWebhookController::class, 'return'])
    ->name('bayarcash.return');
```

---

## Phase 3: Admin Settings UI

### 3.1 Update Payment Settings Component

**File**: `resources/views/livewire/admin/settings-payment.blade.php`

Add new properties and methods to the component:

```php
// New properties for Bayarcash
public $bayarcash_api_token = '';
public $bayarcash_api_secret_key = '';
public $bayarcash_portal_key = '';
public $bayarcash_sandbox = true;
public $enable_bayarcash_payments = false;

// Mount method additions
$this->bayarcash_api_token = $this->getSettingsService()->get('bayarcash_api_token', '');
$this->bayarcash_api_secret_key = $this->getSettingsService()->get('bayarcash_api_secret_key', '');
$this->bayarcash_portal_key = $this->getSettingsService()->get('bayarcash_portal_key', '');
$this->bayarcash_sandbox = (bool) $this->getSettingsService()->get('bayarcash_sandbox', true);
$this->enable_bayarcash_payments = (bool) $this->getSettingsService()->get('enable_bayarcash_payments', false);

// Save method additions
$this->getSettingsService()->set('bayarcash_api_token', $this->bayarcash_api_token, 'encrypted', 'payment');
$this->getSettingsService()->set('bayarcash_api_secret_key', $this->bayarcash_api_secret_key, 'encrypted', 'payment');
$this->getSettingsService()->set('bayarcash_portal_key', $this->bayarcash_portal_key, 'string', 'payment');
$this->getSettingsService()->set('bayarcash_sandbox', $this->bayarcash_sandbox, 'boolean', 'payment');
$this->getSettingsService()->set('enable_bayarcash_payments', $this->enable_bayarcash_payments, 'boolean', 'payment');

// Test connection method
public function testBayarcashConnection(): void
{
    if (empty($this->bayarcash_api_token)) {
        $this->dispatch('bayarcash-test-failed', message: 'Please enter Bayarcash API token first.');
        return;
    }

    try {
        // Save settings temporarily to test
        $this->save();

        $bayarcashService = app(BayarcashService::class);
        $portals = $bayarcashService->getPortals();

        if (!empty($portals)) {
            $this->dispatch('bayarcash-test-success');
        } else {
            $this->dispatch('bayarcash-test-failed', message: 'No portals found. Check your API token.');
        }
    } catch (\Exception $e) {
        $this->dispatch('bayarcash-test-failed', message: $e->getMessage());
    }
}
```

### 3.2 Bayarcash Tab UI

Add a new tab for Bayarcash in the settings UI with:
- Enable/Disable toggle
- API Token input (encrypted)
- API Secret Key input (encrypted)
- Portal Key dropdown (fetched from API)
- Sandbox/Production mode toggle
- Available payment channels display
- Test Connection button
- Webhook URL display

---

## Phase 4: Checkout Integration

### 4.1 Update Checkout Component

Add Bayarcash as a payment option in `resources/views/livewire/cart/checkout.blade.php`:

```php
// Payment method options
public function getPaymentMethods(): array
{
    $methods = [];

    if (setting('enable_stripe_payments')) {
        $methods['credit_card'] = 'Credit/Debit Card (Stripe)';
    }

    if (setting('enable_bayarcash_payments')) {
        $methods['fpx'] = 'FPX Online Banking';
        $methods['duitnow'] = 'DuitNow';
        $methods['boost'] = 'Boost';
        $methods['grabpay'] = 'GrabPay';
        $methods['shopeepay'] = 'ShopeePay';
        $methods['tng'] = 'Touch \'n Go';
    }

    if (setting('enable_bank_transfers')) {
        $methods['bank_transfer'] = 'Manual Bank Transfer';
    }

    return $methods;
}
```

### 4.2 Process Bayarcash Payments

```php
private function processBayarcashPayment(ProductOrder $order, string $channel): void
{
    $bayarcashService = app(BayarcashService::class);

    $channelMap = [
        'fpx' => Bayarcash::FPX,
        'duitnow' => Bayarcash::DUITNOW_QR,
        'boost' => Bayarcash::BOOST,
        'grabpay' => Bayarcash::GRABPAY,
        'shopeepay' => Bayarcash::SHOPEE_PAY,
        'tng' => Bayarcash::TNG,
    ];

    $response = $bayarcashService->createPaymentIntent([
        'order_number' => $order->order_number,
        'amount' => (int) ($order->total * 100), // Convert to cents
        'payer_name' => $order->billing_name,
        'payer_email' => $order->billing_email,
        'payer_phone' => $order->billing_phone,
        'payment_channel' => $channelMap[$channel] ?? Bayarcash::FPX,
        'bank_code' => $this->selectedBank ?? null, // For FPX
    ]);

    // Redirect to Bayarcash payment page
    return redirect()->away($response->url);
}
```

---

## Phase 5: Testing

### 5.1 Unit Tests

**File**: `tests/Feature/BayarcashServiceTest.php`

```php
<?php

use App\Services\BayarcashService;
use App\Services\SettingsService;

test('bayarcash service can be instantiated when configured', function () {
    // Set up test settings
    $settingsService = app(SettingsService::class);
    $settingsService->set('bayarcash_api_token', 'test_token', 'encrypted', 'payment');
    $settingsService->set('bayarcash_api_secret_key', 'test_secret', 'encrypted', 'payment');
    $settingsService->set('bayarcash_portal_key', 'test_portal', 'string', 'payment');
    $settingsService->set('bayarcash_sandbox', true, 'boolean', 'payment');

    $service = app(BayarcashService::class);

    expect($service)->toBeInstanceOf(BayarcashService::class);
    expect($service->isConfigured())->toBeTrue();
});

test('bayarcash callback verification works', function () {
    // Test with valid and invalid callback data
});

test('bayarcash payment intent creation works', function () {
    // Test payment intent creation in sandbox mode
});
```

### 5.2 Feature Tests

**File**: `tests/Feature/BayarcashWebhookTest.php`

```php
<?php

test('bayarcash callback updates order on successful payment', function () {
    $order = ProductOrder::factory()->create([
        'order_number' => 'TEST-123',
        'payment_status' => 'pending',
    ]);

    $callbackData = [
        'order_number' => 'TEST-123',
        'transaction_id' => 'TXN-456',
        'status' => '3',
        'payment_channel' => 'FPX',
        // ... other callback fields
    ];

    $response = $this->post('/bayarcash/callback', $callbackData);

    $response->assertStatus(200);

    $order->refresh();
    expect($order->payment_status)->toBe('paid');
    expect($order->bayarcash_transaction_id)->toBe('TXN-456');
});
```

---

## Phase 6: SettingsService Updates

### 6.1 Add Bayarcash Helper Methods

Add to `app/Services/SettingsService.php`:

```php
/**
 * Get Bayarcash configuration
 */
public function getBayarcashConfig(): array
{
    return [
        'api_token' => $this->get('bayarcash_api_token'),
        'api_secret_key' => $this->get('bayarcash_api_secret_key'),
        'portal_key' => $this->get('bayarcash_portal_key'),
        'sandbox' => (bool) $this->get('bayarcash_sandbox', true),
        'enabled' => (bool) $this->get('enable_bayarcash_payments', false),
    ];
}

/**
 * Check if Bayarcash is configured
 */
public function isBayarcashConfigured(): bool
{
    $config = $this->getBayarcashConfig();

    return !empty($config['api_token'])
        && !empty($config['api_secret_key'])
        && !empty($config['portal_key']);
}

/**
 * Check if Bayarcash is enabled
 */
public function isBayarcashEnabled(): bool
{
    return $this->isBayarcashConfigured()
        && (bool) $this->get('enable_bayarcash_payments', false);
}
```

---

## Implementation Checklist

### Phase 1: Foundation (Day 1)
- [ ] Install Bayarcash PHP SDK
- [ ] Create configuration file
- [ ] Add environment variables
- [ ] Create database migration
- [ ] Run migration

### Phase 2: Core Service (Day 2-3)
- [ ] Create BayarcashService
- [ ] Create BayarcashWebhookController
- [ ] Add routes for callbacks
- [ ] Test in sandbox mode

### Phase 3: Admin UI (Day 4)
- [ ] Update settings-payment.blade.php component
- [ ] Add Bayarcash tab with configuration fields
- [ ] Implement test connection functionality
- [ ] Display webhook URL

### Phase 4: Checkout Integration (Day 5-6)
- [ ] Add Bayarcash payment methods to checkout
- [ ] Implement FPX bank selection
- [ ] Implement redirect to Bayarcash
- [ ] Handle return URLs

### Phase 5: Testing (Day 7)
- [ ] Write unit tests
- [ ] Write feature tests
- [ ] Test all payment channels in sandbox
- [ ] Test webhook callbacks

### Phase 6: Production Setup
- [ ] Create Bayarcash production account
- [ ] Configure production portal
- [ ] Set up production webhook URLs
- [ ] Final testing in production

---

## Payment Channel Constants Reference

```php
use Webimpian\BayarcashSdk\Bayarcash;

Bayarcash::FPX           // FPX Online Banking
Bayarcash::FPX_DD        // FPX Direct Debit
Bayarcash::DUITNOW_QR    // DuitNow QR
Bayarcash::DUITNOW_OBW   // DuitNow Online Banking
Bayarcash::BOOST         // Boost e-wallet
Bayarcash::GRABPAY       // GrabPay
Bayarcash::SHOPEE_PAY    // ShopeePay
Bayarcash::TNG           // Touch 'n Go
Bayarcash::CARD          // Credit/Debit Card
Bayarcash::ALIPAY        // Alipay
Bayarcash::WECHAT_PAY    // WeChat Pay
```

---

## Security Considerations

1. **Always verify callbacks** using the SDK's verification methods
2. **Store API keys encrypted** using Laravel's encryption
3. **Use HTTPS** for all callback and return URLs
4. **Validate order ownership** before processing payments
5. **Log all transactions** for audit trails
6. **Implement idempotency** to prevent duplicate processing

---

## Questions Before Implementation

1. **Which payment channels do you want to enable initially?**
   - FPX only
   - FPX + DuitNow
   - All available channels

2. **Do you need FPX Direct Debit for recurring subscriptions?**

3. **Should Bayarcash be the default payment method for Malaysian users?**

4. **Do you want to show FPX bank selection on the checkout page or redirect to Bayarcash's hosted page?**

5. **What is your preferred error handling strategy for failed payments?**
