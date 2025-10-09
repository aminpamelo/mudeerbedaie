<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\StockLevel;
use App\Models\Warehouse;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public $product_id = '';
    public $product_variant_id = '';
    public $warehouse_id = '';
    public $type = 'in';
    public $quantity = '';
    public $reference_type = '';
    public $reference_id = '';
    public $notes = '';
    public $attachment;

    public $selectedProduct = null;
    public $availableVariants = [];

    public function rules(): array
    {
        return [
            'product_id' => 'required|exists:products,id',
            'product_variant_id' => 'nullable|exists:product_variants,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'type' => 'required|in:in,out,adjustment,transfer',
            'quantity' => 'required|integer|not_in:0',
            'reference_type' => 'nullable|string|max:255',
            'reference_id' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:10240',
        ];
    }

    public function with(): array
    {
        return [
            'products' => Product::active()->get(),
            'warehouses' => Warehouse::active()->get(),
        ];
    }

    public function updatedProductId(): void
    {
        if ($this->product_id) {
            $this->selectedProduct = Product::find($this->product_id);
            $this->availableVariants = ProductVariant::where('product_id', $this->product_id)->get();
        } else {
            $this->selectedProduct = null;
            $this->availableVariants = [];
        }
        $this->product_variant_id = '';
    }

    public function save(): void
    {
        $this->validate();

        // Get current stock level
        $stockLevel = StockLevel::firstOrCreate([
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id ?: null,
            'warehouse_id' => $this->warehouse_id,
        ], [
            'quantity' => 0,
            'reserved_quantity' => 0,
            'average_cost' => 0,
        ]);

        $quantityBefore = $stockLevel->quantity;

        // Calculate new quantity based on movement type
        $movementQuantity = match($this->type) {
            'in', 'adjustment' => abs($this->quantity),
            'out' => -abs($this->quantity),
            'transfer' => -abs($this->quantity), // Outgoing from source warehouse
            default => $this->quantity,
        };

        $quantityAfter = $quantityBefore + $movementQuantity;

        // Prevent negative stock for out movements
        if ($this->type === 'out' && $quantityAfter < 0) {
            $this->addError('quantity', 'Insufficient stock. Available: ' . $quantityBefore);
            return;
        }

        // Handle file upload if provided
        $attachmentPath = null;
        if ($this->attachment) {
            $attachmentPath = $this->attachment->store('stock-movements', 'public');
        }

        // Create stock movement record
        StockMovement::create([
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id ?: null,
            'warehouse_id' => $this->warehouse_id,
            'type' => $this->type,
            'quantity' => $movementQuantity,
            'quantity_before' => $quantityBefore,
            'quantity_after' => $quantityAfter,
            'reference_type' => $this->reference_type ?: null,
            'reference_id' => $this->reference_id ?: null,
            'notes' => $this->notes,
            'attachment' => $attachmentPath,
            'created_by' => auth()->id(),
        ]);

        // Update stock level
        $stockLevel->update(['quantity' => $quantityAfter]);

        session()->flash('success', 'Stock movement recorded successfully.');

        $this->redirect(route('stock.movements'));
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Add Stock Movement</flux:heading>
            <flux:text class="mt-2">Record a new stock transaction</flux:text>
        </div>
        <flux:button variant="outline" href="{{ route('stock.movements') }}" icon="arrow-left">
            Back to Movements
        </flux:button>
    </div>

    <form wire:submit="save" class="max-w-2xl space-y-6">
        <!-- Product Selection -->
        <flux:field>
            <flux:label>Product</flux:label>
            <flux:select wire:model.live="product_id" placeholder="Select a product">
                @foreach($products as $product)
                    <flux:select.option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku }})</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="product_id" />
        </flux:field>

        <!-- Product Variant Selection -->
        @if(count($availableVariants) > 0)
            <flux:field>
                <flux:label>Product Variant</flux:label>
                <flux:select wire:model="product_variant_id" placeholder="Select variant (optional)">
                    @foreach($availableVariants as $variant)
                        <flux:select.option value="{{ $variant->id }}">{{ $variant->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="product_variant_id" />
            </flux:field>
        @endif

        <!-- Warehouse Selection -->
        <flux:field>
            <flux:label>Warehouse</flux:label>
            <flux:select wire:model="warehouse_id" placeholder="Select a warehouse">
                @foreach($warehouses as $warehouse)
                    <flux:select.option value="{{ $warehouse->id }}">{{ $warehouse->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="warehouse_id" />
        </flux:field>

        <!-- Movement Type -->
        <flux:field>
            <flux:label>Movement Type</flux:label>
            <flux:select wire:model="type">
                <flux:select.option value="in">Stock In</flux:select.option>
                <flux:select.option value="out">Stock Out</flux:select.option>
                <flux:select.option value="adjustment">Adjustment</flux:select.option>
                <flux:select.option value="transfer">Transfer</flux:select.option>
            </flux:select>
            <flux:error name="type" />
            <flux:description>
                @switch($type)
                    @case('in')
                        Adding stock to inventory
                        @break
                    @case('out')
                        Removing stock from inventory
                        @break
                    @case('adjustment')
                        Correcting stock levels
                        @break
                    @case('transfer')
                        Moving stock between warehouses
                        @break
                @endswitch
            </flux:description>
        </flux:field>

        <!-- Quantity -->
        <flux:field>
            <flux:label>Quantity</flux:label>
            <flux:input type="number" wire:model="quantity" placeholder="Enter quantity" />
            <flux:error name="quantity" />
            <flux:description>
                @if($type === 'out')
                    Quantity to remove from stock
                @else
                    Quantity to add to stock
                @endif
            </flux:description>
        </flux:field>

        <!-- Reference Information -->
        <div class="grid grid-cols-2 gap-4">
            <flux:field>
                <flux:label>Reference Type</flux:label>
                <flux:input wire:model="reference_type" placeholder="e.g., Purchase Order, Sale" />
                <flux:error name="reference_type" />
            </flux:field>

            <flux:field>
                <flux:label>Reference ID</flux:label>
                <flux:input wire:model="reference_id" placeholder="e.g., PO-123, INV-456" />
                <flux:error name="reference_id" />
            </flux:field>
        </div>

        <!-- Notes -->
        <flux:field>
            <flux:label>Notes</flux:label>
            <flux:textarea wire:model="notes" placeholder="Additional notes about this movement" rows="3" />
            <flux:error name="notes" />
        </flux:field>

        <!-- Attachment Upload -->
        <flux:field>
            <flux:label>Attachment / Receipt</flux:label>
            <input
                type="file"
                wire:model="attachment"
                class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                accept=".jpg,.jpeg,.png,.pdf,.doc,.docx"
            />
            <flux:error name="attachment" />
            <flux:description>Upload a receipt, invoice, or related document (Max: 10MB, Formats: JPG, PNG, PDF, DOC, DOCX)</flux:description>

            @if ($attachment)
                <div class="mt-2 p-3 bg-green-50 border border-green-200 rounded-lg">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <flux:icon name="document" class="h-5 w-5 text-green-600 mr-2" />
                            <span class="text-sm text-green-800 font-medium">{{ $attachment->getClientOriginalName() }}</span>
                        </div>
                        <button
                            type="button"
                            wire:click="$set('attachment', null)"
                            class="text-red-600 hover:text-red-800 text-sm font-medium"
                        >
                            Remove
                        </button>
                    </div>
                </div>
            @endif
        </flux:field>

        <!-- Current Stock Info -->
        @if($selectedProduct && $warehouse_id)
            @php
                $currentStock = \App\Models\StockLevel::where('product_id', $product_id)
                    ->where('warehouse_id', $warehouse_id)
                    ->when($product_variant_id, fn($q) => $q->where('product_variant_id', $product_variant_id))
                    ->value('quantity') ?? 0;
            @endphp
            <div class="rounded-md bg-blue-50 p-4">
                <div class="flex">
                    <flux:icon name="information-circle" class="h-5 w-5 text-blue-400" />
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800">Current Stock Information</h3>
                        <p class="text-sm text-blue-700 mt-1">
                            Current stock level: <strong>{{ number_format($currentStock) }} units</strong>
                        </p>
                    </div>
                </div>
            </div>
        @endif

        <!-- Actions -->
        <div class="flex items-center justify-end space-x-3 border-t border-gray-200 pt-6">
            <flux:button variant="outline" href="{{ route('stock.movements') }}">
                Cancel
            </flux:button>
            <flux:button type="submit" variant="primary">
                Record Movement
            </flux:button>
        </div>
    </form>
</div>