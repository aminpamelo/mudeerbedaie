<!-- Timetable Controls -->
<flux:card class="mb-6">
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <!-- View Buttons -->
        <div class="flex flex-wrap gap-2">
            <flux:button 
                :variant="$currentView === 'week' ? 'primary' : 'ghost'" 
                wire:click="$set('currentView', 'week')"
                size="sm"
            >
                Week
            </flux:button>
            <flux:button 
                :variant="$currentView === 'month' ? 'primary' : 'ghost'" 
                wire:click="$set('currentView', 'month')"
                size="sm"
            >
                Month
            </flux:button>
            <flux:button 
                :variant="$currentView === 'day' ? 'primary' : 'ghost'" 
                wire:click="$set('currentView', 'day')"
                size="sm"
            >
                Day
            </flux:button>
        </div>
        
        <!-- Navigation -->
        <div class="flex flex-col lg:flex-row items-start lg:items-center gap-4">
            <!-- Enhanced Week Navigation -->
            @if($currentView === 'week')
                <div class="flex items-center gap-3 bg-gray-50 dark:bg-gray-800 rounded-lg p-2 border">
                    <flux:button 
                        variant="outline" 
                        wire:click="previousPeriod" 
                        size="sm"
                        title="Previous Week"
                    >
                        <div class="flex items-center justify-center">
                            <flux:icon name="chevron-left" class="w-4 h-4" />
                        </div>
                    </flux:button>
                    
                    <div class="px-4 py-2 text-sm font-semibold text-gray-900 dark:text-gray-100 bg-white dark:bg-gray-700 rounded border min-w-[200px] text-center">
                        <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Current Week</div>
                        <div class="font-medium">{{ $currentPeriodLabel }}</div>
                    </div>
                    
                    <flux:button 
                        variant="outline" 
                        wire:click="nextPeriod" 
                        size="sm"
                        title="Next Week"
                    >
                        <div class="flex items-center justify-center">
                            <flux:icon name="chevron-right" class="w-4 h-4" />
                        </div>
                    </flux:button>
                    
                    <div class="hidden md:block border-l border-gray-300 dark:border-gray-600 pl-3">
                        <flux:button 
                            variant="primary" 
                            wire:click="goToToday" 
                            size="sm"
                            title="Go to current week"
                        >
                            <div class="flex items-center justify-center">
                                <flux:icon name="calendar-days" class="w-4 h-4 mr-1" />
                                This Week
                            </div>
                        </flux:button>
                    </div>
                </div>
            @else
                <!-- Standard Navigation for other views -->
                <div class="flex items-center gap-2">
                    <flux:button variant="ghost" wire:click="previousPeriod" size="sm">
                        <div class="flex items-center justify-center">
                            <flux:icon name="chevron-left" class="w-4 h-4" />
                        </div>
                    </flux:button>
                    
                    <div class="px-4 py-2 text-sm font-medium text-gray-900 dark:text-gray-100 min-w-0">
                        {{ $currentPeriodLabel }}
                    </div>
                    
                    <flux:button variant="ghost" wire:click="nextPeriod" size="sm">
                        <div class="flex items-center justify-center">
                            <flux:icon name="chevron-right" class="w-4 h-4" />
                        </div>
                    </flux:button>
                </div>
            @endif
        </div>
    </div>
</flux:card>

<!-- Timetable Content -->
<flux:card wire:poll.30s="$refresh">
    @if($currentView === 'week')
        @include('livewire.student.class-show.timetable.week-view', ['days' => $calendarData])
    @elseif($currentView === 'month')
        @include('livewire.student.class-show.timetable.month-view', ['weeks' => $calendarData])
    @elseif($currentView === 'day')
        @include('livewire.student.class-show.timetable.day-view', ['timeSlots' => $calendarData])
    @else
        <div class="text-center py-8">
            <flux:text>Invalid view selected</flux:text>
        </div>
    @endif
</flux:card>