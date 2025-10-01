<?php

use App\Models\ProductCategory;
use Illuminate\Support\Str;
use Livewire\Volt\Component;

new class extends Component {
    public ProductCategory $category;

    public $name = '';
    public $slug = '';
    public $description = '';
    public $status = 'active';
    public $parent_id = '';

    public function mount(ProductCategory $category): void
    {
        $this->category = $category;
        $this->name = $category->name;
        $this->slug = $category->slug;
        $this->description = $category->description;
        $this->status = $category->status;
        $this->parent_id = $category->parent_id;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:product_categories,slug,' . $this->category->id,
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive',
            'parent_id' => 'nullable|exists:product_categories,id',
        ];
    }

    public function with(): array
    {
        return [
            'parentCategories' => ProductCategory::active()
                ->where('id', '!=', $this->category->id)
                ->ordered()
                ->get(),
        ];
    }

    public function updatedName(): void
    {
        $this->slug = Str::slug($this->name);
    }

    public function save(): void
    {
        $this->validate();

        $this->category->update([
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'status' => $this->status,
            'parent_id' => $this->parent_id ?: null,
        ]);

        session()->flash('success', 'Category updated successfully.');

        $this->redirect(route('product-categories.show', $this->category));
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Edit Category</flux:heading>
            <flux:text class="mt-2">Update category information</flux:text>
        </div>
        <flux:button variant="outline" href="{{ route('product-categories.index') }}" icon="arrow-left">
            Back to Categories
        </flux:button>
    </div>

    <form wire:submit="save" class="max-w-2xl space-y-6">
        <flux:field>
            <flux:label>Category Name</flux:label>
            <flux:input wire:model.live="name" placeholder="Enter category name" />
            <flux:error name="name" />
        </flux:field>

        <flux:field>
            <flux:label>Slug</flux:label>
            <flux:input wire:model="slug" placeholder="category-slug" />
            <flux:error name="slug" />
            <flux:description>URL-friendly version of the category name</flux:description>
        </flux:field>

        <flux:field>
            <flux:label>Description</flux:label>
            <flux:textarea wire:model="description" placeholder="Category description" rows="3" />
            <flux:error name="description" />
        </flux:field>

        <flux:field>
            <flux:label>Parent Category</flux:label>
            <flux:select wire:model="parent_id" placeholder="Select parent category (optional)">
                @foreach($parentCategories as $parent)
                    <flux:select.option value="{{ $parent->id }}">{{ $parent->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="parent_id" />
            <flux:description>Leave empty for top-level category</flux:description>
        </flux:field>

        <flux:field>
            <flux:label>Status</flux:label>
            <flux:select wire:model="status">
                <flux:select.option value="active">Active</flux:select.option>
                <flux:select.option value="inactive">Inactive</flux:select.option>
            </flux:select>
            <flux:error name="status" />
        </flux:field>

        <!-- Actions -->
        <div class="flex items-center justify-end space-x-3 border-t border-gray-200 pt-6">
            <flux:button variant="outline" href="{{ route('product-categories.show', $category) }}">
                Cancel
            </flux:button>
            <flux:button type="submit" variant="primary">
                Update Category
            </flux:button>
        </div>
    </form>
</div>