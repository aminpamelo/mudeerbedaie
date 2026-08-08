<div class="space-y-6">
    <flux:heading size="lg">Bahan Pembelajaran</flux:heading>
    <flux:text class="mt-1">Akses bahan, nota, dan rakaman kelas anda.</flux:text>

    @php
        $resources = $this->published_resources;
        $grouped = $resources->groupBy(fn ($r) => $r->session_id ? 'Sesi: ' . $r->session->session_date->format('d M Y') : 'Umum');
    @endphp

    @forelse($grouped as $group => $items)
        <div>
            <flux:heading size="sm" class="mb-3">{{ $group }}</flux:heading>
            <div class="space-y-2">
                @foreach($items as $resource)
                    <flux:card class="p-4" wire:key="res-{{ $resource->id }}">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-violet-50">
                                <flux:icon :name="$resource->icon" class="h-5 w-5 text-violet-600" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <flux:text class="font-medium">{{ $resource->title }}</flux:text>
                                <flux:badge size="sm" class="mt-0.5">{{ ucfirst($resource->type) }}</flux:badge>
                            </div>
                            <div>
                                @if($resource->type === 'note')
                                    {{-- Note content shown inline below --}}
                                @elseif($resource->accessible_url)
                                    <a href="{{ $resource->accessible_url }}" target="_blank" wire:click="viewResource({{ $resource->id }})"
                                       class="inline-flex items-center gap-1.5 rounded-lg bg-violet-50 px-3 py-1.5 text-sm font-medium text-violet-700 hover:bg-violet-100">
                                        <flux:icon name="arrow-top-right-on-square" class="h-4 w-4" />
                                        Lihat
                                    </a>
                                @endif
                            </div>
                        </div>
                        @if($resource->type === 'note' && $resource->content)
                            <div class="mt-3 rounded-lg bg-zinc-50 p-4 text-sm leading-relaxed text-zinc-700">
                                {!! nl2br(e($resource->content)) !!}
                            </div>
                        @endif
                    </flux:card>
                @endforeach
            </div>
        </div>
    @empty
        <flux:card class="p-8 text-center">
            <flux:icon name="folder-open" class="mx-auto h-12 w-12 text-zinc-300" />
            <flux:heading size="sm" class="mt-3">Tiada Bahan</flux:heading>
            <flux:text class="mt-1">Guru belum memuat naik bahan untuk kelas ini.</flux:text>
        </flux:card>
    @endforelse
</div>
