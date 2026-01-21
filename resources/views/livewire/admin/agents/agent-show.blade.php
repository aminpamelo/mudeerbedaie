<?php

use App\Models\Agent;
use App\Models\AgentPricing;
use App\Models\Product;
use Livewire\Volt\Component;

new class extends Component {
    public Agent $agent;

    // Custom Pricing Form
    public bool $showPricingModal = false;
    public ?int $editingPricingId = null;
    public string $pricingProductId = '';
    public string $pricingPrice = '';
    public int $pricingMinQuantity = 1;
    public bool $pricingIsActive = true;

    public function mount(Agent $agent): void
    {
        $this->agent = $agent->load(['warehouses.stockLevels', 'customPricing.product']);
    }

    public function toggleStatus(): void
    {
        $this->agent->update(['is_active' => ! $this->agent->is_active]);
        $this->agent->refresh();

        session()->flash('success', 'Agent status updated successfully.');
    }

    public function delete(): void
    {
        if ($this->agent->warehouses()->count() > 0) {
            session()->flash('error', 'Cannot delete agent with existing warehouses. Remove warehouses first.');

            return;
        }

        $this->agent->delete();

        session()->flash('success', 'Agent deleted successfully.');

        $this->redirect(route('agents.index'), navigate: true);
    }

    // Custom Pricing Methods
    public function openPricingModal(): void
    {
        $this->resetPricingForm();
        $this->showPricingModal = true;
    }

    public function editPricing(int $pricingId): void
    {
        $pricing = AgentPricing::find($pricingId);
        if (! $pricing || $pricing->agent_id !== $this->agent->id) {
            return;
        }

        $this->editingPricingId = $pricingId;
        $this->pricingProductId = (string) $pricing->product_id;
        $this->pricingPrice = (string) $pricing->price;
        $this->pricingMinQuantity = $pricing->min_quantity;
        $this->pricingIsActive = $pricing->is_active;
        $this->showPricingModal = true;
    }

    public function savePricing(): void
    {
        $this->validate([
            'pricingProductId' => 'required|exists:products,id',
            'pricingPrice' => 'required|numeric|min:0',
            'pricingMinQuantity' => 'required|integer|min:1',
        ], [
            'pricingProductId.required' => 'Please select a product.',
            'pricingPrice.required' => 'Please enter a price.',
            'pricingPrice.min' => 'Price must be at least 0.',
        ]);

        // Check for duplicate (same product + min_quantity) when creating new
        if (! $this->editingPricingId) {
            $exists = AgentPricing::where('agent_id', $this->agent->id)
                ->where('product_id', $this->pricingProductId)
                ->where('min_quantity', $this->pricingMinQuantity)
                ->exists();

            if ($exists) {
                $this->addError('pricingProductId', 'A pricing entry for this product with the same minimum quantity already exists.');
                return;
            }
        }

        if ($this->editingPricingId) {
            $pricing = AgentPricing::find($this->editingPricingId);
            if ($pricing && $pricing->agent_id === $this->agent->id) {
                $pricing->update([
                    'product_id' => $this->pricingProductId,
                    'price' => $this->pricingPrice,
                    'min_quantity' => $this->pricingMinQuantity,
                    'is_active' => $this->pricingIsActive,
                ]);
                session()->flash('success', 'Custom pricing updated successfully.');
            }
        } else {
            AgentPricing::create([
                'agent_id' => $this->agent->id,
                'product_id' => $this->pricingProductId,
                'price' => $this->pricingPrice,
                'min_quantity' => $this->pricingMinQuantity,
                'is_active' => $this->pricingIsActive,
            ]);
            session()->flash('success', 'Custom pricing added successfully.');
        }

        $this->agent->load('customPricing.product');
        $this->closePricingModal();
    }

    public function deletePricing(int $pricingId): void
    {
        $pricing = AgentPricing::find($pricingId);
        if ($pricing && $pricing->agent_id === $this->agent->id) {
            $pricing->delete();
            $this->agent->load('customPricing.product');
            session()->flash('success', 'Custom pricing deleted successfully.');
        }
    }

    public function togglePricingStatus(int $pricingId): void
    {
        $pricing = AgentPricing::find($pricingId);
        if ($pricing && $pricing->agent_id === $this->agent->id) {
            $pricing->update(['is_active' => ! $pricing->is_active]);
            $this->agent->load('customPricing.product');
        }
    }

    public function closePricingModal(): void
    {
        $this->showPricingModal = false;
        $this->resetPricingForm();
    }

    private function resetPricingForm(): void
    {
        $this->editingPricingId = null;
        $this->pricingProductId = '';
        $this->pricingPrice = '';
        $this->pricingMinQuantity = 1;
        $this->pricingIsActive = true;
        $this->resetErrorBag();
    }

    public function with(): array
    {
        return [
            'products' => Product::active()->orderBy('name')->get(),
            'customPricingList' => $this->agent->customPricing()
                ->with('product')
                ->orderBy('product_id')
                ->orderBy('min_quantity')
                ->get(),
        ];
    }
}; ?>

