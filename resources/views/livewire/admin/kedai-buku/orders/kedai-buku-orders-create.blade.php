<?php

use App\Models\Agent;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\ProductOrderItem;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;

new class extends Component
{
    public array $form = [
        'agent_id' => '',
        'payment_method' => 'credit',
        'payment_status' => 'pending',
        'order_status' => 'pending',
        'notes' => '',
        'required_delivery_date' => '',
    ];

    public array $orderItems = [];

    public float $subtotal = 0;

    public ?float $shippingCost = 0;

    public ?float $taxRate = 0;

    public float $taxAmount = 0;

    public float $total = 0;

    public float $discountAmount = 0;

    public string $agentSearch = '';

    public ?Agent $selectedAgent = null;

    public function mount(): void
    {
        // Check if agent_id is passed via query parameter
        if (request()->has('agent_id')) {
            $agentId = request()->get('agent_id');
            $agent = Agent::find($agentId);
            if ($agent && $agent->isBookstore()) {
                $this->selectAgent($agentId);
            }
        }

        $this->addOrderItem();
    }

    public function addOrderItem(): void
    {
        $this->orderItems[] = [
            'product_id' => '',
            'product_variant_id' => null,
            'warehouse_id' => '',
            'quantity' => 1,
            'original_price' => 0,
            'unit_price' => 0,
            'discount_percentage' => 0,
            'total_price' => 0,
        ];
    }

    public function removeOrderItem(int $index): void
    {
        unset($this->orderItems[$index]);
        $this->orderItems = array_values($this->orderItems);
        $this->calculateTotals();
    }

    public function updatedOrderItems(): void
    {
        $this->calculateTotals();
    }

    public function productSelected(int $index, int $productId): void
    {
        $product = Product::find($productId);
        if ($product) {
            $originalPrice = $product->base_price;
            $this->orderItems[$index]['original_price'] = $originalPrice;

            // Apply bookstore pricing if agent is selected
            if ($this->selectedAgent) {
                $discountedPrice = $this->selectedAgent->getPriceForProduct($productId, $this->orderItems[$index]['quantity']);
                if ($discountedPrice) {
                    $this->orderItems[$index]['unit_price'] = $discountedPrice;
                } else {
                    // Apply tier discount
                    $this->orderItems[$index]['unit_price'] = $this->selectedAgent->calculateTierPrice($originalPrice);
                }

                // Calculate discount percentage
                if ($originalPrice > 0) {
                    $this->orderItems[$index]['discount_percentage'] = round((($originalPrice - $this->orderItems[$index]['unit_price']) / $originalPrice) * 100, 1);
                }
            } else {
                $this->orderItems[$index]['unit_price'] = $originalPrice;
                $this->orderItems[$index]['discount_percentage'] = 0;
            }

            $this->orderItems[$index]['total_price'] = $this->orderItems[$index]['unit_price'] * $this->orderItems[$index]['quantity'];
        }
        $this->calculateTotals();
    }

    public function quantityUpdated(int $index): void
    {
        $quantity = $this->orderItems[$index]['quantity'];
        $productId = $this->orderItems[$index]['product_id'];

        // Re-check custom pricing for quantity-based pricing
        if ($this->selectedAgent && $productId) {
            $product = Product::find($productId);
            if ($product) {
                $originalPrice = $product->base_price;
                $discountedPrice = $this->selectedAgent->getPriceForProduct($productId, $quantity);

                if ($discountedPrice) {
                    $this->orderItems[$index]['unit_price'] = $discountedPrice;
                } else {
                    $this->orderItems[$index]['unit_price'] = $this->selectedAgent->calculateTierPrice($originalPrice);
                }

                if ($originalPrice > 0) {
                    $this->orderItems[$index]['discount_percentage'] = round((($originalPrice - $this->orderItems[$index]['unit_price']) / $originalPrice) * 100, 1);
                }
            }
        }

        $unitPrice = $this->orderItems[$index]['unit_price'];
        $this->orderItems[$index]['total_price'] = $quantity * $unitPrice;
        $this->calculateTotals();
    }

    public function unitPriceUpdated(int $index): void
    {
        $quantity = $this->orderItems[$index]['quantity'];
        $unitPrice = (float) $this->orderItems[$index]['unit_price'];
        $originalPrice = $this->orderItems[$index]['original_price'];

        // Recalculate discount percentage
        if ($originalPrice > 0) {
            $this->orderItems[$index]['discount_percentage'] = round((($originalPrice - $unitPrice) / $originalPrice) * 100, 1);
        }

        $this->orderItems[$index]['total_price'] = $quantity * $unitPrice;
        $this->calculateTotals();
    }

    public function calculateTotals(): void
    {
        $this->subtotal = array_sum(array_column($this->orderItems, 'total_price'));

        // Calculate total discount
        $originalTotal = 0;
        foreach ($this->orderItems as $item) {
            $originalTotal += ($item['original_price'] ?? 0) * ($item['quantity'] ?? 0);
        }
        $this->discountAmount = $originalTotal - $this->subtotal;

        $taxRate = $this->taxRate ?? 0;
        $shippingCost = $this->shippingCost ?? 0;
        $this->taxAmount = $this->subtotal * ($taxRate / 100);
        $this->total = $this->subtotal + $shippingCost + $this->taxAmount;
    }

    public function updatedTaxRate($value): void
    {
        if ($value === '' || $value === null) {
            $this->taxRate = null;
        }
        $this->calculateTotals();
    }

    public function updatedShippingCost($value): void
    {
        if ($value === '' || $value === null) {
            $this->shippingCost = null;
        } else {
            $this->shippingCost = round((float) $value, 2);
        }
        $this->calculateTotals();
    }

    public function selectAgent(int $agentId): void
    {
        $this->form['agent_id'] = $agentId;
        $this->selectedAgent = Agent::find($agentId);

        if ($this->selectedAgent) {
            $this->agentSearch = $this->selectedAgent->name . ' (' . $this->selectedAgent->agent_code . ')';

            // Recalculate prices for all items with new agent pricing
            foreach ($this->orderItems as $index => $item) {
                if ($item['product_id']) {
                    $this->productSelected($index, $item['product_id']);
                }
            }
        }

        $this->dispatch('close-dropdown');
    }

    public function clearAgentSelection(): void
    {
        $this->form['agent_id'] = '';
        $this->selectedAgent = null;
        $this->agentSearch = '';

        // Reset prices to original
        foreach ($this->orderItems as $index => $item) {
            if ($item['product_id']) {
                $this->orderItems[$index]['unit_price'] = $item['original_price'];
                $this->orderItems[$index]['discount_percentage'] = 0;
                $this->orderItems[$index]['total_price'] = $item['original_price'] * $item['quantity'];
            }
        }
        $this->calculateTotals();
    }

    public function createOrder(): void
    {
        $this->validate([
            'form.agent_id' => 'required|exists:agents,id',
            'orderItems' => 'required|array|min:1',
            'orderItems.*.product_id' => 'required|exists:products,id',
            'orderItems.*.warehouse_id' => 'required|exists:warehouses,id',
            'orderItems.*.quantity' => 'required|integer|min:1',
        ], [
            'form.agent_id.required' => 'Please select a bookstore.',
            'orderItems.required' => 'Please add at least one product.',
            'orderItems.*.product_id.required' => 'Please select a product.',
            'orderItems.*.warehouse_id.required' => 'Please select a warehouse.',
        ]);

        // Check credit limit
        $agent = Agent::find($this->form['agent_id']);
        if ($agent && $this->form['payment_method'] === 'credit') {
            if ($agent->wouldExceedCreditLimit($this->total)) {
                session()->flash('error', 'This order would exceed the bookstore\'s credit limit. Available credit: RM ' . number_format($agent->available_credit, 2));
                return;
            }
        }

        DB::transaction(function () use ($agent) {
            $shippingCost = $this->shippingCost ?? 0;
            $order = ProductOrder::create([
                'agent_id' => $agent->id,
                'customer_name' => $agent->name,
                'guest_email' => $agent->email,
                'customer_phone' => $agent->phone,
                'order_number' => 'KB-' . strtoupper(uniqid()),
                'status' => $this->form['order_status'],
                'payment_status' => $this->form['payment_status'],
                'currency' => 'MYR',
                'subtotal' => $this->subtotal,
                'shipping_cost' => $shippingCost,
                'tax_amount' => $this->taxAmount,
                'discount_amount' => $this->discountAmount,
                'total_amount' => $this->total,
                'customer_notes' => $this->form['notes'],
                'order_date' => now(),
                'required_delivery_date' => $this->form['required_delivery_date'] ?: null,
                'source' => 'kedai_buku',
                'order_type' => 'wholesale',
                'payment_method' => $this->form['payment_method'],
            ]);

            foreach ($this->orderItems as $item) {
                $product = Product::find($item['product_id']);

                ProductOrderItem::create([
                    'order_id' => $order->id,
                    'itemable_type' => Product::class,
                    'itemable_id' => $product->id,
                    'product_id' => $product->id,
                    'product_variant_id' => $item['product_variant_id'],
                    'warehouse_id' => $item['warehouse_id'],
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'quantity_ordered' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['total_price'],
                    'unit_cost' => $product->cost_price ?? 0,
                    'product_snapshot' => $product->toArray(),
                ]);
            }

            // Create billing address from agent
            if ($agent->address || $agent->contact_person) {
                $order->addresses()->create([
                    'type' => 'billing',
                    'first_name' => $agent->contact_person ?: $agent->name,
                    'last_name' => '',
                    'company' => $agent->company_name,
                    'email' => $agent->email,
                    'phone' => $agent->phone,
                    'address_line_1' => $agent->address['street'] ?? '',
                    'city' => $agent->address['city'] ?? '',
                    'state' => $agent->address['state'] ?? '',
                    'postal_code' => $agent->address['postal_code'] ?? '',
                    'country' => $agent->address['country'] ?? 'Malaysia',
                ]);
            }

            // Create payment record
            $order->payments()->create([
                'payment_method' => $this->form['payment_method'],
                'amount' => $this->total,
                'currency' => 'MYR',
                'status' => $this->form['payment_status'],
                'paid_at' => $this->form['payment_status'] === 'paid' ? now() : null,
            ]);

            $order->addSystemNote('Kedai Buku order created. Tier: ' . ucfirst($agent->pricing_tier) . '. Total discount: RM ' . number_format($this->discountAmount, 2));

            session()->flash('success', 'Pesanan kedai buku berjaya dibuat!');
            $this->redirectRoute('kedai-buku.orders.show', $order);
        });
    }

    public function with(): array
    {
        $agentsQuery = Agent::active()->where('type', 'bookstore');

        if ($this->agentSearch && ! $this->form['agent_id']) {
            $agentsQuery->where(function ($query) {
                $query->where('name', 'like', '%' . $this->agentSearch . '%')
                    ->orWhere('agent_code', 'like', '%' . $this->agentSearch . '%')
                    ->orWhere('company_name', 'like', '%' . $this->agentSearch . '%')
                    ->orWhere('email', 'like', '%' . $this->agentSearch . '%');
            });
        }

        return [
            'products' => Product::active()->get(),
            'warehouses' => Warehouse::all(),
            'agents' => $agentsQuery->orderBy('name')->limit(50)->get(),
        ];
    }
}; ?>

