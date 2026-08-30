<?php

use App\Models\ClassModel;
use App\Models\WhatsAppGroupCollection;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

new class extends Component
{
    public WhatsAppGroupCollection $collection;

    public bool $showModal = false;

    public ?int $editingItemId = null;

    public string $classSearch = '';

    public ?int $class_id = null;

    public string $label = '';

    public string $description = '';

    public string $invite_link = '';

    public bool $item_is_active = true;

    public function mount(WhatsAppGroupCollection $collection): void
    {
        $this->collection = $collection;
    }

    public function rules(): array
    {
        return [
            'class_id' => 'nullable|integer|exists:classes,id',
            'label' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:500',
            'invite_link' => 'nullable|url|max:2048',
            'item_is_active' => 'boolean',
        ];
    }

    #[Computed]
    public function items()
    {
        return $this->collection->items()->with('class')->get();
    }

    #[Computed]
    public function classOptions()
    {
        return ClassModel::query()
            ->whereNotNull('whatsapp_group_link')
            ->where('whatsapp_group_link', '!=', '')
            ->when($this->classSearch, fn ($q) => $q->where('title', 'like', '%'.$this->classSearch.'%'))
            ->orderBy('title')
            ->limit(50)
            ->get(['id', 'title', 'whatsapp_group_link']);
    }

    public function qrSvg(): string
    {
        return QrCode::format('svg')->size(200)->margin(1)->generate($this->collection->public_url);
    }

    public function addItem(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function editItem(int $id): void
    {
        $item = $this->collection->items()->findOrFail($id);

        $this->editingItemId = $item->id;
        $this->class_id = $item->class_id;
        $this->label = (string) $item->label;
        $this->description = (string) $item->description;
        $this->invite_link = (string) $item->invite_link;
        $this->item_is_active = $item->is_active;

        $this->resetValidation();
        $this->showModal = true;
    }

    public function saveItem(): void
    {
        $this->validate();

        if (! $this->class_id && ! $this->invite_link) {
            $this->addError('invite_link', 'Pilih satu kelas atau masukkan link jemputan secara manual.');

            return;
        }

        $attributes = [
            'class_id' => $this->class_id,
            'label' => $this->label ?: null,
            'description' => $this->description ?: null,
            'invite_link' => $this->invite_link ?: null,
            'is_active' => $this->item_is_active,
        ];

        if ($this->editingItemId) {
            $this->collection->items()->findOrFail($this->editingItemId)->update($attributes);
        } else {
            $attributes['sort_order'] = (int) ($this->collection->items()->max('sort_order') ?? -1) + 1;
            $this->collection->items()->create($attributes);
        }

        session()->flash('success', $this->editingItemId ? 'Group dikemas kini.' : 'Group ditambah.');

        $this->showModal = false;
        $this->resetForm();
        unset($this->items);
    }

    public function toggleItem(int $id): void
    {
        $item = $this->collection->items()->findOrFail($id);
        $item->update(['is_active' => ! $item->is_active]);
        unset($this->items);
    }

    public function deleteItem(int $id): void
    {
        $this->collection->items()->findOrFail($id)->delete();
        unset($this->items);
        session()->flash('success', 'Group dibuang.');
    }

    public function moveUp(int $id): void
    {
        $this->swap($id, 'up');
    }

    public function moveDown(int $id): void
    {
        $this->swap($id, 'down');
    }

    private function swap(int $id, string $direction): void
    {
        $items = $this->collection->items()->orderBy('sort_order')->get();
        $index = $items->search(fn ($i) => $i->id === $id);

        if ($index === false) {
            return;
        }

        $swapIndex = $direction === 'up' ? $index - 1 : $index + 1;

        if ($swapIndex < 0 || $swapIndex >= $items->count()) {
            return;
        }

        $current = $items[$index];
        $other = $items[$swapIndex];

        $currentOrder = $current->sort_order;
        $current->update(['sort_order' => $other->sort_order]);
        $other->update(['sort_order' => $currentOrder]);

        unset($this->items);
    }

    public function updatedClassId($value): void
    {
        if ($value && ! $this->label) {
            $class = ClassModel::find($value);
            if ($class) {
                $this->label = $class->title;
            }
        }
    }

    private function resetForm(): void
    {
        $this->reset(['editingItemId', 'class_id', 'label', 'description', 'invite_link', 'classSearch']);
        $this->item_is_active = true;
        $this->resetValidation();
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:button variant="ghost" size="sm" icon="arrow-left" :href="route('admin.whatsapp-groups.index')" wire:navigate class="mb-2">
                Kembali
            </flux:button>
            <flux:heading size="xl">{{ $collection->name }}</flux:heading>
            <flux:text class="mt-1">Urus group WhatsApp dalam koleksi ini</flux:text>
        </div>
        <flux:button variant="primary" wire:click="addItem" icon="plus">
            Tambah Group
        </flux:button>
    </div>

    @if(session('success'))
        <flux:callout variant="success" class="mb-6" icon="check-circle">{{ session('success') }}</flux:callout>
    @endif

    <div class="mb-6 grid gap-6 lg:grid-cols-3">
        {{-- Public link + QR --}}
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900 lg:col-span-2">
            <flux:heading size="lg">Link Awam &amp; QR</flux:heading>
            <flux:text class="mt-1">Kongsi link atau QR ini kepada orang ramai untuk mereka pilih group.</flux:text>

            <div class="mt-4 flex flex-col gap-5 sm:flex-row sm:items-center">
                <div
                    class="flex shrink-0 flex-col items-center gap-2"
                    x-data="{
                        downloadQr() {
                            const svg = $refs.qrBox.querySelector('svg');
                            if (! svg) { return; }
                            const xml = new XMLSerializer().serializeToString(svg);
                            const blob = new Blob([xml], { type: 'image/svg+xml;charset=utf-8' });
                            const url = URL.createObjectURL(blob);
                            const img = new Image();
                            img.onload = () => {
                                const size = 1024;
                                const canvas = document.createElement('canvas');
                                canvas.width = size;
                                canvas.height = size;
                                const ctx = canvas.getContext('2d');
                                ctx.fillStyle = '#ffffff';
                                ctx.fillRect(0, 0, size, size);
                                ctx.drawImage(img, 0, 0, size, size);
                                URL.revokeObjectURL(url);
                                const link = document.createElement('a');
                                link.href = canvas.toDataURL('image/png');
                                link.download = 'qr-{{ $collection->slug }}.png';
                                link.click();
                            };
                            img.src = url;
                        }
                    }"
                >
                    <div x-ref="qrBox" class="rounded-lg border border-zinc-200 bg-white p-3 dark:border-zinc-700">
                        {!! $this->qrSvg() !!}
                    </div>
                    <flux:button type="button" size="sm" variant="ghost" icon="arrow-down-tray" class="w-full" x-on:click="downloadQr()">
                        Muat turun QR
                    </flux:button>
                </div>

                <div class="min-w-0 flex-1" x-data="{ copied: false }">
                    <flux:label>URL Awam</flux:label>
                    <div class="mt-1 flex items-center gap-2">
                        <flux:input readonly value="{{ $collection->public_url }}" class="font-mono text-sm" />
                        <flux:button
                            type="button"
                            icon="clipboard"
                            x-on:click="navigator.clipboard.writeText('{{ $collection->public_url }}'); copied = true; setTimeout(() => copied = false, 1500)"
                        >
                            <span x-show="!copied">Salin</span>
                            <span x-show="copied" x-cloak>Disalin!</span>
                        </flux:button>
                    </div>
                    @unless($collection->is_active)
                        <flux:callout variant="warning" class="mt-3" icon="exclamation-triangle">
                            Koleksi ini tidak aktif — link awam akan papar 404. Aktifkan di halaman senarai.
                        </flux:callout>
                    @endunless
                    <flux:button variant="ghost" size="sm" icon="arrow-top-right-on-square" href="{{ $collection->public_url }}" target="_blank" class="mt-3">
                        Buka link awam
                    </flux:button>
                </div>
            </div>
        </div>

        {{-- Summary --}}
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg">Ringkasan</flux:heading>
            <dl class="mt-4 space-y-3">
                <div class="flex items-center justify-between">
                    <dt class="text-sm text-zinc-500">Jumlah group</dt>
                    <dd class="font-semibold">{{ $this->items->count() }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-sm text-zinc-500">Group aktif</dt>
                    <dd class="font-semibold">{{ $this->items->where('is_active', true)->count() }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-sm text-zinc-500">Status koleksi</dt>
                    <dd>
                        <flux:badge :color="$collection->is_active ? 'green' : 'zinc'" size="sm">
                            {{ $collection->is_active ? 'Live' : 'Off' }}
                        </flux:badge>
                    </dd>
                </div>
            </dl>
        </div>
    </div>

    {{-- Group list --}}
    @if($this->items->isEmpty())
        <div class="grid place-items-center rounded-xl border border-dashed border-zinc-300 py-16 text-center dark:border-zinc-700">
            <flux:icon name="user-group" class="h-10 w-10 text-zinc-300" />
            <flux:heading size="lg" class="mt-3">Belum ada group</flux:heading>
            <flux:text class="mt-1">Tambah group WhatsApp dari kelas sedia ada.</flux:text>
        </div>
    @else
        <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-left text-sm">
                <thead class="bg-zinc-50 text-zinc-500 dark:bg-zinc-800">
                    <tr>
                        <th class="px-4 py-3 font-medium">Group</th>
                        <th class="px-4 py-3 font-medium">Link</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 text-right font-medium">Tindakan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach($this->items as $item)
                        <tr wire:key="item-{{ $item->id }}" class="bg-white dark:bg-zinc-900">
                            <td class="px-4 py-3">
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $item->display_label }}</div>
                                @if($item->description)
                                    <div class="text-xs text-zinc-400">{{ $item->description }}</div>
                                @endif
                                @if($item->class)
                                    <flux:badge size="sm" color="blue" class="mt-1">{{ $item->class->title }}</flux:badge>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if($item->effective_link)
                                    <a href="{{ $item->effective_link }}" target="_blank" class="text-emerald-600 hover:underline">Lihat link</a>
                                @else
                                    <span class="text-red-500">Tiada link</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <flux:badge :color="$item->is_active ? 'green' : 'zinc'" size="sm">
                                    {{ $item->is_active ? 'Aktif' : 'Off' }}
                                </flux:badge>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <flux:button size="xs" variant="ghost" icon="chevron-up" wire:click="moveUp({{ $item->id }})" />
                                    <flux:button size="xs" variant="ghost" icon="chevron-down" wire:click="moveDown({{ $item->id }})" />
                                    <flux:button size="xs" variant="outline" wire:click="toggleItem({{ $item->id }})">
                                        {{ $item->is_active ? 'Disable' : 'Enable' }}
                                    </flux:button>
                                    <flux:button size="xs" variant="outline" wire:click="editItem({{ $item->id }})">Edit</flux:button>
                                    <flux:button size="xs" variant="danger" wire:click="deleteItem({{ $item->id }})" wire:confirm="Buang group ini dari koleksi?">Buang</flux:button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <flux:modal wire:model.self="showModal" class="md:w-[34rem]">
        <form wire:submit="saveItem" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ $editingItemId ? 'Edit group' : 'Tambah group' }}</flux:heading>
                <flux:text class="mt-1">Pilih kelas sedia ada — link jemputan WhatsApp akan ditarik automatik.</flux:text>
            </div>

            <flux:field>
                <flux:label>Cari kelas</flux:label>
                <flux:input wire:model.live.debounce.300ms="classSearch" placeholder="Taip nama kelas…" icon="magnifying-glass" />
            </flux:field>

            <flux:field>
                <flux:label>Kelas</flux:label>
                <flux:select wire:model.live="class_id" placeholder="Pilih kelas">
                    @foreach($this->classOptions as $class)
                        <flux:select.option value="{{ $class->id }}">{{ $class->title }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:description>Hanya kelas yang ada link group WhatsApp dipaparkan.</flux:description>
                <flux:error name="class_id" />
            </flux:field>

            <flux:field>
                <flux:label>Nama paparan</flux:label>
                <flux:input wire:model="label" placeholder="Guna nama kelas jika dikosongkan" />
                <flux:error name="label" />
            </flux:field>

            <flux:field>
                <flux:label>Penerangan</flux:label>
                <flux:input wire:model="description" placeholder="Cth: Sesi pagi (pilihan)" />
                <flux:error name="description" />
            </flux:field>

            <flux:field>
                <flux:label>Link jemputan manual</flux:label>
                <flux:input wire:model="invite_link" placeholder="https://chat.whatsapp.com/... (pilihan)" />
                <flux:description>Isi hanya jika mahu guna link selain dari kelas.</flux:description>
                <flux:error name="invite_link" />
            </flux:field>

            <flux:field variant="inline">
                <flux:checkbox wire:model="item_is_active" />
                <flux:label>Aktif (dipaparkan di link awam)</flux:label>
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">Batal</flux:button>
                <flux:button type="submit" variant="primary">
                    <span wire:loading.remove wire:target="saveItem">{{ $editingItemId ? 'Simpan' : 'Tambah group' }}</span>
                    <span wire:loading wire:target="saveItem">Menyimpan…</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
