<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\ClassSession;
use App\Models\ClassModel;
use App\Models\Teacher;
use Carbon\Carbon;

new class extends Component {
    use WithPagination;
    
    public string $dateFilter = 'upcoming';
    public string $classFilter = 'all';
    public string $statusFilter = 'all';
    public string $teacherFilter = 'all';
    public string $verificationFilter = 'all';
    public string $search = '';
    
    protected $queryString = [
        'search' => ['except' => ''],
        'dateFilter' => ['except' => 'upcoming'],
        'classFilter' => ['except' => 'all'],
        'statusFilter' => ['except' => 'all'],
        'teacherFilter' => ['except' => 'all'],
        'verificationFilter' => ['except' => 'all'],
    ];
    
    public function with()
    {
        // Get all classes and teachers for filters
        $classes = ClassModel::with('course')->get();
        $teachers = Teacher::with('user')->get();
        
        // Build sessions query
        $query = ClassSession::with(['class.course', 'class.teacher.user', 'attendances.student.user', 'payslips']);
        
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
        
        // Apply teacher filter
        if ($this->teacherFilter !== 'all') {
            $query->whereHas('class', function($q) {
                $q->where('teacher_id', $this->teacherFilter);
            });
        }
        
        // Apply status filter
        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }
        
        // Apply verification filter
        if ($this->verificationFilter !== 'all') {
            if ($this->verificationFilter === 'verified') {
                $query->whereNotNull('verified_at');
            } elseif ($this->verificationFilter === 'unverified') {
                $query->whereNull('verified_at');
            } elseif ($this->verificationFilter === 'verifiable') {
                $query->verifiableForPayroll();
            }
        }
        
        // Apply search filter
        if ($this->search) {
            $query->where(function($q) {
                $q->whereHas('class', function($classQuery) {
                    $classQuery->where('title', 'like', '%' . $this->search . '%')
                              ->orWhereHas('course', function($courseQuery) {
                                  $courseQuery->where('name', 'like', '%' . $this->search . '%');
                              })
                              ->orWhereHas('teacher.user', function($teacherQuery) {
                                  $teacherQuery->where('name', 'like', '%' . $this->search . '%');
                              });
                })
                ->orWhere('teacher_notes', 'like', '%' . $this->search . '%')
                ->orWhere('topic', 'like', '%' . $this->search . '%')
                ->orWhereHas('attendances.student.user', function($userQuery) {
                    $userQuery->where('name', 'like', '%' . $this->search . '%');
                });
            });
        }
        
        $sessions = $query->orderBy('session_date', 'desc')
                         ->orderBy('session_time', 'desc')
                         ->paginate(10);
        
        // Calculate statistics
        $statsQuery = ClassSession::query();
        
        $statistics = [
            'total_sessions' => $statsQuery->count(),
            'upcoming_sessions' => $statsQuery->where('session_date', '>=', $today)->count(),
            'completed_sessions' => $statsQuery->where('status', 'completed')->count(),
            'verified_sessions' => $statsQuery->whereNotNull('verified_at')->count(),
        ];
        
        return [
            'sessions' => $sessions,
            'classes' => $classes,
            'teachers' => $teachers,
            'statistics' => $statistics
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
    
    public function updatedTeacherFilter()
    {
        $this->resetPage();
    }
    
    public function updatedVerificationFilter()
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
    
    public function verifySession($sessionId)
    {
        try {
            $session = ClassSession::findOrFail($sessionId);
            $session->verify(auth()->user());
            
            session()->flash('success', 'Session has been verified successfully.');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to verify session: ' . $e->getMessage());
        }
    }
    
    public function unverifySession($sessionId)
    {
        try {
            $session = ClassSession::findOrFail($sessionId);
            $session->unverify();
            
            session()->flash('success', 'Session verification has been removed.');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to unverify session: ' . $e->getMessage());
        }
    }
    
    public function exportSessions()
    {
        // Build the same query as the main sessions query
        $query = ClassSession::with(['class.course', 'class.teacher.user', 'attendances.student.user', 'payslips']);
        
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
        
        if ($this->teacherFilter !== 'all') {
            $query->whereHas('class', function($q) {
                $q->where('teacher_id', $this->teacherFilter);
            });
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
                              })
                              ->orWhereHas('teacher.user', function($teacherQuery) {
                                  $teacherQuery->where('name', 'like', '%' . $this->search . '%');
                              });
                })
                ->orWhere('teacher_notes', 'like', '%' . $this->search . '%')
                ->orWhere('topic', 'like', '%' . $this->search . '%')
                ->orWhereHas('attendances.student.user', function($userQuery) {
                    $userQuery->where('name', 'like', '%' . $this->search . '%');
                });
            });
        }
        
        $sessions = $query->orderBy('session_date', 'desc')
                         ->orderBy('session_time', 'desc')
                         ->get();
        
        // Create CSV content
        $csvContent = "Date,Time,Class,Course,Teacher,Duration,Status,Students,Present,Allowance,Notes\n";
        
        foreach ($sessions as $session) {
            $attendanceCount = $session->attendances->count();
            $presentCount = $session->attendances->whereIn('status', ['present', 'late'])->count();
            $allowance = $session->allowance_amount ? 'RM' . number_format($session->allowance_amount, 2) : '';
            $teacherName = $session->class->teacher ? $session->class->teacher->user->name : 'N/A';
            
            $csvContent .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s,%d,%d,%s,%s\n",
                $session->session_date->format('Y-m-d'),
                $session->session_time->format('H:i'),
                '"' . str_replace('"', '""', $session->class->title) . '"',
                '"' . str_replace('"', '""', $session->class->course->name) . '"',
                '"' . str_replace('"', '""', $teacherName) . '"',
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
            <flux:heading size="xl">All Sessions</flux:heading>
            <flux:text class="mt-2">View and manage all class sessions across the system</flux:text>
        </div>
        <flux:button wire:click="exportSessions" variant="ghost">
            <flux:icon name="arrow-down-tray" class="w-4 h-4 mr-2" />
            Export CSV
        </flux:button>
    </div>

    @if(session('success'))
        <flux:card class="p-4 mb-6 bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800">
            <flux:text class="text-green-800 dark:text-green-200">{{ session('success') }}</flux:text>
        </flux:card>
    @endif
    
    @if(session('error'))
        <flux:card class="p-4 mb-6 bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800">
            <flux:text class="text-red-800 dark:text-red-200">{{ session('error') }}</flux:text>
        </flux:card>
    @endif

    <!-- Statistics Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <flux:card class="p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $statistics['total_sessions'] }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Total Sessions</div>
                </div>
                <flux:icon name="calendar-days" class="h-8 w-8 text-blue-500" />
            </div>
        </flux:card>

        <flux:card class="p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ $statistics['upcoming_sessions'] }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Upcoming</div>
                </div>
                <flux:icon name="clock" class="h-8 w-8 text-emerald-500" />
            </div>
        </flux:card>

        <flux:card class="p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">{{ $statistics['completed_sessions'] }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Completed</div>
                </div>
                <flux:icon name="check-circle" class="h-8 w-8 text-purple-500" />
            </div>
        </flux:card>

        <flux:card class="p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $statistics['verified_sessions'] }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Verified</div>
                </div>
                <flux:icon name="check-badge" class="h-8 w-8 text-green-500" />
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
                        placeholder="Search by class, course, teacher, notes, or student name..."
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
                    
                    <flux:select wire:model.live="teacherFilter" placeholder="All Teachers" class="min-w-40">
                        <option value="all">All Teachers</option>
                        @foreach($teachers as $teacher)
                            <option value="{{ $teacher->id }}">{{ $teacher->user->name }}</option>
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
                    
                    <flux:select wire:model.live="verificationFilter" placeholder="All Verification" class="min-w-40">
                        <option value="all">All Verification</option>
                        <option value="verified">Verified</option>
                        <option value="unverified">Unverified</option>
                        <option value="verifiable">Verifiable for Payroll</option>
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
                
                <flux:card class="p-6 hover:shadow-lg transition-all duration-200 border-l-4 border-l-transparent hover:border-l-blue-500">
                    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-6">
                        <!-- Session Info -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex-1">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <flux:heading size="sm" class="mb-2">{{ $session->class->title }}</flux:heading>
                                            <flux:text size="xs" class="text-gray-500 dark:text-gray-400 mb-1">
                                                {{ $session->class->course->name }}
                                            </flux:text>
                                            @if($session->class->teacher)
                                                <flux:text size="xs" class="text-gray-500 dark:text-gray-400 mb-2">
                                                    Teacher: {{ $session->class->teacher->user->name }}
                                                </flux:text>
                                            @endif
                                            @if($session->topic)
                                                <flux:text size="sm" class="text-gray-600 dark:text-gray-400">
                                                    Topic: {{ $session->topic }}
                                                </flux:text>
                                            @endif
                                        </div>
                                        
                                        <!-- Status Badges -->
                                        <div class="flex flex-wrap gap-1 justify-end">
                                            @if($session->status === 'scheduled')
                                                <flux:badge color="blue" size="sm">Scheduled</flux:badge>
                                            @elseif($session->status === 'ongoing')
                                                <flux:badge color="yellow" size="sm">Ongoing</flux:badge>
                                            @elseif($session->status === 'completed')
                                                <flux:badge color="green" size="sm">Completed</flux:badge>
                                            @elseif($session->status === 'cancelled')
                                                <flux:badge color="red" size="sm">Cancelled</flux:badge>
                                            @endif
                                            
                                            {{-- Payout Status Badge --}}
                                            @if($session->status === 'completed')
                                                @if($session->isPaid())
                                                    <flux:badge color="green" size="sm">
                                                        <flux:icon name="banknotes" class="w-3 h-3 mr-1" />
                                                        Paid
                                                    </flux:badge>
                                                @elseif($session->isIncludedInPayslip())
                                                    <flux:badge color="yellow" size="sm">
                                                        <flux:icon name="document-text" class="w-3 h-3 mr-1" />
                                                        In Payslip
                                                    </flux:badge>
                                                @elseif($session->isUnpaid() && $session->verified_at)
                                                    <flux:badge color="orange" size="sm">
                                                        <flux:icon name="exclamation-triangle" class="w-3 h-3 mr-1" />
                                                        Unpaid
                                                    </flux:badge>
                                                @endif
                                            @endif
                                            
                                            @if($session->verified_at)
                                                <flux:badge color="emerald" size="sm">
                                                    <flux:icon name="check-badge" class="w-3 h-3 mr-1" />
                                                    Verified
                                                </flux:badge>
                                            @elseif($session->status === 'completed' && $session->allowance_amount)
                                                <flux:badge color="amber" size="sm">
                                                    <flux:icon name="clock" class="w-3 h-3 mr-1" />
                                                    Pending Verification
                                                </flux:badge>
                                            @endif
                                            
                                            @if($isToday)
                                                <flux:badge color="orange" size="sm">
                                                    <flux:icon name="calendar" class="w-3 h-3 mr-1" />
                                                    Today
                                                </flux:badge>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Session Details Grid -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                <div class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                                    <flux:icon name="calendar" class="w-4 h-4 mr-2 text-gray-400" />
                                    <span>{{ $session->session_date->format('M d, Y') }}</span>
                                </div>
                                <div class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                                    <flux:icon name="clock" class="w-4 h-4 mr-2 text-gray-400" />
                                    <span>{{ $session->session_time->format('g:i A') }} ({{ $session->duration_minutes }}min)</span>
                                </div>
                                <div class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                                    <flux:icon name="users" class="w-4 h-4 mr-2 text-gray-400" />
                                    <span>{{ $attendanceCount }} students</span>
                                </div>
                                @if($session->status === 'completed')
                                    <div class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                                        <flux:icon name="chart-bar" class="w-4 h-4 mr-2 text-gray-400" />
                                        <span>{{ $attendanceCount > 0 ? round(($presentCount / $attendanceCount) * 100) : 0 }}% attendance</span>
                                    </div>
                                @endif
                            </div>
                            
                            {{-- Payslip Information --}}
                            @if($session->status === 'completed' && $session->verified_at)
                                <div class="mt-4 p-3 bg-gray-50 dark:bg-gray-800/50 rounded-md">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center text-sm">
                                            <flux:icon name="document-text" class="w-4 h-4 mr-2 text-gray-500" />
                                            <span class="font-medium text-gray-700 dark:text-gray-300">Payslip Status:</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            @if($session->isPaid())
                                                @php
                                                    $payslip = $session->payslips->first();
                                                @endphp
                                                <flux:text size="sm" class="text-green-700 dark:text-green-300">Paid</flux:text>
                                                @if($payslip)
                                                    <flux:button size="xs" variant="ghost" href="{{ route('admin.payslips.show', $payslip) }}" wire:navigate class="text-green-600 hover:text-green-700">
                                                        View Payslip
                                                    </flux:button>
                                                @endif
                                            @elseif($session->isIncludedInPayslip())
                                                @php
                                                    $payslip = $session->payslips->first();
                                                @endphp
                                                <flux:text size="sm" class="text-yellow-700 dark:text-yellow-300">Included in Payslip</flux:text>
                                                @if($payslip)
                                                    <flux:button size="xs" variant="ghost" href="{{ route('admin.payslips.show', $payslip) }}" wire:navigate class="text-yellow-600 hover:text-yellow-700">
                                                        View Payslip
                                                    </flux:button>
                                                @endif
                                            @elseif($session->isUnpaid())
                                                <flux:text size="sm" class="text-orange-700 dark:text-orange-300">Ready for Payslip</flux:text>
                                                <flux:button size="xs" variant="ghost" href="{{ route('admin.payslips.index') }}" wire:navigate class="text-orange-600 hover:text-orange-700">
                                                    Generate Payslip
                                                </flux:button>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                        
                        <!-- Actions -->
                        <div class="flex flex-col items-end gap-3 lg:min-w-fit">
                            @if($session->status === 'completed' && $session->allowance_amount)
                                <div class="text-lg font-bold text-green-600 dark:text-green-400 bg-green-50 dark:bg-green-900/20 px-3 py-1 rounded-md">
                                    RM{{ number_format($session->allowance_amount, 2) }}
                                </div>
                            @endif
                            
                            <!-- Verification Actions - Prominent buttons outside dropdown -->
                            <div class="flex items-center gap-2">
                                @if($session->status === 'completed' && $session->allowance_amount && !$session->verified_at)
                                    <flux:button size="sm" variant="primary" wire:click="verifySession({{ $session->id }})" class="whitespace-nowrap bg-green-600 hover:bg-green-700 text-white border-green-600 hover:border-green-700 shadow-sm">
                                        <div class="flex items-center justify-center">
                                            <flux:icon name="check-badge" class="w-4 h-4 mr-1" />
                                            Verify
                                        </div>
                                    </flux:button>
                                @elseif($session->verified_at)
                                    <flux:button size="sm" variant="outline" wire:click="unverifySession({{ $session->id }})" class="whitespace-nowrap text-amber-700 border-amber-300 hover:bg-amber-50 hover:border-amber-400">
                                        <div class="flex items-center justify-center">
                                            <flux:icon name="x-mark" class="w-4 h-4 mr-1" />
                                            Unverify
                                        </div>
                                    </flux:button>
                                @endif
                                
                                <!-- Navigation Actions Dropdown -->
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" class="hover:bg-gray-100 dark:hover:bg-gray-800" />
                                    
                                    <flux:menu class="min-w-48">
                                        <flux:menu.item icon="eye" href="{{ route('admin.sessions.show', $session) }}" wire:navigate>
                                            View Details
                                        </flux:menu.item>
                                        
                                        <flux:menu.item icon="academic-cap" href="{{ route('classes.show', $session->class) }}" wire:navigate>
                                            View Class
                                        </flux:menu.item>
                                        
                                        @if($session->class->teacher)
                                            <flux:menu.item icon="user" href="{{ route('teachers.show', $session->class->teacher) }}" wire:navigate>
                                                View Teacher
                                            </flux:menu.item>
                                        @endif
                                    </flux:menu>
                                </flux:dropdown>
                            </div>
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
            @if($dateFilter !== 'all' || $classFilter !== 'all' || $statusFilter !== 'all' || $teacherFilter !== 'all' || $verificationFilter !== 'all')
                <flux:heading size="lg" class="mb-4">No Sessions Found</flux:heading>
                <flux:text class="text-gray-600 dark:text-gray-400 mb-6 max-w-md mx-auto">
                    No sessions match your current filter criteria. Try adjusting your filters.
                </flux:text>
                <flux:button variant="ghost" wire:click="$set('dateFilter', 'upcoming'); $set('classFilter', 'all'); $set('statusFilter', 'all'); $set('teacherFilter', 'all'); $set('verificationFilter', 'all')">
                    Clear All Filters
                </flux:button>
            @else
                <flux:heading size="lg" class="mb-4">No Sessions Scheduled</flux:heading>
                <flux:text class="text-gray-600 dark:text-gray-400 mb-6 max-w-md mx-auto">
                    There are no sessions scheduled in the system yet.
                </flux:text>
                <flux:button variant="primary" href="{{ route('classes.index') }}" wire:navigate>
                    View All Classes
                </flux:button>
            @endif
        </flux:card>
    @endif
</div>