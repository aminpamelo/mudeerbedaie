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
    public ProductOrder $order;

    public array $form = [
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

    public ?Agent $selectedAgent = null;

    public function mount(ProductOrder $order): void
    {
        $this->order = $order->load(['items.product', 'agent', 'payments']);

        // Verify this is a kedai buku order
        if (!$this->order->agent || !$this->order->agent->isBookstore()) {
            abort(404, 'Order not found or not a bookstore order.');
        }

        $this->selectedAgent = $this->order->agent;

        // Populate form data
        $this->form = [
            'payment_method' => $this->order->payment_method ?? $this->order->payments->first()?->payment_method ?? 'credit',
            'payment_status' => $this->order->payment_status ?? $this->order->payments->first()?->status ?? 'pending',
            'order_status' => $this->order->status,
            'notes' => $this->order->customer_notes ?? '',
            'required_delivery_date' => $this->order->required_delivery_date?->format('Y-m-d') ?? '',
        ];

        $this->shippingCost = (float) $this->order->shipping_cost;
        $this->taxAmount = (float) $this->order->tax_amount;

        // Calculate tax rate from amount
        if ($this->order->subtotal > 0 && $this->order->tax_amount > 0) {
            $this->taxRate = round(($this->order->tax_amount / $this->order->subtotal) * 100, 2);
        }

        // Populate order items
        foreach ($this->order->items as $item) {
            $product = $item->product;
            $originalPrice = $product ? $product->base_price : $item->unit_price;

            $discountPercentage = 0;
            if ($originalPrice > 0 && $item->unit_price < $originalPrice) {
                $discountPercentage = round((($originalPrice - $item->unit_price) / $originalPrice) * 100, 1);
            }

            $this->orderItems[] = [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'warehouse_id' => $item->warehouse_id,
                'quantity' => $item->quantity_ordered,
                'original_price' => $originalPrice,
                'unit_price' => $item->unit_price,
                'discount_percentage' => $discountPercentage,
                'total_price' => $item->total_price,
            ];
        }

        if (empty($this->orderItems)) {
            $this->addOrderItem();
        }

        $this->calculateTotals();
    }

    public function addOrderItem(): void
    {
        $this->orderItems[] = [
            'id' => null,
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

            // Apply bookstore pricing
            if ($this->selectedAgent) {
                $discountedPrice = $this->selectedAgent->getPriceForProduct($productId, $this->orderItems[$index]['quantity']);
                if ($discountedPrice) {
                    $this->orderItems[$index]['unit_price'] = $discountedPrice;
                } else {
                    $this->orderItems[$index]['unit_price'] = $this->selectedAgent->calculateTierPrice($originalPrice);
                }

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

        if ($originalPrice > 0) {
            $this->orderItems[$index]['discount_percentage'] = round((($originalPrice - $unitPrice) / $originalPrice) * 100, 1);
        }

        $this->orderItems[$index]['total_price'] = $quantity * $unitPrice;
        $this->calculateTotals();
    }

    public function calculateTotals(): void
    {
        $this->subtotal = array_sum(array_column($this->orderItems, 'total_price'));

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

    public function updateOrder(): void
    {
        $this->validate([
            'orderItems' => 'required|array|min:1',
            'orderItems.*.product_id' => 'required|exists:products,id',
            'orderItems.*.warehouse_id' => 'required|exists:warehouses,id',
            'orderItems.*.quantity' => 'required|integer|min:1',
        ], [
            'orderItems.required' => 'Please add at least one product.',
            'orderItems.*.product_id.required' => 'Please select a product.',
            'orderItems.*.warehouse_id.required' => 'Please select a warehouse.',
        ]);

        // Check credit limit if using credit payment and order total increased
        $originalTotal = $this->order->total_amount;
        $additionalAmount = $this->total - $originalTotal;

        if ($this->form['payment_method'] === 'credit' && $additionalAmount > 0) {
            if ($this->selectedAgent->wouldExceedCreditLimit($additionalAmount)) {
                session()->flash('error', 'The increased order amount would exceed the bookstore\'s credit limit. Available credit: RM ' . number_format($this->selectedAgent->available_credit, 2));
                return;
            }
        }

        DB::transaction(function () {
            $shippingCost = $this->shippingCost ?? 0;

            // Update order
            $this->order->update([
                'status' => $this->form['order_status'],
                'payment_status' => $this->form['payment_status'],
                'subtotal' => $this->subtotal,
                'shipping_cost' => $shippingCost,
                'tax_amount' => $this->taxAmount,
                'discount_amount' => $this->discountAmount,
                'total_amount' => $this->total,
                'customer_notes' => $this->form['notes'],
                'required_delivery_date' => $this->form['required_delivery_date'] ?: null,
                'payment_method' => $this->form['payment_method'],
            ]);

            // Get existing item IDs to track which to delete
            $existingItemIds = $this->order->items->pluck('id')->toArray();
            $updatedItemIds = [];

            // Update or create items
            foreach ($this->orderItems as $item) {
                $product = Product::find($item['product_id']);

                $itemData = [
                    'order_id' => $this->order->id,
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
                ];

                if ($item['id']) {
                    // Update existing item
                    ProductOrderItem::find($item['id'])->update($itemData);
                    $updatedItemIds[] = $item['id'];
                } else {
                    // Create new item
                    $newItem = ProductOrderItem::create($itemData);
                    $updatedItemIds[] = $newItem->id;
                }
            }

            // Delete removed items
            $itemsToDelete = array_diff($existingItemIds, $updatedItemIds);
            if (!empty($itemsToDelete)) {
                ProductOrderItem::whereIn('id', $itemsToDelete)->delete();
            }

            // Update payment record
            $payment = $this->order->payments->first();
            if ($payment) {
                $payment->update([
                    'payment_method' => $this->form['payment_method'],
                    'amount' => $this->total,
                    'status' => $this->form['payment_status'],
                    'paid_at' => $this->form['payment_status'] === 'paid' ? now() : null,
                ]);
            }

            $this->order->addSystemNote('Order updated. New total: RM ' . number_format($this->total, 2));

            session()->flash('success', 'Pesanan berjaya dikemaskini!');
            $this->redirectRoute('kedai-buku.orders.show', $this->order);
        });
    }

    public function with(): array
    {
        return [
            'products' => Product::active()->get(),
            'warehouses' => Warehouse::all(),
        ];
    }
}; ?>

<div>
    <!-- Header -->
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Edit Pesanan {{ $order->order_number }}</flux:heading>
            <flux:text class="mt-2">Update order for {{ $selectedAgent->name }}</flux:text>
        </div>
        <div class="flex gap-2">
            <flux:button variant="outline" :href="route('agents-kedai-buku.orders.show', $order)" wire:navigate>
                <div class="flex items-center justify-center">
                    <flux:icon name="x-mark" class="w-4 h-4 mr-2" />
                    Batal
                </div>
            </flux:button>
        </div>
    </div>

    @if(session('error'))
        <div class="mb-6 p-4 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-lg">
            <div class="flex items-center gap-2">
                <flux:icon name="exclamation-triangle" class="w-5 h-5 text-red-600 dark:text-red-400" />
                <span class="text-red-700 dark:text-red-300">{{ session('error') }}</span>
            </div>
        </div>
    @endif

    <form wire:submit="updateOrder">
        <div class="grid lg:grid-cols-3 gap-6">
            <!-- Left Column - Order Details -->
            <div class="lg:col-span-2 space-y-6">

                <!-- Bookstore Info (Read-only) -->
                <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                    <flux:heading size="lg" class="mb-4">Maklumat Kedai Buku</flux:heading>

                    <div class="p-4 bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-lg">
                        <div class="flex items-center justify-between mb-2">
                            <flux:text class="font-semibold text-blue-900 dark:text-blue-100">{{ $selectedAgent->name }}</flux:text>
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
                                <option value="cancelled">Dibatalkan</option>
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

                    <!-- Update Order Button -->
                    <div class="mt-6">
                        <flux:button type="submit" variant="primary" class="w-full">
                            Kemaskini Pesanan
                        </flux:button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
