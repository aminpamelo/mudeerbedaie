<?php

use App\Models\Package;
use App\Models\Product;
use App\Models\Course;
use App\Models\Warehouse;
use Livewire\Volt\Component;
use Illuminate\Support\Str;

new class extends Component {
    public $name = '';
    public $slug = '';
    public $description = '';
    public $short_description = '';
    public $price = '';
    public $discount_type = 'fixed';
    public $discount_value = 0;
    public $status = 'draft';
    public $start_date = '';
    public $end_date = '';
    public $max_purchases = '';
    public $track_stock = true;
    public $default_warehouse_id = '';
    public $meta_title = '';
    public $meta_description = '';

    // Package items
    public $selectedProducts = [];
    public $selectedCourses = [];
    public $productQuantities = [];
    public $productCustomPrices = [];
    public $courseCustomPrices = [];

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:packages,slug',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:500',
            'price' => 'required|numeric|min:0',
            'discount_type' => 'required|in:fixed,percentage',
            'discount_value' => 'nullable|numeric|min:0',
            'status' => 'required|in:active,inactive,draft',
            'start_date' => 'nullable|date|after_or_equal:today',
            'end_date' => 'nullable|date|after:start_date',
            'max_purchases' => 'nullable|integer|min:1',
            'track_stock' => 'boolean',
            'default_warehouse_id' => 'nullable|exists:warehouses,id',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'selectedProducts' => 'array',
            'selectedCourses' => 'array',
            'productQuantities.*' => 'required|integer|min:1',
            'productCustomPrices.*' => 'nullable|numeric|min:0',
            'courseCustomPrices.*' => 'nullable|numeric|min:0',
        ];
    }

    public function mount(): void
    {
        $this->default_warehouse_id = Warehouse::first()?->id;
    }

    public function with(): array
    {
        return [
            'products' => Product::active()->with('category')->orderBy('name')->get(),
            'courses' => Course::where('status', 'active')->orderBy('name')->get(),
            'warehouses' => Warehouse::orderBy('name')->get(),
        ];
    }

    public function updatedName($value): void
    {
        if (empty($this->slug)) {
            $this->slug = Str::slug($value);
        }
    }

    public function addProduct($productId): void
    {
        if ($productId && !in_array($productId, $this->selectedProducts)) {
            $this->selectedProducts[] = (int)$productId;
            $this->productQuantities[$productId] = 1;
            $this->productCustomPrices[$productId] = '';
        }
    }

    public function removeProduct($productId): void
    {
        $this->selectedProducts = array_values(array_filter($this->selectedProducts, fn($id) => $id != $productId));
        unset($this->productQuantities[$productId]);
        unset($this->productCustomPrices[$productId]);
    }

    public function addCourse($courseId): void
    {
        if ($courseId && !in_array($courseId, $this->selectedCourses)) {
            $this->selectedCourses[] = (int)$courseId;
            $this->courseCustomPrices[$courseId] = '';
        }
    }

    public function removeCourse($courseId): void
    {
        $this->selectedCourses = array_values(array_filter($this->selectedCourses, fn($id) => $id != $courseId));
        unset($this->courseCustomPrices[$courseId]);
    }

    public function calculateOriginalPrice(): float
    {
        $total = 0;

        // Add product prices
        foreach ($this->selectedProducts as $productId) {
            $product = Product::find($productId);
            if ($product) {
                $customPrice = (float)($this->productCustomPrices[$productId] ?? 0);
                $price = $customPrice ?: (float)$product->base_price;
                $quantity = $this->productQuantities[$productId] ?? 1;
                $total += $price * $quantity;
            }
        }

        // Add course prices
        foreach ($this->selectedCourses as $courseId) {
            $course = Course::find($courseId);
            if ($course) {
                $customPrice = (float)($this->courseCustomPrices[$courseId] ?? 0);
                $price = $customPrice ?: (float)($course->feeSettings->fee_amount ?? 0);
                $total += $price;
            }
        }

        return $total;
    }

    public function save(): void
    {
        $this->validate();

        if (empty($this->selectedProducts) && empty($this->selectedCourses)) {
            $this->addError('items', 'Please add at least one product or course to the package.');
            return;
        }

        // Create the package
        $package = Package::create([
            'name' => $this->name,
            'slug' => $this->slug ?: Str::slug($this->name),
            'description' => $this->description,
            'short_description' => $this->short_description,
            'price' => $this->price,
            'original_price' => $this->calculateOriginalPrice(),
            'discount_type' => $this->discount_type,
            'discount_value' => $this->discount_value ?: 0,
            'status' => $this->status,
            'start_date' => $this->start_date ?: null,
            'end_date' => $this->end_date ?: null,
            'max_purchases' => $this->max_purchases ?: null,
            'track_stock' => $this->track_stock,
            'default_warehouse_id' => $this->default_warehouse_id,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'created_by' => auth()->id(),
        ]);

        // Add products to package
        foreach ($this->selectedProducts as $index => $productId) {
            $product = Product::find($productId);
            $package->items()->create([
                'itemable_type' => Product::class,
                'itemable_id' => $productId,
                'quantity' => $this->productQuantities[$productId] ?? 1,
                'warehouse_id' => $this->default_warehouse_id,
                'custom_price' => $this->productCustomPrices[$productId] ?: null,
                'original_price' => $product->base_price,
                'sort_order' => $index,
            ]);
        }

        // Add courses to package
        foreach ($this->selectedCourses as $index => $courseId) {
            $course = Course::find($courseId);
            $package->items()->create([
                'itemable_type' => Course::class,
                'itemable_id' => $courseId,
                'quantity' => 1,
                'custom_price' => $this->courseCustomPrices[$courseId] ?: null,
                'original_price' => $course->feeSettings->fee_amount ?? 0,
                'sort_order' => count($this->selectedProducts) + $index,
            ]);
        }

        session()->flash('success', 'Package created successfully!');
        $this->redirect(route('packages.show', $package));
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Create Package</flux:heading>
            <flux:text class="mt-2">Bundle products and courses together</flux:text>
        </div>
        <flux:button href="{{ route('packages.index') }}" variant="outline" icon="arrow-left">
            Back to Packages
        </flux:button>
    </div>

    <form wire:submit="save" class="space-y-8">
        <!-- Basic Information -->
        <div class="bg-white shadow sm:rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Basic Information</h3>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <flux:field>
                        <flux:label>Package Name</flux:label>
                        <flux:input wire:model.live="name" placeholder="e.g., Complete Course Bundle" />
                        <flux:error name="name" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Slug</flux:label>
                        <flux:input wire:model="slug" placeholder="complete-course-bundle" />
                        <flux:error name="slug" />
                    </flux:field>

                    <div class="md:col-span-2">
                        <flux:field>
                            <flux:label>Short Description</flux:label>
                            <flux:input wire:model="short_description" placeholder="Brief description for listings" />
                            <flux:error name="short_description" />
                        </flux:field>
                    </div>

                    <div class="md:col-span-2">
                        <flux:field>
                            <flux:label>Description</flux:label>
                            <flux:textarea wire:model="description" rows="4" placeholder="Detailed package description..." />
                            <flux:error name="description" />
                        </flux:field>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pricing -->
        <div class="bg-white shadow sm:rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Pricing</h3>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <flux:field>
                        <flux:label>Package Price (RM)</flux:label>
                        <flux:input type="number" step="0.01" wire:model.live="price" placeholder="99.00" />
                        <flux:error name="price" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Discount Type</flux:label>
                        <flux:select wire:model="discount_type">
                            <flux:select.option value="fixed">Fixed Amount</flux:select.option>
                            <flux:select.option value="percentage">Percentage</flux:select.option>
                        </flux:select>
                        <flux:error name="discount_type" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Discount Value</flux:label>
                        <flux:input type="number" step="0.01" wire:model="discount_value" placeholder="0" />
                        <flux:error name="discount_value" />
                    </flux:field>
                </div>

                @if(count($selectedProducts) > 0 || count($selectedCourses) > 0)
                    <div class="mt-4 p-4 bg-blue-50 rounded-lg">
                        <h4 class="text-sm font-medium text-blue-900">Pricing Summary</h4>
                        <div class="mt-2 text-sm text-blue-700">
                            <div>Original Total: RM {{ number_format($this->calculateOriginalPrice(), 2) }}</div>
                            <div>Package Price: RM {{ number_format((float)$price, 2) }}</div>
                            @if($this->calculateOriginalPrice() > (float)$price)
                                <div class="text-green-600 font-medium">
                                    Savings: RM {{ number_format($this->calculateOriginalPrice() - (float)$price, 2) }}
                                    ({{ $this->calculateOriginalPrice() > 0 ? round((($this->calculateOriginalPrice() - (float)$price) / $this->calculateOriginalPrice()) * 100, 2) : 0 }}% off)
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Package Items -->
        <div class="bg-white shadow sm:rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium leading-6 text-gray-900">Package Items</h3>
                    <div class="text-sm text-gray-500">
                        Total Items: {{ count($selectedProducts) + count($selectedCourses) }}
                    </div>
                </div>
                <flux:error name="items" />

                @if(count($selectedProducts) === 0 && count($selectedCourses) === 0)
                    <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <div class="flex items-start">
                            <flux:icon name="information-circle" class="h-5 w-5 text-blue-600 mr-2 mt-0.5" />
                            <div class="text-sm text-blue-700">
                                <p class="font-medium">Add multiple products and courses to your package</p>
                                <p class="mt-1">You can select multiple products (with different quantities) and multiple courses. For example: 2 products + 3 courses in one package!</p>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Products Section -->
                <div class="mb-6">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="text-md font-medium text-gray-900">Products</h4>
                        <flux:badge variant="outline" size="sm">{{ count($selectedProducts) }} selected</flux:badge>
                    </div>

                    <div class="mb-4">
                        <flux:field>
                            <flux:label>Add Products to Package</flux:label>
                            <flux:select wire:change="addProduct($event.target.value)" placeholder="Select products to add (you can add multiple)...">
                                <flux:select.option value="">+ Add a product to this package...</flux:select.option>
                            @foreach($products as $product)
                                @if(!in_array($product->id, $selectedProducts))
                                    <flux:select.option value="{{ $product->id }}">
                                        {{ $product->name }} - {{ $product->formatted_price }}
                                    </flux:select.option>
                                @endif
                            @endforeach
                        </flux:select>
                        </flux:field>
                    </div>

                    @if(count($selectedProducts) > 0)
                        <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg">
                            <div class="flex items-center text-sm text-green-700">
                                <flux:icon name="check-circle" class="h-4 w-4 mr-2" />
                                {{ count($selectedProducts) }} product{{ count($selectedProducts) > 1 ? 's' : '' }} added to package
                            </div>
                        </div>
                        <div class="space-y-3">
                            @foreach($selectedProducts as $productId)
                                @php $product = $products->find($productId) @endphp
                                @if($product)
                                    <div class="flex items-center space-x-4 p-4 border rounded-lg bg-gray-50">
                                        <div class="flex-1">
                                            <div class="font-medium">{{ $product->name }}</div>
                                            <div class="text-sm text-gray-500">{{ $product->category?->name ?? 'Uncategorized' }}</div>
                                        </div>

                                        <div class="w-24">
                                            <flux:field>
                                                <flux:label>Qty</flux:label>
                                                <flux:input
                                                    type="number"
                                                    min="1"
                                                    wire:model.live="productQuantities.{{ $productId }}"
                                                />
                                            </flux:field>
                                        </div>

                                        <div class="w-32">
                                            <flux:field>
                                                <flux:label>Custom Price</flux:label>
                                                <flux:input
                                                    type="number"
                                                    step="0.01"
                                                    wire:model.live="productCustomPrices.{{ $productId }}"
                                                    placeholder="{{ $product->base_price }}"
                                                />
                                            </flux:field>
                                        </div>

                                        <div class="text-right">
                                            <div class="text-sm font-medium">
                                                RM {{ number_format(((float)($productCustomPrices[$productId] ?? 0) ?: (float)$product->base_price) * ($productQuantities[$productId] ?? 1), 2) }}
                                            </div>
                                        </div>

                                        <flux:button
                                            wire:click="removeProduct({{ $productId }})"
                                            variant="outline"
                                            size="sm"
                                            icon="x-mark"
                                        >
                                        </flux:button>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>

                <!-- Courses Section -->
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="text-md font-medium text-gray-900">Courses</h4>
                        <flux:badge variant="outline" size="sm">{{ count($selectedCourses) }} selected</flux:badge>
                    </div>

                    <div class="mb-4">
                        <flux:field>
                            <flux:label>Add Courses to Package</flux:label>
                            <flux:select wire:change="addCourse($event.target.value)" placeholder="Select courses to add (you can add multiple)...">
                                <flux:select.option value="">+ Add a course to this package...</flux:select.option>
                            @foreach($courses as $course)
                                @if(!in_array($course->id, $selectedCourses))
                                    <flux:select.option value="{{ $course->id }}">
                                        {{ $course->name }} - {{ $course->formatted_fee }}
                                    </flux:select.option>
                                @endif
                            @endforeach
                        </flux:select>
                        </flux:field>
                    </div>

                    @if(count($selectedCourses) > 0)
                        <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg">
                            <div class="flex items-center text-sm text-green-700">
                                <flux:icon name="check-circle" class="h-4 w-4 mr-2" />
                                {{ count($selectedCourses) }} course{{ count($selectedCourses) > 1 ? 's' : '' }} added to package
                            </div>
                        </div>
                        <div class="space-y-3">
                            @foreach($selectedCourses as $courseId)
                                @php $course = $courses->find($courseId) @endphp
                                @if($course)
                                    <div class="flex items-center space-x-4 p-4 border rounded-lg bg-gray-50">
                                        <div class="flex-1">
                                            <div class="font-medium">{{ $course->name }}</div>
                                            <div class="text-sm text-gray-500">Course</div>
                                        </div>

                                        <div class="w-32">
                                            <flux:field>
                                                <flux:label>Custom Price</flux:label>
                                                <flux:input
                                                    type="number"
                                                    step="0.01"
                                                    wire:model.live="courseCustomPrices.{{ $courseId }}"
                                                    placeholder="{{ $course->feeSettings->fee_amount ?? 0 }}"
                                                />
                                            </flux:field>
                                        </div>

                                        <div class="text-right">
                                            <div class="text-sm font-medium">
                                                RM {{ number_format(((float)($courseCustomPrices[$courseId] ?? 0) ?: (float)($course->feeSettings->fee_amount ?? 0)), 2) }}
                                            </div>
                                        </div>

                                        <flux:button
                                            wire:click="removeCourse({{ $courseId }})"
                                            variant="outline"
                                            size="sm"
                                            icon="x-mark"
                                        >
                                        </flux:button>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Settings -->
        <div class="bg-white shadow sm:rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Settings</h3>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
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
                        <flux:label>Default Warehouse</flux:label>
                        <flux:select wire:model="default_warehouse_id">
                            <flux:select.option value="">Select warehouse...</flux:select.option>
                            @foreach($warehouses as $warehouse)
                                <flux:select.option value="{{ $warehouse->id }}">{{ $warehouse->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="default_warehouse_id" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Start Date</flux:label>
                        <flux:input type="date" wire:model="start_date" />
                        <flux:error name="start_date" />
                    </flux:field>

                    <flux:field>
                        <flux:label>End Date</flux:label>
                        <flux:input type="date" wire:model="end_date" />
                        <flux:error name="end_date" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Maximum Purchases</flux:label>
                        <flux:input type="number" wire:model="max_purchases" placeholder="Leave empty for unlimited" />
                        <flux:error name="max_purchases" />
                    </flux:field>

                    <div class="flex items-center space-x-3">
                        <flux:checkbox wire:model="track_stock" />
                        <flux:label>Track Stock for Products</flux:label>
                    </div>
                </div>
            </div>
        </div>

        <!-- SEO -->
        <div class="bg-white shadow sm:rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">SEO</h3>

                <div class="grid grid-cols-1 gap-6">
                    <flux:field>
                        <flux:label>Meta Title</flux:label>
                        <flux:input wire:model="meta_title" placeholder="SEO title for search engines" />
                        <flux:error name="meta_title" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Meta Description</flux:label>
                        <flux:textarea wire:model="meta_description" rows="3" placeholder="SEO description for search engines" />
                        <flux:error name="meta_description" />
                    </flux:field>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-end space-x-3">
            <flux:button href="{{ route('packages.index') }}" variant="outline">
                Cancel
            </flux:button>
            <flux:button type="submit" variant="primary">
                Create Package
            </flux:button>
        </div>
    </form>
</div>
