<?php

use Livewire\Volt\Component;
use App\Models\Platform;
use App\Models\PlatformAccount;

new class extends Component {
    public Platform $platform;
    public PlatformAccount $account;

    public function mount(Platform $platform, PlatformAccount $account)
    {
        $this->platform = $platform;
        $this->account = $account;

        // Ensure the account belongs to this platform
        if ($this->account->platform_id !== $this->platform->id) {
            abort(404);
        }
    }

    public function toggleStatus()
    {
        $this->account->update(['is_active' => !$this->account->is_active]);

        $this->dispatch('account-updated', [
            'message' => "Account '{$this->account->name}' has been " . ($this->account->is_active ? 'activated' : 'deactivated')
        ]);
    }

    public function deleteAccount()
    {
        $accountName = $this->account->name;
        $this->account->delete();

        return redirect()->route('platforms.accounts.index', $this->platform)
            ->with('success', "Account '{$accountName}' has been deleted successfully");
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
                        <span class="ml-4 text-sm font-medium text-zinc-500">{{ $account->name }}</span>
                    </div>
                </li>
            </ol>
        </nav>
    </div>

    {{-- Header Section --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ $account->name }}</flux:heading>
            <flux:text class="mt-2">{{ $platform->display_name }} account details and management</flux:text>
        </div>
        <div class="flex gap-3">
            <flux:button variant="outline" icon="key" :href="route('platforms.accounts.credentials', [$platform, $account])" wire:navigate>
                API Credentials
            </flux:button>
            <flux:button variant="outline" icon="pencil" :href="route('platforms.accounts.edit', [$platform, $account])" wire:navigate>
                Edit Account
            </flux:button>
            <flux:button
                variant="{{ $account->is_active ? 'outline' : 'primary' }}"
                wire:click="toggleStatus"
                wire:confirm="Are you sure you want to {{ $account->is_active ? 'deactivate' : 'activate' }} this account?"
            >
                <div class="flex items-center justify-center">
                    <flux:icon name="{{ $account->is_active ? 'x-mark' : 'check' }}" class="w-4 h-4 mr-1" />
                    {{ $account->is_active ? 'Deactivate' : 'Activate' }}
                </div>
            </flux:button>
        </div>
    </div>

    {{-- Platform Info Card --}}
    <div class="mb-6 bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-4">
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
                <flux:text size="sm" class="text-zinc-600">{{ ucfirst(str_replace('_', ' ', $platform->type)) }} Platform</flux:text>
                <div class="flex items-center mt-2 space-x-4">
                    @if($platform->is_active)
                        <flux:badge size="sm" color="green">Active</flux:badge>
                    @else
                        <flux:badge size="sm" color="red">Inactive</flux:badge>
                    @endif
                    @if($platform->settings['api_available'] ?? false)
                        <flux:badge size="sm" color="blue">API Available</flux:badge>
                    @else
                        <flux:badge size="sm" color="amber">Manual Only</flux:badge>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Account Details --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Main Account Information --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Basic Information --}}
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-6">
                <flux:heading size="lg" class="mb-4">Account Information</flux:heading>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <flux:text size="sm" class="text-zinc-600 mb-1">Account Name</flux:text>
                        <flux:text size="sm" class="font-medium">{{ $account->name }}</flux:text>
                    </div>

                    <div>
                        <flux:text size="sm" class="text-zinc-600 mb-1">Status</flux:text>
                        @if($account->is_active)
                            <flux:badge size="sm" color="green">Active</flux:badge>
                        @else
                            <flux:badge size="sm" color="red">Inactive</flux:badge>
                        @endif
                    </div>

                    @if($account->account_id)
                        <div>
                            <flux:text size="sm" class="text-zinc-600 mb-1">Seller ID</flux:text>
                            <flux:text size="sm" class="font-mono">{{ $account->account_id }}</flux:text>
                        </div>
                    @endif

                    @if($account->shop_id)
                        <div>
                            <flux:text size="sm" class="text-zinc-600 mb-1">Shop ID</flux:text>
                            <flux:text size="sm" class="font-mono">{{ $account->shop_id }}</flux:text>
                        </div>
                    @endif

                    @if($account->business_manager_id)
                        <div>
                            <flux:text size="sm" class="text-zinc-600 mb-1">Business Manager ID</flux:text>
                            <flux:text size="sm" class="font-mono">{{ $account->business_manager_id }}</flux:text>
                        </div>
                    @endif

                    @if($account->email)
                        <div>
                            <flux:text size="sm" class="text-zinc-600 mb-1">Email</flux:text>
                            <flux:text size="sm">{{ $account->email }}</flux:text>
                        </div>
                    @endif

                    @if($account->country_code)
                        <div>
                            <flux:text size="sm" class="text-zinc-600 mb-1">Country</flux:text>
                            <flux:text size="sm">{{ $account->country_code }}</flux:text>
                        </div>
                    @endif

                    @if($account->currency)
                        <div>
                            <flux:text size="sm" class="text-zinc-600 mb-1">Currency</flux:text>
                            <flux:text size="sm">{{ $account->currency }}</flux:text>
                        </div>
                    @endif
                </div>

                @if($account->description)
                    <div class="mt-6">
                        <flux:text size="sm" class="text-zinc-600 mb-1">Notes</flux:text>
                        <flux:text size="sm" class="text-zinc-700">{{ $account->description }}</flux:text>
                    </div>
                @endif
            </div>

            {{-- Sync Information --}}
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-6">
                <flux:heading size="lg" class="mb-4">Sync Information</flux:heading>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <flux:text size="sm" class="text-zinc-600 mb-1">Auto Sync Orders</flux:text>
                        @if($account->auto_sync_orders)
                            <flux:badge size="sm" color="green">Enabled</flux:badge>
                        @else
                            <flux:badge size="sm" color="amber">Manual Only</flux:badge>
                        @endif
                    </div>

                    <div>
                        <flux:text size="sm" class="text-zinc-600 mb-1">Auto Sync Products</flux:text>
                        @if($account->auto_sync_products)
                            <flux:badge size="sm" color="green">Enabled</flux:badge>
                        @else
                            <flux:badge size="sm" color="amber">Manual Only</flux:badge>
                        @endif
                    </div>

                    <div>
                        <flux:text size="sm" class="text-zinc-600 mb-1">Connected At</flux:text>
                        <flux:text size="sm">
                            @if($account->connected_at)
                                {{ $account->connected_at->format('M j, Y \a\t g:i A') }}
                            @else
                                <span class="text-zinc-400">Not connected</span>
                            @endif
                        </flux:text>
                    </div>

                    <div>
                        <flux:text size="sm" class="text-zinc-600 mb-1">Last Sync</flux:text>
                        <flux:text size="sm">
                            @if($account->last_sync_at)
                                {{ $account->last_sync_at->diffForHumans() }}
                            @else
                                <span class="text-zinc-400">Never synced</span>
                            @endif
                        </flux:text>
                    </div>

                    @if($account->expires_at)
                        <div>
                            <flux:text size="sm" class="text-zinc-600 mb-1">Expires At</flux:text>
                            <flux:text size="sm">{{ $account->expires_at->format('M j, Y \a\t g:i A') }}</flux:text>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            {{-- Quick Actions --}}
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-6">
                <flux:heading size="lg" class="mb-4">Quick Actions</flux:heading>

                <div class="space-y-3">
                    <flux:button variant="outline" size="sm" class="w-full" :href="route('platforms.accounts.credentials', [$platform, $account])" wire:navigate>
                        <div class="flex items-center justify-center">
                            <flux:icon name="key" class="w-4 h-4 mr-2" />
                            Manage API Credentials
                        </div>
                    </flux:button>

                    <flux:button variant="outline" size="sm" class="w-full" disabled>
                        <div class="flex items-center justify-center">
                            <flux:icon name="arrow-down-tray" class="w-4 h-4 mr-2" />
                            Import Orders
                        </div>
                    </flux:button>

                    <flux:button variant="outline" size="sm" class="w-full" disabled>
                        <div class="flex items-center justify-center">
                            <flux:icon name="arrow-path" class="w-4 h-4 mr-2" />
                            Sync Products
                        </div>
                    </flux:button>

                    <flux:button variant="outline" size="sm" class="w-full" disabled>
                        <div class="flex items-center justify-center">
                            <flux:icon name="chart-bar" class="w-4 h-4 mr-2" />
                            View Analytics
                        </div>
                    </flux:button>
                </div>

                <flux:text size="xs" class="text-zinc-500 mt-3">
                    Advanced features coming soon with API integration
                </flux:text>
            </div>

            {{-- Account Stats --}}
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-6">
                <flux:heading size="lg" class="mb-4">Account Stats</flux:heading>

                <div class="space-y-4">
                    <div class="flex justify-between">
                        <flux:text size="sm" class="text-zinc-600">Created</flux:text>
                        <flux:text size="sm">{{ $account->created_at->format('M j, Y') }}</flux:text>
                    </div>

                    <div class="flex justify-between">
                        <flux:text size="sm" class="text-zinc-600">Last Updated</flux:text>
                        <flux:text size="sm">{{ $account->updated_at->diffForHumans() }}</flux:text>
                    </div>

                    <div class="flex justify-between">
                        <flux:text size="sm" class="text-zinc-600">Total Orders</flux:text>
                        <flux:text size="sm">0</flux:text>
                    </div>

                    <div class="flex justify-between">
                        <flux:text size="sm" class="text-zinc-600">Total Revenue</flux:text>
                        <flux:text size="sm">$0.00</flux:text>
                    </div>
                </div>
            </div>

            {{-- Danger Zone --}}
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-red-200 dark:border-red-700 p-6">
                <flux:heading size="lg" class="mb-4 text-red-600">Danger Zone</flux:heading>

                <flux:button
                    variant="danger"
                    size="sm"
                    class="w-full"
                    wire:click="deleteAccount"
                    wire:confirm="Are you sure you want to delete this account? This action cannot be undone and will remove all associated data."
                >
                    <div class="flex items-center justify-center">
                        <flux:icon name="trash" class="w-4 h-4 mr-2" />
                        Delete Account
                    </div>
                </flux:button>
            </div>
        </div>
    </div>
</div>