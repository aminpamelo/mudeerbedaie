<?php

use App\Services\SettingsService;
use App\Services\Shipping\JntShippingService;
use App\Services\Shipping\ShippingManager;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new class extends Component {
    // JNT settings (Malaysia Open Platform)
    public string $jnt_customer_code = '';      // apiAccount header
    public string $jnt_private_key = '';        // For digest calculation (MD5 + Base64)
    public string $jnt_password = '';           // Password in bizContent for order operations
    public string $jnt_sandbox = '1';
    public bool $enable_jnt_shipping = false;
    public string $jnt_default_service_type = 'EZ';

    // Sender defaults
    public string $sender_name = '';
    public string $sender_phone = '';
    public string $sender_address = '';
    public string $sender_city = '';
    public string $sender_state = '';
    public string $sender_postal_code = '';

    #[Url(as: 'tab')]
    public string $activeTab = 'jnt';

    private function getSettingsService(): SettingsService
    {
        return app(SettingsService::class);
    }

    public function mount(): void
    {
        // Load JNT settings (Malaysia Open Platform)
        $this->jnt_customer_code = $this->getSettingsService()->get('jnt_customer_code', '');
        $this->jnt_private_key = $this->getSettingsService()->get('jnt_private_key', '');
        $this->jnt_password = $this->getSettingsService()->get('jnt_password', '');
        $sandboxValue = $this->getSettingsService()->get('jnt_sandbox', true);
        $this->jnt_sandbox = $sandboxValue ? '1' : '0';
        $this->enable_jnt_shipping = (bool) $this->getSettingsService()->get('enable_jnt_shipping', false);
        $this->jnt_default_service_type = $this->getSettingsService()->get('jnt_default_service_type', 'EZ');

        // Load sender defaults
        $senderDefaults = $this->getSettingsService()->getShippingSenderDefaults();
        $this->sender_name = $senderDefaults['name'];
        $this->sender_phone = $senderDefaults['phone'];
        $this->sender_address = $senderDefaults['address'];
        $this->sender_city = $senderDefaults['city'];
        $this->sender_state = $senderDefaults['state'];
        $this->sender_postal_code = $senderDefaults['postal_code'];
    }

    public function saveJnt(): void
    {
        $this->validate([
            'jnt_customer_code' => 'nullable|string|max:255',
            'jnt_private_key' => 'nullable|string|max:500',
            'jnt_password' => 'nullable|string|max:500',
            'jnt_sandbox' => 'required|in:0,1',
            'enable_jnt_shipping' => 'boolean',
            'jnt_default_service_type' => 'required|in:EZ,EX,FD',
        ]);

        $settings = $this->getSettingsService();
        $settings->set('jnt_customer_code', $this->jnt_customer_code, 'string', 'shipping');
        $settings->set('jnt_private_key', $this->jnt_private_key, 'encrypted', 'shipping');
        $settings->set('jnt_password', $this->jnt_password, 'encrypted', 'shipping');
        $settings->set('jnt_sandbox', $this->jnt_sandbox === '1', 'boolean', 'shipping');
        $settings->set('enable_jnt_shipping', $this->enable_jnt_shipping, 'boolean', 'shipping');
        $settings->set('jnt_default_service_type', $this->jnt_default_service_type, 'string', 'shipping');

        $this->dispatch('settings-saved');
    }

    public function saveSenderDefaults(): void
    {
        $this->validate([
            'sender_name' => 'nullable|string|max:255',
            'sender_phone' => 'nullable|string|max:50',
            'sender_address' => 'nullable|string|max:500',
            'sender_city' => 'nullable|string|max:255',
            'sender_state' => 'nullable|string|max:255',
            'sender_postal_code' => 'nullable|string|max:20',
        ]);

        $settings = $this->getSettingsService();
        $settings->set('shipping_sender_name', $this->sender_name, 'string', 'shipping');
        $settings->set('shipping_sender_phone', $this->sender_phone, 'string', 'shipping');
        $settings->set('shipping_sender_address', $this->sender_address, 'string', 'shipping');
        $settings->set('shipping_sender_city', $this->sender_city, 'string', 'shipping');
        $settings->set('shipping_sender_state', $this->sender_state, 'string', 'shipping');
        $settings->set('shipping_sender_postal_code', $this->sender_postal_code, 'string', 'shipping');

        $this->dispatch('settings-saved');
    }

    public function testJntConnection(): void
    {
        if (empty($this->jnt_customer_code) || empty($this->jnt_private_key)) {
            $this->dispatch('jnt-test-failed', message: 'Please enter Customer Code and Private Key first.');
            return;
        }

        try {
            // Save settings first so the service can use them
            $this->saveJnt();

            $shippingManager = app(ShippingManager::class);
            $jntService = $shippingManager->getProvider('jnt');

            if ($jntService->testConnection()) {
                $this->dispatch('jnt-test-success');
            } else {
                $this->dispatch('jnt-test-failed', message: 'Connection test failed. Please check your credentials.');
            }
        } catch (\Exception $e) {
            $this->dispatch('jnt-test-failed', message: $e->getMessage());
        }
    }

    public function switchTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function getServiceTypes(): array
    {
        return [
            'EZ' => 'EZ - Domestic Standard',
            'EX' => 'EX - Express Next Day',
            'FD' => 'FD - Fresh Delivery',
        ];
    }

    public function getMalaysianStates(): array
    {
        return [
            'Johor', 'Kedah', 'Kelantan', 'Melaka', 'Negeri Sembilan',
            'Pahang', 'Perak', 'Perlis', 'Pulau Pinang', 'Sabah',
            'Sarawak', 'Selangor', 'Terengganu',
            'W.P. Kuala Lumpur', 'W.P. Labuan', 'W.P. Putrajaya',
        ];
    }

    public function layout(): string
    {
        return 'components.admin.settings-layout';
    }

    public function layoutData(): array
    {
        return [
            'title' => 'Shipping Settings',
            'activeTab' => 'shipping',
        ];
    }
}
?>

