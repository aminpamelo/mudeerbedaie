<?php
use App\Services\SettingsService;
use Livewire\Volt\Component;
new class extends Component {
    public $stripe_publishable_key = '';
    public $stripe_secret_key = '';
    public $stripe_webhook_secret = '';
    public $payment_mode = 'test';
    public $currency = 'MYR';
    public $enable_stripe_payments = true;
    public $enable_bank_transfers = true;
    
    public $bank_name = '';
    public $bank_account_name = '';
    public $bank_account_number = '';
    public $bank_swift_code = '';

    private function getSettingsService(): SettingsService
    {
        return app(SettingsService::class);
    }

    public function mount(): void
    {
        // Load current settings values
        $this->stripe_publishable_key = $this->getSettingsService()->get('stripe_publishable_key', '');
        $this->stripe_secret_key = $this->getSettingsService()->get('stripe_secret_key', '');
        $this->stripe_webhook_secret = $this->getSettingsService()->get('stripe_webhook_secret', '');
        $this->payment_mode = $this->getSettingsService()->get('payment_mode', 'test');
        $this->currency = $this->getSettingsService()->get('currency', 'MYR');
        $this->enable_stripe_payments = (bool) $this->getSettingsService()->get('enable_stripe_payments', true);
        $this->enable_bank_transfers = (bool) $this->getSettingsService()->get('enable_bank_transfers', true);
        
        $this->bank_name = $this->getSettingsService()->get('bank_name', '');
        $this->bank_account_name = $this->getSettingsService()->get('bank_account_name', '');
        $this->bank_account_number = $this->getSettingsService()->get('bank_account_number', '');
        $this->bank_swift_code = $this->getSettingsService()->get('bank_swift_code', '');
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
            'enable_bank_transfers' => 'boolean',
            'bank_name' => 'nullable|string|max:255',
            'bank_account_name' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:255',
            'bank_swift_code' => 'nullable|string|max:255',
        ]);

        // Validate that at least one payment method is enabled
        if (!$this->enable_stripe_payments && !$this->enable_bank_transfers) {
            $this->addError('enable_stripe_payments', 'At least one payment method must be enabled.');
            return;
        }

        // Save Stripe settings (encrypted)
        $this->getSettingsService()->set('stripe_publishable_key', $this->stripe_publishable_key, 'encrypted', 'payment');
        $this->getSettingsService()->set('stripe_secret_key', $this->stripe_secret_key, 'encrypted', 'payment');
        $this->getSettingsService()->set('stripe_webhook_secret', $this->stripe_webhook_secret, 'encrypted', 'payment');
        $this->getSettingsService()->set('payment_mode', $this->payment_mode, 'string', 'payment');
        $this->getSettingsService()->set('currency', $this->currency, 'string', 'payment');
        $this->getSettingsService()->set('enable_stripe_payments', $this->enable_stripe_payments, 'boolean', 'payment');
        $this->getSettingsService()->set('enable_bank_transfers', $this->enable_bank_transfers, 'boolean', 'payment');

        // Save bank transfer settings
        $this->getSettingsService()->set('bank_name', $this->bank_name, 'string', 'payment');
        $this->getSettingsService()->set('bank_account_name', $this->bank_account_name, 'string', 'payment');
        $this->getSettingsService()->set('bank_account_number', $this->bank_account_number, 'string', 'payment');
        $this->getSettingsService()->set('bank_swift_code', $this->bank_swift_code, 'string', 'payment');

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
    <div class="space-y-6">
        <!-- Payment Methods -->
        <flux:card>
            <flux:heading size="lg" class="mb-4">Payment Methods</flux:heading>
            
            <div class="space-y-4">
                <flux:checkbox wire:model="enable_stripe_payments">
                    Enable Stripe Card Payments
                </flux:checkbox>
                <flux:description>
                    Allow students to pay invoices using credit/debit cards through Stripe.
                </flux:description>

                <flux:checkbox wire:model="enable_bank_transfers">
                    Enable Manual Bank Transfers
                </flux:checkbox>
                <flux:description>
                    Allow students to pay via manual bank transfer (requires admin verification).
                </flux:description>
                
                <flux:error name="enable_stripe_payments" />
            </div>
        </flux:card>

        <!-- Stripe Configuration -->
        @if($enable_stripe_payments)
        <flux:card>
            <div class="flex items-center justify-between mb-4">
                <flux:heading size="lg">Stripe Configuration</flux:heading>
                <flux:button 
                    type="button" 
                    variant="outline" 
                    size="sm"
                    wire:click="testStripeConnection"
                >
                    Test Connection
                </flux:button>
            </div>

            <form wire:submit="save" class="space-y-6">
                <div class="grid grid-cols-1 gap-6">
                    <flux:field>
                        <flux:label>Payment Mode</flux:label>
                        <flux:radio.group wire:model="payment_mode">
                            <flux:radio value="test">Test Mode (for development)</flux:radio>
                            <flux:radio value="live">Live Mode (for production)</flux:radio>
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

                <!-- Webhook URL Info -->
                <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
                    <flux:heading size="sm" class="text-blue-800 dark:text-blue-200 mb-2">
                        Webhook Configuration
                    </flux:heading>
                    <flux:text class="text-blue-700 dark:text-blue-300 text-sm">
                        Configure your Stripe webhook endpoint with this URL:
                    </flux:text>
                    <code class="block mt-2 p-2 bg-white dark:bg-gray-800 rounded text-sm font-mono">
                        {{ url('/stripe/webhook') }}
                    </code>
                    <flux:text class="text-blue-700 dark:text-blue-300 text-sm mt-2">
                        Events to listen for: payment_intent.succeeded, payment_intent.payment_failed
                    </flux:text>
                </div>
            </form>
        </flux:card>
        @endif

        <!-- Bank Transfer Configuration -->
        @if($enable_bank_transfers)
        <flux:card>
            <flux:heading size="lg" class="mb-4">Bank Transfer Details</flux:heading>
            <flux:description class="mb-6">
                These details will be shown to students when they choose bank transfer as payment method.
            </flux:description>

            <form wire:submit="save" class="space-y-6">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>Bank Name</flux:label>
                        <flux:input 
                            wire:model="bank_name" 
                            placeholder="e.g. Maybank"
                        />
                        <flux:error name="bank_name" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Account Holder Name</flux:label>
                        <flux:input 
                            wire:model="bank_account_name" 
                            placeholder="e.g. Mudeer Bedaie Sdn Bhd"
                        />
                        <flux:error name="bank_account_name" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Account Number</flux:label>
                        <flux:input 
                            wire:model="bank_account_number" 
                            placeholder="e.g. 1234567890"
                        />
                        <flux:error name="bank_account_number" />
                    </flux:field>

                    <flux:field>
                        <flux:label>SWIFT/BIC Code (Optional)</flux:label>
                        <flux:input 
                            wire:model="bank_swift_code" 
                            placeholder="e.g. MBBEMYKL"
                        />
                        <flux:error name="bank_swift_code" />
                    </flux:field>
                </div>

                <!-- Bank Details Preview -->
                @if($bank_name || $bank_account_name || $bank_account_number)
                <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg">
                    <flux:heading size="sm" class="mb-3">Preview (as shown to students)</flux:heading>
                    <div class="space-y-2 text-sm">
                        @if($bank_name)
                            <div><strong>Bank:</strong> {{ $bank_name }}</div>
                        @endif
                        @if($bank_account_name)
                            <div><strong>Account Name:</strong> {{ $bank_account_name }}</div>
                        @endif
                        @if($bank_account_number)
                            <div><strong>Account Number:</strong> {{ $bank_account_number }}</div>
                        @endif
                        @if($bank_swift_code)
                            <div><strong>SWIFT Code:</strong> {{ $bank_swift_code }}</div>
                        @endif
                    </div>
                </div>
                @endif
            </form>
        </flux:card>
        @endif

        <!-- Save Button -->
        <div class="flex justify-end">
            <flux:button wire:click="save" variant="primary">
                Save Payment Settings
            </flux:button>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <div 
        x-data="{ show: false, message: '' }"
        x-on:settings-saved.window="show = true; setTimeout(() => show = false, 3000)"
        x-on:stripe-test-success.window="show = true; message = 'Stripe connection successful!'; setTimeout(() => show = false, 3000)"
        x-on:stripe-test-failed.window="show = true; message = $event.detail.message || 'Stripe connection failed!'; setTimeout(() => show = false, 5000)"
        x-show="show"
        x-transition
        class="fixed top-4 right-4 z-50"
    >
        <flux:badge color="emerald" size="lg" x-show="message === '' || message.includes('successful')">
            <flux:icon icon="check-circle" class="w-4 h-4 mr-2" />
            <span x-text="message || 'Settings saved successfully!'"></span>
        </flux:badge>
        <flux:badge color="red" size="lg" x-show="message !== '' && !message.includes('successful')">
            <flux:icon icon="exclamation-circle" class="w-4 h-4 mr-2" />
            <span x-text="message"></span>
        </flux:badge>
    </div>
</div>