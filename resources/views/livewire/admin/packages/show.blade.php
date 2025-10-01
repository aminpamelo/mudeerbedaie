<?php

use App\Models\Package;
use Livewire\Volt\Component;

new class extends Component
{
    public Package $package;

    public function mount(Package $package): void
    {
        $this->package = $package->load([
            'creator',
            'items.itemable',
            'products.stockLevels.warehouse',
            'courses',
            'purchases.customer',
            'defaultWarehouse',
        ]);
    }

    public function with(): array
    {
        return [
            'totalRevenue' => $this->package->purchases()->completed()->sum('amount_paid'),
            'totalSales' => $this->package->purchased_count,
            'averageOrderValue' => $this->package->purchased_count > 0
                ? $this->package->purchases()->completed()->sum('amount_paid') / $this->package->purchased_count
                : 0,
            'recentPurchases' => $this->package->purchases()->with('customer')->latest()->take(5)->get(),
            'stockStatus' => $this->package->track_stock ? $this->package->checkStockAvailability() : null,
        ];
    }

    public function toggleStatus(): void
    {
        $newStatus = match ($this->package->status) {
            'active' => 'inactive',
            'inactive' => 'active',
            'draft' => 'active',
            default => 'inactive',
        };

        $this->package->update(['status' => $newStatus]);
        $this->dispatch('package-status-updated');
    }

    public function duplicate(): void
    {
        $newPackage = $this->package->replicate();
        $newPackage->name = $this->package->name.' (Copy)';
        $newPackage->slug = null;
        $newPackage->status = 'draft';
        $newPackage->purchased_count = 0;
        $newPackage->save();

        foreach ($this->package->items as $item) {
            $newItem = $item->replicate();
            $newItem->package_id = $newPackage->id;
            $newItem->save();
        }

        $this->redirect(route('packages.show', $newPackage));
    }
}; ?>