<div>
    <!-- Success Notification -->
    <div
        x-data="{ show: false }"
        x-on:settings-saved.window="show = true; setTimeout(() => show = false, 3000)"
        x-show="show"
        x-transition
        x-cloak
        class="fixed top-4 right-4 z-50"
    >
        <flux:badge color="emerald" size="lg">
            <div class="flex items-center">
                <flux:icon name="check-circle" class="w-4 h-4 mr-2" />
                Settings saved successfully!
            </div>
        </flux:badge>
    </div>

    <!-- JNT Test Success -->
    <div
        x-data="{ show: false }"
        x-on:jnt-test-success.window="show = true; setTimeout(() => show = false, 3000)"
        x-show="show"
        x-transition
        x-cloak
        class="fixed top-4 right-4 z-50"
    >
        <flux:badge color="emerald" size="lg">
            <div class="flex items-center">
                <flux:icon name="check-circle" class="w-4 h-4 mr-2" />
                J&T Express connection successful!
            </div>
        </flux:badge>
    </div>

    <!-- JNT Test Failed -->
    <div
        x-data="{ show: false, message: '' }"
        x-on:jnt-test-failed.window="show = true; message = $event.detail.message; setTimeout(() => show = false, 5000)"
        x-show="show"
        x-transition
        x-cloak
        class="fixed top-4 right-4 z-50"
    >
        <flux:badge color="red" size="lg">
            <div class="flex items-center">
                <flux:icon name="x-circle" class="w-4 h-4 mr-2" />
                <span x-text="message"></span>
            </div>
        </flux:badge>
    </div>

    <!-- Shipping Provider Tabs -->
    <div class="mb-6">
        <nav class="flex space-x-8" role="tablist">
            <button
                type="button"
                wire:click="switchTab('jnt')"
                class="py-2 px-1 border-b-2 font-medium text-sm transition-colors duration-200 @if($activeTab === 'jnt') border-indigo-500 text-indigo-600 @else border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 @endif"
                role="tab"
                aria-selected="{{ $activeTab === 'jnt' ? 'true' : 'false' }}"
            >
                <div class="flex items-center">
                    <flux:icon name="truck" class="w-4 h-4 mr-2" />
                    J&T Express
                </div>
            </button>

            <button
                type="button"
                wire:click="switchTab('sender')"
                class="py-2 px-1 border-b-2 font-medium text-sm transition-colors duration-200 @if($activeTab === 'sender') border-indigo-500 text-indigo-600 @else border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 @endif"
                role="tab"
                aria-selected="{{ $activeTab === 'sender' ? 'true' : 'false' }}"
            >
                <div class="flex items-center">
                    <flux:icon name="map-pin" class="w-4 h-4 mr-2" />
                    Sender Defaults
                </div>
            </button>
        </nav>
    </div>

    <!-- Tab Content -->
    <div class="space-y-6">
        @if($activeTab === 'jnt')
        <div role="tabpanel" class="space-y-6">
            <!-- JNT Settings Card -->
            <div class="rounded-lg border border-zinc-200 bg-white p-6">
                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-900">J&T Express Configuration</h3>
                    <p class="mt-1 text-sm text-gray-500">Configure your J&T Express API credentials and preferences.</p>
                </div>

                <form wire:submit="saveJnt" class="space-y-6">
                    <!-- Enable Toggle -->
                    <div class="flex items-center justify-between rounded-lg border border-zinc-200 p-4">
                        <div>
                            <h4 class="text-sm font-medium text-gray-900">Enable J&T Express Shipping</h4>
                            <p class="text-sm text-gray-500">Allow J&T Express as a shipping option for orders.</p>
                        </div>
                        <flux:switch wire:model="enable_jnt_shipping" />
                    </div>

                    <!-- Environment Mode -->
                    <flux:field>
                        <flux:label>Environment Mode *</flux:label>
                        <flux:description>Select whether to use sandbox (testing) or production API.</flux:description>
                        <div class="mt-2 flex gap-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <flux:radio wire:model="jnt_sandbox" value="1" />
                                <span class="text-sm">Sandbox (Testing)</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <flux:radio wire:model="jnt_sandbox" value="0" />
                                <span class="text-sm">Production</span>
                            </label>
                        </div>
                        <flux:error name="jnt_sandbox" />
                    </flux:field>

                    <!-- Customer Code -->
                    <flux:field>
                        <flux:label>Customer Code *</flux:label>
                        <flux:description>Your J&T Malaysia account code (apiAccount). Sandbox: ITTEST0001</flux:description>
                        <flux:input wire:model="jnt_customer_code" placeholder="e.g., ITTEST0001" />
                        <flux:error name="jnt_customer_code" />
                    </flux:field>

                    <!-- Private Key -->
                    <flux:field>
                        <flux:label>Private Key *</flux:label>
                        <flux:description>Used for API signature/digest calculation. Sandbox: Sfx6H8d4</flux:description>
                        <flux:input wire:model="jnt_private_key" type="password" placeholder="Enter your private key" />
                        <flux:error name="jnt_private_key" />
                    </flux:field>

                    <!-- Business Password -->
                    <flux:field>
                        <flux:label>Business Password</flux:label>
                        <flux:description>Password included in order operations (bizContent). Sandbox: AA7EDDC3B82704CA3717E88E67A3CAF1</flux:description>
                        <flux:input wire:model="jnt_password" type="password" placeholder="Enter your business password" />
                        <flux:error name="jnt_password" />
                    </flux:field>

                    <!-- Default Service Type -->
                    <flux:field>
                        <flux:label>Default Service Type *</flux:label>
                        <flux:description>The default shipping service type for new orders.</flux:description>
                        <flux:select wire:model="jnt_default_service_type">
                            @foreach($this->getServiceTypes() as $code => $label)
                                <flux:select.option value="{{ $code }}">{{ $label }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="jnt_default_service_type" />
                    </flux:field>

                    <!-- Action Buttons -->
                    <div class="flex items-center justify-between border-t border-zinc-200 pt-4">
                        <flux:button
                            type="button"
                            variant="outline"
                            wire:click="testJntConnection"
                            wire:loading.attr="disabled"
                        >
                            <div class="flex items-center justify-center">
                                <flux:icon name="signal" class="w-4 h-4 mr-1" />
                                <span wire:loading.remove wire:target="testJntConnection">Test Connection</span>
                                <span wire:loading wire:target="testJntConnection">Testing...</span>
                            </div>
                        </flux:button>

                        <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="saveJnt">Save J&T Settings</span>
                            <span wire:loading wire:target="saveJnt">Saving...</span>
                        </flux:button>
                    </div>
                </form>
            </div>

            <!-- Help Info -->
            <div class="rounded-lg border border-blue-200 bg-blue-50 p-4">
                <div class="flex">
                    <flux:icon name="information-circle" class="w-5 h-5 text-blue-400 mr-3 shrink-0" />
                    <div class="text-sm text-blue-700">
                        <p class="font-medium mb-1">J&T Malaysia Open Platform API</p>
                        <ul class="list-disc list-inside space-y-1 text-blue-600">
                            <li>This integration uses the <a href="https://ylopen.jtexpress.my/" target="_blank" class="underline">J&T Malaysia Open Platform</a>.</li>
                            <li>Use sandbox mode for testing. Sandbox credentials are shown in field descriptions.</li>
                            <li>Contact J&T Malaysia to register and obtain production credentials.</li>
                            <li>The signature is calculated as: Base64(MD5(bizContent + privateKey))</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($activeTab === 'sender')
        <div role="tabpanel" class="space-y-6">
            <!-- Sender Defaults Card -->
            <div class="rounded-lg border border-zinc-200 bg-white p-6">
                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-900">Default Sender Information</h3>
                    <p class="mt-1 text-sm text-gray-500">Set the default origin/sender address used for all shipments.</p>
                </div>

                <form wire:submit="saveSenderDefaults" class="space-y-6">
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <!-- Sender Name -->
                        <flux:field>
                            <flux:label>Sender Name</flux:label>
                            <flux:input wire:model="sender_name" placeholder="Company or person name" />
                            <flux:error name="sender_name" />
                        </flux:field>

                        <!-- Sender Phone -->
                        <flux:field>
                            <flux:label>Sender Phone</flux:label>
                            <flux:input wire:model="sender_phone" placeholder="e.g., 0123456789" />
                            <flux:error name="sender_phone" />
                        </flux:field>
                    </div>

                    <!-- Sender Address -->
                    <flux:field>
                        <flux:label>Address</flux:label>
                        <flux:textarea wire:model="sender_address" placeholder="Full street address" rows="2" />
                        <flux:error name="sender_address" />
                    </flux:field>

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                        <!-- City -->
                        <flux:field>
                            <flux:label>City</flux:label>
                            <flux:input wire:model="sender_city" placeholder="e.g., Shah Alam" />
                            <flux:error name="sender_city" />
                        </flux:field>

                        <!-- State -->
                        <flux:field>
                            <flux:label>State</flux:label>
                            <flux:select wire:model="sender_state">
                                <flux:select.option value="">Select state</flux:select.option>
                                @foreach($this->getMalaysianStates() as $state)
                                    <flux:select.option value="{{ $state }}">{{ $state }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="sender_state" />
                        </flux:field>

                        <!-- Postal Code -->
                        <flux:field>
                            <flux:label>Postal Code</flux:label>
                            <flux:input wire:model="sender_postal_code" placeholder="e.g., 40000" />
                            <flux:error name="sender_postal_code" />
                        </flux:field>
                    </div>

                    <div class="flex justify-end border-t border-zinc-200 pt-4">
                        <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="saveSenderDefaults">Save Sender Defaults</span>
                            <span wire:loading wire:target="saveSenderDefaults">Saving...</span>
                        </flux:button>
                    </div>
                </form>
            </div>
        </div>
        @endif
    </div>
</div>
