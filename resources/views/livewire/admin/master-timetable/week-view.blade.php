{{-- Mobile Week View with Horizontal Scrolling --}}
<div class="md:hidden" x-data="{
    scrollContainer: null,
    currentDayIndex: 0,

    init() {
        this.scrollContainer = this.$refs.scrollContainer;
        const todayIndex = {{ collect($days)->search(function($day) { return $day['isToday']; }) ?: 0 }};
        this.currentDayIndex = todayIndex;
        this.$nextTick(() => {
            this.scrollToDay(todayIndex);
        });
        this.scrollContainer.addEventListener('scroll', this.updateCurrentDay.bind(this));
    },

    scrollToDay(dayIndex) {
        if (this.scrollContainer) {
            const dayWidth = this.scrollContainer.scrollWidth / {{ count($days) }};
            const scrollPosition = dayIndex * dayWidth;
            this.scrollContainer.scrollTo({
                left: scrollPosition,
                behavior: 'smooth'
            });
            this.currentDayIndex = dayIndex;
        }
    },

    updateCurrentDay() {
        if (this.scrollContainer) {
            const dayWidth = this.scrollContainer.scrollWidth / {{ count($days) }};
            const scrollLeft = this.scrollContainer.scrollLeft;
            const newDayIndex = Math.round(scrollLeft / dayWidth);
            this.currentDayIndex = Math.max(0, Math.min(newDayIndex, {{ count($days) - 1 }}));
        }
    },

    previousDay() {
        if (this.currentDayIndex > 0) {
            this.scrollToDay(this.currentDayIndex - 1);
        }
    },

    nextDay() {
        if (this.currentDayIndex < {{ count($days) - 1 }}) {
            this.scrollToDay(this.currentDayIndex + 1);
        }
    }
}">
    {{-- Day Navigation --}}
    <div class="flex items-center justify-between mb-4 px-2">
        <flux:button
            variant="ghost"
            size="sm"
            x-on:click="previousDay()"
            x-bind:disabled="currentDayIndex === 0"
            x-bind:class="currentDayIndex === 0 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-100'"
        >
            <flux:icon name="chevron-left" class="w-5 h-5" />
        </flux:button>

        <div class="text-center">
            <div class="text-sm font-medium text-gray-900" x-text="
                (() => {
                    const days = {{ collect($days)->map(function($day) { return $day['dayName'] . ', ' . $day['date']->format('M d'); })->toJson() }};
                    return days[currentDayIndex] || 'Current Day';
                })()">
            </div>
            <div class="text-xs text-gray-500">
                Day <span x-text="currentDayIndex + 1"></span> of {{ count($days) }}
            </div>
        </div>

        <flux:button
            variant="ghost"
            size="sm"
            x-on:click="nextDay()"
            x-bind:disabled="currentDayIndex === {{ count($days) - 1 }}"
            x-bind:class="currentDayIndex === {{ count($days) - 1 }} ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-100'"
        >
            <flux:icon name="chevron-right" class="w-5 h-5" />
        </flux:button>
    </div>

    {{-- Day Dots Indicator --}}
    <div class="flex justify-center items-center gap-2 mb-4">
        @foreach($days as $index => $day)
            <button
                class="w-2 h-2 rounded-full transition-all duration-200"
                x-bind:class="currentDayIndex === {{ $index }} ? 'bg-blue-500 w-6' : 'bg-gray-300'"
                x-on:click="scrollToDay({{ $index }})"
                title="{{ $day['dayName'] }}, {{ $day['date']->format('M d') }}"
            ></button>
        @endforeach
    </div>

    {{-- Horizontal Scrolling Container --}}
    <div
        x-ref="scrollContainer"
        class="overflow-x-auto scrollbar-hide scroll-smooth"
        style="scroll-snap-type: x mandatory;"
    >
        <div class="flex gap-4 pb-4" style="width: {{ count($days) * 100 }}%;">
            @foreach($days as $day)
                <div
                    class="flex-shrink-0 bg-white border border-gray-200 rounded-lg {{ $day['isToday'] ? 'ring-2 ring-blue-500' : '' }}"
                    style="width: calc(100% / {{ count($days) }} - 0.75rem); scroll-snap-align: start;"
                >
                    {{-- Mobile Day Header --}}
                    <div class="p-4 border-b border-gray-200">
                        <div class="text-center">
                            <div class="text-xs font-medium text-gray-500 uppercase tracking-wide">
                                {{ $day['dayName'] }}
                            </div>
                            <div class="mt-1 text-lg font-semibold {{ $day['isToday'] ? 'text-blue-600' : 'text-gray-900' }}">
                                {{ $day['dayNumber'] }}
                            </div>
                            @if($day['isToday'])
                                <flux:badge color="blue" size="sm" class="mt-1">Today</flux:badge>
                            @endif
                        </div>
                        @if($day['sessions']->count() > 0)
                            <div class="text-center mt-2">
                                <flux:badge variant="outline" size="sm">{{ $day['sessions']->count() }} session{{ $day['sessions']->count() !== 1 ? 's' : '' }}</flux:badge>
                            </div>
                        @endif
                    </div>

                    {{-- Mobile Sessions --}}
                    <div class="p-3 min-h-[300px] space-y-2">
                        @forelse($day['sessions'] as $session)
                            @php $color = $this->getClassColor($session->class_id); @endphp
                            <div
                                class="group cursor-pointer rounded-lg p-3 text-sm transition-all duration-200 hover:shadow-md {{ $color['bg'] }} {{ $color['border'] }} border
                                       @if($session->status === 'ongoing') animate-pulse @endif
                                       @if($session->status === 'completed') opacity-75 @endif
                                       @if($session->status === 'cancelled') line-through opacity-50 @endif"
                                wire:click="selectSession({{ $session->id }})"
                            >
                                <div class="font-medium {{ $color['text'] }} mb-1">
                                    {{ $session->session_time->format('g:i A') }}
                                </div>

                                <div class="text-gray-800 font-medium truncate mb-1" title="{{ $session->class->title }}">
                                    {{ $session->class->title }}
                                </div>

                                <div class="text-gray-600 text-xs truncate mb-2" title="{{ $session->class->course?->name }}">
                                    {{ $session->class->course?->name ?? 'N/A' }}
                                </div>

                                <div class="flex items-center justify-between text-xs text-gray-500 mb-2">
                                    <span>{{ $session->formatted_duration }}</span>
                                    @if($session->attendances->count() > 0)
                                        <span>{{ $session->attendances->count() }} students</span>
                                    @endif
                                </div>

                                <div class="flex items-center justify-between">
                                    <flux:badge size="sm" class="{{ $session->status_badge_class }}">
                                        {{ ucfirst($session->status) }}
                                    </flux:badge>

                                    @if($session->status === 'ongoing')
                                        <div class="flex items-center gap-1">
                                            <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                                            <span class="text-xs text-green-600">Live</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-8 text-gray-400">
                                <flux:icon name="calendar" class="w-8 h-8 mx-auto mb-2 opacity-50" />
                                <div class="text-sm">No sessions scheduled</div>
                            </div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

