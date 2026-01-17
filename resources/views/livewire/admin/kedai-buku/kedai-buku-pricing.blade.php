<?php

use App\Models\Agent;
use App\Models\KedaiBukuPricing;
use App\Models\Product;
use Livewire\Volt\Component;

new class extends Component {
    public Agent $kedaiBuku;

    public ?int $selectedProductId = null;
    public string $customPrice = '';
    public int $minQuantity = 1;
    public bool $showAddForm = false;

    public function mount(Agent $kedaiBuku): void
    {
        if (! $kedaiBuku->isBookstore()) {
            abort(404, 'Kedai Buku not found.');
        }

        $this->kedaiBuku = $kedaiBuku->load(['customPricing.product']);
    }

    public function with(): array
    {
        // Get products that don't already have custom pricing for this agent
        $existingProductIds = $this->kedaiBuku->customPricing->pluck('product_id')->toArray();

        return [
            'customPricing' => $this->kedaiBuku->customPricing()->with('product')->orderBy('created_at', 'desc')->get(),
            'availableProducts' => Product::whereNotIn('id', $existingProductIds)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ];
    }

    public function toggleAddForm(): void
    {
        $this->showAddForm = ! $this->showAddForm;
        $this->reset(['selectedProductId', 'customPrice', 'minQuantity']);
    }

    public function addCustomPricing(): void
    {
        $validated = $this->validate([
            'selectedProductId' => 'required|exists:products,id',
            'customPrice' => 'required|numeric|min:0',
            'minQuantity' => 'required|integer|min:1',
        ]);

        // Check if pricing already exists
        $exists = KedaiBukuPricing::where('agent_id', $this->kedaiBuku->id)
            ->where('product_id', $validated['selectedProductId'])
            ->where('min_quantity', $validated['minQuantity'])
            ->exists();

        if ($exists) {
            $this->addError('selectedProductId', 'Custom pricing for this product and quantity already exists.');
            return;
        }

        KedaiBukuPricing::create([
            'agent_id' => $this->kedaiBuku->id,
            'product_id' => $validated['selectedProductId'],
            'price' => $validated['customPrice'],
            'min_quantity' => $validated['minQuantity'],
            'is_active' => true,
        ]);

        $this->kedaiBuku->refresh();
        $this->reset(['selectedProductId', 'customPrice', 'minQuantity', 'showAddForm']);

        session()->flash('success', 'Custom pricing added successfully.');
    }

    public function togglePricingStatus(int $pricingId): void
    {
        $pricing = KedaiBukuPricing::findOrFail($pricingId);

        if ($pricing->agent_id !== $this->kedaiBuku->id) {
            session()->flash('error', 'Invalid pricing entry.');
            return;
        }

        $pricing->update(['is_active' => ! $pricing->is_active]);

        session()->flash('success', 'Pricing status updated.');
    }

    public function deletePricing(int $pricingId): void
    {
        $pricing = KedaiBukuPricing::findOrFail($pricingId);

        if ($pricing->agent_id !== $this->kedaiBuku->id) {
            session()->flash('error', 'Invalid pricing entry.');
            return;
        }

        $pricing->delete();

        session()->flash('success', 'Custom pricing removed.');
    }
}; ?>

