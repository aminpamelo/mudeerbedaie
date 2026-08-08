<div class="space-y-6">
    <flux:heading size="lg">Pengumuman</flux:heading>

    @php
        $announcements = $this->announcements;
        $student = auth()->user()->student;
    @endphp

    @forelse($announcements as $announcement)
        @php
            $isRead = $announcement->isReadBy($student);
        @endphp
        <flux:card
            class="p-5 {{ $announcement->is_pinned ? 'border-amber-200 bg-amber-50/30' : '' }} {{ !$isRead ? 'border-l-4 border-l-violet-500' : '' }}"
            wire:key="ann-{{ $announcement->id }}"
            wire:init="markAnnouncementRead({{ $announcement->id }})"
        >
            <div class="flex items-start gap-2">
                @if($announcement->is_pinned)
                    <flux:icon name="bookmark" class="mt-0.5 h-4 w-4 shrink-0 text-amber-500" />
                @endif
                <div class="flex-1">
                    <div class="flex items-center gap-2">
                        <flux:text class="text-base font-semibold">{{ $announcement->title }}</flux:text>
                        @if(!$isRead)
                            <flux:badge size="sm" color="violet">Baru</flux:badge>
                        @endif
                    </div>
                    <flux:text size="sm" class="mt-0.5 text-zinc-400">
                        {{ $announcement->author?->name }} · {{ $announcement->published_at->diffForHumans() }}
                    </flux:text>
                    <div class="mt-3 text-sm leading-relaxed text-zinc-700">
                        {!! nl2br(e($announcement->body)) !!}
                    </div>
                </div>
            </div>
        </flux:card>
    @empty
        <flux:card class="p-8 text-center">
            <flux:icon name="megaphone" class="mx-auto h-12 w-12 text-zinc-300" />
            <flux:heading size="sm" class="mt-3">Tiada Pengumuman</flux:heading>
            <flux:text class="mt-1">Guru belum berkongsi pengumuman untuk kelas ini.</flux:text>
        </flux:card>
    @endforelse
</div>
