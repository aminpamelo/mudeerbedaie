<?php

use App\Models\ProductAttributeTemplate;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $search = '';
    public $typeFilter = '';

    public function with(): array
    {
        return [
            'attributes' => ProductAttributeTemplate::query()
                ->when($this->search, fn($query) => $query->where('name', 'like', "%{$this->search}%")
                    ->orWhere('label', 'like', "%{$this->search}%"))
                ->when($this->typeFilter, fn($query) => $query->where('type', $this->typeFilter))
                ->latest()
                ->paginate(15),
        ];
    }

    public function delete(ProductAttributeTemplate $attribute): void
    {
        // Check if attribute is being used by any products
        if ($attribute->productVariants()->count() > 0) {
            session()->flash('error', 'Cannot delete attribute that is being used by product variants.');
            return;
        }

        $attribute->delete();

        session()->flash('success', 'Attribute deleted successfully.');
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'typeFilter']);
        $this->resetPage();
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Product Attributes</flux:heading>
            <flux:text class="mt-2">Define attributes for variable products (Size, Color, Material, etc.)</flux:text>
        </div>
        <flux:button variant="primary" href="{{ route('product-attributes.create') }}" icon="plus">
            Add Attribute
        </flux:button>
    </div>

    <!-- Filters -->
    <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
        <flux:input
            wire:model.live.debounce.300ms="search"
            placeholder="Search attributes..."
            icon="magnifying-glass"
        />

        <flux:select wire:model.live="typeFilter" placeholder="All Types">
            <flux:select.option value="">All Types</flux:select.option>
            <flux:select.option value="text">Text</flux:select.option>
            <flux:select.option value="select">Select</flux:select.option>
            <flux:select.option value="color">Color</flux:select.option>
            <flux:select.option value="number">Number</flux:select.option>
        </flux:select>

        <flux:button wire:click="clearFilters" variant="outline" icon="x-mark">
            Clear Filters
        </flux:button>
    </div>

    <!-- Attributes Table -->
    <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse border-0">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6">Attribute</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Type</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Values</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Required</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Used in Products</th>
                        <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                            <span class="sr-only">Actions</span>
                            <span class="text-sm font-semibold text-gray-900">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-zinc-800">
                    @forelse($attributes as $attribute)
                        <tr wire:key="attribute-{{ $attribute->id }}" class="border-b border-gray-200 hover:bg-gray-50">
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm sm:pl-6">
                            <div>
                                <div class="font-medium text-gray-900">{{ $attribute->label }}</div>
                                <div class="text-gray-500">{{ $attribute->name }}</div>
                            </div>
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-900">
                            <flux:badge variant="outline" size="sm">
                                {{ ucfirst($attribute->type) }}
                            </flux:badge>
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-900">
                            @if($attribute->values)
                                <div class="flex flex-wrap gap-1">
                                    @foreach(array_slice($attribute->values, 0, 3) as $value)
                                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800">
                                            {{ $value }}
                                        </span>
                                    @endforeach
                                    @if(count($attribute->values) > 3)
                                        <span class="text-xs text-gray-500">+{{ count($attribute->values) - 3 }} more</span>
                                    @endif
                                </div>
                            @else
                                <span class="text-gray-400">No values</span>
                            @endif
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-900">
                            <flux:badge :variant="$attribute->is_required ? 'warning' : 'gray'" size="sm">
                                {{ $attribute->is_required ? 'Required' : 'Optional' }}
                            </flux:badge>
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-900">
                            <flux:badge variant="outline" size="sm">
                                {{ $attribute->productVariants()->count() }} variants
                            </flux:badge>
                        </td>
                        <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                            <div class="flex items-center justify-end space-x-2">
                                <flux:button
                                    href="{{ route('product-attributes.show', $attribute) }}"
                                    variant="outline"
                                    size="sm"
                                    icon="eye"
                                >
                                    View
                                </flux:button>
                                <flux:button
                                    href="{{ route('product-attributes.edit', $attribute) }}"
                                    variant="outline"
                                    size="sm"
                                    icon="pencil"
                                >
                                    Edit
                                </flux:button>
                                @if($attribute->productVariants()->count() === 0)
                                    <flux:button
                                        wire:click="delete({{ $attribute->id }})"
                                        wire:confirm="Are you sure you want to delete this attribute?"
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
                        <td colspan="6" class="px-6 py-12 text-center">
                            <div>
                                <flux:icon name="tag" class="mx-auto h-12 w-12 text-gray-400" />
                                <h3 class="mt-2 text-sm font-medium text-gray-900">No attributes found</h3>
                                <p class="mt-1 text-sm text-gray-500">Get started by creating product attributes like Size, Color, Material.</p>
                                <div class="mt-6">
                                    <flux:button variant="primary" href="{{ route('product-attributes.create') }}" icon="plus">
                                        Add Attribute
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
        {{ $attributes->links() }}
    </div>
</div>