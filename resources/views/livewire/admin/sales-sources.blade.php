<?php

use App\Models\SalesSource;
use Livewire\Volt\Component;

new class extends Component
{
    public string $name = '';
    public string $description = '';
    public string $color = '#3B82F6';
    public bool $isActive = true;
    public int $sortOrder = 0;
    public ?int $editingId = null;
    public bool $showModal = false;

    public function mount(): void
    {
        if (! auth()->user()->hasAnyRole(['admin', 'employee', 'class_admin', 'sales'])) {
            abort(403, 'Access denied');
        }
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $source = SalesSource::findOrFail($id);
        $this->editingId = $source->id;
        $this->name = $source->name;
        $this->description = $source->description ?? '';
        $this->color = $source->color;
        $this->isActive = $source->is_active;
        $this->sortOrder = $source->sort_order;
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'color' => ['required', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'isActive' => ['boolean'],
            'sortOrder' => ['integer', 'min:0'],
        ]);

        SalesSource::updateOrCreate(
            ['id' => $this->editingId],
            [
                'name' => $validated['name'],
                'description' => $validated['description'],
                'color' => $validated['color'],
                'is_active' => $validated['isActive'],
                'sort_order' => $validated['sortOrder'],
            ]
        );

        $this->showModal = false;
        $this->resetForm();
    }

    public function toggleActive(int $id): void
    {
        $source = SalesSource::findOrFail($id);
        $source->update(['is_active' => ! $source->is_active]);
    }

    public function deleteSource(int $id): void
    {
        $source = SalesSource::findOrFail($id);
        if ($source->orders()->exists()) {
            session()->flash('error', 'Cannot delete a source that has orders. Deactivate it instead.');

            return;
        }
        $source->delete();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->description = '';
        $this->color = '#3B82F6';
        $this->isActive = true;
        $this->sortOrder = 0;
    }

    public function with(): array
    {
        return [
            'sources' => SalesSource::ordered()->get(),
        ];
    }
} ?>

<section>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Sales Sources</flux:heading>
            <flux:text class="mt-2">Manage sales source categories for POS orders</flux:text>
        </div>
        <flux:button variant="primary" wire:click="openCreateModal">Add Source</flux:button>
    </div>

    @if (session()->has('error'))
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/50 dark:text-red-400">
            {{ session('error') }}
        </div>
    @endif

    <div class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
            <thead class="bg-zinc-50 dark:bg-zinc-800">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Color</th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Description</th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Sort Order</th>
                    <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 bg-white dark:divide-zinc-700 dark:bg-zinc-900">
                @forelse ($sources as $source)
                    <tr wire:key="source-{{ $source->id }}">
                        <td class="whitespace-nowrap px-6 py-4">
                            <span class="inline-block h-4 w-4 rounded-full" style="background-color: {{ $source->color }}"></span>
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                            {{ $source->name }}
                        </td>
                        <td class="px-6 py-4 text-sm text-zinc-500 dark:text-zinc-400">
                            {{ $source->description ?? '-' }}
                        </td>
                        <td class="whitespace-nowrap px-6 py-4">
                            @if ($source->is_active)
                                <flux:badge color="green">Active</flux:badge>
                            @else
                                <flux:badge color="zinc">Inactive</flux:badge>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-zinc-500 dark:text-zinc-400">
                            {{ $source->sort_order }}
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                            <div class="flex items-center justify-end gap-2">
                                <flux:button variant="ghost" size="sm" wire:click="openEditModal({{ $source->id }})">
                                    <div class="flex items-center justify-center">
                                        <flux:icon name="pencil-square" class="mr-1 h-4 w-4" />
                                        Edit
                                    </div>
                                </flux:button>
                                <flux:button variant="ghost" size="sm" wire:click="toggleActive({{ $source->id }})">
                                    <div class="flex items-center justify-center">
                                        <flux:icon name="{{ $source->is_active ? 'eye-slash' : 'eye' }}" class="mr-1 h-4 w-4" />
                                        {{ $source->is_active ? 'Deactivate' : 'Activate' }}
                                    </div>
                                </flux:button>
                                <flux:button variant="ghost" size="sm" wire:click="deleteSource({{ $source->id }})" wire:confirm="Are you sure you want to delete this source?">
                                    <div class="flex items-center justify-center text-red-600 dark:text-red-400">
                                        <flux:icon name="trash" class="mr-1 h-4 w-4" />
                                        Delete
                                    </div>
                                </flux:button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-sm text-zinc-500 dark:text-zinc-400">
                            No sales sources found. Click "Add Source" to create one.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <flux:modal wire:model="showModal" class="max-w-md">
        <div class="p-6">
            <flux:heading size="lg" class="mb-4">{{ $editingId ? 'Edit Source' : 'Create Source' }}</flux:heading>

            <form wire:submit="save" class="space-y-4">
                <flux:field>
                    <flux:label>Name</flux:label>
                    <flux:input wire:model="name" placeholder="e.g. Walk-in, Social Media, Referral" />
                    @error('name') <flux:text class="text-sm text-red-500">{{ $message }}</flux:text> @enderror
                </flux:field>

                <flux:field>
                    <flux:label>Description</flux:label>
                    <flux:textarea wire:model="description" placeholder="Optional description" rows="2" />
                    @error('description') <flux:text class="text-sm text-red-500">{{ $message }}</flux:text> @enderror
                </flux:field>

                <div class="grid grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Color</flux:label>
                        <div class="flex items-center gap-2">
                            <input type="color" wire:model="color" class="h-10 w-10 cursor-pointer rounded border border-zinc-300 dark:border-zinc-600" />
                            <flux:input wire:model="color" class="flex-1" placeholder="#3B82F6" />
                        </div>
                        @error('color') <flux:text class="text-sm text-red-500">{{ $message }}</flux:text> @enderror
                    </flux:field>

                    <flux:field>
                        <flux:label>Sort Order</flux:label>
                        <flux:input type="number" wire:model="sortOrder" min="0" />
                        @error('sortOrder') <flux:text class="text-sm text-red-500">{{ $message }}</flux:text> @enderror
                    </flux:field>
                </div>

                <flux:field>
                    <flux:checkbox wire:model="isActive" label="Active" />
                </flux:field>

                <div class="flex justify-end gap-2 pt-2">
                    <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">Cancel</flux:button>
                    <flux:button type="submit" variant="primary">
                        {{ $editingId ? 'Update' : 'Create' }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</section>
