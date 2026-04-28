<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Student;
use App\Models\ClassModel;
use App\Models\Course;
use App\Models\ClassAttendance;
use App\Models\ClassSession;
use App\AcademicStatus;
use Illuminate\Database\Eloquent\Collection;

new #[Layout('components.layouts.teacher')] class extends Component {
    public string $search = '';
    public string $classFilter = 'all';
    public string $courseFilter = 'all';
    public string $statusFilter = 'all';
    public string $sortBy = 'name';
    public string $viewMode = 'grid';
    public ?int $selectedStudentId = null;
    public bool $showStudentModal = false;
    
    public function with()
    {
        $teacher = auth()->user()->teacher;
        
        if (!$teacher) {
            return [
                'students' => collect(),
                'classes' => collect(),
                'courses' => collect(),
                'statistics' => $this->getEmptyStatistics()
            ];
        }
        
        // Get teacher's classes and courses
        $classes = $teacher->classes()->with(['course', 'activeStudents.student.user'])->get();
        $courses = $teacher->courses()->get();
        
        // Get all students from teacher's classes
        $studentIds = collect();
        foreach ($classes as $class) {
            $classStudentIds = $class->activeStudents->pluck('student_id');
            $studentIds = $studentIds->merge($classStudentIds);
        }
        $studentIds = $studentIds->unique();
        
        // Build query for students
        $query = Student::with([
            'user',
            'classAttendances.session.class',
            'activeEnrollments.course'
        ])->whereIn('id', $studentIds);
        
        // Apply search filter
        if ($this->search) {
            $query->whereHas('user', function($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%');
            })->orWhere('student_id', 'like', '%' . $this->search . '%');
        }
        
        // Apply class filter
        if ($this->classFilter !== 'all') {
            $query->whereHas('classAttendances.session', function($q) {
                $q->where('class_id', $this->classFilter);
            });
        }
        
        // Apply course filter
        if ($this->courseFilter !== 'all') {
            $query->whereHas('activeEnrollments', function($q) {
                $q->where('course_id', $this->courseFilter);
            });
        }
        
        // Apply status filter
        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }
        
        // Apply sorting
        switch ($this->sortBy) {
            case 'name':
                $query->whereHas('user', function($q) {
                    $q->orderBy('name');
                });
                break;
            case 'attendance':
                // Will be handled after collection
                break;
            case 'recent':
                $query->orderBy('updated_at', 'desc');
                break;
        }
        
        $students = $query->get();
        
        // Calculate statistics
        $statistics = [
            'total_students' => $students->count(),
            'active_students' => $students->where('status', 'active')->count(),
            'average_attendance' => $this->calculateAverageAttendance($students, $classes),
            'this_week_sessions' => $this->getThisWeekSessions($classes)
        ];
        
        return [
            'students' => $students,
            'classes' => $classes,
            'courses' => $courses,
            'statistics' => $statistics
        ];
    }
    
    private function calculateAverageAttendance($students, $classes): float
    {
        if ($students->isEmpty() || $classes->isEmpty()) {
            return 0;
        }
        
        $totalAttendanceRate = 0;
        $studentCount = 0;
        
        foreach ($students as $student) {
            $attendanceRate = $this->getStudentAttendanceRate($student, $classes);
            $totalAttendanceRate += $attendanceRate;
            $studentCount++;
        }
        
        return $studentCount > 0 ? round($totalAttendanceRate / $studentCount, 1) : 0;
    }
    
    private function getStudentAttendanceRate($student, $classes): float
    {
        $totalSessions = 0;
        $attendedSessions = 0;
        
        foreach ($classes as $class) {
            $studentInClass = $class->activeStudents->where('student_id', $student->id)->first();
            if (!$studentInClass) continue;
            
            $classAttendances = ClassAttendance::whereHas('session', function($q) use ($class) {
                $q->where('class_id', $class->id)->where('status', 'completed');
            })->where('student_id', $student->id)->get();
            
            $totalSessions += $classAttendances->count();
            $attendedSessions += $classAttendances->whereIn('status', ['present', 'late'])->count();
        }
        
        return $totalSessions > 0 ? round(($attendedSessions / $totalSessions) * 100, 1) : 0;
    }
    
    private function getThisWeekSessions($classes): int
    {
        $startOfWeek = now()->startOfWeek();
        $endOfWeek = now()->endOfWeek();
        
        return ClassSession::whereIn('class_id', $classes->pluck('id'))
            ->whereBetween('session_date', [$startOfWeek, $endOfWeek])
            ->count();
    }
    
    private function getEmptyStatistics(): array
    {
        return [
            'total_students' => 0,
            'active_students' => 0,
            'average_attendance' => 0,
            'this_week_sessions' => 0
        ];
    }
    
    public function updatedSearch()
    {
        $this->resetPage();
    }
    
    public function updatedClassFilter()
    {
        $this->resetPage();
    }
    
    public function updatedCourseFilter()
    {
        $this->resetPage();
    }
    
    public function updatedStatusFilter()
    {
        $this->resetPage();
    }
    
    public function resetPage()
    {
        // Reset to first page when filters change
    }
    
    public function selectStudent($studentId)
    {
        $this->selectedStudentId = $studentId;
        $this->showStudentModal = true;
    }
    
    public function closeStudentModal()
    {
        $this->showStudentModal = false;
        $this->selectedStudentId = null;
    }
    
    public function getSelectedStudentProperty()
    {
        if (!$this->selectedStudentId) {
            return null;
        }
        
        return Student::with([
            'user',
            'classAttendances.session.class.course',
            'activeEnrollments.course'
        ])->find($this->selectedStudentId);
    }
}; ?>