<div>
    <div class="mb-6">
        <div class="flex items-center gap-4 mb-4">
            <flux:button href="{{ route('agents.index') }}" variant="outline" size="sm">
                <div class="flex items-center justify-center">
                    <flux:icon name="arrow-left" class="w-4 h-4 mr-1" />
                    Back to Agents
                </div>
            </flux:button>
        </div>

        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">{{ $agent->name }}</flux:heading>
                <flux:text class="mt-2">{{ $agent->agent_code }} • {{ ucfirst($agent->type) }}</flux:text>
            </div>
            <div class="flex items-center gap-3">
                <flux:badge :variant="$agent->is_active ? 'success' : 'gray'" size="lg">
                    {{ $agent->is_active ? 'Active' : 'Inactive' }}
                </flux:badge>
                <flux:button href="{{ route('agents.edit', $agent) }}" variant="primary" icon="pencil">
                    Edit Agent
                </flux:button>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Basic Information -->
            <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
<<<<<<< HEAD
                <h3 class="text-lg font-semibold text-gray-900 dark:text-zinc-100 mb-4">Basic Information</h3>
=======
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Basic Information</h3>
>>>>>>> origin/main

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Agent Code</flux:text>
                        <flux:text class="mt-1 text-gray-900 dark:text-zinc-100">{{ $agent->agent_code }}</flux:text>
                    </div>

                    <div>
                        <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Type</flux:text>
                        <flux:text class="mt-1 text-gray-900 dark:text-zinc-100">{{ ucfirst($agent->type) }}</flux:text>
                    </div>

                    <div>
                        <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Pricing Tier</flux:text>
                        <div class="mt-1 flex items-center gap-2">
                            @php
                                $tierColors = [
                                    'standard' => 'gray',
                                    'premium' => 'blue',
                                    'vip' => 'yellow',
                                ];
                                $tierDiscounts = [
                                    'standard' => '10%',
                                    'premium' => '15%',
                                    'vip' => '20%',
                                ];
                            @endphp
                            <flux:badge color="{{ $tierColors[$agent->pricing_tier] ?? 'gray' }}">
                                {{ ucfirst($agent->pricing_tier ?? 'standard') }}
                            </flux:badge>
                            <flux:text class="text-sm text-green-600 dark:text-green-400">
                                ({{ $tierDiscounts[$agent->pricing_tier] ?? '10%' }} discount)
                            </flux:text>
                        </div>
                    </div>

                    <div>
                        <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Name</flux:text>
                        <flux:text class="mt-1 text-gray-900 dark:text-zinc-100">{{ $agent->name }}</flux:text>
                    </div>

                    @if($agent->company_name)
                        <div>
                            <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Company Name</flux:text>
                            <flux:text class="mt-1 text-gray-900 dark:text-zinc-100">{{ $agent->company_name }}</flux:text>
                        </div>
                    @endif

                    @if($agent->registration_number)
                        <div>
                            <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Registration Number</flux:text>
                            <flux:text class="mt-1 text-gray-900 dark:text-zinc-100">{{ $agent->registration_number }}</flux:text>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Contact Information -->
            <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
<<<<<<< HEAD
                <h3 class="text-lg font-semibold text-gray-900 dark:text-zinc-100 mb-4">Contact Information</h3>
