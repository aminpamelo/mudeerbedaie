<?php

use App\Models\ClassCategory;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Illuminate\Support\Str;

new class extends Component {
    use WithPagination;

    public $search = '';
    public $showModal = false;
    public $editingCategory = null;

    // Form fields
    public $name = '';
    public $description = '';
    public $color = '#6366f1';
    public $icon = '';
    public $sort_order = 0;
    public $is_active = true;

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function getCategoriesProperty()
    {
        return ClassCategory::query()
            ->withCount('classes')
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('description', 'like', '%' . $this->search . '%');
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(10);
    }

    public function openCreateModal()
    {
        $this->resetForm();
        $this->editingCategory = null;
        $this->showModal = true;
    }

    public function openEditModal(ClassCategory $category)
    {
        $this->editingCategory = $category;
        $this->name = $category->name;
        $this->description = $category->description ?? '';
        $this->color = $category->color;
        $this->icon = $category->icon ?? '';
        $this->sort_order = $category->sort_order;
        $this->is_active = $category->is_active;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'color' => 'required|string|max:20',
            'icon' => 'nullable|string|max:50',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        $data = [
            'name' => $this->name,
            'slug' => Str::slug($this->name),
            'description' => $this->description ?: null,
            'color' => $this->color,
            'icon' => $this->icon ?: null,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ];

        if ($this->editingCategory) {
            $this->editingCategory->update($data);
            session()->flash('success', 'Category updated successfully.');
        } else {
            ClassCategory::create($data);
            session()->flash('success', 'Category created successfully.');
        }

        $this->closeModal();
    }

    public function delete(ClassCategory $category)
    {
        if ($category->classes()->count() > 0) {
            session()->flash('error', 'Cannot delete category with assigned classes.');
            return;
        }

        $category->delete();
        session()->flash('success', 'Category deleted successfully.');
    }

    public function toggleActive(ClassCategory $category)
    {
        $category->update(['is_active' => !$category->is_active]);
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->name = '';
        $this->description = '';
        $this->color = '#6366f1';
        $this->icon = '';
        $this->sort_order = 0;
        $this->is_active = true;
        $this->editingCategory = null;
    }
};

?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Class Categories</flux:heading>
            <flux:text class="mt-2">Manage categories to organize your classes</flux:text>
        </div>
        <flux:button variant="primary" wire:click="openCreateModal" icon="plus">
            Add Category
        </flux:button>
    </div>

    @if (session()->has('success'))
        <flux:callout variant="success" class="mb-4">
            {{ session('success') }}
        </flux:callout>
    @endif

    @if (session()->has('error'))
        <flux:callout variant="danger" class="mb-4">
            {{ session('error') }}
        </flux:callout>
    @endif

    <div class="space-y-6">
        <!-- Search -->
        <flux:card>
            <div class="p-4">
                <flux:input
                    wire:model.live="search"
                    placeholder="Search categories..."
                    icon="magnifying-glass"
                />
            </div>
        </flux:card>

        <!-- Categories List -->
        <flux:card>
            <div class="overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-zinc-700">
                    <flux:heading size="lg">Categories</flux:heading>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-700">
                        <thead class="bg-gray-50 dark:bg-zinc-700/50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Category</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Description</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Classes</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Order</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-zinc-800 divide-y divide-gray-200 dark:divide-zinc-700">
                            @forelse ($this->categories as $category)
                                <tr class="hover:bg-gray-50 dark:hover:bg-zinc-700/50 transition-colors duration-150">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="w-4 h-4 rounded-full flex-shrink-0"
                                                style="background-color: {{ $category->color }}"
                                            ></div>
                                            <div>
                                                <div class="font-medium text-gray-900 dark:text-gray-100">{{ $category->name }}</div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $category->slug }}</div>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-500 dark:text-gray-400 max-w-xs truncate">
                                            {{ $category->description ?? '-' }}
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <flux:badge size="sm" color="zinc">
                                            {{ $category->classes_count }} classes
                                        </flux:badge>
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ $category->sort_order }}</span>
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <flux:switch
                                            wire:click="toggleActive({{ $category->id }})"
                                            :checked="$category->is_active"
                                        />
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                icon="pencil"
                                                wire:click="openEditModal({{ $category->id }})"
                                            />
                                            @if($category->classes_count === 0)
                                                <flux:button
                                                    size="sm"
                                                    variant="ghost"
                                                    icon="trash"
                                                    wire:click="delete({{ $category->id }})"
                                                    wire:confirm="Are you sure you want to delete this category?"
                                                />
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center">
                                        <div class="text-gray-500 dark:text-gray-400">
                                            <flux:icon.folder class="h-12 w-12 mx-auto mb-4 text-gray-300 dark:text-gray-500" />
                                            <p class="text-gray-600 dark:text-gray-400">No categories found</p>
                                            <flux:button
                                                wire:click="openCreateModal"
                                                variant="ghost"
                                                size="sm"
                                                class="mt-2"
                                            >
                                                Create your first category
                                            </flux:button>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($this->categories->hasPages())
                    <div class="px-6 py-4 border-t border-gray-200 dark:border-zinc-700">
                        {{ $this->categories->links() }}
                    </div>
                @endif
            </div>
        </flux:card>
    </div>

    <!-- Create/Edit Modal -->
    <flux:modal wire:model="showModal" class="max-w-lg">
        <div class="p-6">
            <flux:heading size="lg" class="mb-4">
                {{ $editingCategory ? 'Edit Category' : 'Create Category' }}
            </flux:heading>

            <form wire:submit="save" class="space-y-4">
                <flux:field>
                    <flux:label>Name</flux:label>
                    <flux:input wire:model="name" placeholder="Enter category name" />
                    @error('name') <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text> @enderror
                </flux:field>

                <flux:field>
                    <flux:label>Description</flux:label>
                    <flux:textarea wire:model="description" placeholder="Optional description" rows="3" />
                    @error('description') <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text> @enderror
                </flux:field>

                <div class="grid grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Color</flux:label>
                        <div class="flex items-center gap-2">
                            <input
                                type="color"
                                wire:model="color"
                                class="w-10 h-10 rounded border border-gray-300 cursor-pointer"
                            />
                            <flux:input wire:model="color" class="flex-1" />
                        </div>
                        @error('color') <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text> @enderror
                    </flux:field>

                    <flux:field>
                        <flux:label>Sort Order</flux:label>
                        <flux:input type="number" wire:model="sort_order" min="0" />
                        @error('sort_order') <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text> @enderror
                    </flux:field>
                </div>

                <flux:field>
                    <flux:checkbox wire:model="is_active" label="Active" />
                </flux:field>

                <div class="flex justify-end gap-2 pt-4">
                    <flux:button type="button" variant="ghost" wire:click="closeModal">
                        Cancel
                    </flux:button>
                    <flux:button type="submit" variant="primary">
                        {{ $editingCategory ? 'Update' : 'Create' }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