{{-- Desktop Week View --}}
<div class="hidden md:block overflow-x-auto scrollbar-hide">
    <div class="grid gap-px bg-gray-200 rounded-lg overflow-hidden" style="grid-template-columns: repeat(7, minmax(180px, 1fr)); min-width: 1260px;">
        @foreach($days as $day)
            <div class="bg-white {{ $day['isToday'] ? 'ring-2 ring-blue-500 ring-inset' : '' }}">
                {{-- Day Header --}}
                <div class="p-4 border-b border-gray-200">
                    <div class="text-center">
                        <div class="text-xs font-medium text-gray-500 uppercase tracking-wide">
                            {{ $day['dayName'] }}
                        </div>
                        <div class="mt-1 text-lg font-semibold {{ $day['isToday'] ? 'text-blue-600' : 'text-gray-900' }}">
                            {{ $day['dayNumber'] }}
                        </div>
                        @if($day['isToday'])
                            <flux:badge color="blue" size="sm" class="mt-1">Today</flux:badge>
                        @endif
                    </div>
                </div>

                {{-- Day Sessions --}}
                <div class="p-2 min-h-[200px] space-y-1">
                    @forelse($day['sessions'] as $session)
                        @php $color = $this->getClassColor($session->class_id); @endphp
                        <div
                            class="group cursor-pointer rounded-lg p-2 text-xs transition-all duration-200 hover:shadow-md {{ $color['bg'] }} {{ $color['border'] }} border
                                   @if($session->status === 'ongoing') animate-pulse @endif
                                   @if($session->status === 'completed') opacity-75 @endif
                                   @if($session->status === 'cancelled') line-through opacity-50 @endif"
                            wire:click="selectSession({{ $session->id }})"
                        >
                            {{-- Session Time --}}
                            <div class="font-medium {{ $color['text'] }}">
                                {{ $session->session_time->format('g:i A') }}
                            </div>

                            {{-- Class Title --}}
                            <div class="text-gray-800 font-medium truncate" title="{{ $session->class->title }}">
                                {{ $session->class->title }}
                            </div>

                            {{-- Course Name --}}
                            <div class="text-gray-600 truncate" title="{{ $session->class->course?->name }}">
                                {{ $session->class->course?->name ?? 'N/A' }}
                            </div>

                            {{-- Teacher --}}
                            @if($session->class->teacher?->user)
                                <div class="text-gray-500 truncate text-xs mt-1">
                                    {{ $session->class->teacher->user->name }}
                                </div>
                            @endif

                            {{-- Status and Duration --}}
                            <div class="flex items-center justify-between mt-1">
                                <flux:badge size="sm" class="{{ $session->status_badge_class }}">
                                    {{ ucfirst($session->status) }}
                                </flux:badge>

                                @if($session->status === 'ongoing')
                                    <div class="flex items-center gap-1">
                                        <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                                        <span class="text-xs text-green-600">Live</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-gray-400">
                            <flux:icon name="calendar" class="w-8 h-8 mx-auto mb-2 opacity-50" />
                            <div class="text-sm">No sessions</div>
                        </div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</div>

{{-- Custom CSS for smooth scrolling --}}
<style>
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
</style>
