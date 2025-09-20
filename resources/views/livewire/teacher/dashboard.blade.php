<?php

use App\Models\ClassSession;
use App\Models\ClassAttendance;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('components.layouts.teacher')] class extends Component {
    
    public function mount(): void
    {
        if (!auth()->user()->isTeacher()) {
            abort(403, 'Unauthorized access');
        }
    }
    
    // Today's sessions for the teacher
    public function getTodaySessionsProperty()
    {
        return auth()->user()->teacher
            ->classes()
            ->with(['sessions' => function($query) {
                $query->today()->with(['class.course', 'attendances.student.user']);
            }])
            ->get()
            ->flatMap->sessions
            ->sortBy('session_time');
    }
    
    // This week's statistics
    public function getWeeklyStatsProperty()
    {
        $teacher = auth()->user()->teacher;
        $weekStart = now()->startOfWeek();
        $weekEnd = now()->endOfWeek();
        
        $weekSessions = ClassSession::whereHas('class', function($query) use ($teacher) {
            $query->where('teacher_id', $teacher->id);
        })
        ->whereBetween('session_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
        ->get();
        
        $completedSessions = $weekSessions->where('status', 'completed');
        
        return [
            'total_sessions' => $weekSessions->count(),
            'completed_sessions' => $completedSessions->count(),
            'total_earnings' => $completedSessions->sum('allowance_amount'),
            'total_students' => $teacher->classes()->withCount('activeStudents')->get()->sum('active_students_count'),
            'attendance_rate' => $this->calculateWeeklyAttendanceRate($weekSessions)
        ];
    }
    
    // Monthly earnings
    public function getMonthlyEarningsProperty()
    {
        $teacher = auth()->user()->teacher;
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();
        
        return ClassSession::whereHas('class', function($query) use ($teacher) {
            $query->where('teacher_id', $teacher->id);
        })
        ->whereBetween('session_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
        ->where('status', 'completed')
        ->sum('allowance_amount');
    }
    
    // Upcoming sessions (next 7 days)
    public function getUpcomingSessionsProperty()
    {
        return auth()->user()->teacher
            ->classes()
            ->with(['sessions' => function($query) {
                $query->where('session_date', '>', now()->toDateString())
                      ->where('session_date', '<=', now()->addDays(7)->toDateString())
                      ->where('status', 'scheduled')
                      ->with(['class.course']);
            }])
            ->get()
            ->flatMap->sessions
            ->sortBy(['session_date', 'session_time'])
            ->take(5);
    }
    
    // Recent activities (last 10 activities)
    public function getRecentActivitiesProperty()
    {
        $teacher = auth()->user()->teacher;
        
        $recentSessions = ClassSession::whereHas('class', function($query) use ($teacher) {
            $query->where('teacher_id', $teacher->id);
        })
        ->with(['class.course'])
        ->whereIn('status', ['completed', 'cancelled', 'no_show'])
        ->orderBy('updated_at', 'desc')
        ->limit(5)
        ->get();
        
        $activities = collect();
        
        foreach ($recentSessions as $session) {
            $activities->push([
                'type' => 'session_' . $session->status,
                'message' => $this->getSessionActivityMessage($session),
                'time' => $session->updated_at->diffForHumans(),
                'icon' => $this->getSessionActivityIcon($session->status)
            ]);
        }
        
        return $activities;
    }
    
    // Quick action methods
    public function startSession($sessionId)
    {
        $session = ClassSession::findOrFail($sessionId);
        
        // Verify this session belongs to the current teacher
        if ($session->class->teacher_id !== auth()->user()->teacher->id) {
            abort(403, 'Unauthorized access');
        }
        
        $session->markAsOngoing();
        
        $this->dispatch('session-started', sessionId: $sessionId);
        session()->flash('message', 'Session started successfully!');
    }
    
    public function completeSession($sessionId)
    {
        $session = ClassSession::findOrFail($sessionId);
        
        // Verify this session belongs to the current teacher
        if ($session->class->teacher_id !== auth()->user()->teacher->id) {
            abort(403, 'Unauthorized access');
        }
        
        $session->markCompleted();
        
        $this->dispatch('session-completed', sessionId: $sessionId);
        session()->flash('message', 'Session completed successfully!');
    }
    
    private function calculateWeeklyAttendanceRate($sessions)
    {
        $completedSessions = $sessions->where('status', 'completed');
        
        if ($completedSessions->isEmpty()) {
            return 0;
        }
        
        $totalPossibleAttendance = $completedSessions->sum(function($session) {
            return $session->class->activeStudents()->count();
        });
        
        if ($totalPossibleAttendance === 0) {
            return 0;
        }
        
        $totalPresentAttendance = $completedSessions->sum('present_count');
        
        return round(($totalPresentAttendance / $totalPossibleAttendance) * 100, 1);
    }
    
    private function getSessionActivityMessage($session)
    {
        return match($session->status) {
            'completed' =>"Completed session for {$session->class->course->name}",
            'cancelled' =>"Cancelled session for {$session->class->course->name}",
            'no_show' =>"No-show session for {$session->class->course->name}",
            default =>"Session updated for {$session->class->course->name}"
        };
    }
    
    private function getSessionActivityIcon($status)
    {
        return match($status) {
            'completed' => 'check-circle',
            'cancelled' => 'x-circle',
            'no_show' => 'exclamation-triangle',
            default => 'information-circle'
        };
    }
}; ?>

<div class="w-full space-y-6">
    <!-- Header -->
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Welcome back, {{ auth()->user()->name }}!</flux:heading>
            <flux:text class="mt-2">Here's what's happening with your classes today</flux:text>
        </div>
        <flux:button variant="primary" :href="route('teacher.timetable')" wire:navigate icon="calendar">Timetable</flux:button>
    </div>

    <!-- Statistics Cards -->
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <!-- Today's Sessions -->
        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="sm" class="text-gray-600">Today's Sessions</flux:heading>
                    <flux:heading size="xl">{{ $this->todaySessions->count() }}</flux:heading>
                    <flux:text size="sm" class="text-blue-600">
                        {{ $this->todaySessions->where('status', 'completed')->count() }} completed
                    </flux:text>
                </div>
                <flux:icon icon="calendar-days" class="w-8 h-8 text-blue-500" />
            </div>
        </flux:card>

        <!-- This Week's Earnings -->
        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="sm" class="text-gray-600">Week Earnings</flux:heading>
                    <flux:heading size="xl">RM {{ number_format($this->weeklyStats['total_earnings'], 2) }}</flux:heading>
                    <flux:text size="sm" class="text-emerald-600">
                        {{ $this->weeklyStats['completed_sessions'] }} sessions
                    </flux:text>
                </div>
                <flux:icon icon="currency-dollar" class="w-8 h-8 text-emerald-500" />
            </div>
        </flux:card>

        <!-- Active Students -->
        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="sm" class="text-gray-600">Active Students</flux:heading>
                    <flux:heading size="xl">{{ $this->weeklyStats['total_students'] }}</flux:heading>
                    <flux:text size="sm" class="text-purple-600">Across all classes</flux:text>
                </div>
                <flux:icon icon="users" class="w-8 h-8 text-purple-500" />
            </div>
        </flux:card>

        <!-- Attendance Rate -->
        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="sm" class="text-gray-600">Attendance Rate</flux:heading>
                    <flux:heading size="xl">{{ $this->weeklyStats['attendance_rate'] }}%</flux:heading>
                    <flux:text size="sm" class="text-orange-600">This week</flux:text>
                </div>
                <flux:icon icon="chart-pie" class="w-8 h-8 text-orange-500" />
            </div>
        </flux:card>
    </div>

    <!-- Main Content Grid -->
    <div class="grid gap-6 lg:grid-cols-3">
        <!-- Today's Schedule -->
        <div class="lg:col-span-2">
            <flux:card>
                <flux:header>
                    <flux:heading size="lg">Today's Schedule</flux:heading>
                    <flux:text size="sm" class="text-gray-600">{{ now()->format('l, F j, Y') }}</flux:text>
                </flux:header>
                
                @if($this->todaySessions->isNotEmpty())
                    <div class="space-y-4">
                        @foreach($this->todaySessions as $session)
                            <div class="flex items-center justify-between p-4 bg-gray-50  rounded-lg">
                                <div class="flex items-center space-x-4">
                                    <div class="text-center min-w-[60px]">
                                        <flux:text size="sm" class="font-medium">{{ $session->session_time->format('g:i A') }}</flux:text>
                                        <flux:text size="xs" class="text-gray-500">{{ $session->formatted_duration }}</flux:text>
                                    </div>
                                    <div class="flex-1">
                                        <flux:heading size="sm">{{ $session->class->course->name }}</flux:heading>
                                        <flux:text size="sm" class="text-gray-600">
                                            {{ $session->class->activeStudents()->count() }} students • {{ $session->class->location ?? 'Online' }}
                                        </flux:text>
                                    </div>
                                    <div>
                                        <flux:badge 
                                            :color="$session->status === 'completed' ? 'emerald' : ($session->status === 'ongoing' ? 'blue' : ($session->status === 'scheduled' ? 'gray' : 'red'))">
                                            {{ ucfirst($session->status) }}
                                        </flux:badge>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    @if($session->isScheduled())
                                        <flux:button 
                                            variant="primary" 
                                            size="sm" 
                                            icon="play"
                                            wire:click="startSession({{ $session->id }})"
                                        >
                                            Start
                                        </flux:button>
                                    @elseif($session->isOngoing())
                                        <flux:button 
                                            variant="primary" 
                                            size="sm" 
                                            icon="check"
                                            wire:click="completeSession({{ $session->id }})"
                                        >
                                            Complete
                                        </flux:button>
                                    @endif
                                    <flux:button 
                                        variant="ghost" 
                                        size="sm" 
                                        icon="eye"
                                        :href="route('teacher.sessions.show', $session)"
                                        wire:navigate
                                    >
                                        View
                                    </flux:button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-12">
                        <flux:icon icon="calendar-days" class="w-12 h-12 text-gray-400 mx-auto mb-4" />
                        <flux:heading size="lg" class="text-gray-600  mb-2">No sessions today</flux:heading>
                        <flux:text class="text-gray-600">Take a well-deserved break!</flux:text>
                    </div>
                @endif
                
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <flux:link :href="route('teacher.sessions.index')" wire:navigate variant="subtle" icon="calendar-days">
                        View all sessions
                    </flux:link>
                </div>
            </flux:card>
        </div>

        <!-- Sidebar Content -->
        <div class="space-y-6">
            <!-- Monthly Earnings Overview -->
            <flux:card>
                <flux:header>
                    <flux:heading size="lg">This Month</flux:heading>
                </flux:header>
                
                <div class="space-y-4">
                    <div class="text-center">
                        <flux:heading size="2xl" class="text-emerald-600">RM {{ number_format($this->monthlyEarnings, 2) }}</flux:heading>
                        <flux:text size="sm" class="text-gray-600">Total Earnings</flux:text>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 text-center">
                        <div>
                            <flux:text class="font-medium">{{ $this->weeklyStats['completed_sessions'] }}</flux:text>
                            <flux:text size="xs" class="text-gray-600">Sessions</flux:text>
                        </div>
                        <div>
                            <flux:text class="font-medium">{{ $this->weeklyStats['attendance_rate'] }}%</flux:text>
                            <flux:text size="xs" class="text-gray-600">Attendance</flux:text>
                        </div>
                    </div>
                </div>
            </flux:card>

            <!-- Recent Activity -->
            <flux:card>
                <flux:header>
                    <flux:heading size="lg">Recent Activity</flux:heading>
                </flux:header>
                
                @if($this->recentActivities->isNotEmpty())
                    <div class="space-y-3">
                        @foreach($this->recentActivities as $activity)
                            <div class="flex items-start space-x-3">
                                <div class="w-8 h-8 bg-gray-100  rounded-full flex items-center justify-center">
                                    <flux:icon icon="{{ $activity['icon'] }}" class="w-4 h-4 text-gray-600" />
                                </div>
                                <div class="flex-1">
                                    <flux:text size="sm">{{ $activity['message'] }}</flux:text>
                                    <flux:text size="xs" class="text-gray-600">{{ $activity['time'] }}</flux:text>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <flux:text class="text-gray-600">No recent activity</flux:text>
                @endif
            </flux:card>

            <!-- Upcoming Sessions -->
            <flux:card>
                <flux:header>
                    <flux:heading size="lg">Coming Up</flux:heading>
                </flux:header>
                
                @if($this->upcomingSessions->isNotEmpty())
                    <div class="space-y-3">
                        @foreach($this->upcomingSessions as $session)
                            <div class="flex items-center justify-between p-3 bg-gray-50  rounded-lg">
                                <div>
                                    <flux:text size="sm" class="font-medium">{{ $session->class->course->name }}</flux:text>
                                    <flux:text size="xs" class="text-gray-600">
                                        {{ $session->session_date->format('M j') }} at {{ $session->session_time->format('g:i A') }}
                                    </flux:text>
                                </div>
                                <flux:text size="xs" class="text-gray-500">
                                    {{ $session->class->activeStudents()->count() }} students
                                </flux:text>
                            </div>
                        @endforeach
                    </div>
                @else
                    <flux:text class="text-gray-600">No upcoming sessions</flux:text>
                @endif
                
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <flux:link :href="route('teacher.timetable')" wire:navigate variant="subtle" icon="calendar">
                        View timetable
                    </flux:link>
                </div>
            </flux:card>
        </div>
    </div>
</div>