<div>
    <!-- Header -->
    <div class="mb-6 flex items-center justify-between">
        <div class="flex items-center space-x-4">
            <flux:button
                href="{{ route('packages.index') }}"
                variant="outline"
                icon="arrow-left"
                size="sm"
            >
                <div class="flex items-center justify-center">
                    <flux:icon name="arrow-left" class="w-4 h-4 mr-1" />
                    Back
                </div>
            </flux:button>
            <div>
                <flux:heading size="xl">{{ $package->name }}</flux:heading>
                <flux:text class="mt-2">Package details and analytics</flux:text>
            </div>
        </div>
        <div class="flex items-center space-x-2">
            <flux:button
                wire:click="duplicate"
                variant="outline"
                icon="document-duplicate"
            >
                Duplicate
            </flux:button>
            <flux:button
                wire:click="toggleStatus"
                variant="outline"
                :icon="$package->isActive() ? 'pause' : 'play'"
            >
                {{ $package->isActive() ? 'Deactivate' : 'Activate' }}
            </flux:button>
            <flux:button
                href="{{ route('packages.edit', $package) }}"
                variant="primary"
                icon="pencil"
            >
                Edit Package
            </flux:button>
        </div>
    </div>

    <!-- Status Banner -->
    @if($package->status === 'draft')
        <div class="mb-6 rounded-lg bg-yellow-50 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <flux:icon name="exclamation-triangle" class="h-5 w-5 text-yellow-400" />
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-yellow-800">Draft Package</h3>
                    <div class="mt-2 text-sm text-yellow-700">
                        <p>This package is in draft mode and not visible to customers.</p>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($package->status === 'active' && !$package->isActive())
        <div class="mb-6 rounded-lg bg-red-50 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <flux:icon name="exclamation-circle" class="h-5 w-5 text-red-400" />
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800">Package Unavailable</h3>
                    <div class="mt-2 text-sm text-red-700">
                        @if($package->isPurchaseLimitReached())
                            <p>This package has reached its purchase limit ({{ $package->max_purchases }} sales).</p>
                        @elseif(!$package->isWithinDateRange())
                            <p>This package is outside its active date range.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Key Stats -->
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-4">
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <flux:icon name="shopping-cart" class="h-6 w-6 text-blue-600" />
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Total Sales</dt>
                            <dd class="text-lg font-medium text-gray-900">{{ number_format($totalSales) }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <flux:icon name="banknotes" class="h-6 w-6 text-green-600" />
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Total Revenue</dt>
                            <dd class="text-lg font-medium text-gray-900">RM {{ number_format($totalRevenue, 2) }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <flux:icon name="chart-bar" class="h-6 w-6 text-purple-600" />
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Avg Order Value</dt>
                            <dd class="text-lg font-medium text-gray-900">RM {{ number_format($averageOrderValue, 2) }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <flux:icon name="percent-badge" class="h-6 w-6 text-yellow-600" />
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Savings</dt>
                            <dd class="text-lg font-medium text-gray-900">{{ $package->getSavingsPercentage() }}% off</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Package Details -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Package Details</h3>

                    @if($package->featured_image)
                        <img src="{{ $package->featured_image }}"
                             alt="{{ $package->name }}"
                             class="w-full h-48 object-cover rounded-lg mb-4">
                    @endif

                    <div class="space-y-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Description</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $package->description ?? 'No description provided' }}</dd>
                        </div>

                        @if($package->short_description)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Short Description</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $package->short_description }}</dd>
                            </div>
                        @endif

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Status</dt>
                                <dd class="mt-1">
                                    <flux:badge :variant="match($package->status) {
                                        'active' => $package->isActive() ? 'success' : 'warning',
                                        'inactive' => 'gray',
                                        'draft' => 'warning',
                                        default => 'gray'
                                    }">
                                        @if($package->status === 'active' && !$package->isActive())
                                            {{ $package->isPurchaseLimitReached() ? 'Sold Out' : 'Expired' }}
                                        @else
                                            {{ ucfirst($package->status) }}
                                        @endif
                                    </flux:badge>
                                </dd>
                            </div>

                            <div>
                                <dt class="text-sm font-medium text-gray-500">Created By</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $package->creator->name }}</dd>
                            </div>
                        </div>

                        @if($package->start_date || $package->end_date)
                            <div class="grid grid-cols-2 gap-4">
                                @if($package->start_date)
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Start Date</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ $package->start_date->format('M j, Y') }}</dd>
                                    </div>
                                @endif

                                @if($package->end_date)
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">End Date</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ $package->end_date->format('M j, Y') }}</dd>
                                    </div>
                                @endif
                            </div>
                        @endif

                        @if($package->max_purchases)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Purchase Limit</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    {{ $package->purchased_count }} / {{ $package->max_purchases }} sold
                                    <div class="mt-2 w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-blue-600 h-2 rounded-full"
                                             style="width: {{ min(100, ($package->purchased_count / $package->max_purchases) * 100) }}%">
                                        </div>
                                    </div>
                                </dd>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Package Items -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Package Contents</h3>

                    <div class="space-y-4">
                        @if($package->products->count() > 0)
                            <div>
                                <h4 class="text-sm font-medium text-gray-900 mb-2">Products ({{ $package->products->count() }})</h4>
                                <ul class="divide-y divide-gray-200 border border-gray-200 rounded-lg">
                                    @foreach($package->products as $product)
                                        <li class="p-4">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center space-x-3">
                                                    @if($product->featured_image)
                                                        <img src="{{ $product->featured_image }}"
                                                             alt="{{ $product->name }}"
                                                             class="h-12 w-12 rounded object-cover">
                                                    @else
                                                        <div class="flex h-12 w-12 items-center justify-center rounded bg-gray-100">
                                                            <flux:icon name="cube" class="h-6 w-6 text-gray-400" />
                                                        </div>
                                                    @endif
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-900">{{ $product->name }}</div>
                                                        <div class="text-xs text-gray-500">
                                                            Qty: {{ $product->pivot->quantity }}
                                                            @if($product->pivot->warehouse_id)
                                                                · Warehouse: {{ $product->stockLevels->where('warehouse_id', $product->pivot->warehouse_id)->first()?->warehouse->name ?? 'N/A' }}
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="text-right">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        RM {{ number_format(($product->pivot->custom_price ?? $product->base_price) * $product->pivot->quantity, 2) }}
                                                    </div>
                                                    @if($product->pivot->custom_price && $product->pivot->custom_price < $product->base_price)
                                                        <div class="text-xs text-gray-500 line-through">
                                                            RM {{ number_format($product->base_price * $product->pivot->quantity, 2) }}
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if($package->courses->count() > 0)
                            <div>
                                <h4 class="text-sm font-medium text-gray-900 mb-2">Courses ({{ $package->courses->count() }})</h4>
                                <ul class="divide-y divide-gray-200 border border-gray-200 rounded-lg">
                                    @foreach($package->courses as $course)
                                        <li class="p-4">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center space-x-3">
                                                    <div class="flex h-12 w-12 items-center justify-center rounded bg-blue-100">
                                                        <flux:icon name="academic-cap" class="h-6 w-6 text-blue-600" />
                                                    </div>
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-900">{{ $course->title }}</div>
                                                        <div class="text-xs text-gray-500">Course Code: {{ $course->code }}</div>
                                                    </div>
                                                </div>
                                                <div class="text-sm font-medium text-gray-900">
                                                    RM {{ number_format($course->pivot->custom_price ?? ($course->feeSettings->fee_amount ?? 0), 2) }}
                                                </div>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if($package->products->count() === 0 && $package->courses->count() === 0)
                            <div class="text-center py-6 text-gray-500">
                                <flux:icon name="inbox" class="mx-auto h-12 w-12 text-gray-400" />
                                <p class="mt-2 text-sm">No items in this package yet</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Stock Status -->
            @if($package->track_stock && $stockStatus)
                <div class="bg-white shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Stock Status</h3>

                        @if($stockStatus['available'])
                            <div class="rounded-md bg-green-50 p-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <flux:icon name="check-circle" class="h-5 w-5 text-green-400" />
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-green-800">Stock Available</p>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="rounded-md bg-red-50 p-4 mb-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <flux:icon name="exclamation-circle" class="h-5 w-5 text-red-400" />
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-red-800">Stock Issues</h3>
                                        <div class="mt-2 text-sm text-red-700">
                                            <ul class="list-disc list-inside space-y-1">
                                                @foreach($stockStatus['issues'] as $issue)
                                                    <li>{{ $issue }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if(!empty($stockStatus['details']))
                            <div class="mt-4">
                                <h4 class="text-sm font-medium text-gray-900 mb-2">Stock Details</h4>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Required</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Available</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 bg-white">
                                            @foreach($stockStatus['details'] as $detail)
                                                <tr>
                                                    <td class="px-3 py-2 text-sm text-gray-900">{{ $detail['product_name'] }}</td>
                                                    <td class="px-3 py-2 text-sm text-gray-900">{{ $detail['required_quantity'] }}</td>
                                                    <td class="px-3 py-2 text-sm text-gray-900">{{ $detail['available_quantity'] }}</td>
                                                    <td class="px-3 py-2 text-sm">
                                                        @if($detail['sufficient'])
                                                            <flux:badge variant="success" size="sm">Sufficient</flux:badge>
                                                        @else
                                                            <flux:badge variant="danger" size="sm">Insufficient</flux:badge>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Recent Purchases -->
            @if($recentPurchases->count() > 0)
                <div class="bg-white shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-medium leading-6 text-gray-900">Recent Purchases</h3>
                            <flux:button
                                href="{{ route('package-purchases.index', ['packageFilter' => $package->id]) }}"
                                variant="outline"
                                size="sm"
                            >
                                View All
                            </flux:button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Purchase #</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    @foreach($recentPurchases as $purchase)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-3 py-2 text-sm font-medium text-gray-900">
                                                <a href="{{ route('package-purchases.show', $purchase) }}" class="text-blue-600 hover:text-blue-800">
                                                    #{{ $purchase->purchase_number }}
                                                </a>
                                            </td>
                                            <td class="px-3 py-2 text-sm text-gray-900">{{ $purchase->customer->name }}</td>
                                            <td class="px-3 py-2 text-sm text-gray-900">RM {{ number_format($purchase->amount_paid, 2) }}</td>
                                            <td class="px-3 py-2 text-sm">
                                                <flux:badge :variant="match($purchase->status) {
                                                    'completed' => 'success',
                                                    'processing' => 'warning',
                                                    'pending' => 'gray',
                                                    'cancelled' => 'danger',
                                                    default => 'gray'
                                                }" size="sm">
                                                    {{ ucfirst($purchase->status) }}
                                                </flux:badge>
                                            </td>
                                            <td class="px-3 py-2 text-sm text-gray-500">{{ $purchase->created_at->format('M j, Y') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="lg:col-span-1 space-y-6">
            <!-- Pricing Info -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Pricing</h3>

                    <div class="space-y-3">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Package Price</dt>
                            <dd class="mt-1 text-2xl font-bold text-gray-900">{{ $package->formatted_price }}</dd>
                        </div>

                        @if($package->calculateOriginalPrice() > $package->price)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Original Price</dt>
                                <dd class="mt-1 text-lg text-gray-500 line-through">{{ $package->formatted_original_price }}</dd>
                            </div>

                            <div class="pt-3 border-t border-gray-200">
                                <dt class="text-sm font-medium text-gray-500">Total Savings</dt>
                                <dd class="mt-1 text-lg font-semibold text-green-600">
                                    {{ $package->formatted_savings }} ({{ $package->getSavingsPercentage() }}% off)
                                </dd>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Quick Actions</h3>

                    <div class="space-y-2">
                        <flux:button
                            href="{{ route('packages.edit', $package) }}"
                            variant="outline"
                            icon="pencil"
                            class="w-full"
                        >
                            Edit Package
                        </flux:button>

                        <flux:button
                            wire:click="duplicate"
                            variant="outline"
                            icon="document-duplicate"
                            class="w-full"
                        >
                            Duplicate Package
                        </flux:button>

                        <flux:button
                            wire:click="toggleStatus"
                            variant="outline"
                            :icon="$package->isActive() ? 'pause' : 'play'"
                            class="w-full"
                        >
                            {{ $package->isActive() ? 'Deactivate' : 'Activate' }}
                        </flux:button>

                        @if(!$package->completedPurchases()->exists())
                            <flux:button
                                href="{{ route('packages.index') }}"
                                wire:confirm="Are you sure you want to delete this package?"
                                variant="outline"
                                icon="trash"
                                class="w-full text-red-600 hover:text-red-700"
                            >
                                Delete Package
                            </flux:button>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Metadata -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Metadata</h3>

                    <dl class="space-y-3 text-sm">
                        <div>
                            <dt class="text-gray-500">Package ID</dt>
                            <dd class="text-gray-900 font-mono">{{ $package->id }}</dd>
                        </div>

                        <div>
                            <dt class="text-gray-500">Slug</dt>
                            <dd class="text-gray-900 font-mono">{{ $package->slug }}</dd>
                        </div>

                        <div>
                            <dt class="text-gray-500">Created</dt>
                            <dd class="text-gray-900">{{ $package->created_at->format('M j, Y g:i A') }}</dd>
                        </div>

                        <div>
                            <dt class="text-gray-500">Last Updated</dt>
                            <dd class="text-gray-900">{{ $package->updated_at->format('M j, Y g:i A') }}</dd>
                        </div>

                        @if($package->defaultWarehouse)
                            <div>
                                <dt class="text-gray-500">Default Warehouse</dt>
                                <dd class="text-gray-900">{{ $package->defaultWarehouse->name }}</dd>
                            </div>
                        @endif

                        <div>
                            <dt class="text-gray-500">Stock Tracking</dt>
                            <dd class="text-gray-900">
                                <flux:badge :variant="$package->track_stock ? 'success' : 'gray'" size="sm">
                                    {{ $package->track_stock ? 'Enabled' : 'Disabled' }}
                                </flux:badge>
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>
