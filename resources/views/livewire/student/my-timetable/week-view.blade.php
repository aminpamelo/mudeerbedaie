{{-- Mobile-First Week View --}}
@php
    $todayIndex = collect($days)->search(function($day) { return $day['isToday']; }) ?? 0;
@endphp

<div x-data="{
    currentDayIndex: {{ $todayIndex }},
    scrollContainer: null,

    init() {
        this.scrollContainer = this.$refs.scrollContainer;
        this.$nextTick(() => {
            this.scrollToDay(this.currentDayIndex);
        });

        if (this.scrollContainer) {
            this.scrollContainer.addEventListener('scroll', this.updateCurrentDay.bind(this));
        }
    },

    scrollToDay(dayIndex) {
        if (this.scrollContainer) {
            const dayCards = this.scrollContainer.querySelectorAll('.day-card');
            if (dayCards[dayIndex]) {
                dayCards[dayIndex].scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest',
                    inline: 'start'
                });
            }
            this.currentDayIndex = dayIndex;
        }
    },

    updateCurrentDay() {
        if (this.scrollContainer) {
            const dayCards = this.scrollContainer.querySelectorAll('.day-card');
            const containerRect = this.scrollContainer.getBoundingClientRect();
            const containerCenter = containerRect.left + containerRect.width / 2;

            let closestIndex = 0;
            let closestDistance = Infinity;

            dayCards.forEach((card, index) => {
                const cardRect = card.getBoundingClientRect();
                const cardCenter = cardRect.left + cardRect.width / 2;
                const distance = Math.abs(containerCenter - cardCenter);

                if (distance < closestDistance) {
                    closestDistance = distance;
                    closestIndex = index;
                }
            });

            this.currentDayIndex = closestIndex;
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
    {{-- Mobile Day Navigation Header --}}
    <div class="lg:hidden">
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-zinc-700">
            <button
                @click="previousDay()"
                :disabled="currentDayIndex === 0"
                :class="currentDayIndex === 0 ? 'opacity-30 cursor-not-allowed' : 'hover:bg-gray-100 dark:hover:bg-zinc-700'"
                class="p-2 rounded-lg transition-colors"
            >
                <flux:icon name="chevron-left" class="w-5 h-5 text-gray-600 dark:text-gray-300" />
            </button>

            <div class="text-center">
                <div class="text-sm font-semibold text-gray-900 dark:text-white" x-text="
                    (() => {
                        const days = {{ collect($days)->map(function($day) { return $day['date']->format('l, M j'); })->toJson() }};
                        return days[currentDayIndex] || '';
                    })()
                "></div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    Day <span x-text="currentDayIndex + 1"></span> of {{ count($days) }}
                </div>
            </div>

            <button
                @click="nextDay()"
                :disabled="currentDayIndex === {{ count($days) - 1 }}"
                :class="currentDayIndex === {{ count($days) - 1 }} ? 'opacity-30 cursor-not-allowed' : 'hover:bg-gray-100 dark:hover:bg-zinc-700'"
                class="p-2 rounded-lg transition-colors"
            >
                <flux:icon name="chevron-right" class="w-5 h-5 text-gray-600 dark:text-gray-300" />
            </button>
        </div>

        {{-- Day Indicator Dots --}}
        <div class="flex justify-center items-center gap-1.5 py-3 border-b border-gray-100 dark:border-zinc-700">
            @foreach($days as $index => $day)
                <button
                    @click="scrollToDay({{ $index }})"
                    class="transition-all duration-200 rounded-full"
                    :class="currentDayIndex === {{ $index }}
                        ? 'w-6 h-2 {{ $day['isToday'] ? 'bg-blue-500' : 'bg-gray-900 dark:bg-white' }}'
                        : 'w-2 h-2 {{ $day['isToday'] ? 'bg-blue-300 dark:bg-blue-600' : 'bg-gray-300 dark:bg-zinc-600' }}'"
                    title="{{ $day['dayName'] }}, {{ $day['date']->format('M d') }}"
                ></button>
            @endforeach
        </div>
    </div>

    {{-- Desktop Week Header (Hidden on Mobile) --}}
    <div class="hidden lg:grid grid-cols-7 border-b border-gray-200 dark:border-zinc-700">
        @foreach($days as $day)
            <div class="p-3 text-center {{ $day['isToday'] ? 'bg-blue-50 dark:bg-blue-900/30' : '' }}">
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                    {{ $day['dayName'] }}
                </div>
                <div class="mt-1 text-lg font-semibold {{ $day['isToday'] ? 'text-blue-600 dark:text-blue-400' : 'text-gray-900 dark:text-white' }}">
                    {{ $day['dayNumber'] }}
                </div>
                @if($day['isToday'])
                    <flux:badge color="blue" size="sm" class="mt-1">{{ __('student.timetable.today') }}</flux:badge>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Mobile Horizontal Scroll Days --}}
    <div
        x-ref="scrollContainer"
        class="lg:hidden overflow-x-auto scrollbar-hide scroll-smooth snap-x snap-mandatory"
    >
        <div class="flex" style="width: {{ count($days) * 100 }}%;">
            @foreach($days as $index => $day)
                <div
                    class="day-card flex-shrink-0 snap-center"
                    style="width: calc(100% / {{ count($days) }});"
                >
                    {{-- Mobile Day Card --}}
                    <div class="p-4 min-h-[400px] {{ $day['isToday'] ? 'bg-blue-50/50 dark:bg-blue-900/10' : '' }}">
                        {{-- Today Badge for Mobile --}}
                        @if($day['isToday'])
                            <div class="flex justify-center mb-3">
                                <flux:badge color="blue" size="sm">{{ __('student.timetable.today') }}</flux:badge>
                            </div>
                        @endif

                        @include('livewire.student.my-timetable.partials.day-sessions', ['day' => $day])
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Desktop Grid View (Hidden on Mobile) --}}
    <div class="hidden lg:grid grid-cols-7 divide-x divide-gray-200 dark:divide-zinc-700">
        @foreach($days as $day)
            <div class="min-h-[300px] p-2 {{ $day['isToday'] ? 'bg-blue-50/30 dark:bg-blue-900/10' : '' }}">
                @include('livewire.student.my-timetable.partials.day-sessions', ['day' => $day])
            </div>
        @endforeach
    </div>
</div>

{{-- Custom Scrollbar Styles --}}
<style>
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
</style>
