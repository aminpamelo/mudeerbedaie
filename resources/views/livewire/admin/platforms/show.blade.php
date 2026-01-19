<?php

use Livewire\Volt\Component;
use App\Models\Platform;

new class extends Component {
    public Platform $platform;

    public function mount(Platform $platform)
    {
        $this->platform = $platform;
    }

    public function toggleStatus()
    {
        $this->platform->update(['is_active' => !$this->platform->is_active]);

        $this->dispatch('platform-updated', [
            'message' => "Platform '{$this->platform->name}' has been " . ($this->platform->is_active ? 'activated' : 'deactivated')
        ]);
    }

    public function deletePlatform()
    {
        $platformName = $this->platform->name;
        $this->platform->delete();

        return redirect()->route('platforms.index')
            ->with('success', "Platform '{$platformName}' has been deleted successfully");
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
                                Back to Platforms
                            </div>
                        </flux:button>
                    </div>
                </li>
                <li>
                    <div class="flex items-center">
                        <flux:icon name="chevron-right" class="w-5 h-5 text-zinc-400" />
                        <span class="ml-4 text-sm font-medium text-zinc-500">{{ $platform->display_name }}</span>
                    </div>
                </li>
            </ol>
        </nav>
    </div>

    {{-- Header Section --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ $platform->display_name }}</flux:heading>
            <flux:text class="mt-2">{{ $platform->description }}</flux:text>
        </div>
        <div class="flex gap-3">
            <flux:button variant="outline" icon="user-group" :href="route('platforms.accounts.index', $platform)" wire:navigate>
                Manage Accounts
            </flux:button>
            <flux:button variant="outline" icon="pencil" :href="route('platforms.edit', $platform)" wire:navigate>
                Edit Platform
            </flux:button>
            <flux:button
                variant="{{ $platform->is_active ? 'outline' : 'primary' }}"
                wire:click="toggleStatus"
                wire:confirm="Are you sure you want to {{ $platform->is_active ? 'deactivate' : 'activate' }} this platform?"
            >
                <div class="flex items-center justify-center">
                    <flux:icon name="{{ $platform->is_active ? 'x-mark' : 'check' }}" class="w-4 h-4 mr-1" />
                    {{ $platform->is_active ? 'Deactivate' : 'Activate' }}
                </div>
            </flux:button>
        </div>
    </div>

    {{-- Platform Header Card --}}
    <div class="mb-6 bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 overflow-hidden">
        <div class="p-6" style="background: linear-gradient(135deg, {{ $platform->color_primary ?? '#6b7280' }}15 0%, {{ $platform->color_secondary ?? '#9ca3af' }}15 100%);">
            <div class="flex items-center space-x-6">
                @if($platform->logo_url)
                    <img src="{{ $platform->logo_url }}" alt="{{ $platform->name }}" class="w-16 h-16 rounded-lg">
                @else
                    <div class="w-16 h-16 rounded-lg flex items-center justify-center text-white text-2xl font-bold"
                         style="background: {{ $platform->color_primary ?? '#6b7280' }}">
                        {{ substr($platform->name, 0, 1) }}
                    </div>
                @endif
                <div class="flex-1">
                    <div class="flex items-center space-x-4 mb-2">
                        <flux:heading size="lg">{{ $platform->display_name }}</flux:heading>
                        @if($platform->is_active)
                            <flux:badge size="sm" color="green">Active</flux:badge>
                        @else
                            <flux:badge size="sm" color="red">Inactive</flux:badge>
                        @endif
                        <flux:badge size="sm" color="blue">{{ ucfirst(str_replace('_', ' ', $platform->type)) }}</flux:badge>
                    </div>
                    <flux:text class="text-zinc-600">{{ $platform->description }}</flux:text>
                    <div class="flex items-center mt-3 space-x-4">
                        @if($platform->settings['api_available'] ?? false)
                            <flux:badge size="sm" color="green">API Available</flux:badge>
                        @else
                            <flux:badge size="sm" color="amber">Manual Only</flux:badge>
                        @endif
                        @if($platform->settings['manual_mode'] ?? false)
                            <flux:badge size="sm" color="blue">Manual Mode</flux:badge>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Content Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Main Content --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Platform Details --}}
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-6">
                <flux:heading size="lg" class="mb-4">Platform Details</flux:heading>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <flux:text size="sm" class="text-zinc-600 mb-1">Platform Name</flux:text>
                        <flux:text size="sm" class="font-medium">{{ $platform->name }}</flux:text>
                    </div>

                    <div>
                        <flux:text size="sm" class="text-zinc-600 mb-1">Display Name</flux:text>
                        <flux:text size="sm" class="font-medium">{{ $platform->display_name }}</flux:text>
                    </div>

                    <div>
                        <flux:text size="sm" class="text-zinc-600 mb-1">Slug</flux:text>
                        <flux:text size="sm" class="font-mono">{{ $platform->slug }}</flux:text>
                    </div>

                    <div>
                        <flux:text size="sm" class="text-zinc-600 mb-1">Platform Type</flux:text>
                        <flux:text size="sm" class="font-medium">{{ ucfirst(str_replace('_', ' ', $platform->type)) }}</flux:text>
                    </div>

                    <div>
                        <flux:text size="sm" class="text-zinc-600 mb-1">Sort Order</flux:text>
                        <flux:text size="sm">{{ $platform->sort_order }}</flux:text>
                    </div>

                    <div>
                        <flux:text size="sm" class="text-zinc-600 mb-1">Status</flux:text>
                        @if($platform->is_active)
                            <flux:badge size="sm" color="green">Active</flux:badge>
                        @else
                            <flux:badge size="sm" color="red">Inactive</flux:badge>
                        @endif
                    </div>
                </div>

                @if($platform->features && count($platform->features) > 0)
                    <div class="mt-6">
                        <flux:text size="sm" class="text-zinc-600 mb-2">Features</flux:text>
                        <div class="flex flex-wrap gap-2">
                            @foreach($platform->features as $feature)
                                <flux:badge size="sm" color="blue">{{ str_replace('_', ' ', $feature) }}</flux:badge>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- External Links --}}
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-6">
                <flux:heading size="lg" class="mb-4">External Links</flux:heading>

                <div class="space-y-4">
                    @if($platform->website_url)
                        <div class="flex justify-between items-center">
                            <flux:text size="sm" class="text-zinc-600">Official Website</flux:text>
                            <flux:button variant="ghost" size="sm" href="{{ $platform->website_url }}" target="_blank">
                                <div class="flex items-center justify-center">
                                    <flux:icon name="globe-alt" class="w-4 h-4 mr-1" />
                                    Visit Website
                                    <flux:icon name="arrow-top-right-on-square" class="w-3 h-3 ml-1" />
                                </div>
                            </flux:button>
                        </div>
                    @endif

                    @if($platform->documentation_url)
                        <div class="flex justify-between items-center">
                            <flux:text size="sm" class="text-zinc-600">API Documentation</flux:text>
                            <flux:button variant="ghost" size="sm" href="{{ $platform->documentation_url }}" target="_blank">
                                <div class="flex items-center justify-center">
                                    <flux:icon name="document-text" class="w-4 h-4 mr-1" />
                                    View Docs
                                    <flux:icon name="arrow-top-right-on-square" class="w-3 h-3 ml-1" />
                                </div>
                            </flux:button>
                        </div>
                    @endif

                    @if($platform->support_url)
                        <div class="flex justify-between items-center">
                            <flux:text size="sm" class="text-zinc-600">Customer Support</flux:text>
                            <flux:button variant="ghost" size="sm" href="{{ $platform->support_url }}" target="_blank">
                                <div class="flex items-center justify-center">
                                    <flux:icon name="question-mark-circle" class="w-4 h-4 mr-1" />
                                    Get Support
                                    <flux:icon name="arrow-top-right-on-square" class="w-3 h-3 ml-1" />
                                </div>
                            </flux:button>
                        </div>
                    @endif

                    @if(!$platform->website_url && !$platform->documentation_url && !$platform->support_url)
                        <flux:text size="sm" class="text-zinc-500 italic">No external links configured</flux:text>
                    @endif
                </div>
            </div>

            {{-- Color Scheme --}}
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-6">
                <flux:heading size="lg" class="mb-4">Color Scheme</flux:heading>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <flux:text size="sm" class="text-zinc-600 mb-2">Primary Color</flux:text>
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 rounded border" style="background-color: {{ $platform->color_primary }}"></div>
                            <flux:text size="sm" class="font-mono">{{ $platform->color_primary }}</flux:text>
                        </div>
                    </div>

                    <div>
                        <flux:text size="sm" class="text-zinc-600 mb-2">Secondary Color</flux:text>
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 rounded border" style="background-color: {{ $platform->color_secondary }}"></div>
                            <flux:text size="sm" class="font-mono">{{ $platform->color_secondary }}</flux:text>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            {{-- Quick Actions --}}
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-6">
                <flux:heading size="lg" class="mb-4">Quick Actions</flux:heading>

                <div class="space-y-3">
                    <flux:button variant="outline" size="sm" class="w-full" :href="route('platforms.accounts.index', $platform)" wire:navigate>
                        <div class="flex items-center justify-center">
                            <flux:icon name="user-group" class="w-4 h-4 mr-2" />
                            Manage Accounts
                        </div>
                    </flux:button>

                    <flux:button variant="outline" size="sm" class="w-full" :href="route('platforms.accounts.create', $platform)" wire:navigate>
                        <div class="flex items-center justify-center">
                            <flux:icon name="plus" class="w-4 h-4 mr-2" />
                            Add New Account
                        </div>
                    </flux:button>

                    <flux:button variant="outline" size="sm" class="w-full" :href="route('platforms.orders.index', $platform)" wire:navigate>
                        <div class="flex items-center justify-center">
                            <flux:icon name="shopping-bag" class="w-4 h-4 mr-2" />
                            View Orders
                        </div>
                    </flux:button>

                    <flux:button variant="outline" size="sm" class="w-full" :href="route('platforms.orders.import', $platform)" wire:navigate>
                        <div class="flex items-center justify-center">
                            <flux:icon name="arrow-up-tray" class="w-4 h-4 mr-2" />
                            Import Orders
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

            {{-- API Integration (Coming Soon) --}}
            <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-lg border border-blue-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="lg" class="text-blue-900">API Integration</flux:heading>
                    <flux:badge size="sm" color="blue">Coming Soon</flux:badge>
                </div>

                <flux:text size="sm" class="text-blue-800 mb-4">
                    Advanced API features will be available in future updates to automate platform operations.
                </flux:text>

                <div class="space-y-3">
                    <div class="flex items-center space-x-3 opacity-60">
                        <flux:icon name="arrow-path" class="w-4 h-4 text-blue-600" />
                        <flux:text size="sm" class="text-blue-800">Auto-sync orders and products</flux:text>
                    </div>

                    <div class="flex items-center space-x-3 opacity-60">
                        <flux:icon name="globe-alt" class="w-4 h-4 text-blue-600" />
                        <flux:text size="sm" class="text-blue-800">Real-time webhook notifications</flux:text>
                    </div>

                    <div class="flex items-center space-x-3 opacity-60">
                        <flux:icon name="chart-bar" class="w-4 h-4 text-blue-600" />
                        <flux:text size="sm" class="text-blue-800">Live analytics and reporting</flux:text>
                    </div>

                    <div class="flex items-center space-x-3 opacity-60">
                        <flux:icon name="bolt" class="w-4 h-4 text-blue-600" />
                        <flux:text size="sm" class="text-blue-800">Automated inventory management</flux:text>
                    </div>

                    <div class="flex items-center space-x-3 opacity-60">
                        <flux:icon name="shield-check" class="w-4 h-4 text-blue-600" />
                        <flux:text size="sm" class="text-blue-800">API health monitoring</flux:text>
                    </div>
                </div>

                <div class="mt-4 pt-4 border-t border-blue-200">
                    <flux:text size="xs" class="text-blue-700">
                        🚀 These features will provide seamless automation once API integrations are implemented.
                    </flux:text>
                </div>
            </div>

            {{-- Platform Statistics --}}
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-6">
                <flux:heading size="lg" class="mb-4">Platform Statistics</flux:heading>

                <div class="space-y-4">
                    <div class="flex justify-between">
                        <flux:text size="sm" class="text-zinc-600">Total Accounts</flux:text>
                        <flux:text size="sm" class="font-medium">{{ $platform->accounts()->count() }}</flux:text>
                    </div>

                    <div class="flex justify-between">
                        <flux:text size="sm" class="text-zinc-600">Active Accounts</flux:text>
                        <flux:text size="sm" class="font-medium">{{ $platform->accounts()->where('is_active', true)->count() }}</flux:text>
                    </div>

                    <div class="flex justify-between">
                        <flux:text size="sm" class="text-zinc-600">Connected Accounts</flux:text>
                        <flux:text size="sm" class="font-medium">{{ $platform->accounts()->whereNotNull('last_sync_at')->count() }}</flux:text>
                    </div>

                    <div class="flex justify-between">
                        <flux:text size="sm" class="text-zinc-600">Total Orders</flux:text>
                        <flux:text size="sm" class="font-medium">0</flux:text>
                    </div>

                    <div class="flex justify-between">
                        <flux:text size="sm" class="text-zinc-600">Total Revenue</flux:text>
                        <flux:text size="sm" class="font-medium">$0.00</flux:text>
                    </div>
                </div>
            </div>

            {{-- Platform Info --}}
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-6">
                <flux:heading size="lg" class="mb-4">Platform Info</flux:heading>

                <div class="space-y-3">
                    <div class="flex justify-between">
                        <flux:text size="sm" class="text-zinc-600">Created</flux:text>
                        <flux:text size="sm">{{ $platform->created_at->format('M j, Y') }}</flux:text>
                    </div>

                    <div class="flex justify-between">
                        <flux:text size="sm" class="text-zinc-600">Last Updated</flux:text>
                        <flux:text size="sm">{{ $platform->updated_at->diffForHumans() }}</flux:text>
                    </div>

                    <div class="flex justify-between">
                        <flux:text size="sm" class="text-zinc-600">Integration Mode</flux:text>
                        <flux:text size="sm">
                            @if($platform->settings['api_available'] ?? false)
                                API + Manual
                            @else
                                Manual Only
                            @endif
                        </flux:text>
                    </div>
                </div>
            </div>

            {{-- Danger Zone --}}
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-red-200 dark:border-red-700 p-6">
                <flux:heading size="lg" class="mb-4 text-red-600">Danger Zone</flux:heading>

                <flux:text size="sm" class="text-zinc-600 mb-4">
                    Deleting this platform will remove all associated accounts and data permanently.
                </flux:text>

                <flux:button
                    variant="danger"
                    size="sm"
                    class="w-full"
                    wire:click="deletePlatform"
                    wire:confirm="Are you sure you want to delete this platform? This action cannot be undone and will remove all associated accounts and data."
                >
                    <div class="flex items-center justify-center">
                        <flux:icon name="trash" class="w-4 h-4 mr-2" />
                        Delete Platform
                    </div>
                </flux:button>
            </div>
        </div>
    </div>
</div>