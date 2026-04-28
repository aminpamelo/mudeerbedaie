<?php

use App\Models\ClassAttendance;
use App\Models\ClassSession;
use App\Models\Student;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.teacher')] class extends Component
{
    public Student $student;

    public string $activeTab = 'overview';

    public function mount(Student $student)
    {
        // Ensure the student belongs to one of the teacher's classes
        $teacher = auth()->user()->teacher;
        $hasAccess = false;

        if ($teacher) {
            $teacherClassIds = $teacher->classes()->pluck('id');
            // Get student's class IDs through attendance -> sessions -> classes
            $studentClassIds = $student->classAttendances()
                ->with('session.class')
                ->get()
                ->pluck('session.class.id')
                ->filter()
                ->unique();
            $hasAccess = $teacherClassIds->intersect($studentClassIds)->isNotEmpty();
        }

        if (! $hasAccess) {
            abort(403, 'You do not have access to view this student.');
        }

        $this->student = $student;
    }

    public function setActiveTab(string $tab)
    {
        $this->activeTab = $tab;
    }

    public function getTeacherClassesProperty()
    {
        $teacher = auth()->user()->teacher;
        if (! $teacher) {
            return collect();
        }

        // Get classes where the student has attendance records
        $studentClassIds = $this->student->classAttendances()
            ->with('session.class')
            ->get()
            ->pluck('session.class.id')
            ->filter()
            ->unique();

        return $teacher->classes()
            ->with(['course'])
            ->whereIn('id', $studentClassIds)
            ->get();
    }

    public function getStudentAttendanceProperty()
    {
        return ClassAttendance::with(['session.class.course'])
            ->where('student_id', $this->student->id)
            ->whereHas('session.class', function ($query) {
                $teacher = auth()->user()->teacher;
                $query->where('teacher_id', $teacher->id);
            })
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getAttendanceStatsProperty()
    {
        $attendances = $this->studentAttendance;
        $total = $attendances->count();
        $present = $attendances->whereIn('status', ['present', 'late'])->count();
        $absent = $attendances->where('status', 'absent')->count();
        $excused = $attendances->where('status', 'excused')->count();

        return [
            'total' => $total,
            'present' => $present,
            'absent' => $absent,
            'excused' => $excused,
            'attendance_rate' => $total > 0 ? round(($present / $total) * 100, 1) : 0,
        ];
    }

    public function getRecentSessionsProperty()
    {
        return $this->studentAttendance->take(10);
    }

    public function getUpcomingSessionsProperty()
    {
        $teacher = auth()->user()->teacher;
        $classIds = $this->teacherClasses->pluck('id');

        return ClassSession::with(['class.course'])
            ->whereIn('class_id', $classIds)
            ->where('session_date', '>=', now()->toDateString())
            ->where('status', 'scheduled')
            ->orderBy('session_date')
            ->orderBy('session_time')
            ->limit(5)
            ->get();
    }

    public function getMonthlyAttendanceProperty()
    {
        $attendances = $this->studentAttendance;
        $monthlyData = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $monthKey = $month->format('Y-m');
            $monthName = $month->format('M Y');

            $monthAttendances = $attendances->filter(function ($attendance) use ($month) {
                return $attendance->session->session_date->format('Y-m') === $month->format('Y-m');
            });

            $total = $monthAttendances->count();
            $present = $monthAttendances->whereIn('status', ['present', 'late'])->count();

            $monthlyData[] = [
                'month' => $monthName,
                'total' => $total,
                'present' => $present,
                'rate' => $total > 0 ? round(($present / $total) * 100, 1) : 0,
            ];
        }

        return collect($monthlyData);
    }

    public function getStudentEnrollmentsProperty()
    {
        return $this->student->activeEnrollments()
            ->with(['course'])
            ->get();
    }

    public function sendEmail()
    {
        $email = $this->student->user->email;
        $subject = urlencode('Student Communication - '.$this->student->user->name);
        $mailtoLink = "mailto:{$email}?subject={$subject}";

        $this->dispatch('open-mailto', url: $mailtoLink);
    }

    public function makeCall()
    {
        if ($this->student->phone) {
            $phone = preg_replace('/[^0-9+]/', '', $this->student->phone);
            $telLink = "tel:{$phone}";
            $this->dispatch('open-tel', url: $telLink);
        }
    }
}; ?>

@php
    $studentInitials = collect(explode(' ', trim($student->user->name)))
        ->take(2)
        ->map(fn ($p) => strtoupper(substr($p, 0, 1)))
        ->join('') ?: '?';
    $attRate = $this->attendanceStats['attendance_rate'];
    $rateTone = $attRate >= 80 ? 'emerald' : ($attRate >= 60 ? 'amber' : 'rose');
    $studentStatusKey = $student->status === 'active' ? 'active' : 'inactive';
@endphp

