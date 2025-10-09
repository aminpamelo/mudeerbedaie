<?php

use App\Models\ProductCategory;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $search = '';
    public $statusFilter = '';

    public function getCategorySummary(): array
    {
        $totalCategories = ProductCategory::count();
        $parentCategories = ProductCategory::rootCategories()->count();
        $activeCategories = ProductCategory::where('is_active', true)->count();

        return [
            'total' => $totalCategories,
            'parents' => $parentCategories,
            'children' => $totalCategories - $parentCategories,
            'active' => $activeCategories,
            'inactive' => $totalCategories - $activeCategories,
        ];
    }

    public function with(): array
    {
        return [
            'categories' => ProductCategory::query()
                ->with('parent')
                ->withCount('products')
                ->when($this->search, fn($query) => $query->where('name', 'like', "%{$this->search}%")
                    ->orWhere('description', 'like', "%{$this->search}%"))
                ->when($this->statusFilter, function($query) {
                    if ($this->statusFilter === 'active') {
                        return $query->where('is_active', true);
                    } elseif ($this->statusFilter === 'inactive') {
                        return $query->where('is_active', false);
                    }
                    return $query;
                })
                ->orderByRaw('parent_id IS NULL DESC')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(15),
            'summary' => $this->getCategorySummary(),
        ];
    }

    public function delete(ProductCategory $category): void
    {
        if ($category->products()->count() > 0) {
            session()->flash('error', 'Cannot delete category with existing products.');
            return;
        }

        $category->delete();

        session()->flash('success', 'Category deleted successfully.');
    }

    public function toggleStatus(ProductCategory $category): void
    {
        $category->update(['is_active' => !$category->is_active]);

        $status = $category->is_active ? 'activated' : 'deactivated';
        session()->flash('success', "Category {$status} successfully.");
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'statusFilter']);
        $this->resetPage();
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Product Categories</flux:heading>
            <flux:text class="mt-2">Organize your products into categories with hierarchical structure</flux:text>
        </div>
        <flux:button variant="primary" href="{{ route('product-categories.create') }}" icon="plus">
            Add Category
        </flux:button>
    </div>

    <!-- Category Summary -->
    <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-5">
        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <flux:icon name="squares-2x2" class="h-8 w-8 text-blue-600" />
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-gray-500">Total Categories</p>
                    <p class="text-lg font-semibold text-gray-900">{{ $summary['total'] }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <flux:icon name="folder" class="h-8 w-8 text-purple-600" />
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-gray-500">Parent Categories</p>
                    <p class="text-lg font-semibold text-gray-900">{{ $summary['parents'] }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <flux:icon name="document" class="h-8 w-8 text-indigo-600" />
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-gray-500">Sub Categories</p>
                    <p class="text-lg font-semibold text-gray-900">{{ $summary['children'] }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <flux:icon name="check-circle" class="h-8 w-8 text-green-600" />
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-gray-500">Active</p>
                    <p class="text-lg font-semibold text-gray-900">{{ $summary['active'] }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <flux:icon name="pause-circle" class="h-8 w-8 text-gray-600" />
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-gray-500">Inactive</p>
                    <p class="text-lg font-semibold text-gray-900">{{ $summary['inactive'] }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
        <flux:input
            wire:model.live.debounce.300ms="search"
            placeholder="Search categories..."
            icon="magnifying-glass"
        />

        <flux:select wire:model.live="statusFilter" placeholder="All Statuses">
            <flux:select.option value="">All Statuses</flux:select.option>
            <flux:select.option value="active">Active</flux:select.option>
            <flux:select.option value="inactive">Inactive</flux:select.option>
        </flux:select>

        <flux:button wire:click="clearFilters" variant="outline" icon="x-mark">
            Clear Filters
        </flux:button>
    </div>

    <!-- Categories Table -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse border-0">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6">
                            <div class="flex items-center">
                                <flux:icon name="squares-2x2" class="h-4 w-4 mr-2 text-gray-500" />
                                Category Hierarchy
                            </div>
                        </th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Description</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Products</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Status</th>
                        <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                            <span class="sr-only">Actions</span>
                            <span class="text-sm font-semibold text-gray-900">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white">
                    @forelse($categories as $category)
                        <tr wire:key="category-{{ $category->id }}" class="border-b border-gray-200 hover:bg-gray-50 {{ $category->hasParent() ? 'bg-blue-25' : '' }}">
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm sm:pl-6">
                            <div class="flex items-center">
                                @if($category->hasParent())
                                    <div class="mr-2 flex items-center text-gray-400">
                                        <flux:icon name="arrow-turn-down-right" class="h-4 w-4 mr-1" />
                                        <span class="text-xs">{{ $category->parent->name }} &gt;</span>
                                    </div>
                                @endif
                                <div class="{{ $category->hasParent() ? 'ml-2' : '' }}">
                                    <div class="font-medium text-gray-900 flex items-center">
                                        @if($category->hasChildren())
                                            <flux:icon name="folder" class="h-4 w-4 mr-2 text-blue-500" />
                                        @else
                                            <flux:icon name="document" class="h-4 w-4 mr-2 text-gray-400" />
                                        @endif
                                        {{ $category->name }}
                                    </div>
                                    <div class="text-gray-500">{{ $category->slug }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-900">
                            <p class="max-w-xs truncate">{{ $category->description ?: '-' }}</p>
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-900">
                            <flux:badge variant="outline" size="sm">
                                {{ $category->products_count }} products
                            </flux:badge>
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-900">
                            <flux:badge :variant="$category->is_active ? 'success' : 'gray'" size="sm">
                                {{ $category->is_active ? 'Active' : 'Inactive' }}
                            </flux:badge>
                        </td>
                        <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                            <div class="flex items-center justify-end space-x-2">
                                <flux:button
                                    href="{{ route('product-categories.show', $category) }}"
                                    variant="outline"
                                    size="sm"
                                    icon="eye"
                                >
                                    View
                                </flux:button>
                                <flux:button
                                    href="{{ route('product-categories.edit', $category) }}"
                                    variant="outline"
                                    size="sm"
                                    icon="pencil"
                                >
                                    Edit
                                </flux:button>
                                <flux:button
                                    wire:click="toggleStatus({{ $category->id }})"
                                    variant="outline"
                                    size="sm"
                                    :icon="$category->is_active ? 'pause' : 'play'"
                                >
                                    {{ $category->is_active ? 'Deactivate' : 'Activate' }}
                                </flux:button>
                                @if($category->products_count === 0)
                                    <flux:button
                                        wire:click="delete({{ $category->id }})"
                                        wire:confirm="Are you sure you want to delete this category?"
                                        variant="outline"
                                        size="sm"
                                        icon="trash"
                                        class="text-red-600 border-red-200 hover:bg-red-50"
                                    >
                                        Delete
                                    </flux:button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center">
                            <div>
                                <flux:icon name="folder" class="mx-auto h-12 w-12 text-gray-400" />
                                <h3 class="mt-2 text-sm font-medium text-gray-900">No categories found</h3>
                                <p class="mt-1 text-sm text-gray-500">Get started by creating your first category.</p>
                                <div class="mt-6">
                                    <flux:button variant="primary" href="{{ route('product-categories.create') }}" icon="plus">
                                        Add Category
                                    </flux:button>
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <div class="mt-6">
        {{ $categories->links() }}
    </div>
</div>