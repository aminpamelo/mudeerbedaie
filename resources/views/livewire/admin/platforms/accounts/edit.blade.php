<?php

use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\User;
use Livewire\Volt\Component;

new class extends Component
{
    public Platform $platform;

    public PlatformAccount $account;

    public $selected_hosts = [];

    public $account_name = '';

    public $seller_id = '';

    public $shop_id = '';

    public $business_manager_id = '';

    public $email = '';

    public $phone = '';

    public $country_code = '';

    public $currency = '';

    public $notes = '';

    public $is_active = true;

    public $auto_sync_orders = false;

    public $auto_sync_products = false;

    public $showAdvancedSettings = false;

    public function mount(Platform $platform, PlatformAccount $account)
    {
        $this->platform = $platform;
        $this->account = $account;

        // Ensure the account belongs to this platform
        if ($this->account->platform_id !== $this->platform->id) {
            abort(404);
        }

        // Populate form fields
        $this->selected_hosts = $account->liveHosts->pluck('id')->toArray();
        $this->account_name = $account->name;
        $this->seller_id = $account->account_id ?? '';
        $this->shop_id = $account->shop_id ?? '';
        $this->business_manager_id = $account->business_manager_id ?? '';
        $this->email = $account->email ?? '';
        $this->phone = $account->phone ?? '';
        $this->country_code = $account->country_code ?? '';
        $this->currency = $account->currency ?? '';
        $this->notes = $account->description ?? '';
        $this->is_active = $account->is_active;
        $this->auto_sync_orders = $account->auto_sync_orders;
        $this->auto_sync_products = $account->auto_sync_products;
    }

    public function rules()
    {
        return [
            'selected_hosts' => 'required|array|min:1',
            'selected_hosts.*' => 'exists:users,id',
            'account_name' => 'required|string|max:255',
            'seller_id' => 'nullable|string|max:255',
            'shop_id' => 'nullable|string|max:255',
            'business_manager_id' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'country_code' => 'nullable|string|max:10',
            'currency' => 'nullable|string|max:10',
            'notes' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
            'auto_sync_orders' => 'boolean',
            'auto_sync_products' => 'boolean',
        ];
    }

    public function update()
    {
        $validated = $this->validate();

        // Update the platform account
        $this->account->update([
            'user_id' => $validated['selected_hosts'][0], // Keep first host for backward compatibility
            'name' => $validated['account_name'],
            'account_id' => $validated['seller_id'] ?: null,
            'shop_id' => $validated['shop_id'] ?: null,
            'business_manager_id' => $validated['business_manager_id'] ?: null,
            'email' => $validated['email'] ?: null,
            'phone' => $validated['phone'] ?: null,
            'country_code' => $validated['country_code'] ?: null,
            'currency' => $validated['currency'] ?: null,
            'description' => $validated['notes'] ?: null,
            'is_active' => $validated['is_active'],
            'auto_sync_orders' => $validated['auto_sync_orders'],
            'auto_sync_products' => $validated['auto_sync_products'],
        ]);

        // Sync selected live hosts with the account
        $this->account->liveHosts()->sync($validated['selected_hosts']);

        return redirect()->route('platforms.accounts.show', [$this->platform, $this->account])
            ->with('success', "Account '{$this->account->name}' has been updated successfully");
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
                        <flux:button variant="ghost" size="sm" :href="route('platforms.accounts.index', $platform)" wire:navigate class="ml-4">
                            {{ $platform->display_name }} Accounts
                        </flux:button>
                    </div>
                </li>
                <li>
                    <div class="flex items-center">
                        <flux:icon name="chevron-right" class="w-5 h-5 text-zinc-400" />
                        <flux:button variant="ghost" size="sm" :href="route('platforms.accounts.show', [$platform, $account])" wire:navigate class="ml-4">
                            {{ $account->name }}
                        </flux:button>
                    </div>
                </li>
                <li>
                    <div class="flex items-center">
                        <flux:icon name="chevron-right" class="w-5 h-5 text-zinc-400" />
                        <span class="ml-4 text-sm font-medium text-zinc-500">Edit</span>
                    </div>
                </li>
            </ol>
        </nav>
    </div>

    {{-- Header Section --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Edit {{ $platform->display_name }} Account</flux:heading>
            <flux:text class="mt-2">Update your {{ $platform->display_name }} account details and settings</flux:text>
        </div>
    </div>

    {{-- Platform Info Card --}}
    <div class="mb-6 bg-white rounded-lg border p-4">
        <div class="flex items-center space-x-4">
            @if($platform->logo_url)
                <img src="{{ $platform->logo_url }}" alt="{{ $platform->name }}" class="w-12 h-12 rounded-lg">
            @else
                <div class="w-12 h-12 rounded-lg flex items-center justify-center text-white text-xl font-bold"
                     style="background: {{ $platform->color_primary ?? '#6b7280' }}">
                    {{ substr($platform->name, 0, 1) }}
                </div>
            @endif
            <div class="flex-1">
                <flux:heading size="sm">{{ $platform->display_name }}</flux:heading>
                <flux:text size="sm" class="text-zinc-600">{{ ucfirst(str_replace('_', ' ', $platform->type)) }} Platform - Edit Mode</flux:text>
            </div>
        </div>
    </div>

    {{-- Edit Form --}}
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <div class="lg:col-span-3">
            <form wire:submit="update" class="space-y-6">
                {{-- Account Information --}}
                <div class="bg-white rounded-lg border p-6">
                    <div class="flex items-center justify-between mb-4">
                        <flux:heading size="lg">Account Information</flux:heading>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {{-- Live Hosts --}}
                        <div class="md:col-span-2">
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
                        </div>

                        {{-- Account Name --}}
                        <div class="md:col-span-2">
                            <flux:field>
                                <flux:label>Account Name *</flux:label>
                                <flux:input wire:model="account_name" placeholder="e.g., My {{ $platform->display_name }} Account" />
                                <flux:description>A friendly name to identify this account in your dashboard</flux:description>
                                <flux:error name="account_name" />
                            </flux:field>
                        </div>

                        {{-- Platform-specific fields --}}
                        <div>
                            <flux:field>
                                <flux:label>Seller ID</flux:label>
                                <flux:input wire:model="seller_id" placeholder="Enter your seller ID" />
                                <flux:error name="seller_id" />
                            </flux:field>
                        </div>

                        <div>
                            <flux:field>
                                <flux:label>Shop ID</flux:label>
                                <flux:input wire:model="shop_id" placeholder="Enter your shop ID" />
                                <flux:error name="shop_id" />
                            </flux:field>
                        </div>

                        <div class="md:col-span-2">
                            <flux:field>
                                <flux:label>Business Manager ID</flux:label>
                                <flux:input wire:model="business_manager_id" placeholder="Enter business manager ID (if applicable)" />
                                <flux:error name="business_manager_id" />
                            </flux:field>
                        </div>

                        {{-- Contact Information --}}
                        <div>
                            <flux:field>
                                <flux:label>Email</flux:label>
                                <flux:input wire:model="email" type="email" placeholder="account@example.com" />
                                <flux:error name="email" />
                            </flux:field>
                        </div>

                        <div>
                            <flux:field>
                                <flux:label>Phone</flux:label>
                                <flux:input wire:model="phone" placeholder="+1234567890" />
                                <flux:error name="phone" />
                            </flux:field>
                        </div>

                        <div>
                            <flux:field>
                                <flux:label>Country Code</flux:label>
                                <flux:input wire:model="country_code" placeholder="US" />
                                <flux:error name="country_code" />
                            </flux:field>
                        </div>

                        <div>
                            <flux:field>
                                <flux:label>Currency</flux:label>
                                <flux:input wire:model="currency" placeholder="USD" />
                                <flux:error name="currency" />
                            </flux:field>
                        </div>

                        {{-- Notes --}}
                        <div class="md:col-span-2">
                            <flux:field>
                                <flux:label>Notes</flux:label>
                                <flux:textarea wire:model="notes" placeholder="Add any notes about this account..." rows="3" />
                                <flux:error name="notes" />
                            </flux:field>
                        </div>
                    </div>
                </div>

                {{-- Account Settings --}}
                <div class="bg-white rounded-lg border p-6">
                    <div class="flex items-center justify-between mb-4">
                        <flux:heading size="lg">Account Settings</flux:heading>
                        <flux:button variant="ghost" size="sm" wire:click="toggleAdvancedSettings">
                            <div class="flex items-center justify-center">
                                <flux:icon name="cog" class="w-4 h-4 mr-1" />
                                <span>{{ $showAdvancedSettings ? 'Hide' : 'Show' }} Advanced</span>
                                <flux:icon name="chevron-{{ $showAdvancedSettings ? 'up' : 'down' }}" class="w-4 h-4 ml-1" />
                            </div>
                        </flux:button>
                    </div>

                    <div class="space-y-4">
                        {{-- Active Status --}}
                        <flux:field>
                            <flux:checkbox wire:model="is_active" />
                            <flux:label>Account is active</flux:label>
                            <flux:description>Inactive accounts will be ignored during sync operations</flux:description>
                        </flux:field>

                        {{-- Advanced Settings --}}
                        @if($showAdvancedSettings)
                            <div class="space-y-4 pt-4 border-t">
                                <flux:field>
                                    <flux:checkbox wire:model="auto_sync_orders" />
                                    <flux:label>Auto-sync orders</flux:label>
                                    <flux:description>Automatically import new orders from this platform (requires API setup)</flux:description>
                                </flux:field>

                                <flux:field>
                                    <flux:checkbox wire:model="auto_sync_products" />
                                    <flux:label>Auto-sync products</flux:label>
                                    <flux:description>Automatically sync product information from this platform (requires API setup)</flux:description>
                                </flux:field>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Form Actions --}}
                <div class="flex items-center justify-between pt-6">
                    <flux:button variant="ghost" :href="route('platforms.accounts.show', [$platform, $account])" wire:navigate>
                        <div class="flex items-center justify-center">
                            <flux:icon name="x-mark" class="w-4 h-4 mr-1" />
                            Cancel
                        </div>
                    </flux:button>

                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                        <div class="flex items-center justify-center">
                            <flux:icon name="check" class="w-4 h-4 mr-1" wire:loading.remove />
                            <flux:icon name="loading" class="w-4 h-4 mr-1 animate-spin" wire:loading />
                            <span wire:loading.remove>Update Account</span>
                            <span wire:loading>Updating...</span>
                        </div>
                    </flux:button>
                </div>
            </form>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            {{-- Update Guide --}}
            <div class="bg-white rounded-lg border p-6">
                <flux:heading size="lg" class="mb-4">Update Guide</flux:heading>

                <div class="space-y-4">
                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0 w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center">
                            <flux:text size="xs" class="font-medium text-blue-600">1</flux:text>
                        </div>
                        <div>
                            <flux:text size="sm" class="font-medium">Update Account Details</flux:text>
                            <flux:text size="xs" class="text-zinc-600">Modify the account information as needed</flux:text>
                        </div>
                    </div>

                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0 w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center">
                            <flux:text size="xs" class="font-medium text-blue-600">2</flux:text>
                        </div>
                        <div>
                            <flux:text size="sm" class="font-medium">Configure Settings</flux:text>
                            <flux:text size="xs" class="text-zinc-600">Adjust sync preferences and active status</flux:text>
                        </div>
                    </div>

                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0 w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center">
                            <flux:text size="xs" class="font-medium text-blue-600">3</flux:text>
                        </div>
                        <div>
                            <flux:text size="sm" class="font-medium">Save Changes</flux:text>
                            <flux:text size="xs" class="text-zinc-600">Click update to save your changes</flux:text>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Account Info --}}
            <div class="bg-white rounded-lg border p-6">
                <flux:heading size="lg" class="mb-4">Account Info</flux:heading>

                <div class="space-y-3">
                    <div class="flex justify-between">
                        <flux:text size="sm" class="text-zinc-600">Created</flux:text>
                        <flux:text size="sm">{{ $account->created_at->format('M j, Y') }}</flux:text>
                    </div>

                    <div class="flex justify-between">
                        <flux:text size="sm" class="text-zinc-600">Last Updated</flux:text>
                        <flux:text size="sm">{{ $account->updated_at->diffForHumans() }}</flux:text>
                    </div>

                    @if($account->last_sync_at)
                        <div class="flex justify-between">
                            <flux:text size="sm" class="text-zinc-600">Last Sync</flux:text>
                            <flux:text size="sm">{{ $account->last_sync_at->diffForHumans() }}</flux:text>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>