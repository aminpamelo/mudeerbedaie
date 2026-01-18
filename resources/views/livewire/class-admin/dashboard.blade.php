<?php

use App\Models\ClassSession;
use App\Models\ClassModel;
use App\Models\ClassStudent;
use App\Models\Enrollment;
use App\Models\User;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('components.layouts.app.sidebar')] class extends Component {
    public bool $showModal = false;
    public ?ClassSession $selectedSession = null;
    public string $completionNotes = '';
    public bool $showNotesField = false;
    public bool $showStartConfirmation = false;
    public ?int $sessionToStartId = null;

    public function mount(): void
    {
        if (!auth()->user()->isClassAdmin()) {
            abort(403, 'Unauthorized access');
        }
    }

    /**
     * Get assigned class IDs for the current class admin
     */
    public function getAssignedClassIdsProperty(): array
    {
        return auth()->user()->getAssignedClassIds();
    }

    /**
     * Get statistics for the dashboard
     */
    public function getStatisticsProperty(): array
    {
        $classIds = $this->assignedClassIds;
        $today = now()->startOfDay();
        $weekStart = now()->startOfWeek();

        return [
            'assigned_classes' => count($classIds),
            'today_sessions' => ClassSession::whereIn('class_id', $classIds)
                ->whereDate('session_date', $today)
                ->count(),
            'active_students' => $classIds ? ClassStudent::whereIn('class_id', $classIds)
                ->where('status', 'active')
                ->distinct('student_id')
                ->count('student_id') : 0,
            'ongoing_sessions' => ClassSession::whereIn('class_id', $classIds)
                ->where('status', 'ongoing')
                ->count(),
            'pending_enrollments' => $this->getPendingEnrollmentsCount(),
            'week_completed' => ClassSession::whereIn('class_id', $classIds)
                ->whereBetween('session_date', [$weekStart, now()])
                ->where('status', 'completed')
                ->count(),
            'pending_verification' => ClassSession::whereIn('class_id', $classIds)
                ->where('status', 'completed')
                ->whereNotNull('allowance_amount')
                ->whereNull('verified_at')
                ->count(),
            'kpi_met_this_week' => ClassSession::whereIn('class_id', $classIds)
                ->whereBetween('session_date', [$weekStart, now()])
                ->where('status', 'completed')
                ->whereNotNull('actual_duration_minutes')
                ->whereRaw('actual_duration_minutes >= duration_minutes - 10')
                ->count(),
            'kpi_missed_this_week' => ClassSession::whereIn('class_id', $classIds)
                ->whereBetween('session_date', [$weekStart, now()])
                ->where('status', 'completed')
                ->whereNotNull('actual_duration_minutes')
                ->whereRaw('actual_duration_minutes < duration_minutes - 10')
                ->count(),
        ];
    }

    /**
     * Get pending enrollments count for assigned classes
     */
    private function getPendingEnrollmentsCount(): int
    {
        $classIds = $this->assignedClassIds;
        if (empty($classIds)) {
            return 0;
        }

        return Enrollment::where('status', 'pending')
            ->whereHas('course.classes', function ($query) use ($classIds) {
                $query->whereIn('classes.id', $classIds);
            })
            ->count();
    }

    /**
     * Get today's sessions for assigned classes
     */
    public function getTodaySessionsProperty()
    {
        $classIds = $this->assignedClassIds;

        if (empty($classIds)) {
            return collect();
        }

        return ClassSession::whereIn('class_id', $classIds)
            ->whereDate('session_date', now())
            ->with(['class.course', 'class.teacher.user', 'attendances.student.user'])
            ->orderBy('session_time')
            ->get();
    }

    /**
     * Get ongoing sessions (real-time monitoring)
     */
    public function getOngoingSessionsProperty()
    {
        $classIds = $this->assignedClassIds;

        if (empty($classIds)) {
            return collect();
        }

        return ClassSession::whereIn('class_id', $classIds)
            ->where('status', 'ongoing')
            ->with(['class.course', 'class.teacher.user', 'attendances'])
            ->get();
    }

    /**
     * Get upcoming sessions (next 7 days)
     */
    public function getUpcomingSessionsProperty()
    {
        $classIds = $this->assignedClassIds;

        if (empty($classIds)) {
            return collect();
        }

        return ClassSession::whereIn('class_id', $classIds)
            ->where('session_date', '>', now()->toDateString())
            ->where('session_date', '<=', now()->addDays(7)->toDateString())
            ->whereIn('status', ['scheduled', 'ongoing'])
            ->with(['class.course', 'class.teacher.user'])
            ->orderBy('session_date')
            ->orderBy('session_time')
            ->limit(5)
            ->get();
    }

    /**
     * Get sessions pending verification
     */
    public function getPendingVerificationSessionsProperty()
    {
        $classIds = $this->assignedClassIds;

        if (empty($classIds)) {
            return collect();
        }

        return ClassSession::whereIn('class_id', $classIds)
            ->where('status', 'completed')
            ->whereNotNull('allowance_amount')
            ->whereNull('verified_at')
            ->with(['class.course', 'class.teacher.user'])
            ->orderBy('session_date', 'desc')
            ->limit(5)
            ->get();
    }

    /**
     * Verify a session from the dashboard
     */
    public function verifySession($sessionId): void
    {
        try {
            $session = ClassSession::findOrFail($sessionId);

            if (! in_array($session->class_id, $this->assignedClassIds)) {
                session()->flash('error', 'You can only verify sessions for your assigned classes.');

                return;
            }

            $session->verify(auth()->user());
            session()->flash('message', 'Session verified successfully!');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to verify session: ' . $e->getMessage());
        }
    }

    /**
     * Get recent activity (completed sessions)
     */
    public function getRecentActivityProperty()
    {
        $classIds = $this->assignedClassIds;

        if (empty($classIds)) {
            return collect();
        }

        return ClassSession::whereIn('class_id', $classIds)
            ->whereIn('status', ['completed', 'cancelled', 'no_show'])
            ->with(['class.course'])
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($session) {
                return [
                    'type' => 'session_' . $session->status,
                    'message' => $this->getSessionActivityMessage($session),
                    'time' => $session->updated_at->diffForHumans(),
                    'icon' => $this->getSessionActivityIcon($session->status),
                    'session' => $session,
                ];
            });
    }

    /**
     * Get assigned classes with details
     */
    public function getAssignedClassesProperty()
    {
        return auth()->user()->picClasses()
            ->with(['course', 'teacher.user', 'activeStudents'])
            ->get();
    }

    public function selectSession(ClassSession $session)
    {
        $this->selectedSession = $session;
        $this->completionNotes = $session->teacher_notes ?? '';
        $this->showNotesField = false;
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->selectedSession = null;
        $this->completionNotes = '';
        $this->showNotesField = false;
    }

    public function showCompleteSessionForm()
    {
        $this->showNotesField = true;
    }

    public function closeStartConfirmation()
    {
        $this->showStartConfirmation = false;
        $this->sessionToStartId = null;
    }

    public function requestStartSession($sessionId)
    {
        // Verify the session belongs to an assigned class
        $session = ClassSession::findOrFail($sessionId);
        if (!in_array($session->class_id, $this->assignedClassIds)) {
            session()->flash('error', 'You can only manage sessions for your assigned classes.');
            return;
        }

        $this->sessionToStartId = $sessionId;
        $this->showStartConfirmation = true;
    }

    public function confirmStartSession()
    {
        if ($this->sessionToStartId) {
            $session = ClassSession::findOrFail($this->sessionToStartId);

            // Verify authorization
            if (!in_array($session->class_id, $this->assignedClassIds)) {
                session()->flash('error', 'You can only manage sessions for your assigned classes.');
                $this->closeStartConfirmation();
                return;
            }

            $session->markAsOngoing(auth()->user());
            $this->dispatch('session-started', sessionId: $session->id);
            session()->flash('message', 'Session started successfully!');
        }

        $this->closeStartConfirmation();
    }

    public function completeSession()
    {
        if (empty(trim($this->completionNotes))) {
            session()->flash('error', 'Please add notes before completing the session.');
            return;
        }

        if ($this->selectedSession && $this->selectedSession->isOngoing()) {
            // Verify authorization
            if (!in_array($this->selectedSession->class_id, $this->assignedClassIds)) {
                session()->flash('error', 'You can only manage sessions for your assigned classes.');
                return;
            }

            $this->selectedSession->markCompleted($this->completionNotes);
            $this->selectedSession = $this->selectedSession->fresh();
            $this->showNotesField = false;
            $this->dispatch('session-completed', sessionId: $this->selectedSession->id);
            session()->flash('message', 'Session completed successfully!');
            $this->closeModal();
        }
    }

    private function getSessionActivityMessage($session): string
    {
        return match ($session->status) {
            'completed' => "Completed session for {$session->class->course->name}",
            'cancelled' => "Cancelled session for {$session->class->course->name}",
            'no_show' => "No-show session for {$session->class->course->name}",
            default => "Session updated for {$session->class->course->name}"
        };
    }

    private function getSessionActivityIcon($status): string
    {
        return match ($status) {
            'completed' => 'check-circle',
            'cancelled' => 'x-circle',
            'no_show' => 'exclamation-triangle',
            default => 'information-circle'
        };
    }
}; ?>

