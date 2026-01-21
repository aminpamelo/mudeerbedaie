<?php

use App\Models\ProductAttributeTemplate;
use Livewire\Volt\Component;

new class extends Component {
    public ProductAttributeTemplate $attribute;

    public function mount(ProductAttributeTemplate $attribute): void
    {
        $this->attribute = $attribute->load(['productVariants.product']);
    }

    public function delete(): void
    {
        if ($this->attribute->productVariants()->count() > 0) {
            session()->flash('error', 'Cannot delete attribute that is being used by product variants.');
            return;
        }

        $this->attribute->delete();

        session()->flash('success', 'Attribute deleted successfully.');

        $this->redirect(route('product-attributes.index'));
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ $attribute->label }}</flux:heading>
            <flux:text class="mt-2">Product attribute details and usage information</flux:text>
        </div>
        <div class="flex items-center space-x-3">
            <flux:button variant="outline" href="{{ route('product-attributes.index') }}" icon="arrow-left">
                Back to Attributes
            </flux:button>
            <flux:button href="{{ route('product-attributes.edit', $attribute) }}" icon="pencil">
                Edit Attribute
            </flux:button>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Main Information -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Basic Information -->
            <div class="rounded-lg border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-6">
                <flux:heading size="lg" class="mb-4">Attribute Information</flux:heading>

                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Label</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $attribute->label }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Name</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                            <code class="text-sm">{{ $attribute->name }}</code>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Type</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                            <flux:badge variant="outline" size="sm">
                                {{ ucfirst($attribute->type) }}
                            </flux:badge>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Required</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                            <flux:badge :variant="$attribute->is_required ? 'warning' : 'gray'" size="sm">
                                {{ $attribute->is_required ? 'Required' : 'Optional' }}
                            </flux:badge>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Created</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $attribute->created_at->format('M j, Y g:i A') }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Last Updated</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $attribute->updated_at->format('M j, Y g:i A') }}</dd>
                    </div>
                </dl>

                @if($attribute->description)
                    <div class="mt-6">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Description</dt>
                        <dd class="mt-2 text-sm text-gray-900 dark:text-gray-100">{{ $attribute->description }}</dd>
                    </div>
                @endif
            </div>

            <!-- Attribute Values -->
            @if($attribute->values)
                <div class="rounded-lg border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-6">
                    <flux:heading size="lg" class="mb-4">Available Values</flux:heading>
                    <div class="flex flex-wrap gap-2">
                        @foreach($attribute->values as $value)
                            <span class="inline-flex items-center rounded-full bg-blue-100 dark:bg-blue-900/30 px-3 py-1 text-sm font-medium text-blue-800 dark:text-blue-300">
                                {{ $value }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Usage in Products -->
            @if($attribute->productVariants->count() > 0)
                <div class="rounded-lg border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-6">
                    <flux:heading size="lg" class="mb-4">Used in Products ({{ $attribute->productVariants->count() }} variants)</flux:heading>
                    <div class="space-y-4">
                        @foreach($attribute->productVariants->take(10) as $variant)
                            <div class="flex items-center justify-between border-b border-gray-100 dark:border-zinc-700 pb-3 last:border-b-0 last:pb-0">
                                <div>
                                    <div class="font-medium text-gray-900 dark:text-gray-100">{{ $variant->product->name }}</div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        SKU: {{ $variant->sku }} •
                                        @if($variant->attributes && isset($variant->attributes[$attribute->name]))
                                            {{ $attribute->label }}: {{ $variant->attributes[$attribute->name] }}
                                        @endif
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ number_format($variant->price, 2) }} {{ config('app.currency', 'MYR') }}
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        Stock: {{ number_format($variant->stockLevels->sum('quantity')) }}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                        @if($attribute->productVariants->count() > 10)
                            <div class="text-center pt-4">
                                <flux:text class="text-gray-500">
                                    And {{ $attribute->productVariants->count() - 10 }} more variants...
                                </flux:text>
                            </div>
                        @endif
                    </div>
                </div>
            @else
                <div class="rounded-lg border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-6">
                    <div class="text-center">
                        <flux:icon name="tag" class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" />
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">Not used yet</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">This attribute hasn't been used in any product variants yet.</p>
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
                        <span class="text-sm text-gray-500 dark:text-gray-400">Type</span>
                        <span class="font-semibold text-gray-900 dark:text-gray-100">{{ ucfirst($attribute->type) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Variants Using</span>
                        <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $attribute->productVariants->count() }}</span>
                    </div>
                    @if($attribute->values)
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500 dark:text-gray-400">Available Values</span>
                            <span class="font-semibold text-gray-900 dark:text-gray-100">{{ count($attribute->values) }}</span>
                        </div>
                    @endif
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Required</span>
                        <span class="font-semibold {{ $attribute->is_required ? 'text-yellow-600' : 'text-gray-900' }}">
                            {{ $attribute->is_required ? 'Yes' : 'No' }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="rounded-lg border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-6">
                <flux:heading size="lg" class="mb-4">Actions</flux:heading>
                <div class="space-y-3">
                    <flux:button href="{{ route('product-attributes.edit', $attribute) }}" variant="primary" class="w-full" icon="pencil">
                        Edit Attribute
                    </flux:button>
                    @if($attribute->productVariants->count() === 0)
                        <flux:button
                            wire:click="delete"
                            wire:confirm="Are you sure you want to delete this attribute?"
                            variant="outline"
                            class="w-full text-red-600 border-red-200 hover:bg-red-50"
                            icon="trash"
                        >
                            Delete Attribute
                        </flux:button>
                    @else
                        <div class="rounded-md bg-yellow-50 dark:bg-yellow-900/30 p-3">
                            <div class="text-sm text-yellow-800 dark:text-yellow-300">
                                <strong>Cannot delete:</strong> This attribute is being used by {{ $attribute->productVariants->count() }} product variant(s).
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>