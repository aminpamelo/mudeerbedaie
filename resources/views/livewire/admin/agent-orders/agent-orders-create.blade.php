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

    public string $agentSearch = '';

    public ?Agent $selectedAgent = null;

    public function mount(): void
    {
        $this->addOrderItem();
    }

    public function addOrderItem(): void
    {
        $this->orderItems[] = [
            'product_id' => '',
            'product_variant_id' => null,
            'warehouse_id' => '',
            'quantity' => 1,
            'unit_price' => 0,
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
            $this->orderItems[$index]['unit_price'] = $product->base_price;
            $this->orderItems[$index]['total_price'] = $product->base_price * $this->orderItems[$index]['quantity'];
        }
        $this->calculateTotals();
    }

    public function quantityUpdated(int $index): void
    {
        $quantity = $this->orderItems[$index]['quantity'];
        $unitPrice = $this->orderItems[$index]['unit_price'];
        $this->orderItems[$index]['total_price'] = $quantity * $unitPrice;
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
        }

        $this->dispatch('close-dropdown');
    }

    public function clearAgentSelection(): void
    {
        $this->form['agent_id'] = '';
        $this->selectedAgent = null;
        $this->agentSearch = '';
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
            'form.agent_id.required' => 'Please select an agent.',
            'orderItems.required' => 'Please add at least one product.',
            'orderItems.*.product_id.required' => 'Please select a product.',
            'orderItems.*.warehouse_id.required' => 'Please select a warehouse.',
        ]);

        DB::transaction(function () {
            $agent = Agent::find($this->form['agent_id']);

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
                'order_date' => now(),
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
                'paid_at' => $this->form['payment_status'] === 'completed' ? now() : null,
            ]);

            $order->addSystemNote('Agent order created manually');

            session()->flash('success', 'Agent order created successfully!');
            $this->redirectRoute('agent-orders.show', $order);
        });
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
            'warehouses' => Warehouse::all(),
            'agents' => $agentsQuery->orderBy('name')->limit(50)->get(),
        ];
    }
}; ?>

