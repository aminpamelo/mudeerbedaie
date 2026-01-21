<?php

use App\Models\ProductCategory;
use Livewire\Volt\Component;

new class extends Component {
    public ProductCategory $category;

    public function mount(ProductCategory $category): void
    {
        $this->category = $category->load(['products', 'parent', 'children']);
    }

    public function delete(): void
    {
        if ($this->category->products()->count() > 0) {
            session()->flash('error', 'Cannot delete category with existing products.');
            return;
        }

        $this->category->delete();

        session()->flash('success', 'Category deleted successfully.');

        $this->redirect(route('product-categories.index'));
    }

    public function toggleStatus(): void
    {
        $this->category->update(['is_active' => !$this->category->is_active]);

        $status = $this->category->is_active ? 'activated' : 'deactivated';
        session()->flash('success', "Category {$status} successfully.");
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ $category->name }}</flux:heading>
            <flux:text class="mt-2">Category details and products</flux:text>
        </div>
        <div class="flex items-center space-x-3">
            <flux:button variant="outline" href="{{ route('product-categories.index') }}" icon="arrow-left">
                Back to Categories
            </flux:button>
            <flux:button href="{{ route('product-categories.edit', $category) }}" icon="pencil">
                Edit Category
            </flux:button>
        </div>
    </div>

    <!-- Category Status Badge -->
    <div class="mb-6">
        <flux:badge :variant="$category->is_active ? 'success' : 'gray'" size="lg">
            {{ $category->is_active ? 'Active' : 'Inactive' }}
        </flux:badge>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Main Information -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Basic Information -->
            <div class="rounded-lg border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-6">
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="lg">Category Information</flux:heading>
                    <flux:button wire:click="toggleStatus" variant="outline" size="sm">
                        {{ $category->is_active ? 'Deactivate' : 'Activate' }}
                    </flux:button>
                </div>

                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Name</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $category->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Slug</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                            <code class="text-sm">{{ $category->slug }}</code>
                        </dd>
                    </div>
                    @if($category->parent)
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Parent Category</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                <flux:badge variant="outline" size="sm">{{ $category->parent->name }}</flux:badge>
                            </dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Created</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $category->created_at->format('M j, Y g:i A') }}</dd>
                    </div>
                </dl>

                @if($category->description)
                    <div class="mt-6">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Description</dt>
                        <dd class="mt-2 text-sm text-gray-900 dark:text-gray-100">{{ $category->description }}</dd>
                    </div>
                @endif
            </div>

            <!-- Products in this Category -->
            @if($category->products->count() > 0)
                <div class="rounded-lg border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-6">
                    <flux:heading size="lg" class="mb-4">Products ({{ $category->products->count() }})</flux:heading>
                    <div class="space-y-4">
                        @foreach($category->products->take(10) as $product)
                            <div class="flex items-center justify-between border-b border-gray-100 dark:border-zinc-700 pb-3 last:border-b-0 last:pb-0">
                                <div>
                                    <div class="font-medium text-gray-900 dark:text-gray-100">{{ $product->name }}</div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">SKU: {{ $product->sku }}</div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <flux:badge :variant="match($product->status) {
                                        'active' => 'success',
                                        'inactive' => 'gray',
                                        'draft' => 'warning',
                                        default => 'gray'
                                    }" size="sm">
                                        {{ ucfirst($product->status) }}
                                    </flux:badge>
                                    <flux:button href="{{ route('products.show', $product) }}" variant="outline" size="sm">
                                        View
                                    </flux:button>
                                </div>
                            </div>
                        @endforeach
                        @if($category->products->count() > 10)
                            <div class="text-center pt-4">
                                <flux:text class="text-gray-500">
                                    And {{ $category->products->count() - 10 }} more products...
                                </flux:text>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Subcategories -->
            @if($category->children->count() > 0)
                <div class="rounded-lg border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-6">
                    <flux:heading size="lg" class="mb-4">Subcategories ({{ $category->children->count() }})</flux:heading>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        @foreach($category->children as $child)
                            <div class="border border-gray-200 dark:border-zinc-700 rounded-lg p-4">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="font-medium text-gray-900 dark:text-gray-100">{{ $child->name }}</h4>
                                    <flux:badge :variant="$child->is_active ? 'success' : 'gray'" size="sm">
                                        {{ $child->is_active ? 'Active' : 'Inactive' }}
                                    </flux:badge>
                                </div>
                                @if($child->description)
                                    <p class="text-sm text-gray-600 dark:text-gray-300 mb-3">{{ Str::limit($child->description, 100) }}</p>
                                @endif
                                <flux:button href="{{ route('product-categories.show', $child) }}" variant="outline" size="sm">
                                    View Category
                                </flux:button>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <!-- Sidebar Information -->
        <div class="space-y-6">
            <!-- Quick Stats -->
            <div class="rounded-lg border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-6">
                <flux:heading size="lg" class="mb-4">Quick Stats</flux:heading>
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Products</span>
                        <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $category->products->count() }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Subcategories</span>
                        <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $category->children->count() }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Active Products</span>
                        <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $category->products->where('status', 'active')->count() }}</span>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="rounded-lg border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-6">
                <flux:heading size="lg" class="mb-4">Actions</flux:heading>
                <div class="space-y-3">
                    <flux:button href="{{ route('product-categories.edit', $category) }}" variant="primary" class="w-full" icon="pencil">
                        Edit Category
                    </flux:button>
                    <flux:button wire:click="toggleStatus" variant="outline" class="w-full">
                        {{ $category->is_active ? 'Deactivate' : 'Activate' }} Category
                    </flux:button>
                    @if($category->products->count() === 0)
                        <flux:button
                            wire:click="delete"
                            wire:confirm="Are you sure you want to delete this category?"
                            variant="outline"
                            class="w-full text-red-600 border-red-200 hover:bg-red-50"
                            icon="trash"
                        >
                            Delete Category
                        </flux:button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>