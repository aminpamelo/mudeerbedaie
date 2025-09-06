<!-- Class Information -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Main Details -->
    <div class="lg:col-span-2">
        <flux:card>
            <flux:heading size="lg" class="mb-4">Class Details</flux:heading>
            
            <div class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <flux:text class="font-medium text-gray-700">Course</flux:text>
                        <flux:text class="text-gray-900">{{ $class->course->name }}</flux:text>
                    </div>
                    
                    <div>
                        <flux:text class="font-medium text-gray-700">Teacher</flux:text>
                        <flux:text class="text-gray-900">{{ $class->teacher->user->name }}</flux:text>
                    </div>
                    
                    <div>
                        <flux:text class="font-medium text-gray-700">Class Type</flux:text>
                        <flux:badge variant="{{ $class->class_type === 'individual' ? 'info' : 'success' }}">
                            {{ ucfirst($class->class_type) }}
                        </flux:badge>
                    </div>
                    
                    @if($class->max_capacity)
                        <div>
                            <flux:text class="font-medium text-gray-700">Class Size</flux:text>
                            <flux:text class="text-gray-900">
                                {{ $class->activeStudents()->count() }} / {{ $class->max_capacity }} students
                            </flux:text>
                        </div>
                    @endif
                    
                    <div>
                        <flux:text class="font-medium text-gray-700">Duration</flux:text>
                        <flux:text class="text-gray-900">{{ $class->formatted_duration }}</flux:text>
                    </div>
                    
                    <div>
                        <flux:text class="font-medium text-gray-700">Enrolled Date</flux:text>
                        <flux:text class="text-gray-900">{{ $classStudent->enrolled_at->format('M j, Y') }}</flux:text>
                    </div>
                </div>
                
                @if($class->description)
                    <div class="pt-4 border-t border-gray-200">
                        <flux:text class="font-medium text-gray-700 block mb-2">Description</flux:text>
                        <flux:text class="text-gray-900">{{ $class->description }}</flux:text>
                    </div>
                @endif
                
                @if($class->location || $class->meeting_url)
                    <div class="pt-4 border-t border-gray-200">
                        <flux:heading size="md" class="mb-3">Class Location</flux:heading>
                        <div class="space-y-2">
                            @if($class->location)
                                <div class="flex items-center gap-2">
                                    <flux:icon name="map-pin" class="w-4 h-4 text-gray-500" />
                                    <flux:text class="text-gray-900">{{ $class->location }}</flux:text>
                                </div>
                            @endif
                            
                            @if($class->meeting_url)
                                <div class="flex items-center gap-2">
                                    <flux:icon name="video-camera" class="w-4 h-4 text-gray-500" />
                                    <a href="{{ $class->meeting_url }}" target="_blank" class="text-blue-600 hover:text-blue-800 underline">
                                        Join Online Meeting
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </flux:card>
    </div>
    
    <!-- Statistics -->
    <div class="space-y-6">
        <!-- Session Statistics -->
        <flux:card>
            <flux:heading size="lg" class="mb-4">Session Statistics</flux:heading>
            
            <div class="space-y-4">
                <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-blue-100 rounded-full">
                            <flux:icon name="calendar-days" class="w-4 h-4 text-blue-600" />
                        </div>
                        <div>
                            <flux:text class="font-medium">Total Sessions</flux:text>
                        </div>
                    </div>
                    <flux:text class="font-bold text-blue-700">{{ $statistics['total_sessions'] }}</flux:text>
                </div>
                
                <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-green-100 rounded-full">
                            <flux:icon name="check-circle" class="w-4 h-4 text-green-600" />
                        </div>
                        <div>
                            <flux:text class="font-medium">Completed</flux:text>
                        </div>
                    </div>
                    <flux:text class="font-bold text-green-700">{{ $statistics['completed_sessions'] }}</flux:text>
                </div>
                
                <div class="flex items-center justify-between p-3 bg-purple-50 rounded-lg">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-purple-100 rounded-full">
                            <flux:icon name="clock" class="w-4 h-4 text-purple-600" />
                        </div>
                        <div>
                            <flux:text class="font-medium">Upcoming</flux:text>
                        </div>
                    </div>
                    <flux:text class="font-bold text-purple-700">{{ $statistics['upcoming_sessions'] }}</flux:text>
                </div>
                
                <div class="flex items-center justify-between p-3 bg-emerald-50 rounded-lg">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-emerald-100 rounded-full">
                            <flux:icon name="check-circle" class="w-4 h-4 text-emerald-600" />
                        </div>
                        <div>
                            <flux:text class="font-medium">Attended</flux:text>
                        </div>
                    </div>
                    <flux:text class="font-bold text-emerald-700">{{ $statistics['attended_sessions'] }}</flux:text>
                </div>
                
                @if($statistics['total_sessions'] > 0)
                    <div class="pt-2 border-t border-gray-200">
                        <div class="flex items-center justify-between">
                            <flux:text class="font-medium text-gray-600">Attendance Rate</flux:text>
                            <flux:text class="font-bold text-gray-900">
                                {{ $statistics['completed_sessions'] > 0 ? number_format(($statistics['attended_sessions'] / $statistics['completed_sessions']) * 100, 1) : 0 }}%
                            </flux:text>
                        </div>
                        <div class="mt-2 w-full bg-gray-200 rounded-full h-2">
                            <div 
                                class="bg-emerald-500 h-2 rounded-full" 
                                style="width: {{ $statistics['completed_sessions'] > 0 ? ($statistics['attended_sessions'] / $statistics['completed_sessions']) * 100 : 0 }}%"
                            ></div>
                        </div>
                    </div>
                @endif
            </div>
        </flux:card>
        
        <!-- Quick Actions -->
        <flux:card>
            <flux:heading size="lg" class="mb-4">Quick Actions</flux:heading>
            
            <div class="space-y-3">
                <flux:button 
                    wire:click="setActiveTab('timetable')" 
                    variant="outline" 
                    class="w-full justify-start"
                >
                    <div class="flex items-center">
                        <flux:icon name="calendar" class="w-4 h-4 mr-2" />
                        View Timetable
                    </div>
                </flux:button>
                
                <flux:button 
                    wire:click="setActiveTab('sessions')" 
                    variant="outline" 
                    class="w-full justify-start"
                >
                    <div class="flex items-center">
                        <flux:icon name="clipboard-document-list" class="w-4 h-4 mr-2" />
                        View All Sessions
                    </div>
                </flux:button>
                
                @if($class->meeting_url)
                    <a href="{{ $class->meeting_url }}" target="_blank" class="block">
                        <flux:button variant="primary" class="w-full justify-start">
                            <div class="flex items-center">
                                <flux:icon name="video-camera" class="w-4 h-4 mr-2" />
                                Join Online Class
                            </div>
                        </flux:button>
                    </a>
                @endif
            </div>
        </flux:card>
    </div>
