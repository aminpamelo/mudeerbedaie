<div class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="lg">Bahan Kelas</flux:heading>
            <flux:text class="mt-1">Muat naik dan urus bahan pembelajaran untuk pelajar.</flux:text>
        </div>
        <flux:button variant="primary" wire:click="$set('showResourceModal', true)">
            <div class="flex items-center justify-center">
                <flux:icon name="plus" class="mr-1.5 h-4 w-4" />
                Tambah Bahan
            </div>
        </flux:button>
    </div>

    {{-- Resource List --}}
    @php
        $resources = $this->resources;
        $grouped = $resources->groupBy(fn ($r) => $r->session_id ? 'Sesi: ' . $r->session->session_date->format('d M Y') : 'Umum');
    @endphp

    @forelse($grouped as $group => $items)
        <div>
            <flux:heading size="sm" class="mb-3">{{ $group }}</flux:heading>
            <div class="space-y-2">
                @foreach($items as $resource)
                    <flux:card class="flex items-center justify-between p-4" wire:key="resource-{{ $resource->id }}">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-violet-50">
                                <flux:icon :name="$resource->icon" class="h-5 w-5 text-violet-600" />
                            </div>
                            <div>
                                <flux:text class="font-medium">{{ $resource->title }}</flux:text>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <flux:badge size="sm" color="{{ $resource->is_published ? 'green' : 'zinc' }}">
                                        {{ $resource->is_published ? 'Diterbitkan' : 'Draf' }}
                                    </flux:badge>
                                    <flux:text size="sm" class="text-zinc-400">{{ $resource->views->count() }} tontonan</flux:text>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <flux:button size="sm" variant="ghost" wire:click="toggleResourcePublished({{ $resource->id }})">
                                <flux:icon name="{{ $resource->is_published ? 'eye-slash' : 'eye' }}" class="h-4 w-4" />
                            </flux:button>
                            <flux:button size="sm" variant="ghost" wire:click="editResource({{ $resource->id }})">
                                <flux:icon name="pencil" class="h-4 w-4" />
                            </flux:button>
                            <flux:button size="sm" variant="ghost" wire:click="deleteResource({{ $resource->id }})" wire:confirm="Padam bahan ini?">
                                <flux:icon name="trash" class="h-4 w-4 text-red-500" />
                            </flux:button>
                        </div>
                    </flux:card>
                @endforeach
            </div>
        </div>
    @empty
        <flux:card class="p-8 text-center">
            <flux:icon name="folder-open" class="mx-auto h-12 w-12 text-zinc-300" />
            <flux:heading size="sm" class="mt-3">Tiada Bahan</flux:heading>
            <flux:text class="mt-1">Klik "Tambah Bahan" untuk mula memuat naik bahan pembelajaran.</flux:text>
        </flux:card>
    @endforelse

    {{-- Resource Modal --}}
    <flux:modal wire:model="showResourceModal" class="max-w-lg">
        <div class="space-y-4">
            <flux:heading size="lg">{{ $editingResourceId ? 'Edit Bahan' : 'Tambah Bahan' }}</flux:heading>

            <flux:input wire:model="resourceTitle" label="Tajuk" placeholder="Cth: Nota Tajwid Bab 1" />

            <flux:select wire:model.live="resourceType" label="Jenis">
                <option value="link">Pautan</option>
                <option value="recording">Rakaman</option>
                <option value="pdf">PDF</option>
                <option value="audio">Audio</option>
                <option value="image">Gambar</option>
                <option value="note">Nota Teks</option>
            </flux:select>

            @if(in_array($resourceType, ['link', 'recording']))
                <flux:input wire:model="resourceUrl" label="URL" placeholder="https://..." />
            @endif

            @if(in_array($resourceType, ['pdf', 'audio', 'image']))
                <div>
                    <label class="block text-sm font-medium text-zinc-700 mb-1">Fail</label>
                    <input type="file" wire:model="resourceFile" class="block w-full text-sm text-zinc-500 file:mr-4 file:rounded-lg file:border-0 file:bg-violet-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-violet-700 hover:file:bg-violet-100" />
                    @error('resourceFile') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                </div>
            @endif

            @if($resourceType === 'note')
                <flux:textarea wire:model="resourceContent" label="Kandungan" rows="6" placeholder="Tulis nota di sini..." />
            @endif

            <flux:select wire:model="resourceSessionId" label="Sesi (Pilihan)">
                <option value="">Umum (tiada sesi)</option>
                @foreach($this->class->sessions()->orderByDesc('session_date')->get() as $session)
                    <option value="{{ $session->id }}">{{ $session->session_date->format('d M Y') }} - {{ $session->session_time?->format('g:i A') ?? '' }}</option>
                @endforeach
            </flux:select>

            <flux:switch wire:model="resourcePublished" label="Terbitkan sekarang" />

            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showResourceModal', false)">Batal</flux:button>
                <flux:button variant="primary" wire:click="saveResource">
                    {{ $editingResourceId ? 'Kemaskini' : 'Simpan' }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