<div>
    <!-- Header -->
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Buat Pesanan Agent Baru</flux:heading>
            <flux:text class="mt-2">Create a new order for agent (kedai buku)</flux:text>
        </div>
        <flux:button variant="outline" :href="route('agent-orders.index')" wire:navigate>
            <flux:icon name="arrow-left" class="w-4 h-4 mr-2" />
            Back to List
        </flux:button>
    </div>

    <form wire:submit="createOrder">
        <div class="grid lg:grid-cols-3 gap-6">
            <!-- Left Column - Order Details -->
            <div class="lg:col-span-2 space-y-6">

                <!-- Agent Selection -->
                <div class="bg-white rounded-lg shadow-sm border p-6">
                    <flux:heading size="lg" class="mb-4">Select Agent</flux:heading>

                    <div class="space-y-4">
                        <!-- Searchable Agent Selection -->
                        <div class="space-y-2" x-data="agentSearchComponent()">
                            <flux:label>Agent / Kedai Buku</flux:label>
                            <div class="relative">
                                <input
                                    type="text"
                                    x-model="search"
                                    @input.debounce.300ms="$wire.set('agentSearch', search)"
                                    @focus="showDropdown = true"
                                    placeholder="Search by name, code, company, or email..."
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 pr-10"
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
                                    class="absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto"
                                    style="display: none;"
                                >
                                    @if($agents->count() > 0)
                                        <ul class="py-1">
                                            @foreach($agents as $agent)
                                                <li>
                                                    <button
                                                        type="button"
                                                        @click="selectAgent({{ $agent->id }}, @js($agent->name), @js($agent->agent_code), @js($agent->company_name ?? ''))"
                                                        class="w-full text-left px-4 py-2 hover:bg-gray-100 transition-colors"
                                                    >
                                                        <div class="flex flex-col">
                                                            <div class="flex items-center gap-2">
                                                                <span class="font-medium text-gray-900">{{ $agent->name }}</span>
                                                                <span class="text-xs px-2 py-0.5 bg-blue-100 text-blue-700 rounded">{{ $agent->agent_code }}</span>
                                                                @if($agent->type)
                                                                    <span class="text-xs px-2 py-0.5 bg-gray-100 text-gray-600 rounded">{{ ucfirst($agent->type) }}</span>
                                                                @endif
                                                            </div>
                                                            @if($agent->company_name)
                                                                <span class="text-sm text-gray-600">{{ $agent->company_name }}</span>
                                                            @endif
                                                            @if($agent->email)
                                                                <span class="text-sm text-gray-500">{{ $agent->email }}</span>
                                                            @endif
                                                        </div>
                                                    </button>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <div class="px-4 py-3 text-sm text-gray-500">
                                            No agents found matching "{{ $agentSearch }}"
                                        </div>
                                    @endif
                                </div>
                            </div>
                            @error('form.agent_id')
                                <p class="text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Selected Agent Details -->
                        @if($selectedAgent)
                            <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                <flux:text class="font-semibold text-blue-900 mb-2">Selected Agent Details</flux:text>
                                <div class="grid md:grid-cols-2 gap-3 text-sm">
                                    <div>
                                        <span class="text-blue-700">Agent Code:</span>
                                        <span class="font-medium text-blue-900">{{ $selectedAgent->agent_code }}</span>
                                    </div>
                                    <div>
                                        <span class="text-blue-700">Type:</span>
                                        <span class="font-medium text-blue-900">{{ ucfirst($selectedAgent->type) }}</span>
                                    </div>
                                    @if($selectedAgent->contact_person)
                                        <div>
                                            <span class="text-blue-700">Contact Person:</span>
                                            <span class="font-medium text-blue-900">{{ $selectedAgent->contact_person }}</span>
                                        </div>
                                    @endif
                                    @if($selectedAgent->phone)
                                        <div>
                                            <span class="text-blue-700">Phone:</span>
                                            <span class="font-medium text-blue-900">{{ $selectedAgent->phone }}</span>
                                        </div>
                                    @endif
                                    @if($selectedAgent->payment_terms)
                                        <div class="md:col-span-2">
                                            <span class="text-blue-700">Payment Terms:</span>
                                            <span class="font-medium text-blue-900">{{ $selectedAgent->payment_terms }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Order Items -->
                <div class="bg-white rounded-lg shadow-sm border p-6">
                    <div class="flex items-center justify-between mb-4">
                        <flux:heading size="lg">Products</flux:heading>
                        <flux:button type="button" wire:click="addOrderItem" size="sm">
                            <flux:icon name="plus" class="w-4 h-4 mr-1" />
                            Add Product
                        </flux:button>
                    </div>

                    <div class="space-y-4">
                        @foreach($orderItems as $index => $item)
                            <div class="border rounded-lg p-4" wire:key="item-{{ $index }}">
                                <div class="grid md:grid-cols-5 gap-4 items-end">
                                    <!-- Product Selection -->
                                    <flux:field class="md:col-span-2">
                                        <flux:label>Product</flux:label>
                                        <flux:select wire:model.live="orderItems.{{ $index }}.product_id" wire:change="productSelected({{ $index }}, $event.target.value)">
                                            <option value="">Select Product...</option>
                                            @foreach($products as $product)
                                                <option value="{{ $product->id }}">{{ $product->name }} (RM {{ number_format($product->base_price, 2) }})</option>
                                            @endforeach
                                        </flux:select>
                                        @error('orderItems.' . $index . '.product_id')
                                            <p class="text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </flux:field>

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
                                            <p class="text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </flux:field>

                                    <!-- Quantity -->
                                    <flux:field>
                                        <flux:label>Qty</flux:label>
                                        <flux:input wire:model.live="orderItems.{{ $index }}.quantity"
                                                   wire:change="quantityUpdated({{ $index }})"
                                                   type="number" min="1" />
                                        @error('orderItems.' . $index . '.quantity')
                                            <p class="text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </flux:field>

                                    <!-- Unit Price -->
                                    <flux:field>
                                        <flux:label>Unit Price</flux:label>
                                        <flux:input wire:model.live="orderItems.{{ $index }}.unit_price"
                                                   wire:change="unitPriceUpdated({{ $index }})"
                                                   type="number" step="0.01" min="0" />
                                    </flux:field>
                                </div>

                                <div class="mt-3 flex items-center justify-between">
                                    @if($item['total_price'] > 0)
                                        <flux:text class="font-medium">Subtotal: RM {{ number_format($item['total_price'], 2) }}</flux:text>
                                    @else
                                        <div></div>
                                    @endif

                                    @if(count($orderItems) > 1)
                                        <flux:button type="button" wire:click="removeOrderItem({{ $index }})"
                                                   variant="outline" size="sm" class="text-red-600 border-red-200 hover:bg-red-50">
                                            <flux:icon name="trash" class="w-4 h-4" />
                                        </flux:button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Order Notes -->
                <div class="bg-white rounded-lg shadow-sm border p-6">
                    <flux:heading size="lg" class="mb-4">Notes / Remarks</flux:heading>
                    <flux:field>
                        <flux:textarea wire:model="form.notes" rows="4" placeholder="Any special instructions or notes for this order..." />
                    </flux:field>
                </div>
            </div>

            <!-- Right Column - Order Summary -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-sm border p-6 sticky top-6">
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
                            </flux:select>
                        </flux:field>

                        <flux:field>
                            <flux:label>Payment Status</flux:label>
                            <flux:select wire:model="form.payment_status">
                                <option value="pending">Pending</option>
                                <option value="completed">Completed</option>
                                <option value="failed">Failed</option>
                            </flux:select>
                        </flux:field>

                        <flux:field>
                            <flux:label>Payment Method</flux:label>
                            <flux:select wire:model="form.payment_method">
                                <option value="cash">Cash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="credit">Credit (Agent Terms)</option>
                            </flux:select>
                        </flux:field>
                    </div>

                    <!-- Delivery Fees -->
                    <div class="border-t pt-4">
                        <flux:field>
                            <flux:label>Shipping Cost (RM)</flux:label>
                            <flux:input
                                type="text"
                                inputmode="decimal"
                                wire:model.live.debounce.500ms="shippingCost"
                                placeholder="0.00"
                            />
                        </flux:field>
                    </div>

                    <!-- Tax Configuration -->
                    <div class="border-t pt-4">
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
                    <div class="space-y-3 border-t pt-4 mt-4">
                        <div class="flex justify-between">
                            <flux:text>Subtotal</flux:text>
                            <flux:text>RM {{ number_format($subtotal, 2) }}</flux:text>
                        </div>

                        @if(($shippingCost ?? 0) > 0)
                            <div class="flex justify-between">
                                <flux:text>Shipping</flux:text>
                                <flux:text>RM {{ number_format($shippingCost ?? 0, 2) }}</flux:text>
                            </div>
                        @endif

                        @if(($taxRate ?? 0) > 0)
                            <div class="flex justify-between">
                                <flux:text>Tax ({{ number_format($taxRate ?? 0, 1) }}%)</flux:text>
                                <flux:text>RM {{ number_format($taxAmount, 2) }}</flux:text>
                            </div>
                        @endif

                        <div class="border-t pt-3">
                            <div class="flex justify-between">
                                <flux:text class="font-semibold text-lg">Total</flux:text>
                                <flux:text class="font-semibold text-lg text-blue-600">RM {{ number_format($total, 2) }}</flux:text>
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
