<?php

use App\Models\Agent;
use App\Models\AgentPricing;
use App\Models\Package;
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
        'order_date' => '', // For keying in older order records

        'billing_address' => [
            'first_name' => '',
            'last_name' => '',
            'company' => '',
            'address_line_1' => '',
            'address_line_2' => '',
            'city' => '',
            'state' => '',
            'postal_code' => '',
            'country' => 'Malaysia',
        ],

        'payment_method' => 'cash',
        'payment_status' => 'pending',
        'order_status' => 'pending',
        'notes' => '',
    ];

    public array $orderItems = [];

    public float $subtotal = 0;

    public ?float $shippingCost = 0;

    public ?float $taxRate = 0;

    public float $taxAmount = 0;

    public float $total = 0;

    public float $totalWeight = 0;

    public string $agentSearch = '';

    public ?Agent $selectedAgent = null;

    public function mount(): void
    {
        $this->form['order_date'] = now()->format('Y-m-d\TH:i'); // Default to current datetime
        $this->addOrderItem();
    }

    public function addOrderItem(): void
    {
        $this->orderItems[] = [
            'item_type' => 'product', // 'product' or 'package'
            'product_id' => '',
            'package_id' => '',
            'product_variant_id' => null,
            'warehouse_id' => '',
            'quantity' => 1,
            'base_price' => 0, // Original price before discount
            'unit_price' => 0, // Price after discount
            'total_price' => 0,
            'pricing_type' => '', // 'custom', 'tier', or 'base'
            'discount_percent' => 0, // Discount percentage applied
            'weight' => 0, // Product weight in kg
            'total_weight' => 0, // Total weight for this item (weight * quantity)
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

    public function itemTypeChanged(int $index): void
    {
        // Reset item data when type changes
        $this->orderItems[$index]['product_id'] = '';
        $this->orderItems[$index]['package_id'] = '';
        $this->orderItems[$index]['base_price'] = 0;
        $this->orderItems[$index]['unit_price'] = 0;
        $this->orderItems[$index]['total_price'] = 0;
        $this->orderItems[$index]['pricing_type'] = '';
        $this->orderItems[$index]['discount_percent'] = 0;
        $this->orderItems[$index]['weight'] = 0;
        $this->orderItems[$index]['total_weight'] = 0;
        $this->calculateTotals();
    }

    public function productSelected(int $index, $productId): void
    {
        if (empty($productId)) {
            return;
        }

        $product = Product::find($productId);
        if (! $product) {
            return;
        }

        $basePrice = $product->base_price;
        $quantity = $this->orderItems[$index]['quantity'] ?? 1;

        // Store the base price
        $this->orderItems[$index]['base_price'] = $basePrice;

        // Store the weight
        $weight = (float) ($product->weight ?? 0);
        $this->orderItems[$index]['weight'] = $weight;
        $this->orderItems[$index]['total_weight'] = $weight * $quantity;

        // Calculate the discounted price based on agent's pricing
        $priceInfo = $this->calculateAgentPrice($product->id, $basePrice, $quantity);

        $this->orderItems[$index]['unit_price'] = $priceInfo['price'];
        $this->orderItems[$index]['pricing_type'] = $priceInfo['type'];
        $this->orderItems[$index]['discount_percent'] = $priceInfo['discount'];
        $this->orderItems[$index]['total_price'] = $priceInfo['price'] * $quantity;

        $this->calculateTotals();
    }

    public function packageSelected(int $index, $packageId): void
    {
        if (empty($packageId)) {
            return;
        }

        $package = Package::with('products')->find($packageId);
        if (! $package) {
            return;
        }

        $basePrice = $package->price;
        $quantity = $this->orderItems[$index]['quantity'] ?? 1;

        // Store the base price
        $this->orderItems[$index]['base_price'] = $basePrice;

        // Calculate total weight from all products in the package
        $packageWeight = 0;
        foreach ($package->products as $product) {
            $productWeight = (float) ($product->weight ?? 0);
            $productQuantity = $product->pivot->quantity ?? 1;
            $packageWeight += $productWeight * $productQuantity;
        }
        $this->orderItems[$index]['weight'] = $packageWeight;
        $this->orderItems[$index]['total_weight'] = $packageWeight * $quantity;

        // For packages, apply tier discount only (no custom pricing for packages)
        $priceInfo = $this->calculateAgentTierPrice($basePrice);

        $this->orderItems[$index]['unit_price'] = $priceInfo['price'];
        $this->orderItems[$index]['pricing_type'] = $priceInfo['type'];
        $this->orderItems[$index]['discount_percent'] = $priceInfo['discount'];
        $this->orderItems[$index]['total_price'] = $priceInfo['price'] * $quantity;

        // Set default warehouse from package if not set
        if (empty($this->orderItems[$index]['warehouse_id']) && $package->default_warehouse_id) {
            $this->orderItems[$index]['warehouse_id'] = $package->default_warehouse_id;
        }

        $this->calculateTotals();
    }

    /**
     * Calculate agent price for a product - checks custom pricing first, then tier discount.
     */
    private function calculateAgentPrice(int $productId, float $basePrice, int $quantity = 1): array
    {
        if (! $this->selectedAgent) {
            return ['price' => $basePrice, 'type' => 'base', 'discount' => 0];
        }

        // First check for custom pricing
        $customPrice = AgentPricing::where('agent_id', $this->selectedAgent->id)
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->where('min_quantity', '<=', $quantity)
            ->orderBy('min_quantity', 'desc')
            ->first();

        if ($customPrice) {
            $discount = $basePrice > 0 ? round((1 - ($customPrice->price / $basePrice)) * 100, 1) : 0;
            return [
                'price' => (float) $customPrice->price,
                'type' => 'custom',
                'discount' => $discount,
            ];
        }

        // Fall back to tier discount
        return $this->calculateAgentTierPrice($basePrice);
    }

    /**
     * Calculate tier-based discount for the selected agent.
     */
    private function calculateAgentTierPrice(float $basePrice): array
    {
        if (! $this->selectedAgent) {
            return ['price' => $basePrice, 'type' => 'base', 'discount' => 0];
        }

        $discountPercent = $this->selectedAgent->getTierDiscountPercentage();
        $discountedPrice = $basePrice * (1 - ($discountPercent / 100));

        return [
            'price' => round($discountedPrice, 2),
            'type' => 'tier',
            'discount' => $discountPercent,
        ];
    }

    public function quantityUpdated(int $index): void
    {
        $quantity = (int) ($this->orderItems[$index]['quantity'] ?? 1);
        $productId = $this->orderItems[$index]['product_id'] ?? null;
        $basePrice = $this->orderItems[$index]['base_price'] ?? 0;
        $weight = $this->orderItems[$index]['weight'] ?? 0;

        // Recalculate price if product selected (quantity-based custom pricing may change)
        if ($this->orderItems[$index]['item_type'] === 'product' && $productId && $basePrice > 0) {
            $priceInfo = $this->calculateAgentPrice((int) $productId, $basePrice, $quantity);
            $this->orderItems[$index]['unit_price'] = $priceInfo['price'];
            $this->orderItems[$index]['pricing_type'] = $priceInfo['type'];
            $this->orderItems[$index]['discount_percent'] = $priceInfo['discount'];
        }

        $unitPrice = $this->orderItems[$index]['unit_price'];
        $this->orderItems[$index]['total_price'] = $quantity * $unitPrice;
        $this->orderItems[$index]['total_weight'] = $quantity * $weight;
        $this->calculateTotals();
    }

    public function unitPriceUpdated(int $index): void
    {
        $quantity = $this->orderItems[$index]['quantity'];
        $unitPrice = (float) $this->orderItems[$index]['unit_price'];
        $this->orderItems[$index]['total_price'] = $quantity * $unitPrice;
        $this->calculateTotals();
    }

    public function calculateTotals(): void
    {
        $this->subtotal = array_sum(array_column($this->orderItems, 'total_price'));
        $this->totalWeight = array_sum(array_column($this->orderItems, 'total_weight'));
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

            // Fill billing address from agent
            $this->form['billing_address']['first_name'] = $this->selectedAgent->contact_person ?: $this->selectedAgent->name;
            $this->form['billing_address']['company'] = $this->selectedAgent->company_name ?? '';

            if (is_array($this->selectedAgent->address)) {
                $this->form['billing_address']['address_line_1'] = $this->selectedAgent->address['street'] ?? '';
                $this->form['billing_address']['city'] = $this->selectedAgent->address['city'] ?? '';
                $this->form['billing_address']['state'] = $this->selectedAgent->address['state'] ?? '';
                $this->form['billing_address']['postal_code'] = $this->selectedAgent->address['postal_code'] ?? '';
                $this->form['billing_address']['country'] = $this->selectedAgent->address['country'] ?? 'Malaysia';
            }

            // Recalculate all item prices with new agent's pricing
            $this->recalculateAllItemPrices();
        }

        $this->dispatch('close-dropdown');
    }

    /**
     * Recalculate all order item prices when agent changes.
     */
    private function recalculateAllItemPrices(): void
    {
        foreach ($this->orderItems as $index => $item) {
            $basePrice = $item['base_price'] ?? 0;
            $quantity = $item['quantity'] ?? 1;

            if ($basePrice > 0) {
                if ($item['item_type'] === 'product' && ! empty($item['product_id'])) {
                    $priceInfo = $this->calculateAgentPrice((int) $item['product_id'], $basePrice, $quantity);
                } else {
                    // For packages, apply tier discount only
                    $priceInfo = $this->calculateAgentTierPrice($basePrice);
                }

                $this->orderItems[$index]['unit_price'] = $priceInfo['price'];
                $this->orderItems[$index]['pricing_type'] = $priceInfo['type'];
                $this->orderItems[$index]['discount_percent'] = $priceInfo['discount'];
                $this->orderItems[$index]['total_price'] = $priceInfo['price'] * $quantity;
            }
        }

        $this->calculateTotals();
    }

    public function clearAgentSelection(): void
    {
        $this->form['agent_id'] = '';
        $this->selectedAgent = null;
        $this->agentSearch = '';

        // Reset billing address
        $this->form['billing_address'] = [
            'first_name' => '',
            'last_name' => '',
            'company' => '',
            'address_line_1' => '',
            'address_line_2' => '',
            'city' => '',
            'state' => '',
            'postal_code' => '',
            'country' => 'Malaysia',
        ];

        // Recalculate prices to remove agent discounts
        $this->recalculateAllItemPrices();
    }

    public function createOrder(): void
    {
        $this->validate([
            'form.agent_id' => 'required|exists:agents,id',
            'form.billing_address.first_name' => 'required|string|max:255',
            'form.billing_address.address_line_1' => 'required|string|max:255',
            'form.billing_address.city' => 'required|string|max:255',
            'form.billing_address.state' => 'required|string|max:255',
            'form.billing_address.postal_code' => 'required|string|max:20',
            'orderItems' => 'required|array|min:1',
            'orderItems.*.item_type' => 'required|in:product,package',
            'orderItems.*.warehouse_id' => 'required|exists:warehouses,id',
            'orderItems.*.quantity' => 'required|integer|min:1',
        ], [
            'form.agent_id.required' => 'Please select an agent.',
            'form.billing_address.first_name.required' => 'Contact name is required.',
            'form.billing_address.address_line_1.required' => 'Address is required.',
            'form.billing_address.city.required' => 'City is required.',
            'form.billing_address.state.required' => 'State is required.',
            'form.billing_address.postal_code.required' => 'Postal code is required.',
            'orderItems.required' => 'Please add at least one item.',
            'orderItems.*.warehouse_id.required' => 'Please select a warehouse.',
        ]);

        // Additional validation based on item type
        foreach ($this->orderItems as $index => $item) {
            if ($item['item_type'] === 'product') {
                $this->validate([
                    "orderItems.{$index}.product_id" => 'required|exists:products,id',
                ], [
                    "orderItems.{$index}.product_id.required" => 'Please select a product.',
                ]);
            } elseif ($item['item_type'] === 'package') {
                $this->validate([
                    "orderItems.{$index}.package_id" => 'required|exists:packages,id',
                ], [
                    "orderItems.{$index}.package_id.required" => 'Please select a package.',
                ]);
            }
        }

        // Validate stock availability
        $stockErrors = $this->validateStockAvailability();
        if (! empty($stockErrors)) {
            $errorMessage = "Insufficient stock for the following items:\n";
            foreach ($stockErrors as $error) {
                $errorMessage .= "• {$error['product_name']}: Need {$error['needed']}, Available {$error['available']} (Shortage: {$error['shortage']})\n";
            }
            session()->flash('error', $errorMessage);

            return;
        }

        DB::transaction(function () {
            $agent = Agent::find($this->form['agent_id']);

            // Parse the order date from form input
            $orderDate = $this->form['order_date']
                ? \Carbon\Carbon::parse($this->form['order_date'])
                : now();

            $shippingCost = $this->shippingCost ?? 0;
            $order = ProductOrder::create([
                'agent_id' => $agent->id,
                'customer_name' => $agent->name,
                'guest_email' => $agent->email,
                'customer_phone' => $agent->phone,
                'order_number' => 'AGT-' . strtoupper(uniqid()),
                'status' => $this->form['order_status'],
                'currency' => 'MYR',
                'subtotal' => $this->subtotal,
                'shipping_cost' => $shippingCost,
                'tax_amount' => $this->taxAmount,
                'total_amount' => $this->total,
                'customer_notes' => $this->form['notes'],
                'order_date' => $orderDate,
                'created_at' => $orderDate, // Also set created_at for older records
            ]);

            // Create order items
            foreach ($this->orderItems as $item) {
                if ($item['item_type'] === 'package' && ! empty($item['package_id'])) {
                    $package = Package::with(['products', 'courses'])->find($item['package_id']);

                    $orderItem = ProductOrderItem::create([
                        'order_id' => $order->id,
                        'itemable_type' => Package::class,
                        'itemable_id' => $package->id,
                        'package_id' => $package->id,
                        'warehouse_id' => $item['warehouse_id'],
                        'product_name' => $package->name,
                        'sku' => 'PKG-' . $package->id,
                        'quantity_ordered' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'total_price' => $item['total_price'],
                        'unit_cost' => 0,
                        'package_snapshot' => $package->toArray(),
                        'package_items_snapshot' => $package->items->toArray(),
                    ]);

                    // Deduct stock for package products
                    $orderItem->deductStock();

                } elseif ($item['item_type'] === 'product' && ! empty($item['product_id'])) {
                    $product = Product::find($item['product_id']);

                    $orderItem = ProductOrderItem::create([
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

                    // Deduct stock for product
                    $orderItem->deductStock();
                }
            }

            // Create billing address
            $order->addresses()->create([
                'type' => 'billing',
                'first_name' => $this->form['billing_address']['first_name'],
                'last_name' => $this->form['billing_address']['last_name'],
                'company' => $this->form['billing_address']['company'],
                'email' => $agent->email,
                'phone' => $agent->phone,
                'address_line_1' => $this->form['billing_address']['address_line_1'],
                'address_line_2' => $this->form['billing_address']['address_line_2'],
                'city' => $this->form['billing_address']['city'],
                'state' => $this->form['billing_address']['state'],
                'postal_code' => $this->form['billing_address']['postal_code'],
                'country' => $this->form['billing_address']['country'],
            ]);

            // Create payment record
            $order->payments()->create([
                'payment_method' => $this->form['payment_method'],
                'amount' => $this->total,
                'currency' => 'MYR',
                'status' => $this->form['payment_status'],
                'paid_at' => $this->form['payment_status'] === 'completed' ? now() : null,
            ]);

            $order->addSystemNote('Agent order created manually');

            session()->flash('success', 'Agent order created successfully!');
            $this->redirectRoute('agent-orders.show', $order);
        });
    }

    private function validateStockAvailability(): array
    {
        $allStockErrors = [];

        foreach ($this->orderItems as $item) {
            if ($item['item_type'] === 'package' && ! empty($item['package_id'])) {
                $package = Package::with('products')->find($item['package_id']);

                if ($package && $package->track_stock) {
                    $warehouseId = $item['warehouse_id'] ?? $package->default_warehouse_id;

                    foreach ($package->products as $product) {
                        $quantityPerPackage = $product->pivot->quantity;
                        $totalQuantityNeeded = $quantityPerPackage * $item['quantity'];
                        $productWarehouseId = $warehouseId ?? $product->pivot->warehouse_id;

                        $stockLevel = $product->stockLevels()
                            ->where('warehouse_id', $productWarehouseId)
                            ->first();

                        $availableStock = $stockLevel ? $stockLevel->quantity : 0;

                        if ($availableStock < $totalQuantityNeeded) {
                            $allStockErrors[] = [
                                'product_name' => $product->name . ' (in ' . $package->name . ')',
                                'needed' => $totalQuantityNeeded,
                                'available' => $availableStock,
                                'shortage' => $totalQuantityNeeded - $availableStock,
                            ];
                        }
                    }
                }
            } elseif ($item['item_type'] === 'product' && ! empty($item['product_id'])) {
                $product = Product::find($item['product_id']);

                if ($product && $product->shouldTrackQuantity()) {
                    $stockLevel = $product->stockLevels()
                        ->where('warehouse_id', $item['warehouse_id'])
                        ->first();

                    $availableStock = $stockLevel ? $stockLevel->quantity : 0;

                    if ($availableStock < $item['quantity']) {
                        $allStockErrors[] = [
                            'product_name' => $product->name,
                            'needed' => $item['quantity'],
                            'available' => $availableStock,
                            'shortage' => $item['quantity'] - $availableStock,
                        ];
                    }
                }
            }
        }

        return $allStockErrors;
    }

    public function with(): array
    {
        $agentsQuery = Agent::active();

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
            'packages' => Package::active()->with(['products', 'courses'])->get(),
            'warehouses' => Warehouse::all(),
            'agents' => $agentsQuery->orderBy('name')->limit(50)->get(),
        ];
    }
}; ?>

<div>
    <!-- Header -->
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Create Agent Order</flux:heading>
            <flux:text class="mt-2">Create a new order for agent or company</flux:text>
        </div>
        <flux:button variant="outline" :href="route('agent-orders.index')" wire:navigate>
            <div class="flex items-center">
                <flux:icon name="arrow-left" class="w-4 h-4 mr-2" />
                Back to List
            </div>
        </flux:button>
    </div>

    @if(session('error'))
        <div class="mb-6 p-4 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-lg">
            <div class="flex items-start">
                <flux:icon name="exclamation-circle" class="w-5 h-5 text-red-600 dark:text-red-400 mr-3 mt-0.5 flex-shrink-0" />
                <div class="text-sm text-red-700 dark:text-red-300 whitespace-pre-line">{{ session('error') }}</div>
            </div>
        </div>
    @endif

    <form wire:submit="createOrder">
        <div class="grid lg:grid-cols-3 gap-6">
            <!-- Left Column - Order Details -->
            <div class="lg:col-span-2 space-y-6">

                <!-- Agent Selection -->
                <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                    <flux:heading size="lg" class="mb-4">Agent Information</flux:heading>

                    <div class="space-y-4">
                        <!-- Searchable Agent Selection -->
                        <div class="space-y-2" x-data="agentSearchComponent()">
                            <flux:label>Select Agent / Company</flux:label>
                            <div class="relative">
                                <input
                                    type="text"
                                    x-model="search"
                                    @input.debounce.300ms="$wire.set('agentSearch', search)"
                                    @focus="showDropdown = true"
                                    placeholder="Search by name, code, company, or email..."
                                    class="w-full pr-10 py-3 text-base rounded-lg border-gray-300 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:focus:border-indigo-400 dark:focus:ring-indigo-400"
                                />
                                <template x-if="!$wire.form.agent_id">
                                    <flux:icon name="magnifying-glass" class="absolute right-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 dark:text-zinc-500 pointer-events-none" />
                                </template>
                                <template x-if="$wire.form.agent_id">
                                    <button
                                        type="button"
                                        @click="clearSelection()"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500 z-10 transition-colors"
                                    >
                                        <flux:icon name="x-circle" class="w-5 h-5" />
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
                                                                @if($agent->type)
                                                                    <span class="text-xs px-2 py-0.5 bg-gray-100 dark:bg-zinc-700 text-gray-600 dark:text-zinc-300 rounded">{{ ucfirst($agent->type) }}</span>
                                                                @endif
                                                            </div>
                                                            @if($agent->company_name)
                                                                <span class="text-sm text-gray-600 dark:text-zinc-400">{{ $agent->company_name }}</span>
                                                            @endif
                                                            @if($agent->email)
                                                                <span class="text-sm text-gray-500 dark:text-zinc-500">{{ $agent->email }}</span>
                                                            @endif
                                                        </div>
                                                    </button>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <div class="px-4 py-3 text-sm text-gray-500 dark:text-zinc-400">
                                            No agents found matching "{{ $agentSearch }}"
                                        </div>
                                    @endif
                                </div>
                            </div>
                            @error('form.agent_id')
                                <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Selected Agent Details -->
                        @if($selectedAgent)
                            @php
                                $tierColors = [
                                    'standard' => 'gray',
                                    'premium' => 'blue',
                                    'vip' => 'yellow',
                                ];
                                $tierDiscount = $selectedAgent->getTierDiscountPercentage();
                            @endphp
                            <div class="mt-4 p-4 bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-lg">
                                <div class="flex items-center justify-between mb-2">
                                    <flux:text class="font-semibold text-blue-900 dark:text-blue-100">Selected Agent Details</flux:text>
                                    <div class="flex items-center gap-2">
                                        <flux:badge color="{{ $tierColors[$selectedAgent->pricing_tier] ?? 'gray' }}" size="sm">
                                            {{ ucfirst($selectedAgent->pricing_tier ?? 'standard') }} Tier
                                        </flux:badge>
                                        <span class="text-xs font-medium text-green-600 dark:text-green-400">
                                            {{ $tierDiscount }}% discount
                                        </span>
                                    </div>
                                </div>
                                <div class="grid md:grid-cols-2 gap-3 text-sm">
                                    <div>
                                        <span class="text-blue-700 dark:text-blue-300">Agent Code:</span>
                                        <span class="font-medium text-blue-900 dark:text-blue-100">{{ $selectedAgent->agent_code }}</span>
                                    </div>
                                    <div>
                                        <span class="text-blue-700 dark:text-blue-300">Type:</span>
                                        <span class="font-medium text-blue-900 dark:text-blue-100">{{ ucfirst($selectedAgent->type) }}</span>
                                    </div>
                                    @if($selectedAgent->contact_person)
                                        <div>
                                            <span class="text-blue-700 dark:text-blue-300">Contact Person:</span>
                                            <span class="font-medium text-blue-900 dark:text-blue-100">{{ $selectedAgent->contact_person }}</span>
                                        </div>
                                    @endif
                                    @if($selectedAgent->phone)
                                        <div>
                                            <span class="text-blue-700 dark:text-blue-300">Phone:</span>
                                            <span class="font-medium text-blue-900 dark:text-blue-100">{{ $selectedAgent->phone }}</span>
                                        </div>
                                    @endif
                                    @if($selectedAgent->email)
                                        <div>
                                            <span class="text-blue-700 dark:text-blue-300">Email:</span>
                                            <span class="font-medium text-blue-900 dark:text-blue-100">{{ $selectedAgent->email }}</span>
                                        </div>
                                    @endif
                                    @if($selectedAgent->payment_terms)
                                        <div class="md:col-span-2">
                                            <span class="text-blue-700 dark:text-blue-300">Payment Terms:</span>
                                            <span class="font-medium text-blue-900 dark:text-blue-100">{{ $selectedAgent->payment_terms }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Order Items -->
                <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <flux:heading size="lg">Order Items</flux:heading>
                        <flux:button type="button" wire:click="addOrderItem" size="sm">
                            <div class="flex items-center">
                                <flux:icon name="plus" class="w-4 h-4 mr-1" />
                                Add Item
                            </div>
                        </flux:button>
                    </div>

                    <div class="space-y-4">
                        @foreach($orderItems as $index => $item)
                            <div class="border border-gray-200 dark:border-zinc-700 rounded-lg p-4" wire:key="item-{{ $index }}">
                                <!-- Item Type Selection -->
                                <div class="mb-4">
                                    <flux:field>
                                        <flux:label>Item Type</flux:label>
                                        <div class="flex gap-4">
                                            <label class="flex items-center cursor-pointer">
                                                <input type="radio" wire:model.live="orderItems.{{ $index }}.item_type"
                                                       wire:change="itemTypeChanged({{ $index }})"
                                                       value="product" class="mr-2 text-indigo-600 dark:text-indigo-400">
                                                <span class="text-gray-900 dark:text-zinc-100">Product</span>
                                            </label>
                                            <label class="flex items-center cursor-pointer">
                                                <input type="radio" wire:model.live="orderItems.{{ $index }}.item_type"
                                                       wire:change="itemTypeChanged({{ $index }})"
                                                       value="package" class="mr-2 text-indigo-600 dark:text-indigo-400">
                                                <span class="text-gray-900 dark:text-zinc-100">Package</span>
                                            </label>
                                        </div>
                                    </flux:field>
                                </div>

                                <div class="grid md:grid-cols-5 gap-4 items-end">
                                    <!-- Product or Package Selection -->
                                    @if($item['item_type'] === 'product')
                                        <flux:field class="md:col-span-2">
                                            <flux:label>Product</flux:label>
                                            <flux:select wire:model.live="orderItems.{{ $index }}.product_id" wire:change="productSelected({{ $index }}, $event.target.value)">
                                                <option value="">Select Product...</option>
                                                @foreach($products as $product)
                                                    <option value="{{ $product->id }}">{{ $product->name }} (RM {{ number_format($product->base_price, 2) }})</option>
                                                @endforeach
                                            </flux:select>
                                            @error('orderItems.' . $index . '.product_id')
                                                <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                            @enderror
                                        </flux:field>
                                    @else
                                        <flux:field class="md:col-span-2">
                                            <flux:label>Package</flux:label>
                                            <flux:select wire:model.live="orderItems.{{ $index }}.package_id" wire:change="packageSelected({{ $index }}, $event.target.value)">
                                                <option value="">Select Package...</option>
                                                @foreach($packages as $package)
                                                    <option value="{{ $package->id }}">
                                                        {{ $package->name }} (RM {{ number_format($package->price, 2) }})
                                                        @if($package->track_stock) - Stock Tracked @endif
                                                    </option>
                                                @endforeach
                                            </flux:select>
                                            @error('orderItems.' . $index . '.package_id')
                                                <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                            @enderror
                                        </flux:field>
                                    @endif

                                    <!-- Warehouse -->
                                    <flux:field>
                                        <flux:label>Warehouse</flux:label>
                                        <flux:select wire:model="orderItems.{{ $index }}.warehouse_id">
                                            <option value="">Select...</option>
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
                                        <flux:label>Qty</flux:label>
                                        <flux:input wire:model.live="orderItems.{{ $index }}.quantity"
                                                   wire:change="quantityUpdated({{ $index }})"
                                                   type="number" min="1" />
                                        @error('orderItems.' . $index . '.quantity')
                                            <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                        @enderror
                                    </flux:field>

                                    <!-- Unit Price -->
                                    <flux:field>
                                        <flux:label>Unit Price</flux:label>
                                        <flux:input wire:model.live="orderItems.{{ $index }}.unit_price"
                                                   wire:change="unitPriceUpdated({{ $index }})"
                                                   type="number" step="0.01" min="0" />
                                        @if(($item['weight'] ?? 0) > 0)
                                            <div class="mt-1 text-xs text-gray-500 dark:text-zinc-400">
                                                <flux:icon name="scale" class="w-3 h-3 inline mr-1" />
                                                {{ number_format($item['weight'], 3) }} kg
                                            </div>
                                        @endif
                                    </flux:field>
                                </div>

                                <!-- Pricing Summary -->
                                <div class="mt-3 flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        @if($item['total_price'] > 0)
                                            <div class="flex flex-col">
                                                {{-- Show original price with strikethrough if discounted --}}
                                                @if(!empty($item['pricing_type']) && $item['pricing_type'] !== 'base' && $item['base_price'] > $item['unit_price'])
                                                    <div class="flex items-center gap-2">
                                                        <span class="text-sm text-gray-400 dark:text-zinc-500 line-through">
                                                            RM {{ number_format($item['base_price'] * $item['quantity'], 2) }}
                                                        </span>
                                                        <flux:text class="font-medium text-green-600 dark:text-green-400">
                                                            RM {{ number_format($item['total_price'], 2) }}
                                                        </flux:text>
                                                    </div>
                                                    <div class="flex items-center gap-2 mt-1">
                                                        @if($item['pricing_type'] === 'custom')
                                                            <flux:badge color="purple" size="sm">Custom Price</flux:badge>
                                                        @elseif($item['pricing_type'] === 'tier')
                                                            <flux:badge color="blue" size="sm">Tier Discount</flux:badge>
                                                        @endif
                                                        <span class="text-xs text-green-600 dark:text-green-400">
                                                            Save {{ $item['discount_percent'] }}%
                                                        </span>
                                                    </div>
                                                @else
                                                    <flux:text class="font-medium text-gray-900 dark:text-zinc-100">
                                                        Subtotal: RM {{ number_format($item['total_price'], 2) }}
                                                    </flux:text>
                                                @endif
                                            </div>
                                        @else
                                            <div></div>
                                        @endif
                                    </div>

                                    @if(count($orderItems) > 1)
                                        <flux:button type="button" wire:click="removeOrderItem({{ $index }})"
                                                   variant="outline" size="sm" class="text-red-600 dark:text-red-400 border-red-200 dark:border-red-800 hover:bg-red-50 dark:hover:bg-red-900/30">
                                            <flux:icon name="trash" class="w-4 h-4" />
                                        </flux:button>
                                    @endif
                                </div>

                                <!-- Show package contents if package is selected -->
                                @if($item['item_type'] === 'package' && !empty($item['package_id']))
                                    @php
                                        $selectedPackage = $packages->find($item['package_id']);
                                    @endphp
                                    @if($selectedPackage)
                                        <div class="mt-4 p-3 bg-gray-50 dark:bg-zinc-900 rounded border border-gray-200 dark:border-zinc-700">
                                            <flux:text class="font-semibold mb-2 text-gray-900 dark:text-zinc-100">Package Contents:</flux:text>
                                            <ul class="space-y-1 text-sm">
                                                @foreach($selectedPackage->products as $product)
                                                    <li class="flex justify-between text-gray-700 dark:text-zinc-300">
                                                        <span>• {{ $product->name }}</span>
                                                        <span class="text-gray-500 dark:text-zinc-500">Qty: {{ $product->pivot->quantity }}</span>
                                                    </li>
                                                @endforeach
                                                @foreach($selectedPackage->courses as $course)
                                                    <li class="flex justify-between text-gray-700 dark:text-zinc-300">
                                                        <span>• {{ $course->name }}</span>
                                                        <span class="text-gray-500 dark:text-zinc-500">(Course)</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Billing Address -->
                <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                    <flux:heading size="lg" class="mb-4">Billing Address</flux:heading>

                    <div class="grid md:grid-cols-2 gap-4">
                        <flux:field>
                            <flux:label>Contact Name</flux:label>
                            <flux:input wire:model="form.billing_address.first_name" />
                            @error('form.billing_address.first_name')
                                <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </flux:field>

                        <flux:field>
                            <flux:label>Company (Optional)</flux:label>
                            <flux:input wire:model="form.billing_address.company" />
                        </flux:field>
                    </div>

                    <flux:field class="mt-4">
                        <flux:label>Address</flux:label>
                        <flux:input wire:model="form.billing_address.address_line_1" placeholder="Street address" />
                        @error('form.billing_address.address_line_1')
                            <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </flux:field>

                    <flux:field class="mt-4">
                        <flux:input wire:model="form.billing_address.address_line_2" placeholder="Apartment, suite, etc. (optional)" />
                    </flux:field>

                    <div class="grid md:grid-cols-3 gap-4 mt-4">
                        <flux:field>
                            <flux:label>City</flux:label>
                            <flux:input wire:model="form.billing_address.city" />
                            @error('form.billing_address.city')
                                <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </flux:field>

                        <flux:field>
                            <flux:label>State</flux:label>
                            <flux:input wire:model="form.billing_address.state" />
                            @error('form.billing_address.state')
                                <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </flux:field>

                        <flux:field>
                            <flux:label>Postal Code</flux:label>
                            <flux:input wire:model="form.billing_address.postal_code" />
                            @error('form.billing_address.postal_code')
                                <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </flux:field>
                    </div>
                </div>

                <!-- Order Date & Notes -->
                <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                    <flux:heading size="lg" class="mb-4">Order Date & Notes</flux:heading>

                    <flux:field class="mb-4">
                        <flux:label>Order Date (Tarikh Key In)</flux:label>
                        <flux:input
                            type="datetime-local"
                            wire:model="form.order_date"
                        />
                        <flux:description class="text-gray-500 dark:text-zinc-400">
                            Use this to key in older order records. Default is current date/time.
                        </flux:description>
                    </flux:field>

                    <flux:field>
                        <flux:label>Notes / Remarks</flux:label>
                        <flux:textarea wire:model="form.notes" rows="4" placeholder="Any special instructions or notes for this order..." />
                    </flux:field>
                </div>
            </div>

            <!-- Right Column - Order Summary -->
            <div class="lg:col-span-1">
                <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6 sticky top-6">
                    <flux:heading size="lg" class="mb-4">Order Summary</flux:heading>

                    <!-- Order Status Settings -->
                    <div class="space-y-4 mb-6">
                        <flux:field>
                            <flux:label>Order Status</flux:label>
                            <flux:select wire:model="form.order_status">
                                <option value="draft">Draft</option>
                                <option value="pending">Pending</option>
                                <option value="processing">Processing</option>
                                <option value="shipped">Shipped</option>
                                <option value="delivered">Delivered</option>
                                <option value="cancelled">Cancelled</option>
                                <option value="refunded">Refunded</option>
                                <option value="returned">Returned</option>
                            </flux:select>
                        </flux:field>

                        <flux:field>
                            <flux:label>Payment Status</flux:label>
                            <flux:select wire:model="form.payment_status">
                                <option value="pending">Pending</option>
                                <option value="completed">Completed</option>
                                <option value="failed">Failed</option>
                                <option value="refunded">Refunded</option>
                            </flux:select>
                        </flux:field>

                        <flux:field>
                            <flux:label>Payment Method</flux:label>
                            <flux:select wire:model="form.payment_method">
                                <option value="cash">Cash</option>
                                <option value="credit_card">Credit Card</option>
                                <option value="debit_card">Debit Card</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="fpx">FPX Online Banking</option>
                                <option value="grabpay">GrabPay</option>
                                <option value="boost">Boost</option>
                                <option value="credit">Credit (Agent Terms)</option>
                            </flux:select>
                        </flux:field>
                    </div>

                    <!-- Total Weight -->
                    <div class="border-t border-gray-200 dark:border-zinc-700 pt-4">
                        <flux:field>
                            <flux:label>Total Weight</flux:label>
                            <div class="flex items-center gap-2 py-2">
                                <flux:icon name="scale" class="w-4 h-4 text-gray-500 dark:text-zinc-400" />
                                <flux:text class="text-gray-900 dark:text-zinc-100">{{ number_format($totalWeight, 3) }} kg</flux:text>
                            </div>
                        </flux:field>
                    </div>

                    <!-- Delivery Fees -->
                    <div class="border-t border-gray-200 dark:border-zinc-700 pt-4">
                        <flux:field>
                            <flux:label>Delivery / Shipping Fees (RM)</flux:label>
                            <flux:input
                                type="text"
                                inputmode="decimal"
                                wire:model.live.debounce.500ms="shippingCost"
                                placeholder="0.00"
                                x-on:blur="$el.value = $el.value ? parseFloat($el.value).toFixed(2) : ''"
                            />
                        </flux:field>
                    </div>

                    <!-- Tax Configuration -->
                    <div class="border-t border-gray-200 dark:border-zinc-700 pt-4">
                        <flux:field>
                            <flux:label>Tax Rate (%)</flux:label>
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
                            <flux:text class="text-gray-600 dark:text-zinc-400">Subtotal</flux:text>
                            <flux:text class="text-gray-900 dark:text-zinc-100">RM {{ number_format($subtotal, 2) }}</flux:text>
                        </div>

                        @if(($shippingCost ?? 0) > 0)
                            <div class="flex justify-between">
                                <flux:text class="text-gray-600 dark:text-zinc-400">Delivery / Shipping</flux:text>
                                <flux:text class="text-gray-900 dark:text-zinc-100">RM {{ number_format($shippingCost ?? 0, 2) }}</flux:text>
                            </div>
                        @endif

                        @if(($taxRate ?? 0) > 0)
                            <div class="flex justify-between">
                                <flux:text class="text-gray-600 dark:text-zinc-400">Tax ({{ number_format($taxRate ?? 0, 1) }}%)</flux:text>
                                <flux:text class="text-gray-900 dark:text-zinc-100">RM {{ number_format($taxAmount, 2) }}</flux:text>
                            </div>
                        @endif

                        <div class="border-t border-gray-200 dark:border-zinc-700 pt-3">
                            <div class="flex justify-between">
                                <flux:text class="font-semibold text-lg text-gray-900 dark:text-zinc-100">Total</flux:text>
                                <flux:text class="font-semibold text-lg text-blue-600 dark:text-blue-400">RM {{ number_format($total, 2) }}</flux:text>
                            </div>
                        </div>
                    </div>

                    <!-- Create Order Button -->
                    <div class="mt-6">
                        <flux:button type="submit" variant="primary" class="w-full">
                            Create Order
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
