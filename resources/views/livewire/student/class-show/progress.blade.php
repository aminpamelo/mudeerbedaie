@php $progress = $this->progress_data; @endphp

<div class="space-y-6">
    <flux:heading size="lg">Kemajuan Saya</flux:heading>

    {{-- Stats Grid --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        {{-- Attendance Rate --}}
        <flux:card class="p-4 text-center">
            <div class="relative mx-auto h-20 w-20">
                <svg class="h-20 w-20 -rotate-90" viewBox="0 0 36 36">
                    <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                          fill="none" stroke="#e4e4e7" stroke-width="3" />
                    <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                          fill="none" stroke="#7c3aed" stroke-width="3"
                          stroke-dasharray="{{ $progress['attendance_rate'] }}, 100" />
                </svg>
                <span class="absolute inset-0 flex items-center justify-center text-lg font-bold text-zinc-900">{{ $progress['attendance_rate'] }}%</span>
            </div>
            <flux:text size="sm" class="mt-2 font-medium">Kehadiran</flux:text>
            <flux:text size="sm" class="text-zinc-400">{{ $progress['attended'] }}/{{ $progress['total_completed'] }} sesi</flux:text>
        </flux:card>

        {{-- Streak --}}
        <flux:card class="flex flex-col items-center justify-center p-4 text-center">
            <div class="text-3xl">&#x1F525;</div>
            <span class="mt-1 text-2xl font-bold text-zinc-900">{{ $progress['streak'] }}</span>
            <flux:text size="sm" class="font-medium">Sesi Berturut</flux:text>
        </flux:card>

        {{-- Syllabus Progress --}}
        <flux:card class="flex flex-col items-center justify-center p-4 text-center">
            <flux:icon name="book-open" class="h-8 w-8 text-violet-500" />
            <span class="mt-1 text-2xl font-bold text-zinc-900">{{ $progress['syllabus_covered'] }}/{{ $progress['syllabus_total'] }}</span>
            <flux:text size="sm" class="font-medium">Silibus</flux:text>
        </flux:card>

        {{-- Milestones --}}
        <flux:card class="flex flex-col items-center justify-center p-4 text-center">
            <flux:icon name="trophy" class="h-8 w-8 text-amber-500" />
            <span class="mt-1 text-2xl font-bold text-zinc-900">{{ $progress['milestones']->count() }}</span>
            <flux:text size="sm" class="font-medium">Pencapaian</flux:text>
        </flux:card>
    </div>

    {{-- Next Session --}}
    @if($progress['next_session'])
        <flux:card class="border-violet-200 bg-violet-50/50 p-4">
            <div class="flex items-center gap-3">
                <flux:icon name="clock" class="h-6 w-6 text-violet-600" />
                <div>
                    <flux:text class="font-semibold text-violet-900">Sesi Seterusnya</flux:text>
                    <flux:text size="sm" class="text-violet-700">
                        {{ $progress['next_session']->session_date->format('l, d M Y') }}
                        @if($progress['next_session']->session_time)
                            — {{ $progress['next_session']->session_time->format('g:i A') }}
                        @endif
                    </flux:text>
                </div>
            </div>
        </flux:card>
    @endif

    {{-- Milestones List --}}
    @if($progress['milestones']->isNotEmpty())
        <div>
            <flux:heading size="sm" class="mb-3">Pencapaian</flux:heading>
            <div class="space-y-2">
                @foreach($progress['milestones'] as $milestone)
                    <flux:card class="flex items-center gap-3 p-3" wire:key="ms-{{ $milestone->id }}">
                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-amber-50">
                            <flux:icon name="star" class="h-4 w-4 text-amber-500" />
                        </div>
                        <div class="flex-1">
                            <flux:text class="font-medium">{{ $milestone->title }}</flux:text>
                            <flux:text size="sm" class="text-zinc-400">{{ $milestone->achieved_at->format('d M Y') }}</flux:text>
                        </div>
                    </flux:card>
                @endforeach
            </div>
        </div>
    @endif
</div>
