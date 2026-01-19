<?php

use App\Models\Platform;
use App\Models\PlatformSkuMapping;
use App\Models\Product;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $search = '';
    public string $platformFilter = '';
    public string $statusFilter = '';

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedPlatformFilter()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    public function getMappingsProperty()
    {
        return PlatformSkuMapping::query()
            ->with(['platform', 'product', 'productVariant', 'platformAccount'])
            ->when($this->search, function ($query) {
                $query->where('platform_sku', 'like', '%' . $this->search . '%')
                      ->orWhere('platform_product_name', 'like', '%' . $this->search . '%');
            })
            ->when($this->platformFilter, fn($query) =>
                $query->where('platform_id', $this->platformFilter)
            )
            ->when($this->statusFilter !== '', fn($query) =>
                $query->where('is_active', $this->statusFilter === '1')
            )
            ->orderBy('updated_at', 'desc')
            ->paginate(15);
    }

    public function getPlatformsProperty()
    {
        return Platform::all();
    }

}; ?>
<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">SKU Mappings</flux:heading>
            <flux:text class="mt-2">Manage platform SKU mappings to link external products with your inventory</flux:text>
        </div>
        <flux:button variant="primary" :href="route('platforms.sku-mappings.create')" wire:navigate>
            Create Mapping
        </flux:button>
    </div>

    <!-- Filters -->
    <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
        <flux:input
            wire:model.live.debounce.300ms="search"
            placeholder="Search SKUs, products..."
        />

        <flux:select wire:model.live="platformFilter" placeholder="All Platforms">
            <option value="">All Platforms</option>
            @foreach($this->platforms as $platform)
                <option value="{{ $platform->id }}">{{ $platform->name }}</option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="statusFilter" placeholder="All Status">
            <option value="">All Status</option>
            <option value="1">Active</option>
            <option value="0">Inactive</option>
        </flux:select>

        <flux:button variant="outline" wire:click="$refresh">
            Refresh
        </flux:button>
    </div>

    <!-- Mappings Table -->
    <div class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-700/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            Platform SKU
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            Platform
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            Product
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            Status
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            Last Updated
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700 bg-white dark:bg-zinc-800">
                    @forelse($this->mappings as $mapping)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-700/50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $mapping->platform_sku }}</div>
                                @if($mapping->platform_product_name)
                                    <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ $mapping->platform_product_name }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $mapping->platform->name }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($mapping->product)
                                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $mapping->product->name }}</div>
                                @else
                                    <span class="text-zinc-400 dark:text-zinc-500">No product linked</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <flux:badge variant="{{ $mapping->is_active ? 'green' : 'gray' }}">
                                    {{ $mapping->is_active ? 'Active' : 'Inactive' }}
                                </flux:badge>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-zinc-500 dark:text-zinc-400">
                                {{ $mapping->updated_at->diffForHumans() }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center">
                                <div class="text-zinc-500 dark:text-zinc-400">No SKU mappings found.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    @if($this->mappings->hasPages())
        <div class="mt-6">
            {{ $this->mappings->links() }}
        </div>
    @endif
</div>