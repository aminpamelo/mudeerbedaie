<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\ClassSession;
use App\Models\ClassModel;
use App\Models\Teacher;
use Carbon\Carbon;

new class extends Component {
    use WithPagination;
    
    public string $dateFilter = 'all';
    public string $classFilter = 'all';
    public string $statusFilter = 'all';
    public string $teacherFilter = 'all';
    public string $verificationFilter = 'all';
    public string $search = '';

    // Date range filter properties
    public string $dateFrom = '';
    public string $dateTo = '';

    // Bulk action properties
    public array $selectedSessions = [];
    public bool $selectAll = false;

    // Stop/Resume/Complete session modal properties
    public bool $showStopModal = false;
    public bool $showResumeModal = false;
    public bool $showCompleteModal = false;
    public ?int $sessionToStop = null;
    public ?int $sessionToResume = null;
    public ?int $sessionToComplete = null;
    
    protected $queryString = [
        'search' => ['except' => ''],
        'dateFilter' => ['except' => 'all'],
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
        $query = ClassSession::with(['class.course', 'class.teacher.user', 'class.pics', 'attendances.student.user', 'payslips', 'starter', 'assignedTeacher.user']);
        
        // Apply date filter
        $today = now()->startOfDay();
        if ($this->dateFilter === 'custom' && $this->dateFrom && $this->dateTo) {
            $query->whereBetween('session_date', [
                Carbon::parse($this->dateFrom)->startOfDay(),
                Carbon::parse($this->dateTo)->endOfDay()
            ]);
        } else {
            switch ($this->dateFilter) {
                case 'all':
                    // Show all sessions - no date filter applied
                    break;
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

    public function updatedDateFrom()
    {
        if ($this->dateFrom) {
            $this->dateFilter = 'custom';
        }
        $this->resetPage();
    }

    public function updatedDateTo()
    {
        if ($this->dateTo) {
            $this->dateFilter = 'custom';
        }
        $this->resetPage();
    }

    // Bulk action methods
    public function updatedSelectAll()
    {
        if ($this->selectAll) {
            // Get all session IDs from current page
            $sessions = $this->getCurrentPageSessions();
            $this->selectedSessions = $sessions->pluck('id')->toArray();
        } else {
            $this->selectedSessions = [];
        }
    }

    public function updatedSelectedSessions()
    {
        // Update selectAll based on current selection
        $sessions = $this->getCurrentPageSessions();
        $this->selectAll = count($this->selectedSessions) === $sessions->count() && $sessions->count() > 0;
    }

    private function getCurrentPageSessions()
    {
        // Build same query as in with() method to get current page sessions
        $query = ClassSession::with(['class.course', 'class.teacher.user', 'class.pics', 'attendances.student.user', 'payslips', 'starter', 'assignedTeacher.user']);

        // Apply same filters as in with() method
        $today = now()->startOfDay();
        if ($this->dateFilter === 'custom' && $this->dateFrom && $this->dateTo) {
            $query->whereBetween('session_date', [
                Carbon::parse($this->dateFrom)->startOfDay(),
                Carbon::parse($this->dateTo)->endOfDay()
            ]);
        } else {
            switch ($this->dateFilter) {
                case 'all':
                    // Show all sessions - no date filter applied
                    break;
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

        if ($this->verificationFilter !== 'all') {
            if ($this->verificationFilter === 'verified') {
                $query->whereNotNull('verified_at');
            } elseif ($this->verificationFilter === 'unverified') {
                $query->whereNull('verified_at');
            } elseif ($this->verificationFilter === 'verifiable') {
                $query->verifiableForPayroll();
            }
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

        return $query->orderBy('session_date', 'desc')
                     ->orderBy('session_time', 'desc')
                     ->paginate(10);
    }

    public function bulkVerifySessions()
    {
        if (empty($this->selectedSessions)) {
            session()->flash('error', 'Please select sessions to verify.');
            return;
        }

        try {
            $sessions = ClassSession::whereIn('id', $this->selectedSessions)
                ->where('status', 'completed')
                ->whereNotNull('allowance_amount')
                ->whereNull('verified_at')
                ->get();

            $verifiedCount = 0;
            foreach ($sessions as $session) {
                $session->verify(auth()->user());
                $verifiedCount++;
            }

            $this->selectedSessions = [];
            $this->selectAll = false;

            session()->flash('success', "Successfully verified {$verifiedCount} sessions.");
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to verify sessions: ' . $e->getMessage());
        }
    }

    public function bulkUnverifySessions()
    {
        if (empty($this->selectedSessions)) {
            session()->flash('error', 'Please select sessions to unverify.');
            return;
        }

        try {
            $sessions = ClassSession::whereIn('id', $this->selectedSessions)
                ->whereNotNull('verified_at')
                ->get();

            $unverifiedCount = 0;
            foreach ($sessions as $session) {
                $session->unverify();
                $unverifiedCount++;
            }

            $this->selectedSessions = [];
            $this->selectAll = false;

            session()->flash('success', "Successfully unverified {$unverifiedCount} sessions.");
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to unverify sessions: ' . $e->getMessage());
        }
    }

    public function clearDateRange()
    {
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->dateFilter = 'all';
        $this->resetPage();
    }

    // Stop/Pause session methods
    public function confirmStopSession($sessionId)
    {
        $this->sessionToStop = $sessionId;
        $this->showStopModal = true;
    }

    public function stopSession()
    {
        if (!$this->sessionToStop) {
            return;
        }

        try {
            $session = ClassSession::findOrFail($this->sessionToStop);
            $session->pause();

            session()->flash('success', 'Session has been stopped/paused successfully.');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to stop session: ' . $e->getMessage());
        }

        $this->showStopModal = false;
        $this->sessionToStop = null;
    }

    public function cancelStopSession()
    {
        $this->showStopModal = false;
        $this->sessionToStop = null;
    }

    // Resume session methods
    public function confirmResumeSession($sessionId)
    {
        $this->sessionToResume = $sessionId;
        $this->showResumeModal = true;
    }

    public function resumeSession()
    {
        if (!$this->sessionToResume) {
            return;
        }

        try {
            $session = ClassSession::findOrFail($this->sessionToResume);
            $session->resume();

            session()->flash('success', 'Session has been resumed successfully.');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to resume session: ' . $e->getMessage());
        }

        $this->showResumeModal = false;
        $this->sessionToResume = null;
    }

    public function cancelResumeSession()
    {
        $this->showResumeModal = false;
        $this->sessionToResume = null;
    }

    // Complete session methods
    public function confirmCompleteSession($sessionId)
    {
        $this->sessionToComplete = $sessionId;
        $this->showCompleteModal = true;
    }

    public function completeSession()
    {
        if (!$this->sessionToComplete) {
            return;
        }

        try {
            $session = ClassSession::findOrFail($this->sessionToComplete);
            $session->markCompleted();

            session()->flash('success', 'Session has been marked as completed successfully.');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to complete session: ' . $e->getMessage());
        }

        $this->showCompleteModal = false;
        $this->sessionToComplete = null;
    }

    public function cancelCompleteSession()
    {
        $this->showCompleteModal = false;
        $this->sessionToComplete = null;
    }

    public function exportSessions()
    {
        // Build the same query as the main sessions query
        $query = ClassSession::with(['class.course', 'class.teacher.user', 'class.pics', 'attendances.student.user', 'payslips', 'starter', 'assignedTeacher.user']);
        
        // Apply the same filters
        $today = now()->startOfDay();
        if ($this->dateFilter === 'custom' && $this->dateFrom && $this->dateTo) {
            $query->whereBetween('session_date', [
                Carbon::parse($this->dateFrom)->startOfDay(),
                Carbon::parse($this->dateTo)->endOfDay()
            ]);
        } else {
            switch ($this->dateFilter) {
                case 'all':
                    // Show all sessions - no date filter applied
                    break;
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
        $csvContent = "Date,Time,Class,Course,Teacher,Assigned Teacher,Started By,PIC,Duration,Status,Students,Present,Allowance,Notes\n";

        foreach ($sessions as $session) {
            $attendanceCount = $session->attendances->count();
            $presentCount = $session->attendances->whereIn('status', ['present', 'late'])->count();
            $allowance = $session->allowance_amount ? 'RM' . number_format($session->allowance_amount, 2) : '';
            $teacherName = $session->class->teacher ? $session->class->teacher->user->name : 'N/A';
            $assignedTeacherName = $session->assignedTeacher ? $session->assignedTeacher->user->name : 'Same as class';
            $starterName = $session->starter ? $session->starter->name : 'N/A';
            $picNames = $session->class->pics->pluck('name')->join(', ') ?: 'N/A';

            $csvContent .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%d,%d,%s,%s\n",
                $session->session_date->format('Y-m-d'),
                $session->session_time->format('H:i'),
                '"' . str_replace('"', '""', $session->class->title) . '"',
                '"' . str_replace('"', '""', $session->class->course->name) . '"',
                '"' . str_replace('"', '""', $teacherName) . '"',
                '"' . str_replace('"', '""', $assignedTeacherName) . '"',
                '"' . str_replace('"', '""', $starterName) . '"',
                '"' . str_replace('"', '""', $picNames) . '"',
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
                    <div class="text-2xl font-bold text-green-600">{{ $statistics['verified_sessions'] }}</div>
                    <div class="text-sm text-gray-600">Verified</div>
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
                        <option value="all">All Sessions</option>
                        <option value="upcoming">Upcoming Sessions</option>
                        <option value="today">Today's Sessions</option>
                        <option value="this_week">This Week</option>
                        <option value="past">Past Sessions</option>
                        <option value="custom">Custom Date Range</option>
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
                        <option value="paused">Paused</option>
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

            <!-- Custom Date Range Inputs -->
            @if($dateFilter === 'custom')
                <div class="flex flex-col sm:flex-row gap-3 pt-4 border-t">
                    <div class="flex-1">
                        <flux:input type="date" wire:model.live="dateFrom" placeholder="From Date" class="w-full" />
                    </div>
                    <div class="flex-1">
                        <flux:input type="date" wire:model.live="dateTo" placeholder="To Date" class="w-full" />
                    </div>
                    <flux:button variant="outline" wire:click="clearDateRange" size="sm" class="whitespace-nowrap">
                        <div class="flex items-center justify-center">
                            <flux:icon name="x-mark" class="w-4 h-4 mr-1" />
                            Clear
                        </div>
                    </flux:button>
                </div>
            @endif
        </div>
    </flux:card>

    @if($sessions->count() > 0)
        <!-- Bulk Actions Bar -->
        <flux:card class="p-4 mb-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-center gap-3">
                    <flux:checkbox wire:model.live="selectAll" />
                    <flux:text size="sm" class="font-medium">
                        Select All
                        @if(count($selectedSessions) > 0)
                            ({{ count($selectedSessions) }} selected)
                        @endif
                    </flux:text>
                </div>

                @if(count($selectedSessions) > 0)
                    <div class="flex items-center gap-2">
                        <flux:button
                            variant="primary"
                            size="sm"
                            wire:click="bulkVerifySessions"
                            class="bg-green-600 hover:bg-green-700 text-white border-green-600 hover:border-green-700"
                        >
                            <div class="flex items-center justify-center">
                                <flux:icon name="check-badge" class="w-4 h-4 mr-1" />
                                Verify Selected
                            </div>
                        </flux:button>

                        <flux:button
                            variant="outline"
                            size="sm"
                            wire:click="bulkUnverifySessions"
                            class="text-amber-700 border-amber-300 hover:bg-amber-50 hover:border-amber-400"
                        >
                            <div class="flex items-center justify-center">
                                <flux:icon name="x-mark" class="w-4 h-4 mr-1" />
                                Unverify Selected
                            </div>
                        </flux:button>
                    </div>
                @endif
            </div>
        </flux:card>

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
                            <div class="flex items-start gap-4">
                                <flux:checkbox
                                    wire:model.live="selectedSessions"
                                    value="{{ $session->id }}"
                                    class="mt-1"
                                />
                                <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex-1">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <flux:heading size="sm" class="mb-2">{{ $session->class->title }}</flux:heading>
                                            <flux:text size="xs" class="text-gray-500  mb-1">
                                                {{ $session->class->course->name }}
                                            </flux:text>
                                            @if($session->class->teacher)
                                                <flux:text size="xs" class="text-gray-500 mb-1">
                                                    Teacher: {{ $session->class->teacher->user->name }}
                                                </flux:text>
                                            @endif
                                            @if($session->assignedTeacher)
                                                <div class="flex items-center gap-1 mb-1">
                                                    <flux:text size="xs" class="text-gray-500">
                                                        Assigned: {{ $session->assignedTeacher->user->name }}
                                                    </flux:text>
                                                    <flux:badge color="amber" size="xs">Substitute</flux:badge>
                                                </div>
                                            @endif
                                            @if($session->starter)
                                                <div class="flex items-center gap-1 mb-1">
                                                    <flux:text size="xs" class="text-gray-500">
                                                        Started by: {{ $session->starter->name }}
                                                    </flux:text>
                                                    @if($session->class->teacher && $session->started_by !== $session->class->teacher->user_id)
                                                        <flux:badge color="amber" size="xs">Not Teacher</flux:badge>
                                                    @endif
                                                </div>
                                            @endif
                                            @if($session->class->pics->count() > 0)
                                                <div class="flex items-center gap-1 mb-2">
                                                    <flux:text size="xs" class="text-gray-500">PIC:</flux:text>
                                                    <div class="flex -space-x-1">
                                                        @foreach($session->class->pics->take(3) as $pic)
                                                            <flux:avatar size="xs" :name="$pic->name" class="ring-1 ring-white" title="{{ $pic->name }}" />
                                                        @endforeach
                                                    </div>
                                                    @if($session->class->pics->count() > 3)
                                                        <span class="text-xs text-gray-500">+{{ $session->class->pics->count() - 3 }}</span>
                                                    @elseif($session->class->pics->count() <= 2)
                                                        <span class="text-xs text-gray-500">{{ $session->class->pics->pluck('name')->join(', ') }}</span>
                                                    @endif
                                                </div>
                                            @endif
                                            @if($session->topic)
                                                <flux:text size="sm" class="text-gray-600">
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
                                            @elseif($session->status === 'paused')
                                                <flux:badge color="purple" size="sm">Paused</flux:badge>
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
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                                <div class="flex items-center text-sm text-gray-600">
                                    <flux:icon name="calendar" class="w-4 h-4 mr-2 text-gray-400" />
                                    <span>{{ $session->session_date->format('M d, Y') }}</span>
                                </div>
                                <div class="flex items-center text-sm text-gray-600">
                                    <flux:icon name="clock" class="w-4 h-4 mr-2 text-gray-400" />
                                    <span>{{ $session->session_time->format('g:i A') }}</span>
                                </div>

                                <!-- Enhanced Duration Tracking -->
                                <div class="flex flex-col text-sm">
                                    <div class="flex items-center text-gray-600 mb-1">
                                        <flux:icon name="clock" class="w-4 h-4 mr-2 text-gray-400" />
                                        <span class="font-medium">Target: {{ $session->formatted_duration }}</span>
                                    </div>
                                    @if($session->formatted_actual_duration)
                                        <div class="flex items-center {{ $session->meetsKpi() === true ? 'text-green-700' : ($session->meetsKpi() === false ? 'text-red-700' : 'text-gray-600') }}">
                                            <span class="ml-6 font-medium">Actual: {{ $session->formatted_actual_duration }}</span>
                                        </div>
                                        <div class="text-xs {{ $session->meetsKpi() === true ? 'text-green-600' : ($session->meetsKpi() === false ? 'text-red-600' : 'text-gray-500') }} ml-6">
                                            {{ $session->duration_comparison }}
                                        </div>
                                    @elseif($session->isOngoing())
                                        <div class="flex items-center text-yellow-700 ml-6">
                                            <span
                                                x-data="sessionTimer('{{ $session->started_at ? $session->started_at->toISOString() : now()->toISOString() }}')"
                                                x-init="startTimer()"
                                                class="font-mono text-sm"
                                                x-text="'Current: ' + formattedTime"
                                            ></span>
                                        </div>
                                    @else
                                        <div class="text-xs text-gray-400 ml-6">Not started</div>
                                    @endif
                                </div>

                                <!-- KPI Status -->
                                <div class="flex items-center text-sm">
                                    @if($session->isCompleted() && $session->meetsKpi() !== null)
                                        <div class="flex items-center gap-2">
                                            <flux:badge size="xs" :class="$session->kpi_badge_class">
                                                {{ $session->meetsKpi() ? 'Met KPI' : 'Missed KPI' }}
                                            </flux:badge>
                                        </div>
                                    @elseif($session->isOngoing())
                                        <flux:badge size="xs" variant="outline" class="animate-pulse">
                                            In Progress
                                        </flux:badge>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </div>

                                <div class="flex items-center text-sm text-gray-600">
                                    <flux:icon name="users" class="w-4 h-4 mr-2 text-gray-400" />
                                    <span>{{ $attendanceCount }} students</span>
                                    @if($session->status === 'completed' && $attendanceCount > 0)
                                        <span class="text-xs text-gray-500 ml-1">
                                            ({{ round(($presentCount / $attendanceCount) * 100) }}% attended)
                                        </span>
                                    @endif
                                </div>
                            </div>
                            
                            {{-- Payslip Information --}}
                            @if($session->status === 'completed' && $session->verified_at)
                                <div class="mt-4 p-3 bg-gray-50 /50 rounded-md">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center text-sm">
                                            <flux:icon name="document-text" class="w-4 h-4 mr-2 text-gray-500" />
                                            <span class="font-medium text-gray-700">Payslip Status:</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            @if($session->isPaid())
                                                @php
                                                    $payslip = $session->payslips->first();
                                                @endphp
                                                <flux:text size="sm" class="text-green-700">Paid</flux:text>
                                                @if($payslip)
                                                    <flux:button size="xs" variant="ghost" href="{{ route('admin.payslips.show', $payslip) }}" wire:navigate class="text-green-600 hover:text-green-700">
                                                        View Payslip
                                                    </flux:button>
                                                @endif
                                            @elseif($session->isIncludedInPayslip())
                                                @php
                                                    $payslip = $session->payslips->first();
                                                @endphp
                                                <flux:text size="sm" class="text-yellow-700">Included in Payslip</flux:text>
                                                @if($payslip)
                                                    <flux:button size="xs" variant="ghost" href="{{ route('admin.payslips.show', $payslip) }}" wire:navigate class="text-yellow-600 hover:text-yellow-700">
                                                        View Payslip
                                                    </flux:button>
                                                @endif
                                            @elseif($session->isUnpaid())
                                                <flux:text size="sm" class="text-orange-700">Ready for Payslip</flux:text>
                                                <flux:button size="xs" variant="ghost" href="{{ route('admin.payslips.index') }}" wire:navigate class="text-orange-600 hover:text-orange-700">
                                                    Generate Payslip
                                                </flux:button>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex flex-col items-end gap-3 lg:min-w-fit">
                            @if($session->status === 'completed' && $session->allowance_amount)
                                <div class="text-lg font-bold text-green-600  bg-green-50 /20 px-3 py-1 rounded-md">
                                    RM{{ number_format($session->allowance_amount, 2) }}
                                </div>
                            @endif
                            
                            <!-- Session Control Actions -->
                            <div class="flex items-center gap-2">
                                @if($session->status === 'ongoing')
                                    <flux:button size="sm" variant="outline" wire:click="confirmStopSession({{ $session->id }})" class="whitespace-nowrap text-red-700 border-red-300 hover:bg-red-50 hover:border-red-400">
                                        <div class="flex items-center justify-center">
                                            <flux:icon name="pause-circle" class="w-4 h-4 mr-1" />
                                            Stop
                                        </div>
                                    </flux:button>
                                    <flux:button size="sm" variant="primary" wire:click="confirmCompleteSession({{ $session->id }})" class="whitespace-nowrap bg-green-600 hover:bg-green-700 text-white border-green-600 hover:border-green-700 shadow-sm">
                                        <div class="flex items-center justify-center">
                                            <flux:icon name="check-circle" class="w-4 h-4 mr-1" />
                                            Complete
                                        </div>
                                    </flux:button>
                                @elseif($session->status === 'paused')
                                    <flux:button size="sm" variant="primary" wire:click="confirmResumeSession({{ $session->id }})" class="whitespace-nowrap bg-blue-600 hover:bg-blue-700 text-white border-blue-600 hover:border-blue-700 shadow-sm">
                                        <div class="flex items-center justify-center">
                                            <flux:icon name="play-circle" class="w-4 h-4 mr-1" />
                                            Resume
                                        </div>
                                    </flux:button>
                                    <flux:button size="sm" variant="primary" wire:click="confirmCompleteSession({{ $session->id }})" class="whitespace-nowrap bg-green-600 hover:bg-green-700 text-white border-green-600 hover:border-green-700 shadow-sm">
                                        <div class="flex items-center justify-center">
                                            <flux:icon name="check-circle" class="w-4 h-4 mr-1" />
                                            Complete
                                        </div>
                                    </flux:button>
                                @endif
                            </div>

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
                                    <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" class="hover:bg-gray-100 :bg-gray-800" />
                                    
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
                <flux:text class="text-gray-600  mb-6 max-w-md mx-auto">
                    No sessions match your current filter criteria. Try adjusting your filters.
                </flux:text>
                <flux:button variant="ghost" wire:click="$set('dateFilter', 'all'); $set('classFilter', 'all'); $set('statusFilter', 'all'); $set('teacherFilter', 'all'); $set('verificationFilter', 'all')">
                    Clear All Filters
                </flux:button>
            @else
                <flux:heading size="lg" class="mb-4">No Sessions Scheduled</flux:heading>
                <flux:text class="text-gray-600  mb-6 max-w-md mx-auto">
                    There are no sessions scheduled in the system yet.
                </flux:text>
                <flux:button variant="primary" href="{{ route('classes.index') }}" wire:navigate>
                    View All Classes
                </flux:button>
            @endif
        </flux:card>
    @endif

    <!-- Stop Session Confirmation Modal -->
    <flux:modal wire:model="showStopModal" class="max-w-md">
        <div class="p-6">
            <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-red-100 rounded-full">
                <flux:icon name="pause-circle" class="w-6 h-6 text-red-600" />
            </div>
            <flux:heading size="lg" class="text-center mb-2">Stop Session?</flux:heading>
            <flux:text class="text-center text-gray-600 mb-6">
                Are you sure you want to stop this session? The session will be paused and can be resumed later.
            </flux:text>
            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="cancelStopSession">
                    Cancel
                </flux:button>
                <flux:button variant="danger" wire:click="stopSession">
                    <div class="flex items-center justify-center">
                        <flux:icon name="pause-circle" class="w-4 h-4 mr-1" />
                        Stop Session
                    </div>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Resume Session Confirmation Modal -->
    <flux:modal wire:model="showResumeModal" class="max-w-md">
        <div class="p-6">
            <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-blue-100 rounded-full">
                <flux:icon name="play-circle" class="w-6 h-6 text-blue-600" />
            </div>
            <flux:heading size="lg" class="text-center mb-2">Resume Session?</flux:heading>
            <flux:text class="text-center text-gray-600 mb-6">
                Are you sure you want to resume this session? The session will continue from where it was paused.
            </flux:text>
            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="cancelResumeSession">
                    Cancel
                </flux:button>
                <flux:button variant="primary" wire:click="resumeSession">
                    <div class="flex items-center justify-center">
                        <flux:icon name="play-circle" class="w-4 h-4 mr-1" />
                        Resume Session
                    </div>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Complete Session Confirmation Modal -->
    <flux:modal wire:model="showCompleteModal" class="max-w-md">
        <div class="p-6">
            <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-green-100 rounded-full">
                <flux:icon name="check-circle" class="w-6 h-6 text-green-600" />
            </div>
            <flux:heading size="lg" class="text-center mb-2">Complete Session?</flux:heading>
            <flux:text class="text-center text-gray-600 mb-6">
                Are you sure you want to mark this session as completed? This will calculate the teacher's allowance based on attendance.
            </flux:text>
            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="cancelCompleteSession">
                    Cancel
                </flux:button>
                <flux:button variant="primary" wire:click="completeSession" class="bg-green-600 hover:bg-green-700 border-green-600 hover:border-green-700">
                    <div class="flex items-center justify-center">
                        <flux:icon name="check-circle" class="w-4 h-4 mr-1" />
                        Complete Session
                    </div>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>

<script>
function sessionTimer(startTime) {
    return {
        startTime: new Date(startTime),
        currentTime: '',
        formattedTime: '',
        interval: null,

        startTimer() {
            this.updateTime();
            this.interval = setInterval(() => {
                this.updateTime();
            }, 1000);
        },

        updateTime() {
            const now = new Date();
            const diffInSeconds = Math.floor((now - this.startTime) / 1000);

            if (diffInSeconds < 0) {
                this.formattedTime = '0:00';
                return;
            }

            const hours = Math.floor(diffInSeconds / 3600);
            const minutes = Math.floor((diffInSeconds % 3600) / 60);
            const seconds = diffInSeconds % 60;

            if (hours > 0) {
                this.formattedTime = `${hours}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            } else {
                this.formattedTime = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            }
        },

        destroy() {
            if (this.interval) {
                clearInterval(this.interval);
            }
        }
    }
}
</script>