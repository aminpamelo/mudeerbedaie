<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use App\Models\ClassSession;
use App\Models\ClassModel;
use Carbon\Carbon;

new #[Layout('components.layouts.teacher')] class extends Component {
    use WithPagination;
    
    public string $dateFilter = 'upcoming';
    public string $classFilter = 'all';
    public string $statusFilter = 'all';
    public string $search = '';
    
    protected $queryString = [
        'search' => ['except' => ''],
        'dateFilter' => ['except' => 'upcoming'],
        'classFilter' => ['except' => 'all'],
        'statusFilter' => ['except' => 'all'],
    ];
    
    public function with()
    {
        $teacher = auth()->user()->teacher;
        
        if (!$teacher) {
            return [
                'sessions' => collect(),
                'classes' => collect(),
                'statistics' => $this->getEmptyStatistics()
            ];
        }
        
        // Get teacher's classes
        $classes = $teacher->classes()->with('course')->get();
        
        // Build sessions query
        $query = ClassSession::with(['class.course', 'attendances.student.user'])
            ->whereHas('class', function($q) use ($teacher) {
                $q->where('teacher_id', $teacher->id);
            });
        
        // Apply date filter
        $today = now()->startOfDay();
        switch ($this->dateFilter) {
            case 'today':
                $query->whereDate('session_date', $today);
                break;
            case 'upcoming':
                $query->where('session_date', '>=', $today);
                break;
            case 'past':
                $query->where('session_date', '<', $today);
                break;
            case 'this_week':
                $query->whereBetween('session_date', [
                    $today->startOfWeek(),
                    $today->copy()->endOfWeek()
                ]);
                break;
        }
        
        // Apply class filter
        if ($this->classFilter !== 'all') {
            $query->where('class_id', $this->classFilter);
        }
        
        // Apply status filter
        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }
        
        // Apply search filter
        if ($this->search) {
            $query->where(function($q) {
                $q->whereHas('class', function($classQuery) {
                    $classQuery->where('title', 'like', '%' . $this->search . '%')
                              ->orWhereHas('course', function($courseQuery) {
                                  $courseQuery->where('name', 'like', '%' . $this->search . '%');
                              });
                })
                ->orWhere('teacher_notes', 'like', '%' . $this->search . '%')
                ->orWhereHas('attendances.student.user', function($userQuery) {
                    $userQuery->where('name', 'like', '%' . $this->search . '%');
                });
            });
        }
        
        $sessions = $query->orderBy('session_date', 'desc')
                         ->orderBy('session_time', 'desc')
                         ->paginate(10);
        
        // Calculate statistics (using separate query for accurate counts)
        $statsQuery = ClassSession::whereHas('class', function($q) use ($teacher) {
            $q->where('teacher_id', $teacher->id);
        });
        
        $statistics = [
            'total_sessions' => $statsQuery->count(),
            'upcoming_sessions' => $statsQuery->where('session_date', '>=', $today)->count(),
            'completed_sessions' => $statsQuery->where('status', 'completed')->count(),
            'cancelled_sessions' => $statsQuery->where('status', 'cancelled')->count(),
        ];
        
        return [
            'sessions' => $sessions,
            'classes' => $classes,
            'statistics' => $statistics
        ];
    }
    
    private function getEmptyStatistics(): array
    {
        return [
            'total_sessions' => 0,
            'upcoming_sessions' => 0,
            'completed_sessions' => 0,
            'cancelled_sessions' => 0,
        ];
    }
    
    public function updatedDateFilter()
    {
        $this->resetPage();
    }
    
    public function updatedClassFilter()
    {
        $this->resetPage();
    }
    
    public function updatedStatusFilter()
    {
        $this->resetPage();
    }
    
    public function updatedSearch()
    {
        $this->resetPage();
    }
    
    public function clearSearch()
    {
        $this->search = '';
        $this->resetPage();
    }
    
    public function startSession($sessionId)
    {
        $session = ClassSession::find($sessionId);
        if ($session && $session->isScheduled()) {
            $session->markAsOngoing();
            session()->flash('success', 'Session started successfully.');
        }
    }
    
    public function completeSession($sessionId)
    {
        $session = ClassSession::find($sessionId);
        if ($session && $session->isOngoing()) {
            $session->markCompleted();
            session()->flash('success', 'Session completed successfully.');
        }
    }
    
    public function cancelSession($sessionId)
    {
        $session = ClassSession::find($sessionId);
        if ($session && $session->isScheduled()) {
            $session->cancel();
            session()->flash('success', 'Session cancelled.');
        }
    }
    
    public function markAsNoShow($sessionId)
    {
        $session = ClassSession::find($sessionId);
        if ($session && ($session->isScheduled() || $session->isOngoing())) {
            $session->markAsNoShow();
            session()->flash('success', 'Session marked as no-show.');
        }
    }
    
    public function exportSessions()
    {
        $teacher = auth()->user()->teacher;
        
        if (!$teacher) {
            session()->flash('error', 'Teacher not found.');
            return;
        }
        
        // Build the same query as the main sessions query
        $query = ClassSession::with(['class.course', 'attendances.student.user'])
            ->whereHas('class', function($q) use ($teacher) {
                $q->where('teacher_id', $teacher->id);
            });
        
        // Apply the same filters
        $today = now()->startOfDay();
        switch ($this->dateFilter) {
            case 'today':
                $query->whereDate('session_date', $today);
                break;
            case 'upcoming':
                $query->where('session_date', '>=', $today);
                break;
            case 'past':
                $query->where('session_date', '<', $today);
                break;
            case 'this_week':
                $query->whereBetween('session_date', [
                    $today->startOfWeek(),
                    $today->copy()->endOfWeek()
                ]);
                break;
        }
        
        if ($this->classFilter !== 'all') {
            $query->where('class_id', $this->classFilter);
        }
        
        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }
        
        if ($this->search) {
            $query->where(function($q) {
                $q->whereHas('class', function($classQuery) {
                    $classQuery->where('title', 'like', '%' . $this->search . '%')
                              ->orWhereHas('course', function($courseQuery) {
                                  $courseQuery->where('name', 'like', '%' . $this->search . '%');
                              });
                })
                ->orWhere('teacher_notes', 'like', '%' . $this->search . '%')
                ->orWhereHas('attendances.student.user', function($userQuery) {
                    $userQuery->where('name', 'like', '%' . $this->search . '%');
                });
            });
        }
        
        $sessions = $query->orderBy('session_date', 'desc')
                         ->orderBy('session_time', 'desc')
                         ->get();
        
        // Create CSV content
        $csvContent ="Date,Time,Class,Course,Duration,Status,Students,Present,Allowance,Notes\n";
        
        foreach ($sessions as $session) {
            $attendanceCount = $session->attendances->count();
            $presentCount = $session->attendances->whereIn('status', ['present', 'late'])->count();
            $allowance = $session->allowance_amount ? 'RM' . number_format($session->allowance_amount, 2) : '';
            
            $csvContent .= sprintf(
"%s,%s,%s,%s,%s,%s,%d,%d,%s,%s\n",
                $session->session_date->format('Y-m-d'),
                $session->session_time->format('H:i'),
                '"' . str_replace('"', '""', $session->class->title) . '"',
                '"' . str_replace('"', '""', $session->class->course->name) . '"',
                $session->duration_minutes . 'min',
                $session->status,
                $attendanceCount,
                $presentCount,
                $allowance,
                '"' . str_replace('"', '""', $session->teacher_notes ?? '') . '"'
            );
        }
        
        // Generate filename with current date and filters
        $filename = 'sessions_export_' . date('Y-m-d_H-i-s') . '.csv';
        
        return response()->streamDownload(function() use ($csvContent) {
            echo $csvContent;
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}; ?>

<div>
    <!-- Page Header -->
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">My Sessions</flux:heading>
            <flux:text class="mt-2">View and manage your upcoming and past class sessions</flux:text>
        </div>
        <flux:button wire:click="exportSessions" variant="ghost">
            <flux:icon name="arrow-down-tray" class="w-4 h-4 mr-2" />
            Export CSV
        </flux:button>
    </div>

    @if(session('success'))
        <flux:card class="p-4 mb-6 bg-green-50 /20 border-green-200">
            <flux:text class="text-green-800">{{ session('success') }}</flux:text>
        </flux:card>
    @endif
    
    @if(session('error'))
        <flux:card class="p-4 mb-6 bg-red-50 /20 border-red-200">
            <flux:text class="text-red-800">{{ session('error') }}</flux:text>
        </flux:card>
    @endif

    <!-- Statistics Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <flux:card class="p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold text-blue-600">{{ $statistics['total_sessions'] }}</div>
                    <div class="text-sm text-gray-600">Total Sessions</div>
                </div>
                <flux:icon name="calendar-days" class="h-8 w-8 text-blue-500" />
            </div>
        </flux:card>

        <flux:card class="p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold text-emerald-600">{{ $statistics['upcoming_sessions'] }}</div>
                    <div class="text-sm text-gray-600">Upcoming</div>
                </div>
                <flux:icon name="clock" class="h-8 w-8 text-emerald-500" />
            </div>
        </flux:card>

        <flux:card class="p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold text-purple-600">{{ $statistics['completed_sessions'] }}</div>
                    <div class="text-sm text-gray-600">Completed</div>
                </div>
                <flux:icon name="check-circle" class="h-8 w-8 text-purple-500" />
            </div>
        </flux:card>

        <flux:card class="p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold text-red-600">{{ $statistics['cancelled_sessions'] }}</div>
                    <div class="text-sm text-gray-600">Cancelled</div>
                </div>
                <flux:icon name="x-circle" class="h-8 w-8 text-red-500" />
            </div>
        </flux:card>
    </div>

    <!-- Filters and Search -->
    <flux:card class="p-6 mb-6">
        <div class="space-y-4">
            <!-- Search Bar -->
            <div class="flex gap-3">
                <div class="flex-1 relative">
                    <flux:input 
                        wire:model.live.debounce.300ms="search" 
                        placeholder="Search by class title, course, teacher notes, or student name..."
                        class="w-full"
                    />
                    @if($search)
                        <flux:button 
                            wire:click="clearSearch" 
                            variant="ghost" 
                            size="sm" 
                            class="absolute right-2 top-1/2 -translate-y-1/2"
                        >
                            <flux:icon name="x-mark" class="w-4 h-4" />
                        </flux:button>
                    @endif
                </div>
            </div>
            
            <!-- Filters -->
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div class="flex flex-col sm:flex-row gap-3">
                    <flux:select wire:model.live="dateFilter" class="min-w-40">
                        <option value="upcoming">Upcoming Sessions</option>
                        <option value="today">Today's Sessions</option>
                        <option value="this_week">This Week</option>
                        <option value="past">Past Sessions</option>
                    </flux:select>
                    
                    <flux:select wire:model.live="classFilter" placeholder="All Classes" class="min-w-40">
                        <option value="all">All Classes</option>
                        @foreach($classes as $class)
                            <option value="{{ $class->id }}">{{ $class->title }}</option>
                        @endforeach
                    </flux:select>
                    
                    <flux:select wire:model.live="statusFilter" placeholder="All Status" class="min-w-32">
                        <option value="all">All Status</option>
                        <option value="scheduled">Scheduled</option>
                        <option value="ongoing">Ongoing</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="no_show">No Show</option>
                        <option value="rescheduled">Rescheduled</option>
                    </flux:select>
                </div>
            </div>
        </div>
    </flux:card>

    @if($sessions->count() > 0)
        <!-- Sessions List -->
        <div class="space-y-4">
            @foreach($sessions as $session)
                @php
                    $isUpcoming = $session->session_date >= now()->startOfDay();
                    $isToday = $session->session_date->isToday();
                    $attendanceCount = $session->attendances->count();
                    $presentCount = $session->attendances->whereIn('status', ['present', 'late'])->count();
                @endphp
                
                <flux:card class="p-6 hover:shadow-lg transition-shadow">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                        <!-- Session Info -->
                        <div class="flex-1">
                            <div class="flex items-start justify-between mb-3">
                                <div>
                                    <flux:heading size="sm" class="mb-2">{{ $session->class->title }}</flux:heading>
                                    <flux:text size="xs" class="text-gray-500  mb-2">
                                        {{ $session->class->course->name }}
                                    </flux:text>
                                    @if($session->topic)
                                        <flux:text size="sm" class="text-gray-600  mb-2">
                                            Topic: {{ $session->topic }}
                                        </flux:text>
                                    @endif
                                </div>
                                <div class="flex flex-col gap-2">
                                    @if($session->status === 'scheduled')
                                        <flux:badge color="blue" size="sm">Scheduled</flux:badge>
                                    @elseif($session->status === 'ongoing')
                                        <flux:badge color="yellow" size="sm">Ongoing</flux:badge>
                                    @elseif($session->status === 'completed')
                                        <flux:badge color="green" size="sm">Completed</flux:badge>
                                    @elseif($session->status === 'cancelled')
                                        <flux:badge color="red" size="sm">Cancelled</flux:badge>
                                    @endif
                                    
                                    @if($isToday)
                                        <flux:badge color="orange" size="sm">Today</flux:badge>
                                    @endif
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 text-sm text-gray-600">
                                <div class="flex items-center">
                                    <flux:icon name="calendar" class="w-4 h-4 mr-2" />
                                    {{ $session->session_date->format('M d, Y') }}
                                </div>
                                <div class="flex items-center">
                                    <flux:icon name="clock" class="w-4 h-4 mr-2" />
                                    {{ $session->session_time->format('g:i A') }} ({{ $session->duration_minutes }}min)
                                </div>
                                <div class="flex items-center">
                                    <flux:icon name="users" class="w-4 h-4 mr-2" />
                                    {{ $attendanceCount }} students
                                </div>
                                @if($session->status === 'completed')
                                    <div class="flex items-center">
                                        <flux:icon name="chart-bar" class="w-4 h-4 mr-2" />
                                        {{ $attendanceCount > 0 ? round(($presentCount / $attendanceCount) * 100) : 0 }}% present
                                    </div>
                                @endif
                            </div>
                        </div>
                        
                        <!-- Actions -->
                        <div class="flex items-center gap-2">
                            @if($isUpcoming && $session->status === 'scheduled')
                                <flux:button size="sm" variant="primary" wire:click="startSession({{ $session->id }})">
                                    <flux:icon name="play" class="w-4 h-4 mr-1" />
                                    Start Session
                                </flux:button>
                            @elseif($session->status === 'ongoing')
                                <flux:button size="sm" variant="primary" wire:click="completeSession({{ $session->id }})">
                                    <flux:icon name="check-circle" class="w-4 h-4 mr-1" />
                                    Complete
                                </flux:button>
                            @endif
                            
                            @if($session->status === 'completed' && $session->allowance_amount)
                                <div class="text-sm text-green-600  font-medium mr-2">
                                    RM{{ number_format($session->allowance_amount, 2) }}
                                </div>
                            @endif
                            
                            <flux:button size="sm" variant="ghost" href="{{ route('teacher.sessions.show', $session) }}">
                                <flux:icon name="eye" class="w-4 h-4 mr-1" />
                                View Details
                            </flux:button>
                            
                            <flux:button size="sm" variant="ghost" href="{{ route('teacher.classes.show', $session->class) }}">
                                <flux:icon name="academic-cap" class="w-4 h-4 mr-1" />
                                View Class
                            </flux:button>
                        </div>
                    </div>
                </flux:card>
            @endforeach
        </div>
        
        <!-- Pagination -->
        <div class="mt-6">
            {{ $sessions->links() }}
        </div>
    @else
        <!-- Empty State -->
        <flux:card class="text-center py-12">
            <flux:icon name="calendar-days" class="w-16 h-16 text-gray-400 mx-auto mb-4" />
            @if($dateFilter !== 'all' || $classFilter !== 'all' || $statusFilter !== 'all')
                <flux:heading size="lg" class="mb-4">No Sessions Found</flux:heading>
                <flux:text class="text-gray-600  mb-6 max-w-md mx-auto">
                    No sessions match your current filter criteria. Try adjusting your filters.
                </flux:text>
                <flux:button variant="ghost" wire:click="$set('dateFilter', 'upcoming'); $set('classFilter', 'all'); $set('statusFilter', 'all')">
                    Clear All Filters
                </flux:button>
            @else
                <flux:heading size="lg" class="mb-4">No Sessions Scheduled</flux:heading>
                <flux:text class="text-gray-600  mb-6 max-w-md mx-auto">
                    You don't have any sessions scheduled yet. Sessions will appear here once they are created for your classes.
                </flux:text>
                <flux:button variant="primary" href="{{ route('teacher.classes.index') }}" wire:navigate>
                    View My Classes
                </flux:button>
            @endif
        </flux:card>
    @endif
</div>