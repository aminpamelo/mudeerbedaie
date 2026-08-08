<div class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="lg">Pengumuman</flux:heading>
            <flux:text class="mt-1">Kongsi maklumat penting dengan pelajar kelas ini.</flux:text>
        </div>
        <flux:button variant="primary" wire:click="$set('showAnnouncementModal', true)">
            <div class="flex items-center justify-center">
                <flux:icon name="megaphone" class="mr-1.5 h-4 w-4" />
                Tulis Pengumuman
            </div>
        </flux:button>
    </div>

    {{-- Announcements List --}}
    @php $announcements = $this->announcements_list; @endphp

    @forelse($announcements as $announcement)
        <flux:card class="p-5 {{ $announcement->is_pinned ? 'border-amber-200 bg-amber-50/30' : '' }}" wire:key="ann-{{ $announcement->id }}">
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-start gap-2">
                    @if($announcement->is_pinned)
                        <flux:icon name="bookmark" class="mt-0.5 h-4 w-4 text-amber-500" />
                    @endif
                    <div>
                        <flux:text class="text-base font-semibold">{{ $announcement->title }}</flux:text>
                        <flux:text size="sm" class="mt-0.5 text-zinc-400">
                            {{ $announcement->author?->name }} · {{ $announcement->published_at->diffForHumans() }}
                        </flux:text>
                    </div>
                </div>
                <div class="flex items-center gap-1">
                    <flux:badge size="sm" color="violet">
                        {{ $announcement->reads->count() }}/{{ $this->class->activeStudents()->count() }} dibaca
                    </flux:badge>
                    <flux:button size="sm" variant="ghost" wire:click="togglePin({{ $announcement->id }})" title="{{ $announcement->is_pinned ? 'Nyahpin' : 'Pin' }}">
                        <flux:icon name="{{ $announcement->is_pinned ? 'bookmark-slash' : 'bookmark' }}" class="h-4 w-4" />
                    </flux:button>
                    <flux:button size="sm" variant="ghost" wire:click="editAnnouncement({{ $announcement->id }})">
                        <flux:icon name="pencil" class="h-4 w-4" />
                    </flux:button>
                    <flux:button size="sm" variant="ghost" wire:click="deleteAnnouncement({{ $announcement->id }})" wire:confirm="Padam pengumuman ini?">
                        <flux:icon name="trash" class="h-4 w-4 text-red-500" />
                    </flux:button>
                </div>
            </div>
            <div class="mt-3 text-sm leading-relaxed text-zinc-700">
                {!! nl2br(e($announcement->body)) !!}
            </div>
        </flux:card>
    @empty
        <flux:card class="p-8 text-center">
            <flux:icon name="megaphone" class="mx-auto h-12 w-12 text-zinc-300" />
            <flux:heading size="sm" class="mt-3">Tiada Pengumuman</flux:heading>
            <flux:text class="mt-1">Klik "Tulis Pengumuman" untuk mula berkongsi dengan pelajar.</flux:text>
        </flux:card>
    @endforelse

    {{-- Announcement Modal --}}
    <flux:modal wire:model="showAnnouncementModal" class="max-w-lg">
        <div class="space-y-4">
            <flux:heading size="lg">{{ $editingAnnouncementId ? 'Edit Pengumuman' : 'Tulis Pengumuman' }}</flux:heading>

            <flux:input wire:model="announcementTitle" label="Tajuk" placeholder="Cth: Peringatan ujian minggu depan" />

            <flux:textarea wire:model="announcementBody" label="Isi Pengumuman" rows="6" placeholder="Tulis pengumuman anda di sini..." />

            <flux:switch wire:model="announcementPinned" label="Pin di atas" />

            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showAnnouncementModal', false)">Batal</flux:button>
                <flux:button variant="primary" wire:click="saveAnnouncement">
                    {{ $editingAnnouncementId ? 'Kemaskini' : 'Terbitkan' }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
