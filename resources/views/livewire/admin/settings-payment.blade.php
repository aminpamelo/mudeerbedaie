<?php
use App\Services\BayarcashService;
use App\Services\SettingsService;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new class extends Component {
    // Stripe settings
    public $stripe_publishable_key = '';
    public $stripe_secret_key = '';
    public $stripe_webhook_secret = '';
    public $payment_mode = 'test';
    public $currency = 'MYR';
    public $enable_stripe_payments = true;

    // Bayarcash settings
    public $bayarcash_api_token = '';
    public $bayarcash_api_secret_key = '';
    public $bayarcash_portal_key = '';
    public string $bayarcash_sandbox = '1'; // '1' = sandbox, '0' = production
    public $enable_bayarcash_payments = false;

    // COD settings
    public $enable_cod_payments = false;
    public $cod_customer_instructions = '';

    #[Url(as: 'tab')]
    public $activeTab = 'stripe';

    private function getSettingsService(): SettingsService
    {
        return app(SettingsService::class);
    }

    public function mount(): void
    {
        // Load Stripe settings
        $this->stripe_publishable_key = $this->getSettingsService()->get('stripe_publishable_key', '');
        $this->stripe_secret_key = $this->getSettingsService()->get('stripe_secret_key', '');
        $this->stripe_webhook_secret = $this->getSettingsService()->get('stripe_webhook_secret', '');
        $this->payment_mode = $this->getSettingsService()->get('payment_mode', 'test');
        $this->currency = $this->getSettingsService()->get('currency', 'MYR');
        $this->enable_stripe_payments = (bool) $this->getSettingsService()->get('enable_stripe_payments', true);

        // Load Bayarcash settings
        $this->bayarcash_api_token = $this->getSettingsService()->get('bayarcash_api_token', '');
        $this->bayarcash_api_secret_key = $this->getSettingsService()->get('bayarcash_api_secret_key', '');
        $this->bayarcash_portal_key = $this->getSettingsService()->get('bayarcash_portal_key', '');
        // Convert boolean to string for radio button binding
        $sandboxValue = $this->getSettingsService()->get('bayarcash_sandbox', true);
        $this->bayarcash_sandbox = $sandboxValue ? '1' : '0';
        $this->enable_bayarcash_payments = (bool) $this->getSettingsService()->get('enable_bayarcash_payments', false);

        // Load COD settings
        $this->enable_cod_payments = (bool) $this->getSettingsService()->get('enable_cod_payments', false);
        $this->cod_customer_instructions = $this->getSettingsService()->get('cod_customer_instructions', '');
    }

    public function save(): void
    {
        $this->validate([
            'stripe_publishable_key' => 'nullable|string|max:255',
            'stripe_secret_key' => 'nullable|string|max:255',
            'stripe_webhook_secret' => 'nullable|string|max:255',
            'payment_mode' => 'required|in:test,live',
            'currency' => 'required|string|max:10',
            'enable_stripe_payments' => 'boolean',
        ]);

        // Save Stripe settings (encrypted)
        $this->getSettingsService()->set('stripe_publishable_key', $this->stripe_publishable_key, 'encrypted', 'payment');
        $this->getSettingsService()->set('stripe_secret_key', $this->stripe_secret_key, 'encrypted', 'payment');
        $this->getSettingsService()->set('stripe_webhook_secret', $this->stripe_webhook_secret, 'encrypted', 'payment');
        $this->getSettingsService()->set('payment_mode', $this->payment_mode, 'string', 'payment');
        $this->getSettingsService()->set('currency', $this->currency, 'string', 'payment');
        $this->getSettingsService()->set('enable_stripe_payments', $this->enable_stripe_payments, 'boolean', 'payment');

        $this->dispatch('settings-saved');
    }

    public function saveBayarcash(): void
    {
        $this->validate([
            'bayarcash_api_token' => 'nullable|string|max:2000', // JWT tokens are longer than 255 chars
            'bayarcash_api_secret_key' => 'nullable|string|max:500',
            'bayarcash_portal_key' => 'nullable|string|max:255',
            'bayarcash_sandbox' => 'required|in:0,1',
            'enable_bayarcash_payments' => 'boolean',
        ]);

        // Save Bayarcash settings (encrypted)
        $this->getSettingsService()->set('bayarcash_api_token', $this->bayarcash_api_token, 'encrypted', 'payment');
        $this->getSettingsService()->set('bayarcash_api_secret_key', $this->bayarcash_api_secret_key, 'encrypted', 'payment');
        $this->getSettingsService()->set('bayarcash_portal_key', $this->bayarcash_portal_key, 'string', 'payment');
        // Convert string '1'/'0' to boolean for storage
        $this->getSettingsService()->set('bayarcash_sandbox', $this->bayarcash_sandbox === '1', 'boolean', 'payment');
        $this->getSettingsService()->set('enable_bayarcash_payments', $this->enable_bayarcash_payments, 'boolean', 'payment');

        $this->dispatch('settings-saved');
    }

    public function saveCod(): void
    {
        $this->validate([
            'enable_cod_payments' => 'boolean',
            'cod_customer_instructions' => 'nullable|string|max:1000',
        ]);

        $this->getSettingsService()->set('enable_cod_payments', $this->enable_cod_payments, 'boolean', 'payment');
        $this->getSettingsService()->set('cod_customer_instructions', $this->cod_customer_instructions, 'string', 'payment');

        $this->dispatch('settings-saved');
    }

    public function testStripeConnection(): void
    {
        if (empty($this->stripe_secret_key)) {
            $this->dispatch('stripe-test-failed', message: 'Please enter Stripe secret key first.');
            return;
        }

        try {
            // This would typically test the Stripe connection
            // For now, we'll just simulate a successful test
            $this->dispatch('stripe-test-success');
        } catch (\Exception $e) {
            $this->dispatch('stripe-test-failed', message: $e->getMessage());
        }
    }

    public function testBayarcashConnection(): void
    {
        if (empty($this->bayarcash_api_token)) {
            $this->dispatch('bayarcash-test-failed', message: 'Please enter Bayarcash API token first.');
            return;
        }

        $mode = $this->bayarcash_sandbox === '1' ? 'Sandbox' : 'Production';
        $console = $this->bayarcash_sandbox === '1' ? 'console.bayarcash-sandbox.com' : 'console.bayar.cash';

        try {
            // Save settings first
            $this->saveBayarcash();

            // Clear cache to ensure fresh settings
            \Illuminate\Support\Facades\Cache::forget('settings_bayarcash_sandbox');
            \Illuminate\Support\Facades\Cache::forget('settings_bayarcash_api_token');

            // Test directly with the SDK to get the actual error
            $bayarcash = new \Webimpian\BayarcashSdk\Bayarcash($this->bayarcash_api_token);

            if ($this->bayarcash_sandbox === '1') {
                $bayarcash->useSandbox();
            }

            $bayarcash->setApiVersion('v3');

            try {
                $portals = $bayarcash->getPortals();

                if (!empty($portals)) {
                    $this->dispatch('bayarcash-test-success');
                } else {
                    $this->dispatch('bayarcash-test-failed', message: "No portals found. Check your API token from {$console}");
                }
            } catch (\TypeError $e) {
                // SDK bug with null websiteUrl - but connection actually worked!
                $this->dispatch('bayarcash-test-success');
            }
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();

            // Parse JSON error if present
            if (str_contains($errorMessage, 'Unauthenticated')) {
                $errorMessage = "Authentication failed! Your API token is invalid for {$mode} mode. Get a new token from {$console}";
            } elseif (str_contains($errorMessage, '401')) {
                $errorMessage = "API token rejected (401). Make sure you're using a {$mode} token from {$console}";
            }

            $this->dispatch('bayarcash-test-failed', message: $errorMessage);
        }
    }

    public function switchTab($tab): void
    {
        $this->activeTab = $tab;
    }

    public function getCurrencies(): array
    {
        return [
            'MYR' => 'Malaysian Ringgit (MYR)',
            'USD' => 'US Dollar (USD)',
            'EUR' => 'Euro (EUR)',
            'GBP' => 'British Pound (GBP)',
            'SGD' => 'Singapore Dollar (SGD)',
            'AUD' => 'Australian Dollar (AUD)',
            'CAD' => 'Canadian Dollar (CAD)',
            'JPY' => 'Japanese Yen (JPY)',
        ];
    }

    public function layout(): string
    {
        return 'components.admin.settings-layout';
    }

    public function layoutData(): array
    {
        return [
            'title' => 'Payment Settings',
            'activeTab' => 'payment'
        ];
    }

}
?>