=======
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Contact Information</h3>
>>>>>>> origin/main

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    @if($agent->contact_person)
                        <div>
                            <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Contact Person</flux:text>
                            <flux:text class="mt-1 text-gray-900 dark:text-zinc-100">{{ $agent->contact_person }}</flux:text>
                        </div>
                    @endif

                    @if($agent->phone)
                        <div>
                            <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Phone</flux:text>
                            <flux:text class="mt-1 text-gray-900 dark:text-zinc-100">{{ $agent->phone }}</flux:text>
                        </div>
                    @endif

                    @if($agent->email)
                        <div class="md:col-span-2">
                            <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Email</flux:text>
                            <flux:text class="mt-1 text-gray-900 dark:text-zinc-100">{{ $agent->email }}</flux:text>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Address -->
            @if($agent->address)
                <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
<<<<<<< HEAD
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-zinc-100 mb-4">Address</h3>
=======
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Address</h3>
>>>>>>> origin/main

                    <flux:text class="text-gray-900 dark:text-zinc-100">{{ $agent->formatted_address }}</flux:text>
                </div>
            @endif

            <!-- Bank Details -->
            @if($agent->bank_details)
                <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
<<<<<<< HEAD
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-zinc-100 mb-4">Bank Details</h3>
=======
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Bank Details</h3>
>>>>>>> origin/main

                    <div class="space-y-3">
                        @if(isset($agent->bank_details['bank_name']))
                            <div>
                                <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Bank Name</flux:text>
                                <flux:text class="mt-1 text-gray-900 dark:text-zinc-100">{{ $agent->bank_details['bank_name'] }}</flux:text>
                            </div>
                        @endif

                        @if(isset($agent->bank_details['account_number']))
                            <div>
                                <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Account Number</flux:text>
                                <flux:text class="mt-1 text-gray-900 dark:text-zinc-100">{{ $agent->bank_details['account_number'] }}</flux:text>
                            </div>
                        @endif

                        @if(isset($agent->bank_details['account_name']))
                            <div>
                                <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Account Name</flux:text>
                                <flux:text class="mt-1 text-gray-900 dark:text-zinc-100">{{ $agent->bank_details['account_name'] }}</flux:text>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Warehouses -->
            <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                <div class="flex items-center justify-between mb-4">
<<<<<<< HEAD
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-zinc-100">Warehouses ({{ $agent->warehouses->count() }})</h3>
=======
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Warehouses ({{ $agent->warehouses->count() }})</h3>
>>>>>>> origin/main
                    <flux:button href="{{ route('warehouses.create') }}" variant="primary" size="sm" icon="plus">
                        Add Warehouse
                    </flux:button>
                </div>

                @if($agent->warehouses->count() > 0)
                    <div class="space-y-3">
                        @foreach($agent->warehouses as $warehouse)
<<<<<<< HEAD
                            <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-zinc-900 rounded-lg border border-gray-200 dark:border-zinc-700">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <flux:text class="font-medium text-gray-900 dark:text-zinc-100">{{ $warehouse->name }}</flux:text>
=======
                            <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-zinc-700/50 rounded-lg border border-gray-200 dark:border-zinc-700">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <flux:text class="font-medium text-gray-900 dark:text-gray-100">{{ $warehouse->name }}</flux:text>
>>>>>>> origin/main
                                        <flux:badge :variant="$warehouse->is_active ? 'success' : 'gray'" size="sm">
                                            {{ $warehouse->is_active ? 'Active' : 'Inactive' }}
                                        </flux:badge>
                                    </div>
<<<<<<< HEAD
                                    <flux:text class="text-sm text-gray-500 dark:text-zinc-400 mt-1">
=======
                                    <flux:text class="text-sm text-gray-500 dark:text-gray-400 mt-1">
>>>>>>> origin/main
                                        {{ $warehouse->code }} • {{ $warehouse->stock_levels_count ?? 0 }} items in stock
                                    </flux:text>
                                </div>
                                <flux:button href="{{ route('warehouses.show', $warehouse) }}" variant="outline" size="sm" icon="eye">
                                    View
                                </flux:button>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8">