<div>
    <div class="mb-6">
        <div class="flex items-center gap-4 mb-4">
            <flux:button href="{{ route('agents-kedai-buku.show', $kedaiBuku) }}" variant="outline" size="sm">
                <div class="flex items-center justify-center">
                    <flux:icon name="arrow-left" class="w-4 h-4 mr-1" />
                    Back to Details
                </div>
            </flux:button>
        </div>

        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">Manage Pricing</flux:heading>
                <flux:text class="mt-2">{{ $kedaiBuku->name }} ({{ $kedaiBuku->agent_code }})</flux:text>
            </div>
            <flux:button wire:click="toggleAddForm" variant="primary" icon="plus">
                Add Custom Price
            </flux:button>
        </div>
    </div>

    <!-- Tier Information -->
    <div class="mb-6 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800 p-4">
        <div class="flex items-start gap-3">
            <flux:icon name="information-circle" class="h-6 w-6 text-blue-500 flex-shrink-0 mt-0.5" />
            <div>
                <h4 class="text-sm font-semibold text-blue-900 dark:text-blue-100">Current Pricing Tier: {{ ucfirst($kedaiBuku->pricing_tier ?? 'standard') }}</h4>
                <p class="text-sm text-blue-700 dark:text-blue-300 mt-1">
                    Default discount: <strong>{{ $kedaiBuku->getTierDiscountPercentage() }}%</strong> applied to all products.
                </p>
                <p class="text-xs text-blue-600 dark:text-blue-400 mt-2">
                    Custom pricing below overrides the tier discount for specific products.
                </p>
            </div>
        </div>
    </div>

    <!-- Add Custom Pricing Form -->
    @if($showAddForm)
        <div class="mb-6 bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-zinc-100 mb-4">Add Custom Price</h3>

            <form wire:submit="addCustomPricing">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <flux:field class="md:col-span-2">
                        <flux:label>Product *</flux:label>
                        <flux:select wire:model="selectedProductId" required>
                            <flux:select.option value="">Select Product</flux:select.option>
                            @foreach($availableProducts as $product)
                                <flux:select.option value="{{ $product->id }}">
                                    {{ $product->name }} - RM {{ number_format($product->price, 2) }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="selectedProductId" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Custom Price (RM) *</flux:label>
                        <flux:input wire:model="customPrice" type="number" step="0.01" min="0" placeholder="0.00" required />
                        <flux:error name="customPrice" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Min Quantity</flux:label>
                        <flux:input wire:model="minQuantity" type="number" min="1" placeholder="1" required />
                        <flux:error name="minQuantity" />
                        <flux:description>Minimum order qty for this price</flux:description>
                    </flux:field>
                </div>

                <div class="mt-4 flex items-center gap-3">
                    <flux:button type="submit" variant="primary">
                        Add Custom Price
                    </flux:button>
                    <flux:button wire:click="toggleAddForm" variant="outline">
                        Cancel
                    </flux:button>
                </div>
            </form>
        </div>
    @endif

    <!-- Custom Pricing Table -->
    <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-zinc-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-zinc-100">Custom Pricing ({{ $customPricing->count() }})</h3>
        </div>

        @if($customPricing->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full border-collapse border-0">
                    <thead class="bg-gray-50 dark:bg-zinc-900 border-b border-gray-200 dark:border-zinc-700">
                        <tr>
                            <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 dark:text-zinc-100 sm:pl-6">Product</th>
                            <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-zinc-100">Original Price</th>
                            <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-zinc-100">Custom Price</th>
                            <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-zinc-100">Discount</th>
                            <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-zinc-100">Min Qty</th>
                            <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-zinc-100">Status</th>
                            <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                                <span class="sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-zinc-800">
                        @foreach($customPricing as $pricing)
                            <tr wire:key="pricing-{{ $pricing->id }}" class="border-b border-gray-200 dark:border-zinc-700 hover:bg-gray-50 dark:hover:bg-zinc-700/50">
                                <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm sm:pl-6">
                                    <div class="font-medium text-gray-900 dark:text-zinc-100">{{ $pricing->product?->name ?? 'Unknown Product' }}</div>
                                    <div class="text-gray-500 dark:text-zinc-400">{{ $pricing->product?->sku ?? '-' }}</div>
                                </td>
                                <td class="px-3 py-4 text-sm text-gray-500 dark:text-zinc-400 line-through">
                                    RM {{ number_format($pricing->product?->price ?? 0, 2) }}
                                </td>
                                <td class="px-3 py-4 text-sm font-semibold text-green-600 dark:text-green-400">
                                    RM {{ number_format($pricing->price, 2) }}
                                </td>
                                <td class="px-3 py-4 text-sm">
                                    @if($pricing->product && $pricing->product->price > 0)
                                        <flux:badge variant="success" size="sm">
                                            {{ number_format($pricing->discount_percentage, 1) }}% off
                                        </flux:badge>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-3 py-4 text-sm text-gray-900 dark:text-zinc-100">
                                    {{ $pricing->min_quantity }}
                                </td>
                                <td class="px-3 py-4 text-sm">
                                    <flux:badge :variant="$pricing->is_active ? 'success' : 'gray'" size="sm">
                                        {{ $pricing->is_active ? 'Active' : 'Inactive' }}
                                    </flux:badge>
                                </td>
                                <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                    <div class="flex items-center justify-end gap-2">
                                        <flux:button
                                            wire:click="togglePricingStatus({{ $pricing->id }})"
                                            variant="outline"
                                            size="sm"
                                        >
                                            {{ $pricing->is_active ? 'Disable' : 'Enable' }}
                                        </flux:button>
                                        <flux:button
                                            wire:click="deletePricing({{ $pricing->id }})"
                                            wire:confirm="Are you sure you want to remove this custom pricing?"
                                            variant="outline"
                                            size="sm"
                                            class="text-red-600 dark:text-red-400 border-red-200 dark:border-red-800 hover:bg-red-50 dark:hover:bg-red-900/30"
                                        >
                                            Remove
                                        </flux:button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="px-6 py-12 text-center">
                <flux:icon name="currency-dollar" class="mx-auto h-12 w-12 text-gray-400 dark:text-zinc-500" />
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-zinc-100">No custom pricing</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-zinc-400">
                    This bookstore uses the default {{ ucfirst($kedaiBuku->pricing_tier ?? 'standard') }} tier discount ({{ $kedaiBuku->getTierDiscountPercentage() }}%).
                </p>
                <div class="mt-6">
                    <flux:button wire:click="toggleAddForm" variant="primary" icon="plus">
                        Add Custom Price
                    </flux:button>
                </div>
            </div>
        @endif
    </div>
</div>
