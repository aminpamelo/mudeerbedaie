<?php

use App\Models\WhatsAppGroupCollection;
use Livewire\Volt\Component;

new class extends Component
{
    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

    public bool $is_active = true;

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ];
    }

    public function with(): array
    {
        return [
            'collections' => WhatsAppGroupCollection::query()
                ->withCount(['items', 'activeItems'])
                ->latest()
                ->get(),
        ];
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $collection = WhatsAppGroupCollection::findOrFail($id);

        $this->editingId = $collection->id;
        $this->name = $collection->name;
        $this->description = (string) $collection->description;
        $this->is_active = $collection->is_active;

        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $attributes = [
            'name' => $this->name,
            'description' => $this->description ?: null,
            'is_active' => $this->is_active,
        ];

        if ($this->editingId) {
            WhatsAppGroupCollection::findOrFail($this->editingId)->update($attributes);
        } else {
            $attributes['created_by'] = auth()->id();
            WhatsAppGroupCollection::create($attributes);
        }

        session()->flash('success', $this->editingId ? 'Koleksi dikemas kini.' : 'Koleksi baru dicipta.');

        $this->showModal = false;
        $this->resetForm();
    }

    public function toggle(int $id): void
    {
        $collection = WhatsAppGroupCollection::findOrFail($id);
        $collection->update(['is_active' => ! $collection->is_active]);
    }

    public function delete(int $id): void
    {
        WhatsAppGroupCollection::findOrFail($id)->delete();

        session()->flash('success', 'Koleksi dipadam.');
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'description']);
        $this->is_active = true;
        $this->resetValidation();
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">WhatsApp Groups</flux:heading>
            <flux:text class="mt-2">Cipta koleksi group WhatsApp yang boleh dikongsi melalui satu link &amp; QR</flux:text>
        </div>
        <flux:button variant="primary" wire:click="create" icon="plus">
            Koleksi Baru
        </flux:button>
    </div>

    @if(session('success'))
        <flux:callout variant="success" class="mb-6" icon="check-circle">{{ session('success') }}</flux:callout>
    @endif

    @if($collections->isEmpty())
        <div class="grid place-items-center rounded-xl border border-dashed border-zinc-300 py-16 text-center dark:border-zinc-700">
            <flux:icon name="chat-bubble-left-right" class="h-10 w-10 text-zinc-300" />
            <flux:heading size="lg" class="mt-3">Belum ada koleksi</flux:heading>
            <flux:text class="mt-1">Cipta koleksi pertama untuk mula kumpulkan group WhatsApp.</flux:text>
        </div>
    @else
        <div class="grid gap-3 md:grid-cols-2">
            @foreach($collections as $collection)
                <div wire:key="collection-{{ $collection->id }}" class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:heading class="truncate">{{ $collection->name }}</flux:heading>
                                <flux:badge :color="$collection->is_active ? 'green' : 'zinc'" size="sm">
                                    {{ $collection->is_active ? 'Live' : 'Off' }}
                                </flux:badge>
                            </div>
                            @if($collection->description)
                                <flux:text size="sm" class="mt-1 line-clamp-2 text-zinc-400">{{ $collection->description }}</flux:text>
                            @endif
                            <flux:text size="sm" class="mt-2 text-zinc-500">
                                {{ $collection->active_items_count }} / {{ $collection->items_count }} group aktif
                            </flux:text>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center justify-end gap-2">
                        <flux:button size="sm" variant="outline" wire:click="toggle({{ $collection->id }})">
                            {{ $collection->is_active ? 'Disable' : 'Enable' }}
                        </flux:button>
                        <flux:button size="sm" variant="outline" wire:click="edit({{ $collection->id }})">Edit</flux:button>
                        <flux:button size="sm" variant="danger" wire:click="delete({{ $collection->id }})" wire:confirm="Padam koleksi ini beserta semua group di dalamnya?">Padam</flux:button>
                        <flux:button size="sm" variant="primary" icon="arrow-right" :href="route('admin.whatsapp-groups.manage', $collection)" wire:navigate>
                            Urus Group
                        </flux:button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <flux:modal wire:model.self="showModal" class="md:w-[32rem]">
        <form wire:submit="save" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Edit koleksi' : 'Koleksi baru' }}</flux:heading>
                <flux:text class="mt-1">Beri nama koleksi ini. Anda tambah group WhatsApp selepas ini.</flux:text>
            </div>

            <flux:field>
                <flux:label>Nama koleksi</flux:label>
                <flux:input wire:model="name" placeholder="Cth: Group Kelas Perubatan" />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>Penerangan</flux:label>
                <flux:textarea wire:model="description" rows="3" placeholder="Penerangan ringkas untuk orang awam (pilihan)" />
                <flux:error name="description" />
            </flux:field>

            <flux:field variant="inline">
                <flux:checkbox wire:model="is_active" />
                <flux:label>Aktif (link awam boleh diakses)</flux:label>
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">Batal</flux:button>
                <flux:button type="submit" variant="primary">
                    <span wire:loading.remove wire:target="save">{{ $editingId ? 'Simpan' : 'Cipta koleksi' }}</span>
                    <span wire:loading wire:target="save">Menyimpan…</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