<div class="w-full space-y-6" wire:poll.30s="$refresh">
    <!-- Header -->
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <flux:heading size="xl" class="text-gray-900 dark:text-white">Class Admin Dashboard</flux:heading>
            <flux:text class="mt-2 text-gray-600 dark:text-gray-400">Manage your assigned classes and monitor sessions</flux:text>
        </div>
        <div class="flex gap-2 self-start sm:self-auto">
            <flux:button variant="outline" :href="route('classes.index')" wire:navigate icon="calendar-days">
                View Classes
            </flux:button>
            <flux:button variant="primary" :href="route('admin.sessions.index')" wire:navigate icon="presentation-chart-bar">
                All Sessions
            </flux:button>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid gap-4 grid-cols-2 lg:grid-cols-4">
        <!-- Assigned Classes -->
        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="sm" class="text-gray-600 dark:text-gray-400">My Classes</flux:heading>
                    <flux:heading size="xl" class="text-gray-900 dark:text-white">{{ $this->statistics['assigned_classes'] }}</flux:heading>
                    <flux:text size="sm" class="text-blue-600 dark:text-blue-400">Assigned</flux:text>
                </div>
                <flux:icon icon="academic-cap" class="w-8 h-8 text-blue-500 dark:text-blue-400 hidden sm:block" />
            </div>
        </flux:card>

        <!-- Today's Sessions -->
        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="sm" class="text-gray-600 dark:text-gray-400">Today's Sessions</flux:heading>
                    <flux:heading size="xl" class="text-gray-900 dark:text-white">{{ $this->statistics['today_sessions'] }}</flux:heading>
                    <flux:text size="sm" class="text-purple-600 dark:text-purple-400">Scheduled</flux:text>
                </div>
                <flux:icon icon="calendar-days" class="w-8 h-8 text-purple-500 dark:text-purple-400 hidden sm:block" />
            </div>
        </flux:card>

        <!-- Active Students -->
        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="sm" class="text-gray-600 dark:text-gray-400">Active Students</flux:heading>
                    <flux:heading size="xl" class="text-gray-900 dark:text-white">{{ $this->statistics['active_students'] }}</flux:heading>
                    <flux:text size="sm" class="text-green-600 dark:text-green-400">In my classes</flux:text>
                </div>
                <flux:icon icon="users" class="w-8 h-8 text-green-500 dark:text-green-400 hidden sm:block" />
            </div>
        </flux:card>

        <!-- Ongoing Sessions -->
        <flux:card class="{{ $this->statistics['ongoing_sessions'] > 0 ? 'ring-2 ring-amber-400' : '' }}">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="sm" class="text-gray-600 dark:text-gray-400">Ongoing Now</flux:heading>
                    <flux:heading size="xl" class="text-gray-900 dark:text-white">{{ $this->statistics['ongoing_sessions'] }}</flux:heading>
                    <flux:text size="sm" class="text-amber-600 dark:text-amber-400">
                        @if($this->statistics['ongoing_sessions'] > 0)
                            Live
                        @else
                            None active
                        @endif
                    </flux:text>
                </div>
                <flux:icon icon="play-circle" class="w-8 h-8 text-amber-500 dark:text-amber-400 hidden sm:block {{ $this->statistics['ongoing_sessions'] > 0 ? 'animate-pulse' : '' }}" />
            </div>
        </flux:card>
    </div>

    <!-- KPI & Verification Stats -->
    <div class="grid gap-4 grid-cols-2 lg:grid-cols-4">
        <!-- Pending Verification -->
        <flux:card class="{{ $this->statistics['pending_verification'] > 0 ? 'ring-2 ring-orange-400' : '' }}">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="sm" class="text-gray-600 dark:text-gray-400">Pending Verify</flux:heading>
                    <flux:heading size="xl" class="text-gray-900 dark:text-white">{{ $this->statistics['pending_verification'] }}</flux:heading>
                    <flux:text size="sm" class="text-orange-600 dark:text-orange-400">
                        @if($this->statistics['pending_verification'] > 0)
                            Needs action
                        @else
                            All verified
                        @endif
                    </flux:text>
                </div>
                <flux:icon icon="check-badge" class="w-8 h-8 text-orange-500 dark:text-orange-400 hidden sm:block {{ $this->statistics['pending_verification'] > 0 ? 'animate-pulse' : '' }}" />
            </div>
        </flux:card>

        <!-- KPI Met -->
        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="sm" class="text-gray-600 dark:text-gray-400">KPI Met</flux:heading>
                    <flux:heading size="xl" class="text-gray-900 dark:text-white">{{ $this->statistics['kpi_met_this_week'] }}</flux:heading>
                    <flux:text size="sm" class="text-green-600 dark:text-green-400">This week</flux:text>
                </div>
                <flux:icon icon="arrow-trending-up" class="w-8 h-8 text-green-500 dark:text-green-400 hidden sm:block" />
            </div>
        </flux:card>

        <!-- KPI Missed -->
        <flux:card class="{{ $this->statistics['kpi_missed_this_week'] > 0 ? 'ring-2 ring-red-300 dark:ring-red-600' : '' }}">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="sm" class="text-gray-600 dark:text-gray-400">KPI Missed</flux:heading>
                    <flux:heading size="xl" class="text-gray-900 dark:text-white">{{ $this->statistics['kpi_missed_this_week'] }}</flux:heading>
                    <flux:text size="sm" class="text-red-600 dark:text-red-400">This week</flux:text>
                </div>
                <flux:icon icon="arrow-trending-down" class="w-8 h-8 text-red-500 dark:text-red-400 hidden sm:block" />
            </div>
        </flux:card>

        <!-- Week Completed -->
        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="sm" class="text-gray-600 dark:text-gray-400">Completed</flux:heading>
                    <flux:heading size="xl" class="text-gray-900 dark:text-white">{{ $this->statistics['week_completed'] }}</flux:heading>
                    <flux:text size="sm" class="text-emerald-600 dark:text-emerald-400">This week</flux:text>
                </div>
                <flux:icon icon="check-circle" class="w-8 h-8 text-emerald-500 dark:text-emerald-400 hidden sm:block" />
            </div>
        </flux:card>
    </div>

    <!-- Real-Time Session Monitor (if any ongoing sessions) -->
    @if($this->ongoingSessions->isNotEmpty())
    <div class="bg-gradient-to-r from-amber-50 to-orange-50 dark:from-amber-900/20 dark:to-orange-900/20 border-2 border-amber-400 dark:border-amber-600 rounded-lg p-6">
        <div class="flex items-center gap-2 mb-4">
            <flux:icon name="play-circle" class="w-5 h-5 text-amber-600 dark:text-amber-400 animate-pulse" />
            <flux:heading size="lg" class="text-amber-900 dark:text-amber-200">Live Sessions</flux:heading>
            <flux:badge color="amber" size="sm">{{ $this->ongoingSessions->count() }} active</flux:badge>
        </div>

        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            @foreach($this->ongoingSessions as $session)
                <div class="bg-white dark:bg-zinc-800 rounded-lg p-4 shadow-sm">
                    <div class="flex items-start justify-between mb-2">
                        <div>
                            <flux:heading size="sm" class="text-gray-900 dark:text-white">{{ $session->class->title }}</flux:heading>
                            <flux:text size="sm" class="text-gray-600 dark:text-gray-400">{{ $session->class->course->name }}</flux:text>
                        </div>
                        <flux:badge color="green" size="sm">Live</flux:badge>
                    </div>
                    <div class="flex items-center gap-4 text-sm text-gray-600 dark:text-gray-400">
                        <span>{{ $session->class->teacher->user->name ?? 'No teacher' }}</span>
                        <span>{{ $session->attendances->count() }} students</span>
                    </div>
                    <div class="mt-3 flex gap-2">
                        <flux:button size="sm" variant="primary" wire:click="selectSession({{ $session->id }})">
                            View
                        </flux:button>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Main Content Grid -->
    <div class="grid gap-6 lg:grid-cols-3">
        <!-- Today's Schedule -->
        <div class="lg:col-span-2">
            <flux:card>
                <flux:header>
                    <flux:heading size="lg" class="text-gray-900 dark:text-white">Today's Schedule</flux:heading>
                    <flux:text size="sm" class="text-gray-600 dark:text-gray-400">{{ now()->format('l, F j, Y') }}</flux:text>
                </flux:header>

                @if($this->todaySessions->isNotEmpty())
                    <div class="space-y-4">
                        @foreach($this->todaySessions as $session)
                            <div class="flex items-center justify-between p-4 rounded-lg bg-gray-50 dark:bg-zinc-800">
                                <div class="flex items-center space-x-4">
                                    <div class="text-center min-w-[60px]">
                                        <flux:text size="sm" class="font-medium text-gray-900 dark:text-white">{{ $session->session_time->format('g:i A') }}</flux:text>
                                        <flux:text size="xs" class="text-gray-500 dark:text-gray-400">{{ $session->duration_minutes }} min</flux:text>
                                    </div>
                                    <div class="flex-1">
                                        <flux:heading size="sm" class="text-gray-900 dark:text-white">{{ $session->class->title }}</flux:heading>
                                        <flux:text size="sm" class="text-gray-600 dark:text-gray-400">
                                            {{ $session->class->course->name }}
                                            - {{ $session->class->teacher->user->name ?? 'No teacher' }}
                                            - {{ $session->class->activeStudents()->count() }} students
                                        </flux:text>
                                        @if($session->isCompleted() && $session->actual_duration_minutes)
                                            <div class="flex items-center gap-2 mt-1">
                                                <flux:text size="xs" class="{{ $session->meetsKpi() === true ? 'text-green-600 dark:text-green-400' : ($session->meetsKpi() === false ? 'text-red-600 dark:text-red-400' : 'text-gray-500') }}">
                                                    Target: {{ $session->duration_minutes }}min → Actual: {{ $session->actual_duration_minutes }}min
                                                </flux:text>
                                                @if($session->meetsKpi() !== null)
                                                    <flux:badge size="xs" :color="$session->meetsKpi() ? 'green' : 'red'">
                                                        {{ $session->meetsKpi() ? 'Met' : 'Missed' }}
                                                    </flux:badge>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                    <div class="flex flex-col items-end gap-1">
                                        <flux:badge
                                            :color="$session->status === 'completed' ? 'emerald' : ($session->status === 'ongoing' ? 'blue' : ($session->status === 'scheduled' ? 'gray' : 'red'))">
                                            {{ ucfirst($session->status) }}
                                        </flux:badge>
                                        @if($session->isCompleted() && $session->allowance_amount)
                                            @if($session->verified_at)
                                                <flux:badge size="xs" color="green">Verified</flux:badge>
                                            @else
                                                <flux:badge size="xs" color="orange">Pending Verify</flux:badge>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    @if($session->isScheduled())
                                        <flux:button
                                            variant="primary"
                                            size="sm"
                                            icon="play"
                                            wire:click="requestStartSession({{ $session->id }})"
                                        >
                                            Start
                                        </flux:button>
                                    @elseif($session->isOngoing())
                                        <div class="flex items-center gap-2">
                                            <!-- Elapsed Time Display -->
                                            <div class="bg-green-100 dark:bg-green-900/50 border border-green-200 dark:border-green-700 rounded px-2 py-1"
                                                 x-data="{
                                                    elapsedTime: 0,
                                                    timer: null,
                                                    formatTime(seconds) {
                                                        const hours = Math.floor(seconds / 3600);
                                                        const minutes = Math.floor((seconds % 3600) / 60);
                                                        const secs = seconds % 60;
                                                        if (hours > 0) {
                                                            return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
                                                        } else {
                                                            return `${minutes}:${secs.toString().padStart(2, '0')}`;
                                                        }
                                                    }
                                                }"
                                                x-init="
                                                    const startTime = new Date('{{ $session->started_at ? $session->started_at->toISOString() : now()->toISOString() }}').getTime();
                                                    elapsedTime = Math.floor((Date.now() - startTime) / 1000);
                                                    timer = setInterval(() => {
                                                        elapsedTime = Math.floor((Date.now() - startTime) / 1000);
                                                    }, 1000);
                                                "
                                                x-destroy="timer && clearInterval(timer)">
                                                <div class="text-xs font-mono font-semibold text-green-700 dark:text-green-300" x-text="formatTime(elapsedTime)"></div>
                                                <div class="text-xs text-green-600 dark:text-green-400">Elapsed</div>
                                            </div>
                                            <flux:button
                                                variant="primary"
                                                size="sm"
                                                icon="check"
                                                wire:click="selectSession({{ $session->id }})"
                                            >
                                                Complete
                                            </flux:button>
                                        </div>
                                    @elseif($session->isCompleted() && $session->allowance_amount && !$session->verified_at)
                                        <flux:button
                                            variant="primary"
                                            size="sm"
                                            wire:click="verifySession({{ $session->id }})"
                                            class="bg-green-600 hover:bg-green-700"
                                        >
                                            <div class="flex items-center justify-center">
                                                <flux:icon name="check-badge" class="w-4 h-4 mr-1" />
                                                Verify
                                            </div>
                                        </flux:button>
                                    @endif
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        icon="eye"
                                        wire:click="selectSession({{ $session->id }})"
                                    >
                                        View
                                    </flux:button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-12">
                        <flux:icon icon="calendar-days" class="w-12 h-12 text-gray-400 dark:text-gray-500 mx-auto mb-4" />
                        <flux:heading size="lg" class="text-gray-600 dark:text-gray-300 mb-2">No sessions today</flux:heading>
                        <flux:text class="text-gray-500 dark:text-gray-400">
                            @if($this->statistics['assigned_classes'] === 0)
                                You haven't been assigned to any classes yet.
                            @else
                                No sessions scheduled for your assigned classes today.
                            @endif
                        </flux:text>
                    </div>
                @endif

                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-zinc-700">
                    <flux:link :href="route('admin.sessions.index')" wire:navigate variant="subtle" icon="presentation-chart-bar">
                        View all sessions
                    </flux:link>
                </div>
            </flux:card>
        </div>

        <!-- Sidebar Content -->
        <div class="space-y-6">
            <!-- Sessions Pending Verification -->
            @if($this->pendingVerificationSessions->isNotEmpty())
            <flux:card class="border-2 border-orange-300 dark:border-orange-600 bg-orange-50 dark:bg-orange-900/20">
                <flux:header>
                    <div class="flex items-center gap-2">
                        <flux:icon name="check-badge" class="w-5 h-5 text-orange-600 dark:text-orange-400" />
                        <flux:heading size="lg" class="text-gray-900 dark:text-white">Pending Verification</flux:heading>
                        <flux:badge color="orange" size="sm">{{ $this->pendingVerificationSessions->count() }}</flux:badge>
                    </div>
                </flux:header>

                <div class="space-y-3">
                    @foreach($this->pendingVerificationSessions as $session)
                        <div class="p-3 rounded-lg bg-white dark:bg-zinc-800 border border-orange-200 dark:border-orange-700">
                            <div class="flex items-start justify-between mb-2">
                                <div>
                                    <flux:text size="sm" class="font-medium text-gray-900 dark:text-white">
                                        {{ $session->class->title }}
                                    </flux:text>
                                    <flux:text size="xs" class="text-gray-600 dark:text-gray-400">
                                        {{ $session->class->course->name }}
                                    </flux:text>
                                    <flux:text size="xs" class="text-gray-500 dark:text-gray-400">
                                        {{ $session->session_date->format('M j, Y') }} at {{ $session->session_time->format('g:i A') }}
                                    </flux:text>
                                </div>
                                <div class="text-right">
                                    <flux:text size="sm" class="font-bold text-green-600 dark:text-green-400">
                                        RM{{ number_format($session->allowance_amount, 2) }}
                                    </flux:text>
                                    @if($session->meetsKpi() !== null)
                                        <flux:badge size="xs" :color="$session->meetsKpi() ? 'green' : 'red'">
                                            {{ $session->meetsKpi() ? 'Met KPI' : 'Missed KPI' }}
                                        </flux:badge>
                                    @endif
                                </div>
                            </div>
                            <div class="flex justify-end gap-2 mt-2">
                                <flux:button
                                    size="xs"
                                    variant="primary"
                                    wire:click="verifySession({{ $session->id }})"
                                    class="bg-green-600 hover:bg-green-700"
                                >
                                    <div class="flex items-center justify-center">
                                        <flux:icon name="check-badge" class="w-3 h-3 mr-1" />
                                        Verify
                                    </div>
                                </flux:button>
                                <flux:button
                                    size="xs"
                                    variant="ghost"
                                    :href="route('admin.sessions.show', $session)"
                                    wire:navigate
                                >
                                    View
                                </flux:button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4 pt-4 border-t border-orange-200 dark:border-orange-700">
                    <flux:link :href="route('admin.sessions.index', ['verificationFilter' => 'verifiable'])" wire:navigate variant="subtle" icon="arrow-right">
                        View all pending verification
                    </flux:link>
                </div>
            </flux:card>
            @endif

            <!-- Quick Actions -->
            <flux:card>
                <flux:header>
                    <flux:heading size="lg" class="text-gray-900 dark:text-white">Quick Actions</flux:heading>
                </flux:header>

                <div class="space-y-2">
                    <flux:button variant="outline" class="w-full justify-start" :href="route('enrollments.create')" wire:navigate icon="plus">
                        Add Enrollment
                    </flux:button>
                    <flux:button variant="outline" class="w-full justify-start" :href="route('admin.sessions.index', ['verificationFilter' => 'verifiable'])" wire:navigate icon="check-badge">
                        Verify Sessions
                    </flux:button>
                    <flux:button variant="outline" class="w-full justify-start" :href="route('classes.index')" wire:navigate icon="calendar-days">
                        View All Classes
                    </flux:button>
                    <flux:button variant="outline" class="w-full justify-start" :href="route('students.index')" wire:navigate icon="users">
                        View Students
                    </flux:button>
                </div>
            </flux:card>

            <!-- Upcoming Sessions -->
            <flux:card>
                <flux:header>
                    <flux:heading size="lg" class="text-gray-900 dark:text-white">Coming Up</flux:heading>
                </flux:header>

                @if($this->upcomingSessions->isNotEmpty())
                    <div class="space-y-3">
                        @foreach($this->upcomingSessions as $session)
                            <div class="flex items-center justify-between p-3 rounded-lg bg-gray-50 dark:bg-zinc-800">
                                <div>
                                    <flux:text size="sm" class="font-medium text-gray-900 dark:text-white">
                                        {{ $session->class->course->name }}
                                    </flux:text>
                                    <flux:text size="xs" class="text-gray-600 dark:text-gray-400">
                                        {{ $session->session_date->format('M j') }} at {{ $session->session_time->format('g:i A') }}
                                    </flux:text>
                                    <flux:text size="xs" class="text-gray-500 dark:text-gray-400">
                                        {{ $session->class->title }}
                                    </flux:text>
                                </div>
                                <div class="text-right">
                                    <flux:badge
                                        size="sm"
                                        :color="$session->status === 'completed' ? 'emerald' : ($session->status === 'ongoing' ? 'blue' : 'gray')"
                                    >
                                        {{ ucfirst($session->status) }}
                                    </flux:badge>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <flux:text class="text-gray-500 dark:text-gray-400">No upcoming sessions</flux:text>
                @endif
            </flux:card>

            <!-- Recent Activity -->
            <flux:card>
                <flux:header>
                    <flux:heading size="lg" class="text-gray-900 dark:text-white">Recent Activity</flux:heading>
                </flux:header>

                @if($this->recentActivity->isNotEmpty())
                    <div class="space-y-3">
                        @foreach($this->recentActivity as $activity)
                            <div class="flex items-start space-x-3">
                                <div class="w-8 h-8 bg-gray-100 dark:bg-zinc-700 rounded-full flex items-center justify-center">
                                    <flux:icon icon="{{ $activity['icon'] }}" class="w-4 h-4 text-gray-600 dark:text-gray-300" />
                                </div>
                                <div class="flex-1">
                                    <flux:text size="sm" class="text-gray-900 dark:text-white">{{ $activity['message'] }}</flux:text>
                                    <flux:text size="xs" class="text-gray-500 dark:text-gray-400">{{ $activity['time'] }}</flux:text>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <flux:text class="text-gray-500 dark:text-gray-400">No recent activity</flux:text>
                @endif
            </flux:card>

            <!-- My Assigned Classes -->
            <flux:card>
                <flux:header>
                    <flux:heading size="lg" class="text-gray-900 dark:text-white">My Assigned Classes</flux:heading>
                </flux:header>

                @if($this->assignedClasses->isNotEmpty())
                    <div class="space-y-3">
                        @foreach($this->assignedClasses->take(5) as $class)
                            <a href="{{ route('classes.show', $class) }}" wire:navigate
                               class="flex items-center justify-between p-3 rounded-lg bg-gray-50 dark:bg-zinc-800 hover:bg-gray-100 dark:hover:bg-zinc-700 transition-colors">
                                <div>
                                    <flux:text size="sm" class="font-medium text-gray-900 dark:text-white">
                                        {{ $class->title }}
                                    </flux:text>
                                    <flux:text size="xs" class="text-gray-600 dark:text-gray-400">
                                        {{ $class->course->name }}
                                    </flux:text>
                                </div>
                                <div class="text-right">
                                    <flux:text size="xs" class="text-gray-500 dark:text-gray-400">
                                        {{ $class->activeStudents->count() }} students
                                    </flux:text>
                                </div>
                            </a>
                        @endforeach
                    </div>

                    @if($this->assignedClasses->count() > 5)
                        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-zinc-700">
                            <flux:link :href="route('classes.index')" wire:navigate variant="subtle">
                                View all {{ $this->assignedClasses->count() }} classes
                            </flux:link>
                        </div>
                    @endif
                @else
                    <div class="text-center py-6">
                        <flux:icon icon="academic-cap" class="w-8 h-8 text-gray-400 dark:text-gray-500 mx-auto mb-2" />
                        <flux:text class="text-gray-500 dark:text-gray-400">No classes assigned yet</flux:text>
                    </div>
                @endif
            </flux:card>
        </div>
    </div>

    <!-- Session Details Modal -->
    <flux:modal wire:model="showModal" class="max-w-2xl">
        @if($selectedSession)
            <div class="p-6 border-b border-gray-200 dark:border-zinc-700">
                <flux:heading size="lg" class="text-gray-900 dark:text-white">{{ $selectedSession->class->title }}</flux:heading>
            </div>

            <div class="p-6">
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <flux:text class="font-medium text-gray-900 dark:text-white">Date & Time</flux:text>
                            <flux:text class="text-gray-600 dark:text-gray-400">
                                {{ $selectedSession->session_date->format('M j, Y') }} at {{ $selectedSession->session_time->format('g:i A') }}
                            </flux:text>
                        </div>
                        <div>
                            <flux:text class="font-medium text-gray-900 dark:text-white">Duration</flux:text>
                            <flux:text class="text-gray-600 dark:text-gray-400">
                                {{ $selectedSession->duration_minutes }} minutes
                            </flux:text>
                        </div>
                        <div>
                            <flux:text class="font-medium text-gray-900 dark:text-white">Status</flux:text>
                            <flux:badge :color="$selectedSession->status === 'completed' ? 'emerald' : ($selectedSession->status === 'ongoing' ? 'blue' : ($selectedSession->status === 'scheduled' ? 'gray' : 'red'))">
                                {{ ucfirst($selectedSession->status) }}
                            </flux:badge>
                        </div>
                        <div>
                            <flux:text class="font-medium text-gray-900 dark:text-white">Teacher</flux:text>
                            <flux:text class="text-gray-600 dark:text-gray-400">
                                {{ $selectedSession->class->teacher->user->name ?? 'No teacher assigned' }}
                            </flux:text>
                        </div>
                    </div>

                    @if($selectedSession->isOngoing())
                        <div class="bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-700 rounded-lg p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <flux:text class="font-medium text-green-800 dark:text-green-300">Session Timer</flux:text>
                                    <flux:text class="text-green-600 dark:text-green-400 text-sm">Session in progress</flux:text>
                                </div>
                                <div class="text-right" x-data="{
                                    modalTimer: 0,
                                    modalInterval: null,
                                    formatTime(seconds) {
                                        const hours = Math.floor(seconds / 3600);
                                        const minutes = Math.floor((seconds % 3600) / 60);
                                        const secs = seconds % 60;
                                        if (hours > 0) {
                                            return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
                                        } else {
                                            return `${minutes}:${secs.toString().padStart(2, '0')}`;
                                        }
                                    }
                                }" x-init="
                                    const startedAt = '{{ $selectedSession->started_at ? $selectedSession->started_at->toISOString() : now()->toISOString() }}';
                                    if (startedAt) {
                                        const startTime = new Date(startedAt).getTime();
                                        modalTimer = Math.floor((Date.now() - startTime) / 1000);
                                        modalInterval = setInterval(() => {
                                            modalTimer = Math.floor((Date.now() - startTime) / 1000);
                                        }, 1000);
                                    }
                                " x-destroy="modalInterval && clearInterval(modalInterval)">
                                    <div class="text-2xl font-mono font-bold text-green-700 dark:text-green-300" x-text="formatTime(modalTimer)">
                                    </div>
                                    <flux:text class="text-green-600 dark:text-green-400 text-xs">Elapsed time</flux:text>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if($selectedSession->attendances->count() > 0)
                        <div>
                            <flux:text class="font-medium text-gray-900 dark:text-white mb-2">Students ({{ $selectedSession->attendances->count() }})</flux:text>
                            <div class="space-y-1 max-h-40 overflow-y-auto">
                                @foreach($selectedSession->attendances as $attendance)
                                    <div class="flex items-center justify-between py-1">
                                        <flux:text class="text-sm text-gray-900 dark:text-white">{{ $attendance->student->user->name }}</flux:text>
                                        <flux:badge size="sm" :color="$attendance->status === 'present' ? 'emerald' : ($attendance->status === 'late' ? 'amber' : 'red')">
                                            {{ ucfirst($attendance->status) }}
                                        </flux:badge>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($selectedSession->teacher_notes && !$showNotesField)
                        <div>
                            <flux:text class="font-medium text-gray-900 dark:text-white">Session Notes</flux:text>
                            <flux:text class="text-gray-600 dark:text-gray-400 text-sm">
                                {{ $selectedSession->teacher_notes }}
                            </flux:text>
                        </div>
                    @endif

                    @if($showNotesField)
                        <div>
                            <flux:text class="font-medium text-gray-900 dark:text-white">Session Notes</flux:text>
                            <flux:text class="text-gray-500 dark:text-gray-400 text-xs mb-2">
                                Please add notes before completing the session
                            </flux:text>
                            <flux:textarea
                                wire:model="completionNotes"
                                placeholder="Add session notes, summary, or any important details..."
                                rows="4"
                                class="w-full"
                            />
                        </div>
                    @endif
                </div>
            </div>

            <div class="p-6 border-t border-gray-200 dark:border-zinc-700">
                <div class="flex items-center justify-between w-full">
                    <div class="flex gap-2">
                        @if($selectedSession->isScheduled() && in_array($selectedSession->class_id, $this->assignedClassIds))
                            <flux:button wire:click="requestStartSession({{ $selectedSession->id }})" variant="primary" size="sm">
                                Start Session
                            </flux:button>
                        @elseif($selectedSession->isOngoing() && in_array($selectedSession->class_id, $this->assignedClassIds))
                            @if(!$showNotesField)
                                <flux:button wire:click="showCompleteSessionForm" variant="primary" size="sm">
                                    Complete Session
                                </flux:button>
                            @else
                                <div class="flex gap-2">
                                    <flux:button wire:click="completeSession" variant="primary" size="sm">
                                        Confirm Complete
                                    </flux:button>
                                    <flux:button wire:click="$set('showNotesField', false)" variant="ghost" size="sm">
                                        Cancel
                                    </flux:button>
                                </div>
                            @endif
                        @endif

                        <flux:button variant="outline" size="sm" :href="route('admin.sessions.show', $selectedSession)" wire:navigate>
                            Full Details
                        </flux:button>
                    </div>

                    <flux:button wire:click="closeModal" variant="ghost">
                        Close
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    <!-- Start Session Confirmation Modal -->
    <flux:modal wire:model="showStartConfirmation" class="max-w-md">
        <div class="p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="flex-shrink-0 w-12 h-12 bg-blue-100 dark:bg-blue-900/50 rounded-full flex items-center justify-center">
                    <flux:icon name="play" class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                </div>
                <div>
                    <flux:heading size="lg" class="text-gray-900 dark:text-white">Start Session?</flux:heading>
                    <flux:text size="sm" class="text-gray-600 dark:text-gray-400">Are you ready to begin this session?</flux:text>
                </div>
            </div>

            <div class="bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-700 rounded-lg p-4 mb-6">
                <flux:text size="sm" class="text-blue-800 dark:text-blue-300">
                    Once you start the session, the timer will begin and attendance records will be created for all enrolled students.
                </flux:text>
            </div>

            <div class="flex gap-3 justify-end">
                <flux:button wire:click="closeStartConfirmation" variant="ghost">
                    Cancel
                </flux:button>
                <flux:button wire:click="confirmStartSession" variant="primary">
                    <div class="flex items-center justify-center gap-2">
                        <flux:icon name="play" class="w-4 h-4" />
                        Yes, Start Session
                    </div>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