</div>

<!-- Recent Sessions -->
@if($sessions->take(5)->count() > 0)
    <flux:card class="mt-6">
        <div class="flex items-center justify-between mb-4">
            <flux:heading size="lg">Recent Sessions</flux:heading>
            <flux:button 
                wire:click="setActiveTab('sessions')" 
                variant="ghost" 
                size="sm"
            >
                View All
            </flux:button>
        </div>
        
        <div class="space-y-3">
            @foreach($sessions->sortByDesc('session_date')->take(5) as $session)
                <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg hover:bg-gray-50">
                    <div class="flex-1">
                        <div class="flex items-center gap-3">
                            <div class="flex flex-col">
                                <flux:text class="font-medium">
                                    {{ $session->session_date->format('M j, Y') }}
                                </flux:text>
                                <flux:text size="sm" class="text-gray-600">
                                    {{ $session->session_time->format('g:i A') }} - {{ $session->formatted_duration }}
                                </flux:text>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-3">
                        @if($session->attendances->count() > 0)
                            @foreach($session->attendances as $attendance)
                                <flux:badge class="{{ $attendance->status_badge_class }}" size="sm">
                                    {{ $attendance->status_label }}
                                </flux:badge>
                            @endforeach
                        @endif
                        
                        <flux:badge class="{{ $session->status_badge_class }}" size="sm">
                            {{ $session->status_label }}
                        </flux:badge>
                        
                        @if($session->status === 'completed')
                            <flux:button 
                                wire:click="selectSession({{ $session->id }})" 
                                variant="ghost" 
                                size="sm"
                            >
                                <flux:icon name="eye" class="w-4 h-4" />
                            </flux:button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </flux:card>
@endif