<div class="teacher-app w-full">
    {{-- ──────────────────────────────────────────────────────────
         HERO HEADER  -  gradient student header card
         ────────────────────────────────────────────────────────── --}}
    <div class="teacher-modal-hero relative overflow-hidden rounded-2xl mb-6 px-6 py-7 sm:px-8 sm:py-8 text-white">
        <div class="teacher-grain absolute inset-0 pointer-events-none"></div>

        <div class="relative flex items-start gap-5">
            {{-- back link top-left --}}
            <a href="{{ route('teacher.students.index') }}" wire:navigate class="absolute top-0 left-0 inline-flex items-center gap-1 text-xs font-semibold text-white/80 hover:text-white transition">
                <flux:icon name="arrow-left" class="w-3.5 h-3.5" />
                Back to students
            </a>

            {{-- big avatar bubble --}}
            <div class="shrink-0 w-20 h-20 rounded-2xl bg-white/15 ring-2 ring-white/30 backdrop-blur flex items-center justify-center mt-6">
                <span class="teacher-display text-3xl font-bold text-white">{{ $studentInitials }}</span>
            </div>

            <div class="flex-1 min-w-0 mt-6">
                {{-- status pill --}}
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/95 text-violet-700 px-2.5 py-0.5 text-[11px] font-bold ring-1 ring-white/40">
                    @if($student->status === 'active')
                        <flux:icon name="check-circle" class="w-3 h-3" />
                    @else
                        <flux:icon name="clock" class="w-3 h-3" />
                    @endif
                    {{ ucfirst($student->status) }}
                </span>

                <h1 class="teacher-display mt-2 text-2xl sm:text-3xl font-bold leading-tight truncate">
                    {{ $student->user->name }}
                </h1>
                <p class="text-white/80 text-sm truncate">{{ $student->user->email }}</p>

                {{-- meta chips --}}
                <div class="mt-3 flex flex-wrap gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 ring-1 ring-white/20 backdrop-blur px-3 py-1 text-xs font-medium">
                        <flux:icon name="user" class="w-3.5 h-3.5" />
                        ID {{ $student->student_id }}
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 ring-1 ring-white/20 backdrop-blur px-3 py-1 text-xs font-medium">
                        <flux:icon name="phone" class="w-3.5 h-3.5" />
                        {{ $student->phone ?? 'No phone' }}
                    </span>
                    @if($student->date_of_birth)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 ring-1 ring-white/20 backdrop-blur px-3 py-1 text-xs font-medium">
                            <flux:icon name="calendar" class="w-3.5 h-3.5" />
                            {{ $student->age }} yrs
                        </span>
                    @endif
                    @if($this->studentEnrollments->isNotEmpty())
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 ring-1 ring-white/20 backdrop-blur px-3 py-1 text-xs font-medium">
                            <flux:icon name="academic-cap" class="w-3.5 h-3.5" />
                            {{ $this->studentEnrollments->count() }} {{ Str::plural('course', $this->studentEnrollments->count()) }}
                        </span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ──────────────────────────────────────────────────────────
         QUICK STATS  -  4 stat cards
         ────────────────────────────────────────────────────────── --}}
    <div class="grid gap-4 grid-cols-2 lg:grid-cols-4 mb-6">
        <x-teacher.stat-card
            eyebrow="Attendance"
            :value="$attRate.'%'"
            tone="emerald"
            icon="chart-pie"
        >
            <div class="mt-2 h-1.5 w-full rounded-full bg-emerald-200/50 dark:bg-emerald-900/40 overflow-hidden">
                <div class="h-full rounded-full bg-gradient-to-r from-emerald-400 to-teal-500" style="width: {{ min(100, $attRate) }}%"></div>
            </div>
        </x-teacher.stat-card>

        <x-teacher.stat-card
            eyebrow="Sessions"
            :value="$this->attendanceStats['present']"
            tone="indigo"
            icon="check-circle"
        >
            <span class="font-medium text-violet-700/80 dark:text-violet-300/80">
                of {{ $this->attendanceStats['total'] }} attended
            </span>
        </x-teacher.stat-card>

        <x-teacher.stat-card
            eyebrow="Classes"
            :value="$this->teacherClasses->count()"
            tone="violet"
            icon="academic-cap"
        >
            <span class="font-medium text-violet-700/80 dark:text-violet-300/80">
                Active with you
            </span>
        </x-teacher.stat-card>

        <x-teacher.stat-card
            eyebrow="Enrollments"
            :value="$this->studentEnrollments->count()"
            tone="amber"
            icon="banknotes"
        >
            <span class="font-medium text-amber-700/80 dark:text-amber-300/80">
                Active courses
            </span>
        </x-teacher.stat-card>
    </div>

    {{-- ──────────────────────────────────────────────────────────
         TAB NAVIGATION
         ────────────────────────────────────────────────────────── --}}
    <x-teacher.tabs
        :tabs="[
            ['key' => 'overview', 'label' => 'Overview', 'icon' => 'home'],
            ['key' => 'attendance', 'label' => 'Attendance', 'icon' => 'chart-pie', 'badge' => $this->attendanceStats['total']],
            ['key' => 'progress', 'label' => 'Progress', 'icon' => 'arrow-trending-up'],
            ['key' => 'communication', 'label' => 'Communication', 'icon' => 'envelope'],
        ]"
        :active="$activeTab"
    />

    {{-- ──────────────────────────────────────────────────────────
         OVERVIEW TAB
         ────────────────────────────────────────────────────────── --}}
    <div class="{{ $activeTab === 'overview' ? 'block' : 'hidden' }}">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Personal Information --}}
            <div class="teacher-card p-5 sm:p-6">
                <div class="flex items-center gap-2 mb-4">
                    <h2 class="teacher-display text-base sm:text-lg font-bold text-slate-900 dark:text-white">Personal Information</h2>
                    <flux:icon name="user" class="w-4 h-4 text-violet-500" />
                </div>
                <dl class="divide-y divide-slate-100 dark:divide-zinc-800">
                    <div class="flex justify-between items-center py-2.5">
                        <dt class="text-sm font-medium text-slate-500 dark:text-zinc-400">Full Name</dt>
                        <dd class="text-sm font-semibold text-slate-900 dark:text-white text-right">{{ $student->user->name }}</dd>
                    </div>
                    <div class="flex justify-between items-center py-2.5">
                        <dt class="text-sm font-medium text-slate-500 dark:text-zinc-400">Email</dt>
                        <dd class="text-sm font-semibold text-slate-900 dark:text-white text-right truncate ml-3">{{ $student->user->email }}</dd>
                    </div>
                    <div class="flex justify-between items-center py-2.5">
                        <dt class="text-sm font-medium text-slate-500 dark:text-zinc-400">Student ID</dt>
                        <dd class="teacher-num text-sm font-semibold text-slate-900 dark:text-white">{{ $student->student_id }}</dd>
                    </div>
                    @if($student->phone)
                        <div class="flex justify-between items-center py-2.5">
                            <dt class="text-sm font-medium text-slate-500 dark:text-zinc-400">Phone</dt>
                            <dd class="teacher-num text-sm font-semibold text-slate-900 dark:text-white">{{ $student->phone }}</dd>
                        </div>
                    @endif
                    @if($student->date_of_birth)
                        <div class="flex justify-between items-center py-2.5">
                            <dt class="text-sm font-medium text-slate-500 dark:text-zinc-400">Date of Birth</dt>
                            <dd class="text-sm font-semibold text-slate-900 dark:text-white">{{ $student->date_of_birth->format('M d, Y') }}</dd>
                        </div>
                        <div class="flex justify-between items-center py-2.5">
                            <dt class="text-sm font-medium text-slate-500 dark:text-zinc-400">Age</dt>
                            <dd class="text-sm font-semibold text-slate-900 dark:text-white">{{ $student->age }} years</dd>
                        </div>
                    @endif
                    @if($student->gender)
                        <div class="flex justify-between items-center py-2.5">
                            <dt class="text-sm font-medium text-slate-500 dark:text-zinc-400">Gender</dt>
                            <dd class="text-sm font-semibold text-slate-900 dark:text-white">{{ ucfirst($student->gender) }}</dd>
                        </div>
                    @endif
                    @if($student->nationality)
                        <div class="flex justify-between items-center py-2.5">
                            <dt class="text-sm font-medium text-slate-500 dark:text-zinc-400">Nationality</dt>
                            <dd class="text-sm font-semibold text-slate-900 dark:text-white">{{ $student->nationality }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between items-center py-2.5">
                        <dt class="text-sm font-medium text-slate-500 dark:text-zinc-400">Status</dt>
                        <dd>
                            <x-teacher.status-pill :status="$studentStatusKey" />
                        </dd>
                    </div>
                </dl>
            </div>

            {{-- Current Enrollments --}}
            <div class="teacher-card p-5 sm:p-6">
                <div class="flex items-center gap-2 mb-4">
                    <h2 class="teacher-display text-base sm:text-lg font-bold text-slate-900 dark:text-white">Current Enrollments</h2>
                    <flux:icon name="academic-cap" class="w-4 h-4 text-violet-500" />
                </div>
                @if($this->studentEnrollments->isNotEmpty())
                    <div class="space-y-2.5">
                        @foreach($this->studentEnrollments as $enrollment)
                            <div wire:key="enrollment-{{ $enrollment->id }}" class="flex items-center gap-3 rounded-xl px-3 py-3 ring-1 ring-slate-200/70 dark:ring-zinc-800 bg-slate-50/60 dark:bg-zinc-800/40 hover:ring-violet-300 dark:hover:ring-violet-700/60 transition">
                                <div class="shrink-0 w-9 h-9 rounded-xl bg-gradient-to-br from-violet-600 to-violet-400 text-white shadow-sm flex items-center justify-center">
                                    <flux:icon name="academic-cap" class="w-4 h-4" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white truncate">{{ $enrollment->course->name }}</p>
                                    <p class="text-xs text-slate-500 dark:text-zinc-400 truncate">
                                        Enrolled {{ $enrollment->enrollment_date?->format('M d, Y') ?? 'N/A' }}
                                    </p>
                                </div>
                                <x-teacher.status-pill :status="$enrollment->status" size="sm" />
                            </div>
                        @endforeach
                    </div>
                @else
                    <x-teacher.empty-state icon="academic-cap" title="No active enrollments" message="This student isn't currently enrolled in any courses." />
                @endif
            </div>

            {{-- Classes --}}
            <div class="teacher-card p-5 sm:p-6">
                <div class="flex items-center gap-2 mb-4">
                    <h2 class="teacher-display text-base sm:text-lg font-bold text-slate-900 dark:text-white">Classes</h2>
                    <flux:icon name="users" class="w-4 h-4 text-violet-500" />
                </div>
                @if($this->teacherClasses->isNotEmpty())
                    <div class="space-y-2.5">
                        @foreach($this->teacherClasses as $class)
                            <div wire:key="tclass-{{ $class->id }}" class="rounded-xl px-3.5 py-3 ring-1 ring-slate-200/70 dark:ring-zinc-800 bg-slate-50/60 dark:bg-zinc-800/40 hover:ring-violet-300 dark:hover:ring-violet-700/60 transition">
                                <div class="flex items-start justify-between gap-2 mb-1">
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white truncate">{{ $class->title }}</p>
                                    <x-teacher.status-pill :status="$class->status" size="sm" />
                                </div>
                                <p class="text-xs text-slate-500 dark:text-zinc-400 truncate mb-2">{{ $class->course->name }}</p>
                                <div class="flex items-center gap-3 text-[11px] text-slate-500 dark:text-zinc-400">
                                    <span class="inline-flex items-center gap-1">
                                        <flux:icon name="clock" class="w-3 h-3" />
                                        {{ $class->formatted_duration }}
                                    </span>
                                    @if($class->location)
                                        <span class="inline-flex items-center gap-1">
                                            <flux:icon name="academic-cap" class="w-3 h-3" />
                                            {{ $class->location }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <x-teacher.empty-state icon="users" title="No classes" message="This student isn't in any of your classes yet." />
                @endif
            </div>

            {{-- Upcoming Sessions --}}
            <div class="teacher-card p-5 sm:p-6">
                <div class="flex items-center gap-2 mb-4">
                    <h2 class="teacher-display text-base sm:text-lg font-bold text-slate-900 dark:text-white">Upcoming Sessions</h2>
                    <flux:icon name="calendar" class="w-4 h-4 text-violet-500" />
                </div>
                @if($this->upcomingSessions->isNotEmpty())
                    <div class="space-y-2.5">
                        @foreach($this->upcomingSessions as $session)
                            <div wire:key="upc-{{ $session->id }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 bg-gradient-to-r from-slate-50 to-violet-50/40 dark:from-zinc-800/40 dark:to-violet-950/20 ring-1 ring-slate-200/60 dark:ring-zinc-800 hover:ring-violet-300/50 dark:hover:ring-violet-700/40 transition">
                                <div class="shrink-0 text-center w-12">
                                    <div class="teacher-display text-[11px] font-bold uppercase text-violet-600 dark:text-violet-400 leading-none">
                                        {{ $session->session_date->format('M') }}
                                    </div>
                                    <div class="teacher-display teacher-num text-xl font-bold text-slate-900 dark:text-white leading-tight">
                                        {{ $session->session_date->format('j') }}
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white truncate">{{ $session->class->title }}</p>
                                    <p class="text-xs text-slate-500 dark:text-zinc-400 truncate">{{ $session->formatted_date_time }}</p>
                                </div>
                                <span class="inline-flex items-center gap-1 rounded-full bg-violet-100 dark:bg-violet-500/15 text-violet-700 dark:text-violet-300 px-2 py-0.5 text-[11px] font-semibold">
                                    {{ $session->formatted_duration }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <x-teacher.empty-state icon="calendar" title="No upcoming sessions" message="No scheduled sessions ahead." />
                @endif
            </div>

        </div>
    </div>

    {{-- ──────────────────────────────────────────────────────────
         ATTENDANCE TAB
         ────────────────────────────────────────────────────────── --}}
    <div class="{{ $activeTab === 'attendance' ? 'block' : 'hidden' }}">

        {{-- Attendance stat strip --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <x-teacher.stat-card eyebrow="Total" :value="$this->attendanceStats['total']" tone="indigo" icon="calendar">
                <span class="font-medium text-violet-700/80 dark:text-violet-300/80">Sessions tracked</span>
            </x-teacher.stat-card>
            <x-teacher.stat-card eyebrow="Present" :value="$this->attendanceStats['present']" tone="emerald" icon="check-circle">
                <span class="font-medium text-emerald-700/80 dark:text-emerald-300/80">On time / late</span>
            </x-teacher.stat-card>
            <x-teacher.stat-card eyebrow="Absent" :value="$this->attendanceStats['absent']" tone="amber" icon="clock">
                <span class="font-medium text-amber-700/80 dark:text-amber-300/80">Missed</span>
            </x-teacher.stat-card>
            <x-teacher.stat-card eyebrow="Excused" :value="$this->attendanceStats['excused']" tone="violet" icon="document-text">
                <span class="font-medium text-violet-700/80 dark:text-violet-300/80">With reason</span>
            </x-teacher.stat-card>
        </div>

        {{-- Monthly Attendance Trend --}}
        <div class="teacher-card p-5 sm:p-6 mb-6">
            <div class="flex items-center justify-between mb-5">
                <div class="flex items-center gap-2">
                    <h2 class="teacher-display text-base sm:text-lg font-bold text-slate-900 dark:text-white">Monthly Attendance Trend</h2>
                    <flux:icon name="arrow-trending-up" class="w-4 h-4 text-violet-500" />
                </div>
                <span class="text-xs font-semibold text-slate-500 dark:text-zinc-400">Last 6 months</span>
            </div>
            <div class="space-y-4">
                @foreach($this->monthlyAttendance as $month)
                    @php
                        $barColor = $month['rate'] >= 80
                            ? 'from-emerald-400 to-teal-500'
                            : ($month['rate'] >= 60 ? 'from-amber-400 to-orange-500' : 'from-rose-400 to-red-500');
                        $trackColor = $month['rate'] >= 80
                            ? 'bg-emerald-100 dark:bg-emerald-900/40'
                            : ($month['rate'] >= 60 ? 'bg-amber-100 dark:bg-amber-900/40' : 'bg-rose-100 dark:bg-rose-900/40');
                    @endphp
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-sm font-semibold text-slate-900 dark:text-white">{{ $month['month'] }}</span>
                            <span class="teacher-num text-xs font-medium text-slate-500 dark:text-zinc-400">
                                {{ $month['present'] }}/{{ $month['total'] }} <span class="font-bold text-slate-700 dark:text-zinc-200">({{ $month['rate'] }}%)</span>
                            </span>
                        </div>
                        <div class="h-2 w-full rounded-full {{ $trackColor }} overflow-hidden">
                            <div class="h-full rounded-full bg-gradient-to-r {{ $barColor }}" style="width: {{ $month['rate'] }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Attendance History --}}
        <div class="teacher-card p-5 sm:p-6">
            <div class="flex items-center gap-2 mb-4">
                <h2 class="teacher-display text-base sm:text-lg font-bold text-slate-900 dark:text-white">Attendance History</h2>
                @if($this->studentAttendance->isNotEmpty())
                    <span class="rounded-full bg-violet-100 dark:bg-violet-500/15 text-violet-700 dark:text-violet-300 px-2 py-0.5 text-[11px] font-bold">
                        {{ $this->studentAttendance->count() }}
                    </span>
                @endif
            </div>
            @if($this->studentAttendance->isNotEmpty())
                <div class="space-y-2.5">
                    @foreach($this->studentAttendance as $i => $attendance)
                        @php
                            $statusKey = $attendance->status;
                            $statusTone = match($statusKey) {
                                'present' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
                                'absent'  => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
                                'late'    => 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
                                'excused' => 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
                                default   => 'bg-slate-100 text-slate-600 dark:bg-zinc-700/50 dark:text-zinc-300',
                            };
                            $avatarVariant = ($i % 6) + 1;
                        @endphp
                        <div wire:key="att-{{ $attendance->id }}" class="flex items-start gap-3 rounded-xl px-3.5 py-3 ring-1 ring-slate-200/70 dark:ring-zinc-800 bg-slate-50/60 dark:bg-zinc-800/40 hover:ring-violet-300 dark:hover:ring-violet-700/60 transition">
                            <div class="teacher-avatar teacher-avatar-{{ $avatarVariant }} shrink-0">
                                <flux:icon name="calendar" class="w-4 h-4" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between gap-2 flex-wrap">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-slate-900 dark:text-white truncate">
                                            {{ $attendance->session->class->title ?? 'N/A' }}
                                        </p>
                                        <p class="text-xs text-slate-500 dark:text-zinc-400 truncate">
                                            {{ $attendance->session->class->course->name ?? 'N/A' }}
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <span class="rounded-full px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wider {{ $statusTone }}">
                                            {{ ucfirst($statusKey) }}
                                        </span>
                                    </div>
                                </div>
                                <div class="mt-1.5 flex items-center gap-3 text-[11px] text-slate-500 dark:text-zinc-400">
                                    <span class="inline-flex items-center gap-1">
                                        <flux:icon name="clock" class="w-3 h-3" />
                                        {{ $attendance->session->formatted_date_time ?? 'N/A' }}
                                    </span>
                                    @if($attendance->checked_in_at)
                                        <span class="inline-flex items-center gap-1">
                                            <flux:icon name="check-circle" class="w-3 h-3" />
                                            Checked in {{ $attendance->checked_in_at->format('g:i A') }}
                                        </span>
                                    @endif
                                </div>
                                @if($attendance->teacher_remarks)
                                    <div class="mt-2 rounded-lg bg-violet-50/70 dark:bg-violet-950/30 ring-1 ring-violet-100 dark:ring-violet-900/40 px-3 py-2">
                                        <p class="text-xs text-slate-700 dark:text-zinc-200 leading-relaxed">
                                            <span class="font-semibold text-violet-700 dark:text-violet-300">Note:</span>
                                            {{ $attendance->teacher_remarks }}
                                        </p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <x-teacher.empty-state icon="chart-pie" title="No attendance records" message="Attendance history will appear here once sessions are completed." />
            @endif
        </div>

    </div>

    {{-- ──────────────────────────────────────────────────────────
         PROGRESS TAB
         ────────────────────────────────────────────────────────── --}}
    <div class="{{ $activeTab === 'progress' ? 'block' : 'hidden' }}">

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

            {{-- Overall Performance --}}
            <div class="teacher-card p-5 sm:p-6">
                <div class="flex items-center gap-2 mb-4">
                    <h2 class="teacher-display text-base sm:text-lg font-bold text-slate-900 dark:text-white">Overall Performance</h2>
                    <flux:icon name="sparkles" class="w-4 h-4 text-violet-500" />
                </div>
                <div class="space-y-4">
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-sm font-semibold text-slate-700 dark:text-zinc-200">Attendance Rate</span>
                            <span class="teacher-num text-sm font-bold text-slate-900 dark:text-white">{{ $this->attendanceStats['attendance_rate'] }}%</span>
                        </div>
                        @php
                            $overallTrack = $attRate >= 80 ? 'bg-emerald-100 dark:bg-emerald-900/40' : ($attRate >= 60 ? 'bg-amber-100 dark:bg-amber-900/40' : 'bg-rose-100 dark:bg-rose-900/40');
                            $overallBar = $attRate >= 80 ? 'from-emerald-400 to-teal-500' : ($attRate >= 60 ? 'from-amber-400 to-orange-500' : 'from-rose-400 to-red-500');
                        @endphp
                        <div class="h-2 w-full rounded-full {{ $overallTrack }} overflow-hidden">
                            <div class="h-full rounded-full bg-gradient-to-r {{ $overallBar }}" style="width: {{ $attRate }}%"></div>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div class="rounded-xl bg-slate-50 dark:bg-zinc-800/50 px-3 py-3">
                            <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400">Total</div>
                            <div class="teacher-num text-xl font-bold text-slate-900 dark:text-white mt-0.5">{{ $this->attendanceStats['total'] }}</div>
                        </div>
                        <div class="rounded-xl bg-slate-50 dark:bg-zinc-800/50 px-3 py-3">
                            <div class="text-[11px] font-semibold uppercase tracking-wider text-emerald-700/80 dark:text-emerald-300/80">Present</div>
                            <div class="teacher-num text-xl font-bold text-emerald-700 dark:text-emerald-300 mt-0.5">{{ $this->attendanceStats['present'] }}</div>
                        </div>
                        <div class="rounded-xl bg-slate-50 dark:bg-zinc-800/50 px-3 py-3">
                            <div class="text-[11px] font-semibold uppercase tracking-wider text-violet-700/80 dark:text-violet-300/80">Courses</div>
                            <div class="teacher-num text-xl font-bold text-violet-700 dark:text-violet-300 mt-0.5">{{ $this->studentEnrollments->count() }}</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Performance by Class --}}
            <div class="teacher-card p-5 sm:p-6">
                <div class="flex items-center gap-2 mb-4">
                    <h2 class="teacher-display text-base sm:text-lg font-bold text-slate-900 dark:text-white">Performance by Class</h2>
                    <flux:icon name="academic-cap" class="w-4 h-4 text-violet-500" />
                </div>
                @if($this->teacherClasses->isNotEmpty())
                    <div class="space-y-4">
                        @foreach($this->teacherClasses as $class)
                            @php
                                $classAttendances = $this->studentAttendance->filter(function($attendance) use ($class) {
                                    return $attendance->session && $attendance->session->class_id == $class->id;
                                });
                                $classTotal = $classAttendances->count();
                                $classPresent = $classAttendances->whereIn('status', ['present', 'late'])->count();
                                $classRate = $classTotal > 0 ? round(($classPresent / $classTotal) * 100, 1) : 0;
                                $classTrack = $classRate >= 80 ? 'bg-emerald-100 dark:bg-emerald-900/40' : ($classRate >= 60 ? 'bg-amber-100 dark:bg-amber-900/40' : 'bg-rose-100 dark:bg-rose-900/40');
                                $classBar = $classRate >= 80 ? 'from-emerald-400 to-teal-500' : ($classRate >= 60 ? 'from-amber-400 to-orange-500' : 'from-rose-400 to-red-500');
                            @endphp
                            <div wire:key="cls-{{ $class->id }}">
                                <div class="flex items-center justify-between mb-1.5">
                                    <span class="text-sm font-semibold text-slate-900 dark:text-white truncate">{{ $class->title }}</span>
                                    <span class="teacher-num text-xs font-medium text-slate-500 dark:text-zinc-400 shrink-0 ml-2">
                                        {{ $classPresent }}/{{ $classTotal }} <span class="font-bold text-slate-700 dark:text-zinc-200">({{ $classRate }}%)</span>
                                    </span>
                                </div>
                                <div class="h-2 w-full rounded-full {{ $classTrack }} overflow-hidden">
                                    <div class="h-full rounded-full bg-gradient-to-r {{ $classBar }}" style="width: {{ $classRate }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <x-teacher.empty-state icon="academic-cap" title="No class data" message="Class performance will populate as the student attends sessions." />
                @endif
            </div>

        </div>

        {{-- Achievements --}}
        <div class="teacher-card p-5 sm:p-6">
            <div class="flex items-center gap-2 mb-4">
                <h2 class="teacher-display text-base sm:text-lg font-bold text-slate-900 dark:text-white">Achievements</h2>
                <flux:icon name="sparkles" class="w-4 h-4 text-violet-500" />
            </div>
            @php
                $achievements = collect();
                if($this->attendanceStats['attendance_rate'] >= 95) {
                    $achievements->push(['icon' => 'sparkles', 'label' => 'Perfect Attendance', 'tone' => 'emerald']);
                }
                if($this->attendanceStats['attendance_rate'] >= 80) {
                    $achievements->push(['icon' => 'check-circle', 'label' => 'Regular Attendee', 'tone' => 'emerald']);
                }
                if($this->studentEnrollments->count() > 1) {
                    $achievements->push(['icon' => 'academic-cap', 'label' => 'Multi-Course Student', 'tone' => 'violet']);
                }
                if($this->attendanceStats['total'] >= 10) {
                    $achievements->push(['icon' => 'bolt', 'label' => 'Committed Student', 'tone' => 'sky']);
                }
                if($this->attendanceStats['attendance_rate'] < 80 && $this->attendanceStats['total'] > 5) {
                    $achievements->push(['icon' => 'clock', 'label' => 'Needs Support', 'tone' => 'amber']);
                }
                $toneMap = [
                    'emerald' => 'bg-emerald-100 text-emerald-700 ring-emerald-200/70 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-700/40',
                    'violet'  => 'bg-violet-100 text-violet-700 ring-violet-200/70 dark:bg-violet-500/15 dark:text-violet-300 dark:ring-violet-700/40',
                    'sky'     => 'bg-sky-100 text-sky-700 ring-sky-200/70 dark:bg-sky-500/15 dark:text-sky-300 dark:ring-sky-700/40',
                    'amber'   => 'bg-amber-100 text-amber-800 ring-amber-200/70 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-700/40',
                ];
            @endphp
            @if($achievements->isNotEmpty())
                <div class="flex flex-wrap gap-2.5">
                    @foreach($achievements as $a)
                        <span class="inline-flex items-center gap-1.5 rounded-full ring-1 px-3 py-1.5 text-xs font-semibold {{ $toneMap[$a['tone']] }}">
                            <flux:icon name="{{ $a['icon'] }}" class="w-3.5 h-3.5" />
                            {{ $a['label'] }}
                        </span>
                    @endforeach
                </div>
            @else
                <x-teacher.empty-state icon="sparkles" title="No achievements yet" message="Achievements unlock as the student progresses." />
            @endif
        </div>

    </div>

    {{-- ──────────────────────────────────────────────────────────
         COMMUNICATION TAB
         ────────────────────────────────────────────────────────── --}}
    <div class="{{ $activeTab === 'communication' ? 'block' : 'hidden' }}">

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Contact Information --}}
            <div class="teacher-card p-5 sm:p-6">
                <div class="flex items-center gap-2 mb-4">
                    <h2 class="teacher-display text-base sm:text-lg font-bold text-slate-900 dark:text-white">Contact Information</h2>
                    <flux:icon name="envelope" class="w-4 h-4 text-violet-500" />
                </div>
                <div class="space-y-3">
                    {{-- Email --}}
                    <div class="rounded-xl px-3.5 py-3 ring-1 ring-slate-200/70 dark:ring-zinc-800 bg-slate-50/60 dark:bg-zinc-800/40">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="shrink-0 w-9 h-9 rounded-xl bg-gradient-to-br from-violet-600 to-violet-400 text-white shadow-sm flex items-center justify-center">
                                    <flux:icon name="envelope" class="w-4 h-4" />
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400">Email</p>
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white truncate">{{ $student->user->email }}</p>
                                </div>
                            </div>
                            <button type="button" wire:click="sendEmail" class="teacher-cta shrink-0">
                                <flux:icon name="envelope" class="w-4 h-4" />
                                Email
                            </button>
                        </div>
                    </div>

                    @if($student->phone)
                        {{-- Phone --}}
                        <div class="rounded-xl px-3.5 py-3 ring-1 ring-slate-200/70 dark:ring-zinc-800 bg-slate-50/60 dark:bg-zinc-800/40">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="shrink-0 w-9 h-9 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-500 text-white shadow-sm flex items-center justify-center">
                                        <flux:icon name="phone" class="w-4 h-4" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400">Phone</p>
                                        <p class="teacher-num text-sm font-semibold text-slate-900 dark:text-white truncate">{{ $student->phone }}</p>
                                    </div>
                                </div>
                                <button type="button" wire:click="makeCall" class="teacher-cta shrink-0">
                                    <flux:icon name="phone" class="w-4 h-4" />
                                    Call
                                </button>
                            </div>
                        </div>
                    @endif

                    @if($student->address)
                        {{-- Address --}}
                        <div class="rounded-xl px-3.5 py-3 ring-1 ring-slate-200/70 dark:ring-zinc-800 bg-slate-50/60 dark:bg-zinc-800/40">
                            <div class="flex items-start gap-3">
                                <div class="shrink-0 w-9 h-9 rounded-xl bg-gradient-to-br from-sky-500 to-violet-500 text-white shadow-sm flex items-center justify-center">
                                    <flux:icon name="document-text" class="w-4 h-4" />
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400">Address</p>
                                    <p class="text-sm font-medium text-slate-700 dark:text-zinc-200 leading-relaxed">{{ $student->address }}</p>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Quick Actions --}}
            <div class="teacher-card p-5 sm:p-6">
                <div class="flex items-center gap-2 mb-4">
                    <h2 class="teacher-display text-base sm:text-lg font-bold text-slate-900 dark:text-white">Quick Actions</h2>
                    <flux:icon name="bolt" class="w-4 h-4 text-violet-500" />
                </div>
                <div class="space-y-2.5">
                    <button type="button" wire:click="sendEmail" class="w-full inline-flex items-center gap-2.5 rounded-xl bg-gradient-to-r from-violet-700 to-violet-600 px-4 py-3 text-sm font-semibold text-white shadow-md shadow-violet-500/25 hover:shadow-lg hover:from-violet-600 hover:to-violet-400 transition">
                        <flux:icon name="envelope" class="w-4 h-4" />
                        Send Message via Email
                    </button>
                    <button type="button" class="w-full inline-flex items-center gap-2.5 rounded-xl ring-1 ring-slate-200 dark:ring-zinc-700 bg-white dark:bg-zinc-900 hover:bg-violet-50 dark:hover:bg-violet-500/10 hover:ring-violet-300 dark:hover:ring-violet-700/60 px-4 py-3 text-sm font-semibold text-slate-700 dark:text-zinc-200 transition">
                        <flux:icon name="document-text" class="w-4 h-4 text-violet-500" />
                        Generate Progress Report
                    </button>
                    <button type="button" class="w-full inline-flex items-center gap-2.5 rounded-xl ring-1 ring-slate-200 dark:ring-zinc-700 bg-white dark:bg-zinc-900 hover:bg-violet-50 dark:hover:bg-violet-500/10 hover:ring-violet-300 dark:hover:ring-violet-700/60 px-4 py-3 text-sm font-semibold text-slate-700 dark:text-zinc-200 transition">
                        <flux:icon name="calendar" class="w-4 h-4 text-violet-500" />
                        Schedule Meeting
                    </button>
                    <button type="button" class="w-full inline-flex items-center gap-2.5 rounded-xl ring-1 ring-slate-200 dark:ring-zinc-700 bg-white dark:bg-zinc-900 hover:bg-violet-50 dark:hover:bg-violet-500/10 hover:ring-violet-300 dark:hover:ring-violet-700/60 px-4 py-3 text-sm font-semibold text-slate-700 dark:text-zinc-200 transition">
                        <flux:icon name="clock" class="w-4 h-4 text-violet-500" />
                        Set Reminder
                    </button>
                </div>
            </div>

        </div>

        {{-- Teacher Notes --}}
        <div class="teacher-card p-5 sm:p-6 mt-6">
            <div class="flex items-center gap-2 mb-2">
                <h2 class="teacher-display text-base sm:text-lg font-bold text-slate-900 dark:text-white">Teacher Notes</h2>
                <flux:icon name="pencil-square" class="w-4 h-4 text-violet-500" />
            </div>
            <p class="text-xs text-slate-500 dark:text-zinc-400 mb-3">
                Capture observations about progress, behavior, or anything worth remembering.
            </p>
            <textarea
                placeholder="Add notes about this student's progress, behavior, or any observations…"
                rows="6"
                class="w-full rounded-xl border-0 ring-1 ring-slate-200 dark:ring-zinc-700 bg-white dark:bg-zinc-900 text-sm text-slate-900 dark:text-zinc-100 placeholder:text-slate-400 dark:placeholder:text-zinc-500 px-4 py-3 focus:ring-2 focus:ring-violet-500 dark:focus:ring-violet-400 transition"
            ></textarea>
            <div class="flex justify-end mt-3">
                <button type="button" class="teacher-cta">
                    <flux:icon name="pencil-square" class="w-4 h-4" />
                    Add Note
                </button>
            </div>
        </div>

    </div>
</div>

<script>
document.addEventListener('livewire:init', function () {
    Livewire.on('open-mailto', (data) => {
        window.open(data.url, '_blank');
    });
    
    Livewire.on('open-tel', (data) => {
        window.location.href = data.url;
    });
});
</script>