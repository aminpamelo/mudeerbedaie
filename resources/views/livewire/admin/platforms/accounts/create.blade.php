<?php

use App\Models\Platform;
use App\Models\User;
use Livewire\Volt\Component;

new class extends Component
{
    public Platform $platform;

    // Form fields
    public $selected_hosts = [];

    public $account_name = '';

    public $seller_id = '';

    public $shop_id = '';

    public $business_manager_id = '';

    public $api_key = '';

    public $api_secret = '';

    public $access_token = '';

    public $refresh_token = '';

    public $notes = '';

    public $is_active = true;

    // Form state
    public $showAdvancedSettings = false;

    public $showApiCredentials = false;

    public function mount(Platform $platform)
    {
        $this->platform = $platform;

        // Show API credentials section if platform supports API
        $this->showApiCredentials = $platform->settings['api_available'] ?? false;
    }

    public function rules()
    {
        $rules = [
            'selected_hosts' => 'required|array|min:1',
            'selected_hosts.*' => 'exists:users,id',
            'account_name' => 'required|string|max:255',
            'seller_id' => 'nullable|string|max:255',
            'shop_id' => 'nullable|string|max:255',
            'business_manager_id' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ];

        // Add API credential rules if platform supports API
        if ($this->platform->settings['api_available'] ?? false) {
            $rules['api_key'] = 'nullable|string|max:500';
            $rules['api_secret'] = 'nullable|string|max:500';
            $rules['access_token'] = 'nullable|string|max:1000';
            $rules['refresh_token'] = 'nullable|string|max:1000';
        }

        return $rules;
    }

    public function messages()
    {
        return [
            'selected_hosts.required' => 'Please select at least one live host for this account.',
            'selected_hosts.min' => 'Please select at least one live host for this account.',
            'selected_hosts.*.exists' => 'One or more selected live hosts are invalid.',
            'account_name.required' => 'Account name is required to identify this connection.',
            'account_name.max' => 'Account name cannot exceed 255 characters.',
            'seller_id.max' => 'Seller ID cannot exceed 255 characters.',
            'shop_id.max' => 'Shop ID cannot exceed 255 characters.',
            'business_manager_id.max' => 'Business Manager ID cannot exceed 255 characters.',
            'notes.max' => 'Notes cannot exceed 1000 characters.',
        ];
    }

    public function create()
    {
        $validated = $this->validate();

        // Create the platform account
        $account = $this->platform->accounts()->create([
            'user_id' => $validated['selected_hosts'][0], // Keep first host for backward compatibility
            'name' => $validated['account_name'],
            'account_id' => $validated['seller_id'] ?: null,
            'shop_id' => $validated['shop_id'] ?: null,
            'business_manager_id' => $validated['business_manager_id'] ?: null,
            'description' => $validated['notes'] ?: null,
            'is_active' => $validated['is_active'],
            'auto_sync_orders' => false,
            'auto_sync_products' => false,
        ]);

        // Sync selected live hosts with the account
        $account->liveHosts()->sync($validated['selected_hosts']);

        // Store API credentials if provided and platform supports API
        if (($this->platform->settings['api_available'] ?? false) &&
            ($validated['api_key'] || $validated['api_secret'] || $validated['access_token'])) {

            $account->credentials()->create([
                'platform_id' => $this->platform->id,
                'credential_type' => 'api_key',
                'name' => 'API Credentials',
                'encrypted_value' => $validated['api_key'] ? encrypt($validated['api_key']) : null,
                'encrypted_refresh_token' => $validated['refresh_token'] ? encrypt($validated['refresh_token']) : null,
                'metadata' => [
                    'api_secret' => $validated['api_secret'] ? encrypt($validated['api_secret']) : null,
                    'access_token' => $validated['access_token'] ? encrypt($validated['access_token']) : null,
                ],
                'is_active' => true,
                'auto_refresh' => false,
            ]);
        }

        session()->flash('success', "Account '{$validated['account_name']}' has been created successfully for {$this->platform->display_name}.");

        return redirect()->route('platforms.accounts.index', $this->platform);
    }

    public function toggleAdvancedSettings()
    {
        $this->showAdvancedSettings = ! $this->showAdvancedSettings;
    }

    public function getLiveHostsProperty()
    {
        return User::where('role', 'live_host')
            ->orderBy('name')
            ->get();
    }

    public function with()
    {
        return [
            'platformRequirements' => $this->platform->credential_requirements ?? [],
        ];
    }
}; ?>