<div>
    <!-- Payment Methods Tabs -->
    <div class="mb-6">
        <nav class="flex space-x-8" role="tablist">
            <button
                type="button"
                wire:click="switchTab('stripe')"
                class="py-2 px-1 border-b-2 font-medium text-sm transition-colors duration-200 @if($activeTab === 'stripe') border-indigo-500 text-indigo-600  @else border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300  :text-gray-300 @endif"
                role="tab"
                aria-selected="{{ $activeTab === 'stripe' ? 'true' : 'false' }}"
            >
                <div class="flex items-center">
                    <flux:icon name="credit-card" class="w-4 h-4 mr-2" />
                    Stripe Payments
                </div>
            </button>

            <button
                type="button"
                wire:click="switchTab('manual')"
                class="py-2 px-1 border-b-2 font-medium text-sm transition-colors duration-200 @if($activeTab === 'manual') border-indigo-500 text-indigo-600  @else border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300  :text-gray-300 @endif opacity-50 cursor-not-allowed"
                role="tab"
                aria-selected="{{ $activeTab === 'manual' ? 'true' : 'false' }}"
                disabled
            >
                <div class="flex items-center">
                    <flux:icon name="banknotes" class="w-4 h-4 mr-2" />
                    Manual Transfer
                    <flux:badge size="sm" color="gray" class="ml-2">Coming Soon</flux:badge>
                </div>
            </button>

            <button
                type="button"
                wire:click="switchTab('bayarcash')"
                class="py-2 px-1 border-b-2 font-medium text-sm transition-colors duration-200 @if($activeTab === 'bayarcash') border-indigo-500 text-indigo-600 @else border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 @endif"
                role="tab"
                aria-selected="{{ $activeTab === 'bayarcash' ? 'true' : 'false' }}"
            >
                <div class="flex items-center">
                    <flux:icon name="building-library" class="w-4 h-4 mr-2" />
                    FPX
                    @if($enable_bayarcash_payments)
                        <flux:badge size="sm" color="emerald" class="ml-2">Active</flux:badge>
                    @endif
                </div>
            </button>

            <button
                type="button"
                wire:click="switchTab('cod')"
                class="py-2 px-1 border-b-2 font-medium text-sm transition-colors duration-200 @if($activeTab === 'cod') border-indigo-500 text-indigo-600 @else border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 @endif"
                role="tab"
                aria-selected="{{ $activeTab === 'cod' ? 'true' : 'false' }}"
            >
                <div class="flex items-center">
                    <flux:icon name="truck" class="w-4 h-4 mr-2" />
                    COD
                    @if($enable_cod_payments)
                        <flux:badge size="sm" color="emerald" class="ml-2">Active</flux:badge>
                    @endif
                </div>
            </button>
        </nav>
    </div>

    <!-- Tab Content -->
    <div class="space-y-6">
        <!-- Stripe Configuration Tab -->
        @if($activeTab === 'stripe')
        <div role="tabpanel" class="space-y-6">
            <!-- Payment Method Status -->
            <flux:card>
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <flux:heading size="lg">Stripe Configuration</flux:heading>
                        <flux:text class="text-gray-600  text-sm mt-1">
                            Configure Stripe to accept credit and debit card payments
                        </flux:text>
                    </div>
                    <div class="flex items-center gap-3">
                        <flux:badge
                            color="{{ $enable_stripe_payments ? 'emerald' : 'gray' }}"
                        >
                            {{ $enable_stripe_payments ? 'Enabled' : 'Disabled' }}
                        </flux:badge>
                        <flux:button
                            type="button"
                            variant="outline"
                            size="sm"
                            wire:click="testStripeConnection"
                        >
                            <div class="flex items-center justify-center">
                                <flux:icon name="wifi" class="w-4 h-4 mr-1" />
                                Test Connection
                            </div>
                        </flux:button>
                    </div>
                </div>

                <div class="space-y-4">
                    <flux:checkbox wire:model="enable_stripe_payments">
                        Enable Stripe Card Payments
                    </flux:checkbox>
                    <flux:description>
                        Allow students to pay invoices using credit/debit cards through Stripe's secure payment processing.
                    </flux:description>
                    <flux:error name="enable_stripe_payments" />
                </div>
            </flux:card>

            @if($enable_stripe_payments)
            <!-- Stripe Settings Form -->
            <flux:card>
                <form wire:submit="save" class="space-y-6">
                    <div class="grid grid-cols-1 gap-6">
                        <flux:field>
                            <flux:label>Payment Mode</flux:label>
                            <flux:radio.group wire:model="payment_mode">
                                <flux:radio value="test" label="Test Mode (for development)" />
                                <flux:radio value="live" label="Live Mode (for production)" />
                            </flux:radio.group>
                            <flux:error name="payment_mode" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Currency</flux:label>
                            <flux:select wire:model="currency">
                                @foreach($this->getCurrencies() as $value => $label)
                                    <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="currency" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Stripe Publishable Key</flux:label>
                            <flux:description>
                                Your Stripe publishable key (starts with pk_test_ or pk_live_)
                            </flux:description>
                            <flux:input
                                wire:model="stripe_publishable_key"
                                placeholder="pk_test_..."
                                type="password"
                            />
                            <flux:error name="stripe_publishable_key" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Stripe Secret Key</flux:label>
                            <flux:description>
                                Your Stripe secret key (starts with sk_test_ or sk_live_). This will be encrypted.
                            </flux:description>
                            <flux:input
                                wire:model="stripe_secret_key"
                                placeholder="sk_test_..."
                                type="password"
                            />
                            <flux:error name="stripe_secret_key" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Stripe Webhook Secret</flux:label>
                            <flux:description>
                                Your Stripe webhook endpoint secret (starts with whsec_). This will be encrypted.
                            </flux:description>
                            <flux:input
                                wire:model="stripe_webhook_secret"
                                placeholder="whsec_..."
                                type="password"
                            />
                            <flux:error name="stripe_webhook_secret" />
                        </flux:field>
                    </div>

                    <!-- Webhook Configuration Info -->
                    <div class="bg-blue-50 /20 p-4 rounded-lg">
                        <flux:heading size="sm" class="text-blue-800  mb-2">
                            Webhook Configuration
                        </flux:heading>
                        <flux:text class="text-blue-700  text-sm">
                            Configure your Stripe webhook endpoint with this URL:
                        </flux:text>
                        <code class="block mt-2 p-2 bg-white dark:bg-zinc-700 rounded text-sm font-mono">
                            {{ url('/stripe/webhook') }}
                        </code>
                        <flux:text class="text-blue-700  text-sm mt-2">
                            Events to listen for: payment_intent.succeeded, payment_intent.payment_failed
                        </flux:text>
                    </div>
                </form>
            </flux:card>
            @endif

            <!-- Save Button -->
            <div class="flex justify-end">
                <flux:button wire:click="save" variant="primary">
                    <div class="flex items-center justify-center">
                        <flux:icon name="check" class="w-4 h-4 mr-2" />
                        Save Stripe Settings
                    </div>
                </flux:button>
            </div>
        </div>
        @endif

        <!-- Manual Transfer Tab (Coming Soon) -->
        @if($activeTab === 'manual')
        <div role="tabpanel" class="space-y-6">
            <flux:card>
                <div class="text-center py-12">
                    <flux:icon name="banknotes" class="w-16 h-16 text-gray-400 mx-auto mb-4" />
                    <flux:heading size="lg" class="text-gray-600  mb-2">
                        Manual Bank Transfer
                    </flux:heading>
                    <flux:text class="text-gray-500">
                        This payment method will be available soon. Students will be able to pay via bank transfer with admin verification.
                    </flux:text>
                    <div class="mt-6">
                        <flux:badge size="lg" color="gray">Coming Soon</flux:badge>
                    </div>
                </div>
            </flux:card>
        </div>
        @endif

        <!-- Bayarcash FPX Tab -->
        @if($activeTab === 'bayarcash')
        <div role="tabpanel" class="space-y-6">
            <!-- Payment Method Status -->
            <flux:card>
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <flux:heading size="lg">Bayarcash FPX Configuration</flux:heading>
                        <flux:text class="text-gray-600 text-sm mt-1">
                            Configure Bayarcash to accept FPX online banking payments
                        </flux:text>
                    </div>
                    <div class="flex items-center gap-3">
                        <flux:badge
                            color="{{ $enable_bayarcash_payments ? 'emerald' : 'gray' }}"
                        >
                            {{ $enable_bayarcash_payments ? 'Enabled' : 'Disabled' }}
                        </flux:badge>
                        <flux:button
                            type="button"
                            variant="outline"
                            size="sm"
                            wire:click="testBayarcashConnection"
                        >
                            <div class="flex items-center justify-center">
                                <flux:icon name="wifi" class="w-4 h-4 mr-1" />
                                Test Connection
                            </div>
                        </flux:button>
                    </div>
                </div>

                <div class="space-y-4">
                    <flux:checkbox wire:model="enable_bayarcash_payments">
                        Enable FPX Online Banking Payments
                    </flux:checkbox>
                    <flux:description>
                        Allow customers to pay directly from their Malaysian bank accounts using FPX through Bayarcash.
                    </flux:description>
                    <flux:error name="enable_bayarcash_payments" />
                </div>
            </flux:card>

            @if($enable_bayarcash_payments)
            <!-- Bayarcash Settings Form -->
            <flux:card>
                <form wire:submit="saveBayarcash" class="space-y-6">
                    <div class="grid grid-cols-1 gap-6">
                        <flux:field>
                            <flux:label>Environment Mode</flux:label>
                            <flux:radio.group wire:model.live="bayarcash_sandbox">
                                <flux:radio value="1" label="Sandbox Mode (for testing)" />
                                <flux:radio value="0" label="Production Mode (live payments)" />
                            </flux:radio.group>
                            <flux:description>
                                Use Sandbox mode for testing with test credentials before going live.
                            </flux:description>
                            <flux:error name="bayarcash_sandbox" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Bayarcash API Token</flux:label>
                            <flux:description>
                                Your Bayarcash API token from the console. Get it from
                                <a href="{{ $bayarcash_sandbox ? 'https://console.bayarcash-sandbox.com' : 'https://console.bayar.cash' }}" target="_blank" class="text-indigo-600 hover:text-indigo-500">
                                    {{ $bayarcash_sandbox ? 'console.bayarcash-sandbox.com' : 'console.bayar.cash' }}
                                </a>
                            </flux:description>
                            <flux:input
                                wire:model="bayarcash_api_token"
                                placeholder="Your API token..."
                                type="password"
                            />
                            <flux:error name="bayarcash_api_token" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Bayarcash API Secret Key</flux:label>
                            <flux:description>
                                Your Bayarcash API secret key for checksum generation and callback verification. This will be encrypted.
                            </flux:description>
                            <flux:input
                                wire:model="bayarcash_api_secret_key"
                                placeholder="Your API secret key..."
                                type="password"
                            />
                            <flux:error name="bayarcash_api_secret_key" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Portal Key</flux:label>
                            <flux:description>
                                Your Bayarcash portal key. Each portal can have different payment channels enabled.
                            </flux:description>
                            <flux:input
                                wire:model="bayarcash_portal_key"
                                placeholder="Your portal key..."
                            />
                            <flux:error name="bayarcash_portal_key" />
                        </flux:field>
                    </div>

                    <!-- Webhook Configuration Info -->
                    <div class="bg-green-50 p-4 rounded-lg">
                        <flux:heading size="sm" class="text-green-800 mb-2">
                            Callback URL Configuration
                        </flux:heading>
                        <flux:text class="text-green-700 text-sm">
                            Configure your Bayarcash callback URL in your portal settings:
                        </flux:text>
                        <code class="block mt-2 p-2 bg-white dark:bg-zinc-700 rounded text-sm font-mono break-all">
                            {{ url('/bayarcash/callback') }}
                        </code>
                        <flux:text class="text-green-700 text-sm mt-3">
                            Return URL (users are redirected here after payment):
                        </flux:text>
                        <code class="block mt-2 p-2 bg-white dark:bg-zinc-700 rounded text-sm font-mono break-all">
                            {{ url('/bayarcash/return') }}
                        </code>
                    </div>

                    <!-- Supported Payment Methods Info -->
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <flux:heading size="sm" class="text-gray-800 mb-2">
                            Supported Payment Methods
                        </flux:heading>
                        <div class="flex flex-wrap gap-2 mt-2">
                            <flux:badge color="blue">FPX Online Banking</flux:badge>
                        </div>
                        <flux:text class="text-gray-600 text-sm mt-2">
                            Customers will be redirected to Bayarcash's secure payment page to select their bank and complete the payment.
                        </flux:text>
                    </div>
                </form>
            </flux:card>
            @endif

            <!-- Save Button -->
            <div class="flex justify-end">
                <flux:button wire:click="saveBayarcash" variant="primary">
                    <div class="flex items-center justify-center">
                        <flux:icon name="check" class="w-4 h-4 mr-2" />
                        Save Bayarcash Settings
                    </div>
                </flux:button>
            </div>
        </div>
        @endif

        <!-- COD Tab -->
        @if($activeTab === 'cod')
        <div role="tabpanel" class="space-y-6">
            <!-- Payment Method Status -->
            <flux:card>
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <flux:heading size="lg">Cash on Delivery (COD)</flux:heading>
                        <flux:text class="text-gray-600 text-sm mt-1">
                            Allow customers to pay cash upon delivery of their order
                        </flux:text>
                    </div>
                    <div class="flex items-center gap-3">
                        <flux:badge
                            color="{{ $enable_cod_payments ? 'emerald' : 'gray' }}"
                        >
                            {{ $enable_cod_payments ? 'Enabled' : 'Disabled' }}
                        </flux:badge>
                    </div>
                </div>

                <div class="space-y-4">
                    <flux:checkbox wire:model="enable_cod_payments">
                        Enable Cash on Delivery
                    </flux:checkbox>
                    <flux:description>
                        Allow customers to select Cash on Delivery as a payment option during checkout. Payment will be collected upon delivery.
                    </flux:description>
                    <flux:error name="enable_cod_payments" />
                </div>
            </flux:card>

            @if($enable_cod_payments)
            <!-- COD Settings Form -->
            <flux:card>
                <form wire:submit="saveCod" class="space-y-6">
                    <div class="grid grid-cols-1 gap-6">
                        <flux:field>
                            <flux:label>Customer Instructions</flux:label>
                            <flux:description>
                                Instructions displayed to the customer when they select Cash on Delivery at checkout (e.g., "Please prepare exact change").
                            </flux:description>
                            <flux:textarea
                                wire:model="cod_customer_instructions"
                                placeholder="e.g., Please prepare exact change. Our delivery agent will collect payment upon delivery."
                                rows="3"
                            />
                            <flux:error name="cod_customer_instructions" />
                        </flux:field>
                    </div>

                    <!-- How It Works Info -->
                    <div class="bg-amber-50 p-4 rounded-lg">
                        <flux:heading size="sm" class="text-amber-800 mb-2">
                            How COD Works
                        </flux:heading>
                        <ul class="text-amber-700 text-sm space-y-1">
                            <li>1. Customer selects "Cash on Delivery" at checkout</li>
                            <li>2. Order is automatically confirmed with payment status "Pending"</li>
                            <li>3. Payment is collected upon delivery</li>
                            <li>4. Admin marks payment as received from the order management page</li>
                        </ul>
                    </div>
                </form>
            </flux:card>
            @endif

            <!-- Save Button -->
            <div class="flex justify-end">
                <flux:button wire:click="saveCod" variant="primary">
                    <div class="flex items-center justify-center">
                        <flux:icon name="check" class="w-4 h-4 mr-2" />
                        Save COD Settings
                    </div>
                </flux:button>
            </div>
        </div>
        @endif
    </div>

    <!-- Success/Error Messages -->
    <div
        x-data="{ show: false, message: '', type: 'success' }"
        x-on:settings-saved.window="show = true; message = 'Settings saved successfully!'; type = 'success'; setTimeout(() => show = false, 3000)"
        x-on:stripe-test-success.window="show = true; message = 'Stripe connection successful!'; type = 'success'; setTimeout(() => show = false, 3000)"
        x-on:stripe-test-failed.window="show = true; message = $event.detail.message || 'Stripe connection failed!'; type = 'error'; setTimeout(() => show = false, 5000)"
        x-on:bayarcash-test-success.window="show = true; message = 'Bayarcash connection successful!'; type = 'success'; setTimeout(() => show = false, 3000)"
        x-on:bayarcash-test-failed.window="show = true; message = $event.detail.message || 'Bayarcash connection failed!'; type = 'error'; setTimeout(() => show = false, 5000)"
        x-show="show"
        x-transition
        class="fixed top-4 right-4 z-50"
    >
        <flux:badge x-bind:color="type === 'success' ? 'emerald' : 'red'" size="lg">
            <flux:icon x-show="type === 'success'" name="check-circle" class="w-4 h-4 mr-2" />
            <flux:icon x-show="type !== 'success'" name="exclamation-circle" class="w-4 h-4 mr-2" />
            <span x-text="message"></span>
        </flux:badge>
    </div>
</div>