<<<<<<< HEAD
                        <flux:icon name="building-office" class="mx-auto h-12 w-12 text-gray-400 dark:text-zinc-500" />
                        <flux:text class="mt-2 text-gray-500 dark:text-zinc-400">No warehouses assigned yet</flux:text>
                    </div>
                @endif
            </div>

            <!-- Custom Pricing -->
            <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-zinc-100">Custom Pricing ({{ $customPricingList->count() }})</h3>
                        <flux:text class="text-sm text-gray-500 dark:text-zinc-400">Special prices for specific products</flux:text>
                    </div>
                    <flux:button wire:click="openPricingModal" variant="primary" size="sm">
                        <div class="flex items-center">
                            <flux:icon name="plus" class="w-4 h-4 mr-1" />
                            Add Pricing
                        </div>
                    </flux:button>
                </div>

                @if($customPricingList->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-700">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Product</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Original Price</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Custom Price</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Min Qty</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Discount</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Status</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-zinc-700">
                                @foreach($customPricingList as $pricing)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-zinc-900/50">
                                        <td class="px-4 py-3">
                                            <flux:text class="font-medium text-gray-900 dark:text-zinc-100">{{ $pricing->product->name ?? 'Unknown' }}</flux:text>
                                            @if($pricing->product?->sku)
                                                <flux:text class="text-xs text-gray-500 dark:text-zinc-400">{{ $pricing->product->sku }}</flux:text>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <flux:text class="text-gray-600 dark:text-zinc-400">RM {{ number_format($pricing->product->base_price ?? 0, 2) }}</flux:text>
                                        </td>
                                        <td class="px-4 py-3">
                                            <flux:text class="font-medium text-green-600 dark:text-green-400">RM {{ number_format($pricing->price, 2) }}</flux:text>
                                        </td>
                                        <td class="px-4 py-3">
                                            <flux:text class="text-gray-900 dark:text-zinc-100">{{ $pricing->min_quantity }}</flux:text>
                                        </td>
                                        <td class="px-4 py-3">
                                            @php
                                                $originalPrice = $pricing->product->base_price ?? 0;
                                                $discountPercent = $originalPrice > 0 ? round((1 - ($pricing->price / $originalPrice)) * 100, 1) : 0;
                                            @endphp
                                            @if($discountPercent > 0)
                                                <flux:badge color="green" size="sm">{{ $discountPercent }}% off</flux:badge>
                                            @elseif($discountPercent < 0)
                                                <flux:badge color="red" size="sm">{{ abs($discountPercent) }}% markup</flux:badge>
                                            @else
                                                <flux:text class="text-gray-500 dark:text-zinc-400">-</flux:text>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <button wire:click="togglePricingStatus({{ $pricing->id }})" class="cursor-pointer">
                                                <flux:badge :color="$pricing->is_active ? 'green' : 'gray'" size="sm">
                                                    {{ $pricing->is_active ? 'Active' : 'Inactive' }}
                                                </flux:badge>
                                            </button>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <flux:button wire:click="editPricing({{ $pricing->id }})" variant="outline" size="sm">
                                                    <flux:icon name="pencil" class="w-4 h-4" />
                                                </flux:button>
                                                <flux:button
                                                    wire:click="deletePricing({{ $pricing->id }})"
                                                    wire:confirm="Are you sure you want to delete this custom pricing?"
                                                    variant="outline"
                                                    size="sm"
                                                    class="text-red-600 dark:text-red-400 border-red-200 dark:border-red-800 hover:bg-red-50 dark:hover:bg-red-900/30"
                                                >
                                                    <flux:icon name="trash" class="w-4 h-4" />
                                                </flux:button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-8">
                        <flux:icon name="currency-dollar" class="mx-auto h-12 w-12 text-gray-400 dark:text-zinc-500" />
                        <flux:text class="mt-2 text-gray-500 dark:text-zinc-400">No custom pricing set</flux:text>
                        <flux:text class="text-sm text-gray-400 dark:text-zinc-500">This agent will use tier-based discounts ({{ $agent->getTierDiscountPercentage() }}%)</flux:text>
=======
                        <flux:icon name="building-office" class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" />
                        <flux:text class="mt-2 text-gray-500 dark:text-gray-400">No warehouses assigned yet</flux:text>
>>>>>>> origin/main
                    </div>
                @endif
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Payment Terms -->
            <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
<<<<<<< HEAD
                <h3 class="text-lg font-semibold text-gray-900 dark:text-zinc-100 mb-4">Payment Terms</h3>

                <flux:text class="text-gray-900 dark:text-zinc-100">
=======
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Payment Terms</h3>

                <flux:text class="text-gray-900 dark:text-gray-100">
>>>>>>> origin/main
                    {{ $agent->payment_terms ?: 'Not specified' }}
                </flux:text>
            </div>

            <!-- Notes -->
            @if($agent->notes)
                <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
<<<<<<< HEAD
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-zinc-100 mb-4">Notes</h3>

                    <flux:text class="text-gray-900 dark:text-zinc-100 whitespace-pre-wrap">{{ $agent->notes }}</flux:text>
=======
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Notes</h3>

                    <flux:text class="text-gray-900 dark:text-gray-100 whitespace-pre-wrap">{{ $agent->notes }}</flux:text>
>>>>>>> origin/main
                </div>
            @endif

            <!-- Actions -->
            <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
<<<<<<< HEAD
                <h3 class="text-lg font-semibold text-gray-900 dark:text-zinc-100 mb-4">Actions</h3>
=======
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Actions</h3>
>>>>>>> origin/main

                <div class="flex flex-col gap-3">
                    <flux:button wire:click="toggleStatus" variant="outline" class="w-full">
                        {{ $agent->is_active ? 'Deactivate' : 'Activate' }} Agent
                    </flux:button>

                    @if($agent->warehouses->count() === 0)
                        <flux:button
                            wire:click="delete"
                            wire:confirm="Are you sure you want to delete this agent? This action cannot be undone."
                            variant="outline"
                            class="w-full text-red-600 dark:text-red-400 border-red-200 dark:border-red-800 hover:bg-red-50 dark:hover:bg-red-900/30"
                        >
                            Delete Agent
                        </flux:button>
                    @endif
                </div>
            </div>

            <!-- Metadata -->
            <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
<<<<<<< HEAD
                <h3 class="text-lg font-semibold text-gray-900 dark:text-zinc-100 mb-4">Information</h3>
=======
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Information</h3>
>>>>>>> origin/main

                <div class="space-y-3">
                    <div>
                        <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Created</flux:text>
                        <flux:text class="mt-1 text-sm text-gray-900 dark:text-zinc-100">{{ $agent->created_at->format('M d, Y h:i A') }}</flux:text>
                    </div>

                    <div>
                        <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Last Updated</flux:text>
                        <flux:text class="mt-1 text-sm text-gray-900 dark:text-zinc-100">{{ $agent->updated_at->format('M d, Y h:i A') }}</flux:text>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Custom Pricing Modal -->
    <flux:modal wire:model="showPricingModal" class="max-w-lg">
        <div class="p-6">
            <flux:heading size="lg" class="mb-4">
                {{ $editingPricingId ? 'Edit Custom Pricing' : 'Add Custom Pricing' }}
            </flux:heading>

            <form wire:submit="savePricing">
                <div class="space-y-4">
                    <flux:field>
                        <flux:label>Product</flux:label>
                        <flux:select wire:model="pricingProductId" :disabled="$editingPricingId !== null">
                            <option value="">Select a product...</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}">
                                    {{ $product->name }} (RM {{ number_format($product->base_price, 2) }})
                                </option>
                            @endforeach
                        </flux:select>
                        @error('pricingProductId')
                            <flux:error>{{ $message }}</flux:error>
                        @enderror
                    </flux:field>

                    <div class="grid grid-cols-2 gap-4">
                        <flux:field>
                            <flux:label>Custom Price (RM)</flux:label>
                            <flux:input
                                type="number"
                                step="0.01"
                                min="0"
                                wire:model="pricingPrice"
                                placeholder="0.00"
                            />
                            @error('pricingPrice')
                                <flux:error>{{ $message }}</flux:error>
                            @enderror
                        </flux:field>

                        <flux:field>
                            <flux:label>Minimum Quantity</flux:label>
                            <flux:input
                                type="number"
                                min="1"
                                wire:model="pricingMinQuantity"
                            />
                            <flux:description>Price applies when ordering this qty or more</flux:description>
                            @error('pricingMinQuantity')
                                <flux:error>{{ $message }}</flux:error>
                            @enderror
                        </flux:field>
                    </div>

                    <flux:field>
                        <flux:label class="flex items-center gap-2">
                            <flux:checkbox wire:model="pricingIsActive" />
                            Active
                        </flux:label>
                        <flux:description>Only active pricing will be applied to orders</flux:description>
                    </flux:field>
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <flux:button type="button" wire:click="closePricingModal" variant="outline">
                        Cancel
                    </flux:button>
                    <flux:button type="submit" variant="primary">
                        {{ $editingPricingId ? 'Update Pricing' : 'Add Pricing' }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