<div>
    <!-- Header -->
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Buat Pesanan Kedai Buku</flux:heading>
            <flux:text class="mt-2">Create a new wholesale order for bookstore</flux:text>
        </div>
        <flux:button variant="outline" :href="route('agents-kedai-buku.orders.index')" wire:navigate>
            <div class="flex items-center justify-center">
                <flux:icon name="arrow-left" class="w-4 h-4 mr-2" />
                Kembali ke Senarai
            </div>
        </flux:button>
    </div>

    @if(session('error'))
        <div class="mb-6 p-4 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-lg">
            <div class="flex items-center gap-2">
                <flux:icon name="exclamation-triangle" class="w-5 h-5 text-red-600 dark:text-red-400" />
                <span class="text-red-700 dark:text-red-300">{{ session('error') }}</span>
            </div>
        </div>
    @endif

    <form wire:submit="createOrder">
        <div class="grid lg:grid-cols-3 gap-6">
            <!-- Left Column - Order Details -->
            <div class="lg:col-span-2 space-y-6">

                <!-- Bookstore Selection -->
                <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                    <flux:heading size="lg" class="mb-4">Pilih Kedai Buku</flux:heading>

                    <div class="space-y-4">
                        <!-- Searchable Agent Selection -->
                        <div class="space-y-2" x-data="agentSearchComponent()">
                            <flux:label>Kedai Buku</flux:label>
                            <div class="relative">
                                <input
                                    type="text"
                                    x-model="search"
                                    @input.debounce.300ms="$wire.set('agentSearch', search)"
                                    @focus="showDropdown = true"
                                    placeholder="Cari nama, kod, atau email..."
                                    class="w-full rounded-lg border-gray-300 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:focus:border-indigo-400 dark:focus:ring-indigo-400 pr-10"
                                />
                                <template x-if="!$wire.form.agent_id">
                                    <flux:icon name="magnifying-glass" class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
                                </template>
                                <template x-if="$wire.form.agent_id">
                                    <button
                                        type="button"
                                        @click="clearSelection()"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 z-10"
                                    >
                                        <flux:icon name="x-mark" class="w-4 h-4" />
                                    </button>
                                </template>

                                <!-- Dropdown Results -->
                                <div
                                    x-show="showDropdown && search.length > 0 && !$wire.form.agent_id"
                                    @click.away="showDropdown = false"
                                    x-transition
                                    class="absolute z-50 w-full mt-1 bg-white dark:bg-zinc-800 border border-gray-300 dark:border-zinc-600 rounded-lg shadow-lg max-h-60 overflow-y-auto"
                                    style="display: none;"
                                >
                                    @if($agents->count() > 0)
                                        <ul class="py-1">
                                            @foreach($agents as $agent)
                                                <li>
                                                    <button
                                                        type="button"
                                                        @click="selectAgent({{ $agent->id }}, @js($agent->name), @js($agent->agent_code), @js($agent->company_name ?? ''))"
                                                        class="w-full text-left px-4 py-2 hover:bg-gray-100 dark:hover:bg-zinc-700 transition-colors"
                                                    >
                                                        <div class="flex flex-col">
                                                            <div class="flex items-center gap-2">
                                                                <span class="font-medium text-gray-900 dark:text-zinc-100">{{ $agent->name }}</span>
                                                                <span class="text-xs px-2 py-0.5 bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300 rounded">{{ $agent->agent_code }}</span>
                                                                <span class="text-xs px-2 py-0.5 bg-purple-100 dark:bg-purple-900 text-purple-700 dark:text-purple-300 rounded">{{ ucfirst($agent->pricing_tier ?? 'standard') }}</span>
                                                            </div>
                                                            @if($agent->company_name)
                                                                <span class="text-sm text-gray-600 dark:text-zinc-400">{{ $agent->company_name }}</span>
                                                            @endif
                                                            <div class="flex items-center gap-3 text-xs text-gray-500 dark:text-zinc-500 mt-1">
                                                                <span>Credit: RM {{ number_format($agent->available_credit, 2) }}</span>
                                                                <span>Discount: {{ $agent->getTierDiscountPercentage() }}%</span>
                                                            </div>
                                                        </div>
                                                    </button>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <div class="px-4 py-3 text-sm text-gray-500 dark:text-zinc-400">
                                            Tiada kedai buku dijumpai untuk "{{ $agentSearch }}"
                                        </div>
                                    @endif
                                </div>
                            </div>
                            @error('form.agent_id')
                                <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Selected Bookstore Details -->
                        @if($selectedAgent)
                            <div class="mt-4 p-4 bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-lg">
                                <div class="flex items-center justify-between mb-2">
                                    <flux:text class="font-semibold text-blue-900 dark:text-blue-100">Maklumat Kedai Buku</flux:text>
                                    <flux:badge variant="info" size="sm">{{ ucfirst($selectedAgent->pricing_tier ?? 'standard') }} Tier</flux:badge>
                                </div>
                                <div class="grid md:grid-cols-2 gap-3 text-sm">
                                    <div>
                                        <span class="text-blue-700 dark:text-blue-300">Kod:</span>
                                        <span class="font-medium text-blue-900 dark:text-blue-100">{{ $selectedAgent->agent_code }}</span>
                                    </div>
                                    <div>
                                        <span class="text-blue-700 dark:text-blue-300">Diskaun Tier:</span>
                                        <span class="font-medium text-blue-900 dark:text-blue-100">{{ $selectedAgent->getTierDiscountPercentage() }}%</span>
                                    </div>
                                    @if($selectedAgent->contact_person)
                                        <div>
                                            <span class="text-blue-700 dark:text-blue-300">Pengurus:</span>
                                            <span class="font-medium text-blue-900 dark:text-blue-100">{{ $selectedAgent->contact_person }}</span>
                                        </div>
                                    @endif
                                    @if($selectedAgent->phone)
                                        <div>
                                            <span class="text-blue-700 dark:text-blue-300">Telefon:</span>
                                            <span class="font-medium text-blue-900 dark:text-blue-100">{{ $selectedAgent->phone }}</span>
                                        </div>
                                    @endif
                                    <div>
                                        <span class="text-blue-700 dark:text-blue-300">Had Kredit:</span>
                                        <span class="font-medium text-blue-900 dark:text-blue-100">RM {{ number_format($selectedAgent->credit_limit, 2) }}</span>
                                    </div>
                                    <div>
                                        <span class="text-blue-700 dark:text-blue-300">Kredit Tersedia:</span>
                                        <span class="font-medium {{ $selectedAgent->available_credit <= 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                                            RM {{ number_format($selectedAgent->available_credit, 2) }}
                                        </span>
                                    </div>
                                </div>

                                @if($selectedAgent->available_credit < $total && $form['payment_method'] === 'credit')
                                    <div class="mt-3 p-2 bg-red-100 dark:bg-red-900/50 border border-red-200 dark:border-red-700 rounded text-sm text-red-700 dark:text-red-300">
                                        <flux:icon name="exclamation-triangle" class="w-4 h-4 inline mr-1" />
                                        Amaran: Jumlah pesanan melebihi kredit yang tersedia
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Order Items -->
                <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <flux:heading size="lg">Produk</flux:heading>
                        <flux:button type="button" wire:click="addOrderItem" size="sm">
                            <div class="flex items-center justify-center">
                                <flux:icon name="plus" class="w-4 h-4 mr-1" />
                                Tambah Produk
                            </div>
                        </flux:button>
                    </div>

                    <div class="space-y-4">
                        @foreach($orderItems as $index => $item)
                            <div class="border border-gray-200 dark:border-zinc-700 rounded-lg p-4" wire:key="item-{{ $index }}">
                                <div class="grid md:grid-cols-6 gap-4 items-end">
                                    <!-- Product Selection -->
                                    <flux:field class="md:col-span-2">
                                        <flux:label>Produk</flux:label>
                                        <flux:select wire:model.live="orderItems.{{ $index }}.product_id" wire:change="productSelected({{ $index }}, $event.target.value)">
                                            <option value="">Pilih Produk...</option>
                                            @foreach($products as $product)
                                                <option value="{{ $product->id }}">{{ $product->name }} (RM {{ number_format($product->base_price, 2) }})</option>
                                            @endforeach
                                        </flux:select>
                                        @error('orderItems.' . $index . '.product_id')
                                            <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                        @enderror
                                    </flux:field>

                                    <!-- Warehouse -->
                                    <flux:field>
                                        <flux:label>Gudang</flux:label>
                                        <flux:select wire:model="orderItems.{{ $index }}.warehouse_id">
                                            <option value="">Pilih...</option>
                                            @foreach($warehouses as $warehouse)
                                                <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                            @endforeach
                                        </flux:select>
                                        @error('orderItems.' . $index . '.warehouse_id')
                                            <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                        @enderror
                                    </flux:field>

                                    <!-- Quantity -->
                                    <flux:field>
                                        <flux:label>Kuantiti</flux:label>
                                        <flux:input wire:model.live="orderItems.{{ $index }}.quantity"
                                                   wire:change="quantityUpdated({{ $index }})"
                                                   type="number" min="1" />
                                        @error('orderItems.' . $index . '.quantity')
                                            <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                        @enderror
                                    </flux:field>

                                    <!-- Unit Price -->
                                    <flux:field>
                                        <flux:label>Harga Unit</flux:label>
                                        <flux:input wire:model.live="orderItems.{{ $index }}.unit_price"
                                                   wire:change="unitPriceUpdated({{ $index }})"
                                                   type="number" step="0.01" min="0" />
                                    </flux:field>

                                    <!-- Discount Badge -->
                                    <div class="flex items-end pb-2">
                                        @if($item['discount_percentage'] > 0)
                                            <flux:badge variant="success" size="sm">-{{ $item['discount_percentage'] }}%</flux:badge>
                                        @endif
                                    </div>
                                </div>

                                <div class="mt-3 flex items-center justify-between">
                                    <div class="flex items-center gap-4">
                                        @if($item['total_price'] > 0)
                                            <flux:text class="font-medium">Jumlah: RM {{ number_format($item['total_price'], 2) }}</flux:text>
                                        @endif
                                        @if($item['original_price'] > 0 && $item['original_price'] != $item['unit_price'])
                                            <flux:text class="text-sm text-gray-500 dark:text-zinc-400 line-through">
                                                Asal: RM {{ number_format($item['original_price'] * $item['quantity'], 2) }}
                                            </flux:text>
                                        @endif
                                    </div>

                                    @if(count($orderItems) > 1)
                                        <flux:button type="button" wire:click="removeOrderItem({{ $index }})"
                                                   variant="outline" size="sm" class="text-red-600 dark:text-red-400 border-red-200 dark:border-red-800 hover:bg-red-50 dark:hover:bg-red-900/30">
                                            <flux:icon name="trash" class="w-4 h-4" />
                                        </flux:button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Order Notes -->
                <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                    <flux:heading size="lg" class="mb-4">Nota / Catatan</flux:heading>
                    <flux:field>
                        <flux:textarea wire:model="form.notes" rows="4" placeholder="Sebarang arahan khas atau nota untuk pesanan ini..." />
                    </flux:field>
                </div>
            </div>

            <!-- Right Column - Order Summary -->
            <div class="lg:col-span-1">
                <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6 sticky top-6">
                    <flux:heading size="lg" class="mb-4">Ringkasan Pesanan</flux:heading>

                    <!-- Order Status Settings -->
                    <div class="space-y-4 mb-6">
                        <flux:field>
                            <flux:label>Status Pesanan</flux:label>
                            <flux:select wire:model="form.order_status">
                                <option value="draft">Draf</option>
                                <option value="pending">Menunggu</option>
                                <option value="processing">Diproses</option>
                                <option value="shipped">Dihantar</option>
                                <option value="delivered">Diterima</option>
                            </flux:select>
                        </flux:field>

                        <flux:field>
                            <flux:label>Status Pembayaran</flux:label>
                            <flux:select wire:model="form.payment_status">
                                <option value="pending">Menunggu</option>
                                <option value="partial">Separa</option>
                                <option value="paid">Dibayar</option>
                            </flux:select>
                        </flux:field>

                        <flux:field>
                            <flux:label>Kaedah Pembayaran</flux:label>
                            <flux:select wire:model="form.payment_method">
                                <option value="credit">Kredit (Terma Kedai Buku)</option>
                                <option value="cash">Tunai</option>
                                <option value="bank_transfer">Pemindahan Bank</option>
                            </flux:select>
                        </flux:field>

                        <flux:field>
                            <flux:label>Tarikh Penghantaran Diperlukan</flux:label>
                            <flux:input type="date" wire:model="form.required_delivery_date" />
                        </flux:field>
                    </div>

                    <!-- Delivery Fees -->
                    <div class="border-t border-gray-200 dark:border-zinc-700 pt-4">
                        <flux:field>
                            <flux:label>Kos Penghantaran (RM)</flux:label>
                            <flux:input
                                type="text"
                                inputmode="decimal"
                                wire:model.live.debounce.500ms="shippingCost"
                                placeholder="0.00"
                            />
                        </flux:field>
                    </div>

                    <!-- Tax Configuration -->
                    <div class="border-t border-gray-200 dark:border-zinc-700 pt-4">
                        <flux:field>
                            <flux:label>Kadar Cukai (%)</flux:label>
                            <flux:input
                                type="number"
                                step="0.01"
                                min="0"
                                max="100"
                                wire:model.live="taxRate"
                                placeholder="0"
                            />
                        </flux:field>
                    </div>

                    <!-- Order Totals -->
                    <div class="space-y-3 border-t border-gray-200 dark:border-zinc-700 pt-4 mt-4">
                        <div class="flex justify-between">
                            <flux:text>Jumlah Kecil</flux:text>
                            <flux:text>RM {{ number_format($subtotal, 2) }}</flux:text>
                        </div>

                        @if($discountAmount > 0)
                            <div class="flex justify-between text-green-600 dark:text-green-400">
                                <flux:text>Diskaun</flux:text>
                                <flux:text>- RM {{ number_format($discountAmount, 2) }}</flux:text>
                            </div>
                        @endif

                        @if(($shippingCost ?? 0) > 0)
                            <div class="flex justify-between">
                                <flux:text>Penghantaran</flux:text>
                                <flux:text>RM {{ number_format($shippingCost ?? 0, 2) }}</flux:text>
                            </div>
                        @endif

                        @if(($taxRate ?? 0) > 0)
                            <div class="flex justify-between">
                                <flux:text>Cukai ({{ number_format($taxRate ?? 0, 1) }}%)</flux:text>
                                <flux:text>RM {{ number_format($taxAmount, 2) }}</flux:text>
                            </div>
                        @endif

                        <div class="border-t border-gray-200 dark:border-zinc-700 pt-3">
                            <div class="flex justify-between">
                                <flux:text class="font-semibold text-lg">Jumlah</flux:text>
                                <flux:text class="font-semibold text-lg text-blue-600 dark:text-blue-400">RM {{ number_format($total, 2) }}</flux:text>
                            </div>
                        </div>
                    </div>

                    <!-- Create Order Button -->
                    <div class="mt-6">
                        <flux:button type="submit" variant="primary" class="w-full">
                            Buat Pesanan
                        </flux:button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <!-- Alpine.js Component Definition -->
    <script>
        function agentSearchComponent() {
            return {
                search: @entangle('agentSearch').live,
                showDropdown: false,
                selectAgent(id, name, code, company) {
                    this.$wire.selectAgent(id);
                    this.search = name + ' (' + code + ')' + (company ? ' - ' + company : '');
                    this.showDropdown = false;
                },
                clearSelection() {
                    this.$wire.clearAgentSelection();
                    this.search = '';
                    this.showDropdown = true;
                }
            }
        }
    </script>
</div>
