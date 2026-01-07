{{-- Day Sessions Partial --}}
@php
    // Combine sessions and scheduled slots, sorted by time
    $combinedItems = collect();

    // Add existing sessions
    foreach($day['sessions'] as $session) {
        $combinedItems->push([
            'type' => 'session',
            'time' => $session->session_time->format('H:i'),
            'displayTime' => $session->session_time->format('g:i A'),
            'session' => $session,
            'class' => $session->class,
        ]);
    }

    // Add scheduled slots without existing sessions
    foreach($day['scheduledSlots'] as $slot) {
        if (!$slot['session']) {
            $combinedItems->push([
                'type' => 'scheduled',
                'time' => $slot['time'],
                'displayTime' => \Carbon\Carbon::parse($slot['time'])->format('g:i A'),
                'session' => null,
                'class' => $slot['class'],
            ]);
        }
    }

    $sortedItems = $combinedItems->sortBy('time');
@endphp

<div class="space-y-2">
    @forelse($sortedItems as $item)
        @if($item['type'] === 'session')
            {{-- Existing Session Card --}}
            @php $session = $item['session']; @endphp
            <div
                class="group rounded-xl p-3 transition-all duration-200 hover:shadow-md cursor-pointer
                    @switch($session->status)
                        @case('scheduled')
                            bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 hover:bg-blue-100 dark:hover:bg-blue-900/50
                            @break
                        @case('ongoing')
                            bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 hover:bg-green-100 dark:hover:bg-green-900/50
                            @break
                        @case('completed')
                            bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 hover:bg-gray-100 dark:hover:bg-zinc-700
                            @break
                        @case('cancelled')
                            bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 hover:bg-red-100 dark:hover:bg-red-900/50
                            @break
                        @case('no_show')
                            bg-orange-50 dark:bg-orange-900/30 border border-orange-200 dark:border-orange-800 hover:bg-orange-100 dark:hover:bg-orange-900/50
                            @break
                        @default
                            bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 hover:bg-gray-100 dark:hover:bg-zinc-700
                    @endswitch"
                @if($session->status === 'completed')
                    wire:click="selectSession({{ $session->id }})"
                @endif
            >
                {{-- Time Badge --}}
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-semibold
                        @if($session->status === 'ongoing')
                            text-green-700 dark:text-green-400
                        @elseif($session->status === 'scheduled')
                            text-blue-700 dark:text-blue-400
                        @else
                            text-gray-700 dark:text-gray-300
                        @endif
                    ">
                        {{ $item['displayTime'] }}
                    </span>

                    @if($session->status === 'ongoing')
                        <div class="flex items-center gap-1.5">
                            <span class="relative flex h-2 w-2">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
                            </span>
                            <span class="text-xs font-medium text-green-600 dark:text-green-400">{{ __('student.status.live') }}</span>
                        </div>
                    @endif
                </div>

                {{-- Session Title --}}
                <h4 class="text-sm font-medium text-gray-900 dark:text-white truncate mb-0.5">
                    {{ $session->class->title }}
                </h4>

                {{-- Course Name --}}
                <p class="text-xs text-gray-600 dark:text-gray-400 truncate mb-1">
                    {{ $session->class->course->name }}
                </p>

                {{-- Teacher --}}
                <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400 mb-2">
                    <flux:icon name="user-circle" class="w-3.5 h-3.5" />
                    <span class="truncate">{{ $session->class->teacher->user->name }}</span>
                </div>

                {{-- Footer: Duration & Status --}}
                <div class="flex items-center justify-between pt-2 border-t border-gray-200/50 dark:border-zinc-600/50">
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $session->formatted_duration }}
                    </span>

                    <flux:badge
                        size="sm"
                        class="{{ $session->status_badge_class }}"
                    >
                        {{ ucfirst($session->status) }}
                    </flux:badge>
                </div>

                {{-- Timer for Ongoing Sessions --}}
                @if($session->status === 'ongoing')
                    <div class="mt-2" x-data="{
                        elapsedTime: 0,
                        timer: null,
                        formatTime(seconds) {
                            const hours = Math.floor(seconds / 3600);
                            const minutes = Math.floor((seconds % 3600) / 60);
                            const secs = seconds % 60;
                            if (hours > 0) {
                                return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
                            }
                            return `${minutes}:${secs.toString().padStart(2, '0')}`;
                        }
                    }" x-init="
                        const startTime = new Date('{{ $session->started_at ? $session->started_at->toISOString() : now()->toISOString() }}').getTime();
                        elapsedTime = Math.floor((Date.now() - startTime) / 1000);
                        timer = setInterval(() => {
                            elapsedTime = Math.floor((Date.now() - startTime) / 1000);
                        }, 1000);
                    " x-destroy="timer && clearInterval(timer)">
                        <div class="flex items-center justify-center gap-2 bg-green-100 dark:bg-green-900/50 rounded-lg px-3 py-1.5">
                            <flux:icon name="clock" class="w-4 h-4 text-green-600 dark:text-green-400" />
                            <span class="text-sm font-mono font-semibold text-green-700 dark:text-green-300" x-text="formatTime(elapsedTime)"></span>
                        </div>
                    </div>
                @endif

                {{-- Tap hint for completed --}}
                @if($session->status === 'completed')
                    <div class="mt-2 text-center">
                        <span class="text-xs text-gray-400 dark:text-gray-500">{{ __('student.timetable.session_details') }}</span>
                    </div>
                @endif
            </div>

        @else
            {{-- Scheduled Slot Without Session --}}
            @php $class = $item['class']; @endphp
            <div class="rounded-xl p-3 bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800 border-dashed">
                {{-- Time --}}
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-semibold text-indigo-700 dark:text-indigo-400">
                        {{ $item['displayTime'] }}
                    </span>
                    <flux:badge size="sm" color="indigo">Expected</flux:badge>
                </div>

                {{-- Class Title --}}
                <h4 class="text-sm font-medium text-indigo-900 dark:text-indigo-200 truncate mb-0.5">
                    {{ $class->title }}
                </h4>

                {{-- Course Name --}}
                <p class="text-xs text-indigo-600 dark:text-indigo-400 truncate mb-1">
                    {{ $class->course->name }}
                </p>

                {{-- Teacher --}}
                <div class="flex items-center gap-1.5 text-xs text-indigo-500 dark:text-indigo-400">
                    <flux:icon name="user-circle" class="w-3.5 h-3.5" />
                    <span class="truncate">{{ $class->teacher->user->name }}</span>
                </div>
            </div>
        @endif
    @empty
        {{-- Empty State --}}
        <div class="flex flex-col items-center justify-center py-12 text-center">
            <div class="w-12 h-12 rounded-full bg-gray-100 dark:bg-zinc-700 flex items-center justify-center mb-3">
                <flux:icon name="calendar" class="w-6 h-6 text-gray-400 dark:text-gray-500" />
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('student.empty.no_sessions') }}</p>
        </div>
    @endforelse
</div>