<div class="teacher-app w-full">
    {{-- Page Header --}}
    <x-teacher.page-header
        title="My Students"
        subtitle="Students across all your classes"
    />

    {{-- Stat Strip --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <x-teacher.stat-card
            eyebrow="Total Students"
            :value="$statistics['total_students']"
            tone="indigo"
            icon="users"
        >
            <span class="text-violet-700/80 dark:text-violet-300/80 font-medium">Across all classes</span>
        </x-teacher.stat-card>

        <x-teacher.stat-card
            eyebrow="Active"
            :value="$statistics['active_students']"
            tone="emerald"
            icon="check-circle"
        >
            <span class="text-emerald-700/80 dark:text-emerald-300/80 font-medium">Currently enrolled</span>
        </x-teacher.stat-card>

        <x-teacher.stat-card
            eyebrow="Avg Attendance"
            :value="$statistics['average_attendance'].'%'"
            tone="amber"
            icon="chart-pie"
        >
            <div class="mt-1 h-1.5 w-full rounded-full bg-amber-200/50 dark:bg-amber-900/40 overflow-hidden">
                <div class="h-full rounded-full bg-gradient-to-r from-amber-400 to-orange-500" style="width: {{ min(100, $statistics['average_attendance']) }}%"></div>
            </div>
        </x-teacher.stat-card>

        <x-teacher.stat-card
            eyebrow="This Week"
            :value="$statistics['this_week_sessions']"
            tone="violet"
            icon="calendar"
        >
            <span class="text-violet-700/80 dark:text-violet-300/80 font-medium">{{ Str::plural('session', $statistics['this_week_sessions']) }} scheduled</span>
        </x-teacher.stat-card>
    </div>

    {{-- Filter Bar --}}
    <x-teacher.filter-bar>
        <div class="flex-1 min-w-[220px] max-w-md">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="Search by name, email, or ID..."
                icon="magnifying-glass"
            />
        </div>

        <flux:select wire:model.live="classFilter" class="min-w-40">
            <option value="all">All Classes</option>
            @foreach($classes as $class)
                <option value="{{ $class->id }}">{{ $class->title }}</option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="courseFilter" class="min-w-40">
            <option value="all">All Courses</option>
            @foreach($courses as $course)
                <option value="{{ $course->id }}">{{ $course->name }}</option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="statusFilter" class="min-w-32">
            <option value="all">All Status</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="completed">Completed</option>
        </flux:select>

        <flux:select wire:model.live="sortBy" class="min-w-32">
            <option value="name">Sort by Name</option>
            <option value="attendance">Sort by Attendance</option>
            <option value="recent">Recently Active</option>
        </flux:select>

        <x-slot name="actions">
            <div class="inline-flex items-center gap-1 rounded-xl bg-slate-100/70 dark:bg-zinc-800/60 p-1 ring-1 ring-slate-200/70 dark:ring-zinc-700/60">
                <button type="button" wire:click="$set('viewMode','grid')" @class([
                    'inline-flex items-center justify-center w-9 h-9 rounded-lg transition',
                    'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300 shadow-sm' => $viewMode === 'grid',
                    'text-slate-500 hover:bg-slate-100 dark:text-zinc-400 dark:hover:bg-zinc-800' => $viewMode !== 'grid',
                ]) aria-label="Grid view">
                    <flux:icon name="squares-2x2" class="w-4 h-4" />
                </button>
                <button type="button" wire:click="$set('viewMode','list')" @class([
                    'inline-flex items-center justify-center w-9 h-9 rounded-lg transition',
                    'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300 shadow-sm' => $viewMode === 'list',
                    'text-slate-500 hover:bg-slate-100 dark:text-zinc-400 dark:hover:bg-zinc-800' => $viewMode !== 'list',
                ]) aria-label="List view">
                    <flux:icon name="bars-3" class="w-4 h-4" />
                </button>
            </div>
        </x-slot>
    </x-teacher.filter-bar>

    @if($students->count() > 0)
        @if($viewMode === 'grid')
            {{-- ─── GRID VIEW ─── --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                @foreach($students as $student)
                    @php
                        $attendanceRate = $this->getStudentAttendanceRate($student, $classes);
                        $activeEnrollments = $student->activeEnrollments;
                        $totalAttendances = $student->classAttendances->count();
                        $presentAttendances = $student->classAttendances->whereIn('status', ['present', 'late'])->count();
                        $initials = collect(explode(' ', trim($student->user->name)))
                            ->take(2)
                            ->map(fn ($p) => strtoupper(substr($p, 0, 1)))
                            ->join('');
                        $avatarVariant = ($loop->index % 6) + 1;
                        $progressGradient = $attendanceRate >= 80
                            ? 'from-emerald-500 to-teal-500'
                            : ($attendanceRate >= 60 ? 'from-amber-400 to-orange-500' : 'from-rose-500 to-red-500');
                    @endphp

                    <div wire:key="student-grid-{{ $student->id }}"
                         wire:click="selectStudent({{ $student->id }})"
                         class="teacher-card teacher-card-hover p-5 cursor-pointer flex flex-col">
                        {{-- Avatar + name --}}
                        <div class="flex flex-col items-center text-center">
                            <div class="teacher-avatar teacher-avatar-{{ $avatarVariant }}" style="width: 3rem; height: 3rem; font-size: 0.95rem;">
                                {{ $initials }}
                            </div>
                            <h3 class="teacher-display font-bold text-base text-slate-900 dark:text-white mt-3 truncate max-w-full">
                                {{ $student->user->name }}
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5 truncate max-w-full">
                                {{ $student->user->email }}
                            </p>
                            <div class="mt-2">
                                <x-teacher.status-pill :status="$student->status" size="sm" />
                            </div>
                        </div>

                        {{-- Course chip --}}
                        @if($activeEnrollments->isNotEmpty())
                            <div class="mt-3 flex flex-wrap justify-center gap-1">
                                @foreach($activeEnrollments->take(2) as $enrollment)
                                    <span class="inline-flex items-center rounded-full bg-violet-50 dark:bg-violet-500/10 text-violet-700 dark:text-violet-300 px-2 py-0.5 text-[10px] font-semibold ring-1 ring-violet-100 dark:ring-violet-900/40 truncate max-w-[140px]">
                                        {{ $enrollment->course->name }}
                                    </span>
                                @endforeach
                                @if($activeEnrollments->count() > 2)
                                    <span class="inline-flex items-center rounded-full bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-300 px-2 py-0.5 text-[10px] font-semibold">
                                        +{{ $activeEnrollments->count() - 2 }}
                                    </span>
                                @endif
                            </div>
                        @endif

                        {{-- Mini stats --}}
                        <div class="mt-4 grid grid-cols-2 gap-2">
                            <div class="rounded-xl bg-slate-50 dark:bg-zinc-800/50 px-3 py-2 text-center">
                                <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400">Attended</div>
                                <div class="teacher-num text-sm font-bold text-slate-900 dark:text-white mt-0.5">{{ $presentAttendances }}/{{ $totalAttendances }}</div>
                            </div>
                            <div class="rounded-xl bg-slate-50 dark:bg-zinc-800/50 px-3 py-2 text-center">
                                <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400">Rate</div>
                                <div class="teacher-num text-sm font-bold text-slate-900 dark:text-white mt-0.5">{{ $attendanceRate }}%</div>
                            </div>
                        </div>

                        {{-- Attendance progress --}}
                        <div class="mt-3">
                            <div class="flex items-center justify-between text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400 mb-1.5">
                                <span>Attendance</span>
                                <span class="teacher-num text-slate-900 dark:text-white">{{ $attendanceRate }}%</span>
                            </div>
                            <div class="h-1.5 rounded-full bg-slate-100 dark:bg-zinc-800 overflow-hidden">
                                <div style="width: {{ min(100, $attendanceRate) }}%" class="h-full rounded-full bg-gradient-to-r {{ $progressGradient }}"></div>
                            </div>
                        </div>

                        {{-- Action --}}
                        <div class="mt-4 pt-3 border-t border-slate-100 dark:border-zinc-800 flex items-center justify-between gap-2">
                            <a href="{{ route('teacher.students.show', $student) }}"
                               wire:navigate
                               wire:click.stop
                               class="inline-flex items-center gap-1 text-xs font-semibold text-violet-600 dark:text-violet-400 hover:text-violet-700 dark:hover:text-violet-300">
                                <flux:icon name="arrow-right" class="w-3.5 h-3.5" />
                                Open profile
                            </a>
                            <button type="button"
                                    wire:click.stop="selectStudent({{ $student->id }})"
                                    class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-600 dark:text-zinc-300 ring-1 ring-slate-200 dark:ring-zinc-700 hover:bg-slate-50 dark:hover:bg-zinc-800 transition">
                                <flux:icon name="eye" class="w-3.5 h-3.5" />
                                Preview
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            {{-- ─── LIST VIEW ─── --}}
            <div class="space-y-3">
                @foreach($students as $student)
                    @php
                        $attendanceRate = $this->getStudentAttendanceRate($student, $classes);
                        $activeEnrollments = $student->activeEnrollments;
                        $initials = collect(explode(' ', trim($student->user->name)))
                            ->take(2)
                            ->map(fn ($p) => strtoupper(substr($p, 0, 1)))
                            ->join('');
                        $avatarVariant = ($loop->index % 6) + 1;
                        $progressGradient = $attendanceRate >= 80
                            ? 'from-emerald-500 to-teal-500'
                            : ($attendanceRate >= 60 ? 'from-amber-400 to-orange-500' : 'from-rose-500 to-red-500');
                    @endphp

                    <div wire:key="student-list-{{ $student->id }}"
                         wire:click="selectStudent({{ $student->id }})"
                         class="teacher-card teacher-card-hover p-4 cursor-pointer flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4">
                        {{-- Avatar --}}
                        <div class="teacher-avatar teacher-avatar-{{ $avatarVariant }}" style="width: 2.5rem; height: 2.5rem; font-size: 0.85rem;">
                            {{ $initials }}
                        </div>

                        {{-- Center info --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="font-semibold text-slate-900 dark:text-white truncate">
                                    {{ $student->user->name }}
                                </h3>
                                <x-teacher.status-pill :status="$student->status" size="sm" />
                            </div>
                            <p class="text-xs text-slate-500 dark:text-zinc-400 truncate mt-0.5">
                                {{ $student->user->email }}
                                @if($student->student_id)
                                    · ID {{ $student->student_id }}
                                @endif
                            </p>
                            @if($activeEnrollments->isNotEmpty())
                                <div class="mt-1.5 flex flex-wrap gap-1">
                                    @foreach($activeEnrollments->take(3) as $enrollment)
                                        <span class="inline-flex items-center rounded-full bg-violet-50 dark:bg-violet-500/10 text-violet-700 dark:text-violet-300 px-2 py-0.5 text-[10px] font-semibold ring-1 ring-violet-100 dark:ring-violet-900/40 truncate max-w-[160px]">
                                            {{ $enrollment->course->name }}
                                        </span>
                                    @endforeach
                                    @if($activeEnrollments->count() > 3)
                                        <span class="inline-flex items-center rounded-full bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-300 px-2 py-0.5 text-[10px] font-semibold">
                                            +{{ $activeEnrollments->count() - 3 }}
                                        </span>
                                    @endif
                                </div>
                            @endif
                        </div>

                        {{-- Right rail --}}
                        <div class="flex items-center gap-3 sm:justify-end shrink-0">
                            <div class="text-right hidden sm:block">
                                <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400">Attendance</div>
                                <div class="teacher-num text-sm font-bold text-slate-900 dark:text-white">{{ $attendanceRate }}%</div>
                                <div class="mt-1 h-1 w-24 rounded-full bg-slate-100 dark:bg-zinc-800 overflow-hidden">
                                    <div style="width: {{ min(100, $attendanceRate) }}%" class="h-full rounded-full bg-gradient-to-r {{ $progressGradient }}"></div>
                                </div>
                            </div>
                            <a href="{{ route('teacher.students.show', $student) }}"
                               wire:navigate
                               wire:click.stop
                               class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-slate-500 dark:text-zinc-400 hover:bg-slate-100 dark:hover:bg-zinc-800 hover:text-violet-600 dark:hover:text-violet-300 transition"
                               aria-label="Open profile">
                                <flux:icon name="arrow-right" class="w-4 h-4" />
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @else
        {{-- Empty state --}}
        @if($search || $classFilter !== 'all' || $courseFilter !== 'all' || $statusFilter !== 'all')
            <x-teacher.empty-state
                icon="users"
                title="No students found"
                message="No students match your current filters. Try adjusting search terms or clearing filters."
            >
                <button type="button"
                        wire:click="$set('search', ''); $set('classFilter', 'all'); $set('courseFilter', 'all'); $set('statusFilter', 'all')"
                        class="teacher-cta">
                    Clear all filters
                </button>
            </x-teacher.empty-state>
        @else
            <x-teacher.empty-state
                icon="users"
                title="No students yet"
                message="You don't have any students in your classes yet. They'll appear here once enrolled."
            />
        @endif
    @endif

    {{-- ──────────────────────────────────────────────────────────
         STUDENT DETAIL MODAL  -  vibrant gradient redesign
         ────────────────────────────────────────────────────────── --}}
    @if($showStudentModal && $this->selectedStudent)
        @php
            $student = $this->selectedStudent;
            $modalAttendanceRate = $this->getStudentAttendanceRate($student, $classes);
            $modalTotalAttendances = $student->classAttendances->count();
            $modalPresentAttendances = $student->classAttendances->whereIn('status', ['present', 'late'])->count();
            $modalInitials = collect(explode(' ', trim($student->user->name)))
                ->take(2)
                ->map(fn ($p) => strtoupper(substr($p, 0, 1)))
                ->join('');
        @endphp
        <div class="fixed inset-0 z-50 overflow-y-auto teacher-app"
             x-data="{ show: @entangle('showStudentModal') }"
             x-show="show"
             x-cloak
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">

            {{-- Backdrop --}}
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"
                 x-show="show"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 @click="$wire.closeStudentModal()"></div>

            {{-- Modal --}}
            <div class="flex items-start sm:items-center justify-center min-h-screen px-4 py-8">
                <div class="relative bg-white dark:bg-zinc-900 rounded-2xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-hidden ring-1 ring-slate-200/60 dark:ring-zinc-800"
                     x-show="show"
                     x-transition:enter="ease-out duration-300"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="ease-in duration-200"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-95">

                    {{-- HERO HEADER --}}
                    <div class="teacher-modal-hero relative px-6 pt-6 pb-7 sm:px-8 sm:pt-8 sm:pb-9 text-white">
                        <div class="teacher-grain absolute inset-0 pointer-events-none"></div>

                        {{-- Close --}}
                        <button type="button"
                                wire:click="closeStudentModal"
                                class="absolute top-4 right-4 w-9 h-9 rounded-full bg-white/15 hover:bg-white/25 ring-1 ring-white/20 backdrop-blur flex items-center justify-center transition"
                                aria-label="Close">
                            <flux:icon name="x-mark" class="w-4 h-4 text-white" />
                        </button>

                        <div class="relative flex items-start gap-4">
                            <div class="shrink-0 w-16 h-16 rounded-2xl bg-white/15 ring-1 ring-white/30 backdrop-blur flex items-center justify-center text-white font-bold text-xl teacher-display">
                                {{ $modalInitials }}
                            </div>
                            <div class="min-w-0 flex-1 pr-8">
                                {{-- status pill --}}
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/95 text-violet-700 px-3 py-1 text-xs font-bold ring-1 ring-white/40">
                                    @if($student->status === 'active')
                                        <flux:icon name="check-circle" class="w-3 h-3" />
                                        Active
                                    @else
                                        <flux:icon name="user" class="w-3 h-3" />
                                        {{ ucfirst($student->status) }}
                                    @endif
                                </span>

                                <h2 class="teacher-display mt-2 text-2xl sm:text-3xl font-bold leading-tight truncate">
                                    {{ $student->user->name }}
                                </h2>

                                <div class="mt-3 flex flex-wrap gap-2">
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-medium ring-1 ring-white/20 backdrop-blur max-w-full truncate">
                                        <flux:icon name="envelope" class="w-3.5 h-3.5 shrink-0" />
                                        <span class="truncate">{{ $student->user->email }}</span>
                                    </span>
                                    @if($student->student_id)
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-medium ring-1 ring-white/20 backdrop-blur">
                                            <flux:icon name="sparkles" class="w-3.5 h-3.5" />
                                            ID {{ $student->student_id }}
                                        </span>
                                    @endif
                                    @if($student->phone)
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-medium ring-1 ring-white/20 backdrop-blur">
                                            <flux:icon name="phone" class="w-3.5 h-3.5" />
                                            {{ $student->phone }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- BODY --}}
                    <div class="bg-white dark:bg-zinc-900 px-6 py-6 sm:px-8 sm:py-7 space-y-6 max-h-[55vh] overflow-y-auto">

                        {{-- Stat row --}}
                        <div class="grid grid-cols-3 gap-3">
                            <div class="rounded-2xl ring-1 ring-violet-100 dark:ring-violet-900/40 bg-gradient-to-br from-violet-50 to-violet-100/40 dark:from-violet-950/40 dark:to-violet-900/20 px-4 py-4 text-center">
                                <div class="text-[10px] font-semibold uppercase tracking-wider text-violet-700/80 dark:text-violet-300/80">Active Courses</div>
                                <div class="teacher-num teacher-display text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ $student->activeEnrollments->count() }}</div>
                            </div>
                            <div class="rounded-2xl ring-1 ring-emerald-100 dark:ring-emerald-900/40 bg-gradient-to-br from-emerald-50 to-emerald-100/40 dark:from-emerald-950/40 dark:to-emerald-900/20 px-4 py-4 text-center">
                                <div class="text-[10px] font-semibold uppercase tracking-wider text-emerald-700/80 dark:text-emerald-300/80">Attendance</div>
                                <div class="teacher-num teacher-display text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ $modalAttendanceRate }}%</div>
                            </div>
                            <div class="rounded-2xl ring-1 ring-amber-100 dark:ring-amber-900/40 bg-gradient-to-br from-amber-50 to-amber-100/40 dark:from-amber-950/40 dark:to-amber-900/20 px-4 py-4 text-center">
                                <div class="text-[10px] font-semibold uppercase tracking-wider text-amber-700/80 dark:text-amber-300/80">Sessions</div>
                                <div class="teacher-num teacher-display text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ $modalPresentAttendances }}<span class="text-base text-slate-500 dark:text-zinc-400">/{{ $modalTotalAttendances }}</span></div>
                            </div>
                        </div>

                        {{-- Personal info & enrollments --}}
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                            {{-- Personal --}}
                            <div class="rounded-2xl ring-1 ring-slate-200/70 dark:ring-zinc-800 bg-slate-50/40 dark:bg-zinc-800/30 p-4">
                                <h3 class="teacher-display font-bold text-slate-900 dark:text-white text-sm flex items-center gap-1.5 mb-3">
                                    <flux:icon name="user" class="w-4 h-4 text-violet-500" />
                                    Personal Info
                                </h3>
                                <div class="space-y-2.5 text-sm">
                                    <div class="flex justify-between gap-2">
                                        <span class="text-slate-500 dark:text-zinc-400">Full Name</span>
                                        <span class="font-medium text-slate-900 dark:text-white text-right truncate">{{ $student->user->name }}</span>
                                    </div>
                                    <div class="flex justify-between gap-2">
                                        <span class="text-slate-500 dark:text-zinc-400">Email</span>
                                        <span class="font-medium text-slate-900 dark:text-white text-right truncate">{{ $student->user->email }}</span>
                                    </div>
                                    @if($student->phone)
                                        <div class="flex justify-between gap-2">
                                            <span class="text-slate-500 dark:text-zinc-400">Phone</span>
                                            <span class="font-medium text-slate-900 dark:text-white text-right">{{ $student->phone }}</span>
                                        </div>
                                    @endif
                                    @if($student->date_of_birth)
                                        <div class="flex justify-between gap-2">
                                            <span class="text-slate-500 dark:text-zinc-400">Date of Birth</span>
                                            <span class="font-medium text-slate-900 dark:text-white text-right">{{ $student->date_of_birth->format('M d, Y') }}</span>
                                        </div>
                                    @endif
                                    @if($student->gender)
                                        <div class="flex justify-between gap-2">
                                            <span class="text-slate-500 dark:text-zinc-400">Gender</span>
                                            <span class="font-medium text-slate-900 dark:text-white text-right">{{ ucfirst($student->gender) }}</span>
                                        </div>
                                    @endif
                                    <div class="flex justify-between items-center gap-2 pt-1">
                                        <span class="text-slate-500 dark:text-zinc-400">Status</span>
                                        <x-teacher.status-pill :status="$student->status" size="sm" />
                                    </div>
                                </div>
                            </div>

                            {{-- Enrollments --}}
                            <div class="rounded-2xl ring-1 ring-slate-200/70 dark:ring-zinc-800 bg-slate-50/40 dark:bg-zinc-800/30 p-4">
                                <h3 class="teacher-display font-bold text-slate-900 dark:text-white text-sm flex items-center gap-1.5 mb-3">
                                    <flux:icon name="sparkles" class="w-4 h-4 text-violet-500" />
                                    Course Enrollments
                                </h3>
                                @if($student->activeEnrollments->isNotEmpty())
                                    <div class="space-y-2">
                                        @foreach($student->activeEnrollments as $enrollment)
                                            <div class="flex items-center justify-between gap-2 rounded-xl px-3 py-2.5 ring-1 ring-slate-200/70 dark:ring-zinc-800 bg-white dark:bg-zinc-900/60">
                                                <div class="min-w-0">
                                                    <p class="text-sm font-semibold text-slate-900 dark:text-white truncate">{{ $enrollment->course->name }}</p>
                                                    <p class="text-xs text-slate-500 dark:text-zinc-400 truncate">
                                                        {{ $enrollment->enrollment_date ? 'Enrolled '.$enrollment->enrollment_date->format('M d, Y') : 'Active' }}
                                                    </p>
                                                </div>
                                                <span class="inline-flex items-center rounded-full bg-violet-100 dark:bg-violet-500/15 text-violet-700 dark:text-violet-300 px-2 py-0.5 text-[10px] font-semibold whitespace-nowrap">
                                                    {{ $enrollment->academic_status->label() }}
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="text-sm text-slate-500 dark:text-zinc-400">No active enrollments</p>
                                @endif
                            </div>
                        </div>

                        {{-- Recent attendance --}}
                        @if($student->classAttendances->isNotEmpty())
                            <div>
                                <h3 class="teacher-display font-bold text-slate-900 dark:text-white text-sm flex items-center gap-1.5 mb-3">
                                    <flux:icon name="calendar" class="w-4 h-4 text-violet-500" />
                                    Recent Attendance
                                </h3>
                                <div class="space-y-2">
                                    @foreach($student->classAttendances()->with(['session.class'])->latest()->limit(8)->get() as $attendance)
                                        @php
                                            $statusTone = match($attendance->status) {
                                                'present' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
                                                'absent'  => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
                                                'late'    => 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
                                                'excused' => 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
                                                default   => 'bg-slate-100 text-slate-600 dark:bg-zinc-700/50 dark:text-zinc-300',
                                            };
                                        @endphp
                                        <div class="flex items-center justify-between gap-3 rounded-xl px-3 py-2.5 ring-1 ring-slate-200/70 dark:ring-zinc-800 bg-slate-50/60 dark:bg-zinc-800/40">
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-semibold text-slate-900 dark:text-white truncate">{{ $attendance->session->class->title ?? 'N/A' }}</p>
                                                <p class="text-xs text-slate-500 dark:text-zinc-400 truncate">{{ $attendance->session->formatted_date_time ?? 'N/A' }}</p>
                                            </div>
                                            <div class="flex items-center gap-2 shrink-0">
                                                <span class="rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider {{ $statusTone }}">
                                                    {{ ucfirst($attendance->status) }}
                                                </span>
                                                @if($attendance->checked_in_at)
                                                    <span class="text-xs text-slate-500 dark:text-zinc-400 font-mono whitespace-nowrap">{{ $attendance->checked_in_at->format('g:i A') }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- FOOTER --}}
                    <div class="bg-slate-50/80 dark:bg-zinc-950/60 px-6 py-4 sm:px-8 border-t border-slate-200/70 dark:border-zinc-800 flex items-center justify-between gap-3">
                        <button type="button" wire:click="closeStudentModal" class="teacher-cta-ghost">
                            Close
                        </button>
                        <a href="{{ route('teacher.students.show', $student) }}"
                           wire:navigate
                           class="teacher-cta">
                            <flux:icon name="arrow-right" class="w-4 h-4" />
                            Open full profile
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>