<div>
    {{-- Breadcrumb Navigation --}}
    <div class="mb-6">
        <nav class="flex" aria-label="Breadcrumb">
            <ol class="flex items-center space-x-4">
                <li>
                    <div>
                        <flux:button variant="ghost" size="sm" :href="route('platforms.index')" wire:navigate>
                            <div class="flex items-center justify-center">
                                <flux:icon name="chevron-left" class="w-4 h-4 mr-1" />
                                Platforms
                            </div>
                        </flux:button>
                    </div>
                </li>
                <li>
                    <div class="flex items-center">
                        <flux:icon name="chevron-right" class="w-5 h-5 text-zinc-400" />
                        <flux:button variant="ghost" size="sm" :href="route('platforms.accounts.index', $platform)" wire:navigate>
                            <span class="ml-4 text-sm font-medium text-zinc-500">{{ $platform->display_name }} Accounts</span>
                        </flux:button>
                    </div>
                </li>
                <li>
                    <div class="flex items-center">
                        <flux:icon name="chevron-right" class="w-5 h-5 text-zinc-400" />
                        <span class="ml-4 text-sm font-medium text-zinc-500">Add Account</span>
                    </div>
                </li>
            </ol>
        </nav>
    </div>

    {{-- Header Section --}}
    <div class="mb-6">
        <flux:heading size="xl">Add {{ $platform->display_name }} Account</flux:heading>
        <flux:text class="mt-2">Set up a new seller account connection for {{ $platform->display_name }}. Enter your account details manually to get started.</flux:text>
    </div>

    {{-- Platform Info --}}
    <div class="mb-6 bg-white rounded-lg border p-4">
        <div class="flex items-center space-x-4">
            @if($platform->logo_url)
                <img src="{{ $platform->logo_url }}" alt="{{ $platform->name }}" class="w-10 h-10 rounded-lg">
            @else
                <div class="w-10 h-10 rounded-lg flex items-center justify-center text-white text-lg font-bold"
                     style="background: {{ $platform->color_primary ?? '#6b7280' }}">
                    {{ substr($platform->name, 0, 1) }}
                </div>
            @endif
            <div>
                <flux:heading size="sm">{{ $platform->display_name }}</flux:heading>
                <flux:text size="sm" class="text-zinc-600">{{ ucfirst(str_replace('_', ' ', $platform->type)) }} Platform - Manual Setup</flux:text>
            </div>
        </div>
    </div>

    <form wire:submit="create">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Main Form --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- Basic Account Information --}}
                <div class="bg-white rounded-lg border p-6">
                    <flux:heading size="lg" class="mb-4">Account Information</flux:heading>

                    <div class="space-y-4">
                        <flux:field>
                            <flux:label>Live Hosts *</flux:label>
                            <flux:description>Select one or more live hosts who can manage this platform account</flux:description>

                            @if($this->liveHosts->isNotEmpty())
                                <div class="mt-2 space-y-2">
                                    @foreach($this->liveHosts as $host)
                                        <flux:checkbox
                                            wire:model="selected_hosts"
                                            value="{{ $host->id }}"
                                            label="{{ $host->name }} ({{ $host->email }})"
                                        />
                                    @endforeach
                                </div>
                            @else
                                <flux:description class="text-amber-600">
                                    No live hosts found. Please create a user with the 'live_host' role first.
                                </flux:description>
                            @endif

                            <flux:error name="selected_hosts" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Account Name *</flux:label>
                            <flux:input wire:model="account_name" placeholder="e.g., My TikTok Shop Account" />
                            <flux:error name="account_name" />
                            <flux:description>A friendly name to identify this account in your dashboard</flux:description>
                        </flux:field>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <flux:field>
                                <flux:label>Seller ID</flux:label>
                                <flux:input wire:model="seller_id" placeholder="Enter your seller ID" />
                                <flux:error name="seller_id" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Shop ID</flux:label>
                                <flux:input wire:model="shop_id" placeholder="Enter your shop ID" />
                                <flux:error name="shop_id" />
                            </flux:field>
                        </div>

                        <flux:field>
                            <flux:label>Business Manager ID</flux:label>
                            <flux:input wire:model="business_manager_id" placeholder="Enter business manager ID (if applicable)" />
                            <flux:error name="business_manager_id" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Notes</flux:label>
                            <flux:textarea wire:model="notes" placeholder="Add any notes about this account setup..." rows="3" />
                            <flux:error name="notes" />
                        </flux:field>
                    </div>
                </div>

                {{-- API Credentials (if platform supports it) --}}
                @if($showApiCredentials)
                <div class="bg-white rounded-lg border p-6">
                    <div class="flex items-center justify-between mb-4">
                        <flux:heading size="lg">API Credentials</flux:heading>
                        <flux:badge color="amber">Coming Soon</flux:badge>
                    </div>

                    <div class="p-4 bg-amber-50 border border-amber-200 rounded-lg mb-4">
                        <div class="flex">
                            <flux:icon name="information-circle" class="w-5 h-5 text-amber-400 mr-2 mt-0.5" />
                            <div>
                                <flux:text size="sm" class="text-amber-800">
                                    <strong>Manual Mode Active:</strong> Currently, all {{ $platform->display_name }} accounts operate in manual mode.
                                    API integration will be available in a future update.
                                </flux:text>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4 opacity-50">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <flux:field>
                                <flux:label>API Key</flux:label>
                                <flux:input wire:model="api_key" placeholder="Your API key" disabled />
                                <flux:description>Will be encrypted and stored securely</flux:description>
                            </flux:field>

                            <flux:field>
                                <flux:label>API Secret</flux:label>
                                <flux:input wire:model="api_secret" placeholder="Your API secret" disabled />
                                <flux:description>Will be encrypted and stored securely</flux:description>
                            </flux:field>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <flux:field>
                                <flux:label>Access Token</flux:label>
                                <flux:input wire:model="access_token" placeholder="Access token" disabled />
                            </flux:field>

                            <flux:field>
                                <flux:label>Refresh Token</flux:label>
                                <flux:input wire:model="refresh_token" placeholder="Refresh token" disabled />
                            </flux:field>
                        </div>
                    </div>
                </div>
                @endif

                {{-- Advanced Settings --}}
                <div class="bg-white rounded-lg border p-6">
                    <div class="flex items-center justify-between mb-4">
                        <flux:heading size="lg">Account Settings</flux:heading>
                        <flux:button type="button" variant="ghost" size="sm" wire:click="toggleAdvancedSettings">
                            <div class="flex items-center">
                                <span class="mr-2">{{ $showAdvancedSettings ? 'Hide' : 'Show' }} Advanced</span>
                                <flux:icon name="{{ $showAdvancedSettings ? 'chevron-up' : 'chevron-down' }}" class="w-4 h-4" />
                            </div>
                        </flux:button>
                    </div>

                    <div class="space-y-4">
                        <flux:field>
                            <flux:checkbox wire:model="is_active">Account is active</flux:checkbox>
                            <flux:description>Inactive accounts will be ignored during sync operations</flux:description>
                        </flux:field>

                        @if($showAdvancedSettings)
                        <div class="pt-4 border-t">
                            <flux:text size="sm" class="text-zinc-600 mb-3">Advanced Configuration</flux:text>

                            <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                <div class="flex">
                                    <flux:icon name="information-circle" class="w-5 h-5 text-blue-400 mr-2 mt-0.5" />
                                    <div>
                                        <flux:text size="sm" class="text-blue-800">
                                            <strong>Manual Mode:</strong> This account will operate in manual mode. Orders and data will need to be imported manually via CSV files.
                                            Auto-sync features will be available when API integration is implemented.
                                        </flux:text>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Sidebar --}}
            <div class="space-y-6">
                {{-- Setup Guide --}}
                <div class="bg-white rounded-lg border p-6">
                    <flux:heading size="lg" class="mb-4">Setup Guide</flux:heading>

                    <div class="space-y-3">
                        <div class="flex items-start space-x-3">
                            <div class="flex-shrink-0 w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center">
                                <span class="text-blue-600 text-xs font-bold">1</span>
                            </div>
                            <div>
                                <flux:text size="sm" class="font-medium">Find Your Account IDs</flux:text>
                                <flux:text size="xs" class="text-zinc-600">Log in to your {{ $platform->display_name }} seller center to find your seller ID and shop ID</flux:text>
                            </div>
                        </div>

                        <div class="flex items-start space-x-3">
                            <div class="flex-shrink-0 w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center">
                                <span class="text-blue-600 text-xs font-bold">2</span>
                            </div>
                            <div>
                                <flux:text size="sm" class="font-medium">Enter Account Details</flux:text>
                                <flux:text size="xs" class="text-zinc-600">Fill in the account information to identify this connection</flux:text>
                            </div>
                        </div>

                        <div class="flex items-start space-x-3">
                            <div class="flex-shrink-0 w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center">
                                <span class="text-blue-600 text-xs font-bold">3</span>
                            </div>
                            <div>
                                <flux:text size="sm" class="font-medium">Manual Import Ready</flux:text>
                                <flux:text size="xs" class="text-zinc-600">Once saved, you can start importing orders via CSV export from {{ $platform->display_name }}</flux:text>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Platform Requirements --}}
                @if($platformRequirements)
                <div class="bg-white rounded-lg border p-6">
                    <flux:heading size="lg" class="mb-4">Required Information</flux:heading>

                    <div class="space-y-2">
                        @foreach($platformRequirements as $requirement)
                        <div class="flex items-center space-x-2">
                            <flux:icon name="check-circle" class="w-4 h-4 text-green-500" />
                            <flux:text size="sm">{{ $requirement }}</flux:text>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Help & Support --}}
                <div class="bg-white rounded-lg border p-6">
                    <flux:heading size="lg" class="mb-4">Need Help?</flux:heading>

                    <div class="space-y-3">
                        <flux:text size="sm" class="text-zinc-600">
                            Having trouble finding your account IDs? Check the {{ $platform->display_name }} documentation or contact their support team.
                        </flux:text>

                        <div class="space-y-2">
                            <flux:button variant="outline" size="sm" class="w-full">
                                <div class="flex items-center justify-center">
                                    <flux:icon name="question-mark-circle" class="w-4 h-4 mr-2" />
                                    Platform Documentation
                                </div>
                            </flux:button>

                            <flux:button variant="outline" size="sm" class="w-full">
                                <div class="flex items-center justify-center">
                                    <flux:icon name="chat-bubble-left" class="w-4 h-4 mr-2" />
                                    Contact Support
                                </div>
                            </flux:button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Form Actions --}}
        <div class="mt-6 flex items-center justify-between bg-white rounded-lg border p-4">
            <flux:button
                type="button"
                variant="ghost"
                :href="route('platforms.accounts.index', $platform)"
                wire:navigate
            >
                <div class="flex items-center justify-center">
                    <flux:icon name="chevron-left" class="w-4 h-4 mr-1" />
                    Cancel
                </div>
            </flux:button>

            <div class="flex space-x-3">
                <flux:button type="submit" variant="primary">
                    <div class="flex items-center justify-center">
                        <flux:icon name="plus" class="w-4 h-4 mr-2" />
                        Create Account
                    </div>
                </flux:button>
            </div>
        </div>
    </form>
</div>