<?php

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductAttributeTemplate;
use App\Models\ProductVariant;
use App\Models\ProductMedia;
use Illuminate\Support\Str;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public $name = '';
    public $slug = '';
    public $description = '';
    public $short_description = '';
    public $sku = '';
    public $barcode = '';
    public $base_price = '';
    public $cost_price = '';
    public $category_id = '';
    public $status = 'draft';
    public $type = 'simple';
    public $track_quantity = true;
    public $min_quantity = 0;

    // Image upload properties
    public $images = [];
    public $primary_image_index = 0;

    // Variable product properties
    public $selected_attributes = [];
    public $variants = [];
    public $generate_all_combinations = false;

    public function rules(): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:products,slug',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:500',
            'sku' => 'required|string|max:100|unique:products,sku',
            'barcode' => 'nullable|string|max:100|unique:products,barcode',
            'category_id' => 'nullable|exists:product_categories,id',
            'status' => 'required|in:active,inactive,draft',
            'type' => 'required|in:simple,variable',
            'track_quantity' => 'boolean',
            'min_quantity' => 'required|integer|min:0',
            'images.*' => 'nullable|image|max:5120', // Max 5MB per image
            'primary_image_index' => 'integer|min:0',
        ];

        if ($this->type === 'simple') {
            $rules['base_price'] = 'required|numeric|min:0';
            $rules['cost_price'] = 'required|numeric|min:0';
        } else {
            $rules['selected_attributes'] = 'required_if:type,variable|array|min:1';
            $rules['variants'] = 'required_if:type,variable|array|min:1';
            $rules['variants.*.sku'] = 'required|string|max:100|distinct';
            $rules['variants.*.price'] = 'required|numeric|min:0';
            $rules['variants.*.cost_price'] = 'required|numeric|min:0';
            $rules['variants.*.stock_quantity'] = 'required|integer|min:0';
        }

        return $rules;
    }

    public function with(): array
    {
        return [
            'categories' => ProductCategory::active()->ordered()->get(),
            'attributes' => ProductAttributeTemplate::all(),
        ];
    }

    public function updatedName(): void
    {
        $this->slug = Str::slug($this->name);
    }

    public function updatedSelectedAttributes(): void
    {
        if ($this->type === 'variable' && $this->generate_all_combinations) {
            $this->generateVariantCombinations();
        }
    }

    public function generateVariantCombinations(): void
    {
        if (empty($this->selected_attributes)) {
            $this->variants = [];
            return;
        }

        $attributes = ProductAttributeTemplate::whereIn('id', $this->selected_attributes)->get();
        $combinations = [[]];

        foreach ($attributes as $attribute) {
            $newCombinations = [];
            foreach ($combinations as $combination) {
                if ($attribute->values) {
                    foreach ($attribute->values as $value) {
                        $newCombination = $combination;
                        $newCombination[$attribute->name] = $value;
                        $newCombinations[] = $newCombination;
                    }
                }
            }
            $combinations = $newCombinations;
        }

        $this->variants = [];
        foreach ($combinations as $index => $combination) {
            $variantName = implode(' - ', $combination);
            $this->variants[] = [
                'attributes' => $combination,
                'sku' => $this->sku . '-' . ($index + 1),
                'price' => $this->base_price ?: 0,
                'cost_price' => $this->cost_price ?: 0,
                'stock_quantity' => 0,
                'variant_name' => $variantName,
            ];
        }
    }

    public function addVariant(): void
    {
        $this->variants[] = [
            'attributes' => [],
            'sku' => $this->sku . '-' . (count($this->variants) + 1),
            'price' => $this->base_price ?: 0,
            'cost_price' => $this->cost_price ?: 0,
            'stock_quantity' => 0,
            'variant_name' => '',
        ];
    }

    public function removeVariant($index): void
    {
        unset($this->variants[$index]);
        $this->variants = array_values($this->variants);
    }

    public function removeImage($index): void
    {
        unset($this->images[$index]);
        $this->images = array_values($this->images);

        // Adjust primary image index if necessary
        if ($this->primary_image_index >= count($this->images)) {
            $this->primary_image_index = count($this->images) > 0 ? 0 : 0;
        }
    }

    public function setPrimaryImage($index): void
    {
        $this->primary_image_index = $index;
    }

    public function save(): void
    {
        $this->validate();

        $productData = [
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'short_description' => $this->short_description,
            'sku' => $this->sku,
            'barcode' => $this->barcode ?: null,
            'category_id' => $this->category_id ?: null,
            'status' => $this->status,
            'type' => $this->type,
            'track_quantity' => $this->track_quantity,
            'min_quantity' => $this->min_quantity,
        ];

        if ($this->type === 'simple') {
            $productData['base_price'] = $this->base_price;
            $productData['cost_price'] = $this->cost_price;
        }

        $product = Product::create($productData);

        // Create variants for variable products
        if ($this->type === 'variable' && !empty($this->variants)) {
            foreach ($this->variants as $variantData) {
                ProductVariant::create([
                    'product_id' => $product->id,
                    'sku' => $variantData['sku'],
                    'price' => $variantData['price'],
                    'cost_price' => $variantData['cost_price'],
                    'attributes' => $variantData['attributes'],
                ]);
            }
        }

        // Upload and save images
        if (!empty($this->images)) {
            foreach ($this->images as $index => $image) {
                // Sanitize filename by replacing spaces and special characters
                $originalName = $image->getClientOriginalName();
                $sanitizedName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
                $fileName = time() . '_' . $sanitizedName;
                $filePath = $image->storeAs('products/' . $product->id, $fileName, 'public');

                // Create media record
                ProductMedia::create([
                    'product_id' => $product->id,
                    'type' => 'image',
                    'file_name' => $fileName,
                    'file_path' => $filePath,
                    'mime_type' => $image->getMimeType(),
                    'file_size' => $image->getSize(),
                    'is_primary' => $index === $this->primary_image_index,
                    'sort_order' => $index,
                ]);
            }
        }

        session()->flash('success', 'Product created successfully.');

        $this->redirect(route('products.show', $product));
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Create Product</flux:heading>
            <flux:text class="mt-2">Add a new product to your inventory</flux:text>
        </div>
        <flux:button variant="outline" href="{{ route('products.index') }}" icon="arrow-left">
            Back to Products
        </flux:button>
    </div>

    <form wire:submit="save" class="space-y-6">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <!-- Basic Information -->
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Basic Information</flux:heading>
                    <flux:text class="mt-1 text-sm">Enter the basic product details</flux:text>
                </div>

                <flux:field>
                    <flux:label>Product Name</flux:label>
                    <flux:input wire:model.live="name" placeholder="Enter product name" />
                    <flux:error name="name" />
                </flux:field>

                <flux:field>
                    <flux:label>Slug</flux:label>
                    <flux:input wire:model="slug" placeholder="product-slug" />
                    <flux:error name="slug" />
                    <flux:description>URL-friendly version of the product name</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>Short Description</flux:label>
                    <flux:textarea wire:model="short_description" placeholder="Brief product description" rows="2" />
                    <flux:error name="short_description" />
                </flux:field>

                <flux:field>
                    <flux:label>Description</flux:label>
                    <flux:textarea wire:model="description" placeholder="Detailed product description" rows="4" />
                    <flux:error name="description" />
                </flux:field>

                <flux:field>
                    <flux:label>Category</flux:label>
                    <flux:select wire:model="category_id" placeholder="Select a category">
                        @foreach($categories as $category)
                            <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="category_id" />
                </flux:field>
            </div>

            <!-- Product Details -->
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Product Details</flux:heading>
                    <flux:text class="mt-1 text-sm">Configure product specifications and pricing</flux:text>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>SKU</flux:label>
                        <flux:input wire:model="sku" placeholder="PROD-001" />
                        <flux:error name="sku" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Barcode</flux:label>
                        <flux:input wire:model="barcode" placeholder="1234567890123" />
                        <flux:error name="barcode" />
                    </flux:field>
                </div>

                @if($type === 'simple')
                    <div class="grid grid-cols-2 gap-4">
                        <flux:field>
                            <flux:label>Selling Price (RM)</flux:label>
                            <flux:input type="number" step="0.01" wire:model="base_price" placeholder="0.00" />
                            <flux:error name="base_price" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Cost Price (RM)</flux:label>
                            <flux:input type="number" step="0.01" wire:model="cost_price" placeholder="0.00" />
                            <flux:error name="cost_price" />
                        </flux:field>
                    </div>
                @endif

                <div class="grid grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Status</flux:label>
                        <flux:select wire:model="status">
                            <flux:select.option value="draft">Draft</flux:select.option>
                            <flux:select.option value="active">Active</flux:select.option>
                            <flux:select.option value="inactive">Inactive</flux:select.option>
                        </flux:select>
                        <flux:error name="status" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Product Type</flux:label>
                        <flux:select wire:model.live="type">
                            <flux:select.option value="simple">Simple Product</flux:select.option>
                            <flux:select.option value="variable">Variable Product</flux:select.option>
                        </flux:select>
                        <flux:error name="type" />
                    </flux:field>
                </div>

                <!-- Inventory Settings -->
                @if($type === 'simple')
                    <div class="space-y-4 rounded-lg border border-gray-200 p-4">
                        <flux:heading size="md">Inventory Settings</flux:heading>

                        <div class="flex items-center space-x-3">
                            <flux:checkbox wire:model="track_quantity" />
                            <flux:label>Track quantity for this product</flux:label>
                        </div>

                        @if($track_quantity)
                            <flux:field>
                                <flux:label>Minimum Quantity Alert</flux:label>
                                <flux:input type="number" wire:model="min_quantity" placeholder="0" />
                                <flux:error name="min_quantity" />
                                <flux:description>You'll be alerted when stock falls below this level</flux:description>
                            </flux:field>
                        @endif
                    </div>
                @endif
            </div>

            <!-- Product Images -->
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Product Images</flux:heading>
                    <flux:text class="mt-1 text-sm">Upload images for your product</flux:text>
                </div>

                <div class="space-y-4 rounded-lg border border-gray-200 p-4">
                    <flux:field>
                        <flux:label>Upload Images</flux:label>
                        <input
                            type="file"
                            wire:model="images"
                            multiple
                            accept="image/*"
                            class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                        />
                        <flux:error name="images.*" />
                        <flux:description>You can upload multiple images (Max 5MB each)</flux:description>
                    </flux:field>

                    <!-- Image Preview -->
                    @if(!empty($images))
                        <div>
                            <flux:heading size="md" class="mb-3">Image Preview</flux:heading>
                            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                                @foreach($images as $index => $image)
                                    <div class="relative group rounded-lg border border-gray-200 overflow-hidden" wire:key="image-{{ $index }}">
                                        @if(is_object($image) && method_exists($image, 'temporaryUrl'))
                                            <img src="{{ $image->temporaryUrl() }}" alt="Product Image {{ $index + 1 }}" class="w-full h-32 object-cover">
                                        @endif

                                        <!-- Image controls overlay -->
                                        <div class="absolute inset-0 bg-black/0 group-hover:bg-black/50 transition-all duration-200 flex items-center justify-center">
                                            <div class="opacity-0 group-hover:opacity-100 transition-opacity duration-200 flex space-x-2">
                                                <!-- Set as primary button -->
                                                @if($index !== $primary_image_index)
                                                    <flux:button
                                                        wire:click="setPrimaryImage({{ $index }})"
                                                        size="sm"
                                                        variant="primary"
                                                        icon="star"
                                                    >
                                                        Primary
                                                    </flux:button>
                                                @endif

                                                <!-- Remove button -->
                                                <flux:button
                                                    wire:click="removeImage({{ $index }})"
                                                    size="sm"
                                                    variant="outline"
                                                    icon="trash"
                                                    class="text-red-600 border-red-200 hover:bg-red-50"
                                                >
                                                    Remove
                                                </flux:button>
                                            </div>
                                        </div>

                                        <!-- Primary image badge -->
                                        @if($index === $primary_image_index)
                                            <div class="absolute top-2 left-2">
                                                <flux:badge variant="success" size="sm" icon="star">
                                                    Primary
                                                </flux:badge>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Loading state for images -->
                    <div wire:loading wire:target="images" class="text-center py-4">
                        <flux:icon name="arrow-path" class="w-6 h-6 animate-spin mx-auto text-blue-600" />
                        <p class="mt-2 text-sm text-gray-600">Processing images...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Variable Product Configuration -->
        @if($type === 'variable')
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Variable Product Configuration</flux:heading>
                    <flux:text class="mt-1 text-sm">Configure attributes and variants for this variable product</flux:text>
                </div>

                <!-- Attribute Selection -->
                <div class="rounded-lg border border-gray-200 bg-white p-6">
                    <flux:heading size="md" class="mb-4">Product Attributes</flux:heading>

                    <flux:field>
                        <flux:label>Select Attributes</flux:label>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach($attributes as $attribute)
                                <label class="flex items-center space-x-3 rounded-lg border border-gray-200 p-3 hover:bg-gray-50">
                                    <input
                                        type="checkbox"
                                        wire:model.live="selected_attributes"
                                        value="{{ $attribute->id }}"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <div class="flex-1">
                                        <div class="font-medium text-gray-900">{{ $attribute->label }}</div>
                                        <div class="text-sm text-gray-500">{{ ucfirst($attribute->type) }}</div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                        <flux:error name="selected_attributes" />
                        <flux:description>Select the attributes that will define your product variants</flux:description>
                    </flux:field>

                    @if(!empty($selected_attributes))
                        <div class="mt-6 flex items-center space-x-4">
                            <flux:button
                                type="button"
                                wire:click="generateVariantCombinations"
                                variant="outline"
                                icon="squares-plus"
                            >
                                Generate All Combinations
                            </flux:button>

                            <flux:button
                                type="button"
                                wire:click="addVariant"
                                variant="outline"
                                icon="plus"
                            >
                                Add Custom Variant
                            </flux:button>

                            <label class="flex items-center space-x-2">
                                <flux:checkbox wire:model.live="generate_all_combinations" />
                                <span class="text-sm text-gray-700">Auto-generate on attribute change</span>
                            </label>
                        </div>
                    @endif
                </div>

                <!-- Variant Management -->
                @if(!empty($variants))
                    <div class="rounded-lg border border-gray-200 bg-white p-6">
                        <div class="mb-4 flex items-center justify-between">
                            <flux:heading size="md">Product Variants ({{ count($variants) }})</flux:heading>
                            <flux:text class="text-sm text-gray-500">Configure pricing and stock for each variant</flux:text>
                        </div>

                        <div class="space-y-4">
                            @foreach($variants as $index => $variant)
                                <div class="rounded-lg border border-gray-200 p-4" wire:key="variant-{{ $index }}">
                                    <div class="mb-4 flex items-center justify-between">
                                        <div>
                                            <flux:heading size="sm">Variant {{ $index + 1 }}</flux:heading>
                                            @if(!empty($variant['attributes']))
                                                <div class="mt-1 flex flex-wrap gap-1">
                                                    @foreach($variant['attributes'] as $attrName => $attrValue)
                                                        <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800">
                                                            {{ $attrName }}: {{ $attrValue }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                        <flux:button
                                            type="button"
                                            wire:click="removeVariant({{ $index }})"
                                            variant="outline"
                                            size="sm"
                                            icon="trash"
                                            class="text-red-600 border-red-200 hover:bg-red-50"
                                        >
                                            Remove
                                        </flux:button>
                                    </div>

                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                        <flux:field>
                                            <flux:label>SKU</flux:label>
                                            <flux:input wire:model="variants.{{ $index }}.sku" placeholder="VARIANT-SKU" />
                                            <flux:error name="variants.{{ $index }}.sku" />
                                        </flux:field>

                                        <flux:field>
                                            <flux:label>Price (RM)</flux:label>
                                            <flux:input type="number" step="0.01" wire:model="variants.{{ $index }}.price" placeholder="0.00" />
                                            <flux:error name="variants.{{ $index }}.price" />
                                        </flux:field>

                                        <flux:field>
                                            <flux:label>Cost Price (RM)</flux:label>
                                            <flux:input type="number" step="0.01" wire:model="variants.{{ $index }}.cost_price" placeholder="0.00" />
                                            <flux:error name="variants.{{ $index }}.cost_price" />
                                        </flux:field>

                                        <flux:field>
                                            <flux:label>Stock Quantity</flux:label>
                                            <flux:input type="number" wire:model="variants.{{ $index }}.stock_quantity" placeholder="0" />
                                            <flux:error name="variants.{{ $index }}.stock_quantity" />
                                        </flux:field>
                                    </div>

                                    <!-- Custom Attributes for Manual Variants -->
                                    @if(empty($variant['attributes']) && !empty($selected_attributes))
                                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                            @foreach($attributes->whereIn('id', $selected_attributes) as $attribute)
                                                <flux:field>
                                                    <flux:label>{{ $attribute->label }}</flux:label>
                                                    @if($attribute->type === 'select' && $attribute->values)
                                                        <flux:select wire:model="variants.{{ $index }}.attributes.{{ $attribute->name }}">
                                                            <flux:select.option value="">Select {{ $attribute->label }}</flux:select.option>
                                                            @foreach($attribute->values as $value)
                                                                <flux:select.option value="{{ $value }}">{{ $value }}</flux:select.option>
                                                            @endforeach
                                                        </flux:select>
                                                    @elseif($attribute->type === 'color')
                                                        <flux:input type="color" wire:model="variants.{{ $index }}.attributes.{{ $attribute->name }}" />
                                                    @elseif($attribute->type === 'number')
                                                        <flux:input type="number" wire:model="variants.{{ $index }}.attributes.{{ $attribute->name }}" placeholder="Enter {{ $attribute->label }}" />
                                                    @else
                                                        <flux:input wire:model="variants.{{ $index }}.attributes.{{ $attribute->name }}" placeholder="Enter {{ $attribute->label }}" />
                                                    @endif
                                                </flux:field>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        @if(empty($variants))
                            <div class="text-center py-8">
                                <flux:icon name="squares-plus" class="mx-auto h-12 w-12 text-gray-400" />
                                <h3 class="mt-2 text-sm font-medium text-gray-900">No variants configured</h3>
                                <p class="mt-1 text-sm text-gray-500">Select attributes above and generate variants to get started.</p>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        @endif

        <!-- Actions -->
        <div class="flex items-center justify-end space-x-3 border-t border-gray-200 pt-6">
            <flux:button variant="outline" href="{{ route('products.index') }}">
                Cancel
            </flux:button>
            <flux:button type="submit" variant="primary">
                Create Product
            </flux:button>
        </div>
    </form>
</div>