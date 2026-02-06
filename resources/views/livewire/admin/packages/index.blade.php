<?php

use App\Models\Package;
use App\Models\User;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public $search = '';

    public $statusFilter = '';

    public $creatorFilter = '';

    public function with(): array
    {
        return [
            'packages' => Package::query()
                ->with(['creator', 'products', 'courses', 'purchases'])
                ->when($this->search, fn ($query) => $query->search($this->search))
                ->when($this->statusFilter, fn ($query) => $query->where('status', $this->statusFilter))
                ->when($this->creatorFilter, fn ($query) => $query->where('created_by', $this->creatorFilter))
                ->latest()
                ->paginate(15),
            'creators' => User::whereIn('id', Package::distinct()->pluck('created_by'))->get(),
        ];
    }

    public function delete(Package $package): void
    {
        // Check if package has any completed purchases
        if ($package->completedPurchases()->exists()) {
            $this->dispatch('package-delete-error', message: 'Cannot delete package with completed purchases.');

            return;
        }

        $package->delete();
        $this->dispatch('package-deleted');
    }

    public function toggleStatus(Package $package): void
    {
        $newStatus = match ($package->status) {
            'active' => 'inactive',
            'inactive' => 'active',
            'draft' => 'active',
            default => 'inactive',
        };

        $package->update(['status' => $newStatus]);
    }

    public function duplicate(Package $package): void
    {
        $newPackage = $package->replicate();
        $newPackage->name = $package->name.' (Copy)';
        $newPackage->slug = null; // Will be auto-generated
        $newPackage->status = 'draft';
        $newPackage->purchased_count = 0;
        $newPackage->save();

        // Copy package items
        foreach ($package->items as $item) {
            $newItem = $item->replicate();
            $newItem->package_id = $newPackage->id;
            $newItem->save();
        }

        $this->dispatch('package-duplicated', packageId: $newPackage->id);
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'creatorFilter']);
        $this->resetPage();
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Packages</flux:heading>
            <flux:text class="mt-2">Create and manage product and course packages</flux:text>
        </div>
        <flux:button variant="primary" href="{{ route('packages.create') }}" icon="plus">
            Create Package
        </flux:button>
    </div>

    <!-- Filters -->
    <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-4">
        <flux:input
            wire:model.live.debounce.300ms="search"
            placeholder="Search packages..."
            icon="magnifying-glass"
        />

        <flux:select wire:model.live="statusFilter" placeholder="All Statuses">
            <flux:select.option value="">All Statuses</flux:select.option>
            <flux:select.option value="active">Active</flux:select.option>
            <flux:select.option value="inactive">Inactive</flux:select.option>
            <flux:select.option value="draft">Draft</flux:select.option>
        </flux:select>

        <flux:select wire:model.live="creatorFilter" placeholder="All Creators">
            <flux:select.option value="">All Creators</flux:select.option>
            @foreach($creators as $creator)
                <flux:select.option value="{{ $creator->id }}">{{ $creator->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:button wire:click="clearFilters" variant="outline" icon="x-mark">
            Clear Filters
        </flux:button>
    </div>

    <!-- Package Stats -->
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-4">
        <div class="bg-white dark:bg-zinc-800 overflow-hidden shadow rounded-lg border border-gray-200 dark:border-zinc-700">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <flux:icon name="cube" class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Total Packages</dt>
                            <dd class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ $packages->total() }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-zinc-800 overflow-hidden shadow rounded-lg border border-gray-200 dark:border-zinc-700">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <flux:icon name="check-circle" class="h-6 w-6 text-green-600 dark:text-green-400" />
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Active Packages</dt>
                            <dd class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                {{ Package::active()->count() }}
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-zinc-800 overflow-hidden shadow rounded-lg border border-gray-200 dark:border-zinc-700">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <flux:icon name="shopping-cart" class="h-6 w-6 text-purple-600 dark:text-purple-400" />
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Total Sales</dt>
                            <dd class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                {{ Package::sum('purchased_count') }}
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-zinc-800 overflow-hidden shadow rounded-lg border border-gray-200 dark:border-zinc-700">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <flux:icon name="banknotes" class="h-6 w-6 text-yellow-600 dark:text-yellow-400" />
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Total Revenue</dt>
                            <dd class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                RM {{ number_format(\App\Models\PackagePurchase::completed()->sum('amount_paid'), 2) }}
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Packages Table -->
    <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse border-0">
                <thead class="bg-gray-50 dark:bg-zinc-700/50 border-b border-gray-200 dark:border-zinc-700">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Package</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Items</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Price</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Savings</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Sales</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Created By</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-zinc-800">
                @forelse($packages as $package)
                    <tr wire:key="package-{{ $package->id }}" class="border-b border-gray-200 dark:border-zinc-700 hover:bg-gray-50 dark:hover:bg-zinc-700/50">
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm sm:pl-6">
                            <div class="flex items-center space-x-3">
                                @if($package->featured_image)
                                    <img src="{{ $package->featured_image }}"
                                         alt="{{ $package->name }}"
                                         class="h-10 w-10 rounded object-cover">
                                @else
                                    <div class="flex h-10 w-10 items-center justify-center rounded bg-gradient-to-br from-purple-500 to-blue-600">
                                        <flux:icon name="gift" class="h-5 w-5 text-white" />
                                    </div>
                                @endif
                                <div>
                                    <div class="font-medium text-gray-900 dark:text-gray-100">{{ $package->name }}</div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ $package->short_description }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-900 dark:text-gray-100">
                            <div class="flex space-x-1">
                                @if($package->getProductCount() > 0)
                                    <flux:badge variant="outline" size="sm">
                                        {{ $package->getProductCount() }} Product{{ $package->getProductCount() > 1 ? 's' : '' }}
                                    </flux:badge>
                                @endif
                                @if($package->getCourseCount() > 0)
                                    <flux:badge variant="outline" size="sm">
                                        {{ $package->getCourseCount() }} Course{{ $package->getCourseCount() > 1 ? 's' : '' }}
                                    </flux:badge>
                                @endif
                            </div>
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-900 dark:text-gray-100">
                            <div>
                                <div class="font-medium">{{ $package->formatted_price }}</div>
                                @if($package->calculateOriginalPrice() > $package->price)
                                    <div class="text-xs text-gray-500 dark:text-gray-400 line-through">
                                        {{ $package->formatted_original_price }}
                                    </div>
                                @endif
                            </div>
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-900 dark:text-gray-100">
                            @if($package->calculateSavings() > 0)
                                <div class="text-green-600 font-medium">
                                    {{ $package->formatted_savings }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    ({{ $package->getSavingsPercentage() }}% off)
                                </div>
                            @else
                                <span class="text-gray-400 dark:text-gray-500">No discount</span>
                            @endif
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-900 dark:text-gray-100">
                            <div class="text-sm">
                                <div class="font-medium">{{ $package->purchased_count }}</div>
                                @if($package->max_purchases)
                                    <div class="text-gray-500 dark:text-gray-400">
                                        / {{ $package->max_purchases }} limit
                                    </div>
                                @endif
                            </div>
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-900 dark:text-gray-100">
                            <flux:badge :variant="match($package->status) {
                                'active' => $package->isActive() ? 'success' : 'warning',
                                'inactive' => 'gray',
                                'draft' => 'warning',
                                default => 'gray'
                            }" size="sm">
                                @if($package->status === 'active' && !$package->isActive())
                                    {{ $package->isPurchaseLimitReached() ? 'Sold Out' : 'Expired' }}
                                @else
                                    {{ ucfirst($package->status) }}
                                @endif
                            </flux:badge>
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                            {{ $package->creator->name }}
                        </td>
                        <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                            <div class="flex items-center justify-end space-x-1">
                                <flux:button
                                    href="{{ route('packages.show', $package) }}"
                                    variant="outline"
                                    size="sm"
                                    icon="eye"
                                >
                                </flux:button>
                                <flux:button
                                    href="{{ route('packages.edit', $package) }}"
                                    variant="outline"
                                    size="sm"
                                    icon="pencil"
                                >
                                </flux:button>
                                <flux:button
                                    wire:click="duplicate({{ $package->id }})"
                                    variant="outline"
                                    size="sm"
                                    icon="document-duplicate"
                                >
                                </flux:button>
                                <flux:button
                                    wire:click="toggleStatus({{ $package->id }})"
                                    variant="outline"
                                    size="sm"
                                    :icon="$package->isActive() ? 'pause' : 'play'"
                                >
                                </flux:button>
                                @if(!$package->completedPurchases()->exists())
                                <flux:button
                                    wire:click="delete({{ $package->id }})"
                                    wire:confirm="Are you sure you want to delete this package?"
                                    variant="outline"
                                    size="sm"
                                    icon="trash"
                                >
                                </flux:button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center">
                            <div>
                                <flux:icon name="gift" class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" />
                                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No packages found</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by creating your first package.</p>
                                <div class="mt-6">
                                    <flux:button variant="primary" href="{{ route('packages.create') }}" icon="plus">
                                        Create Package
                                    </flux:button>
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    <!-- Pagination -->
    <div class="mt-6">
        {{ $packages->links() }}
    </div>
</div>
