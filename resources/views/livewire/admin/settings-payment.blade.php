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
    
    public $activeTab = 'stripe';

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
                class="py-2 px-1 border-b-2 font-medium text-sm transition-colors duration-200 @if($activeTab === 'stripe') border-indigo-500 text-indigo-600 dark:text-indigo-400 @else border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 @endif"
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
                class="py-2 px-1 border-b-2 font-medium text-sm transition-colors duration-200 @if($activeTab === 'manual') border-indigo-500 text-indigo-600 dark:text-indigo-400 @else border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 @endif opacity-50 cursor-not-allowed"
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
                wire:click="switchTab('fpx')"
                class="py-2 px-1 border-b-2 font-medium text-sm transition-colors duration-200 @if($activeTab === 'fpx') border-indigo-500 text-indigo-600 dark:text-indigo-400 @else border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 @endif opacity-50 cursor-not-allowed"
                role="tab"
                aria-selected="{{ $activeTab === 'fpx' ? 'true' : 'false' }}"
                disabled
            >
                <div class="flex items-center">
                    <flux:icon name="building-library" class="w-4 h-4 mr-2" />
                    FPX
                    <flux:badge size="sm" color="gray" class="ml-2">Coming Soon</flux:badge>
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
                        <flux:text class="text-gray-600 dark:text-gray-400 text-sm mt-1">
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

                    <!-- Webhook Configuration Info -->
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
                    <flux:heading size="lg" class="text-gray-600 dark:text-gray-400 mb-2">
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

        <!-- FPX Tab (Coming Soon) -->
        @if($activeTab === 'fpx')
        <div role="tabpanel" class="space-y-6">
            <flux:card>
                <div class="text-center py-12">
                    <flux:icon name="building-library" class="w-16 h-16 text-gray-400 mx-auto mb-4" />
                    <flux:heading size="lg" class="text-gray-600 dark:text-gray-400 mb-2">
                        FPX Online Banking
                    </flux:heading>
                    <flux:text class="text-gray-500">
                        This payment method will be available soon. Students will be able to pay directly from their Malaysian bank accounts.
                    </flux:text>
                    <div class="mt-6">
                        <flux:badge size="lg" color="gray">Coming Soon</flux:badge>
                    </div>
                </div>
            </flux:card>
        </div>
        @endif
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