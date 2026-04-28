<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Course;
use App\Models\ClassModel;
use Illuminate\Support\Str;

new #[Layout('components.layouts.teacher')] class extends Component {
    public Course $course;

    public function mount(Course $course)
    {
        $this->course = $course;
        
        // Verify the teacher has access to this course (has classes in this course)
        $teacher = auth()->user()->teacher;
        if (!$teacher) {
            abort(403, 'Teacher access required');
        }

        $hasAccess = $course->classes()
            ->where('teacher_id', $teacher->id)
            ->exists();

        if (!$hasAccess) {
            abort(403, 'You do not have access to this course');
        }
    }

    public function with()
    {
        $teacher = auth()->user()->teacher;
        
        // Get classes for this course taught by this teacher
        $classes = ClassModel::where('course_id', $this->course->id)
            ->where('teacher_id', $teacher->id)
            ->withCount(['activeStudents', 'sessions', 'classStudents'])
            ->with(['timetable', 'sessions' => function($query) {
                $query->orderBy('session_date', 'desc')->limit(5);
            }])
            ->latest()
            ->get();

        // Get all students from all classes for this course
        $allStudents = collect();
        foreach ($classes as $class) {
            $classStudents = $class->classStudents()
                ->with(['student.user'])
                ->where('status', 'active')
                ->get()
                ->map(function ($classStudent) use ($class) {
                    return [
                        'student' => $classStudent->student,
                        'class' => $class,
                        'enrolled_at' => $classStudent->enrolled_at,
                        'status' => $classStudent->status,
                    ];
                });
            
            $allStudents = $allStudents->concat($classStudents);
        }

        // Remove duplicates based on student ID
        $uniqueStudents = $allStudents->unique(function ($item) {
            return $item['student']->id;
        });

        return [
            'classes' => $classes,
            'students' => $uniqueStudents,
            'totalClasses' => $classes->count(),
            'totalStudents' => $uniqueStudents->count(),
            'totalSessions' => $classes->sum('sessions_count'),
            'activeClasses' => $classes->where('status', 'active')->count(),
        ];
    }
}; ?>

<div class="teacher-app w-full">
    {{-- Page Header --}}
    <x-teacher.page-header
        :title="$course->name"
        :subtitle="$course->code ?? null"
        :back="route('teacher.courses.index')"
    >
        <x-teacher.status-pill :status="$course->status" />
    </x-teacher.page-header>

    {{-- Hero card --}}
    <div class="teacher-card mb-6 p-6 sm:p-7 relative overflow-hidden">
        <div class="absolute -top-16 -right-16 w-48 h-48 rounded-full bg-gradient-to-br from-violet-500/20 to-violet-700/10 blur-2xl pointer-events-none"></div>
        <div class="absolute -bottom-20 -left-12 w-48 h-48 rounded-full bg-gradient-to-tr from-emerald-400/15 to-violet-500/10 blur-2xl pointer-events-none"></div>

        <div class="relative">
            @if($course->code)
                <div class="text-[11px] font-bold uppercase tracking-[0.18em] text-violet-600 dark:text-violet-300 mb-2">
                    {{ $course->code }}
                </div>
            @else
                <div class="text-[11px] font-bold uppercase tracking-[0.18em] text-violet-600 dark:text-violet-300 mb-2">
                    Course
                </div>
            @endif

            <h2 class="teacher-display text-2xl sm:text-3xl font-bold text-slate-900 dark:text-white leading-tight">
                {{ $course->name }}
            </h2>

            @if($course->description)
                <p class="mt-3 text-sm sm:text-base text-slate-600 dark:text-zinc-400 max-w-3xl line-clamp-3 leading-relaxed">
                    {{ $course->description }}
                </p>
            @endif

            <div class="mt-4 flex items-center gap-2">
                <x-teacher.status-pill :status="$course->status" />
                <span class="inline-flex items-center gap-1.5 rounded-full bg-violet-100 dark:bg-violet-500/15 text-violet-700 dark:text-violet-300 px-2.5 py-0.5 text-[11px] font-semibold">
                    <flux:icon name="book-open" class="w-3 h-3" />
                    {{ $totalClasses }} {{ Str::plural('class', $totalClasses) }}
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 px-2.5 py-0.5 text-[11px] font-semibold">
                    <flux:icon name="users" class="w-3 h-3" />
                    {{ $totalStudents }} {{ Str::plural('student', $totalStudents) }}
                </span>
            </div>
        </div>
    </div>

    {{-- Stat strip --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <x-teacher.stat-card eyebrow="Total Classes" :value="$totalClasses" tone="indigo" icon="academic-cap">
            <span class="text-violet-700/80 dark:text-violet-300/80 font-medium">All classes in course</span>
        </x-teacher.stat-card>

        <x-teacher.stat-card eyebrow="Active Classes" :value="$activeClasses" tone="emerald" icon="check-circle">
            <span class="text-emerald-700/80 dark:text-emerald-300/80 font-medium">
                {{ $activeClasses }} of {{ $totalClasses }} active
            </span>
        </x-teacher.stat-card>

        <x-teacher.stat-card eyebrow="Total Students" :value="$totalStudents" tone="violet" icon="users">
            <span class="text-violet-700/80 dark:text-violet-300/80 font-medium">Enrolled across classes</span>
        </x-teacher.stat-card>

        <x-teacher.stat-card eyebrow="Total Sessions" :value="$totalSessions" tone="amber" icon="clock">
            <span class="text-amber-700/80 dark:text-amber-300/80 font-medium">All scheduled & past</span>
        </x-teacher.stat-card>
    </div>

    {{-- Two-column layout --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Classes list --}}
        <div class="lg:col-span-2">
            <div class="teacher-card p-5 sm:p-6">
                <div class="flex items-start justify-between mb-5">
                    <div>
                        <h2 class="teacher-display text-xl font-bold text-slate-900 dark:text-white">Classes</h2>
                        <p class="text-sm text-slate-500 dark:text-zinc-400 mt-0.5">
                            {{ $classes->count() }} {{ Str::plural('class', $classes->count()) }} you teach in this course
                        </p>
                    </div>
                </div>

                @if($classes->count() > 0)
                    <div class="space-y-3">
                        @foreach($classes as $class)
                            @php
                                $statusKey = $class->status;
                                $borderClass = match($statusKey) {
                                    'active' => 'border-l-emerald-500',
                                    'completed' => 'border-l-slate-300 dark:border-l-zinc-600',
                                    'cancelled' => 'border-l-rose-500',
                                    default => 'border-l-violet-500',
                                };
                            @endphp
                            <div wire:key="class-{{ $class->id }}"
                                 class="group relative flex flex-col sm:flex-row sm:items-center gap-3 rounded-xl border-l-4 {{ $borderClass }} bg-white dark:bg-zinc-900/40 ring-1 ring-slate-200/70 dark:ring-zinc-800 px-4 py-3.5 hover:shadow-md hover:-translate-y-px transition-all">

                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h3 class="font-semibold text-slate-900 dark:text-white truncate">
                                            {{ $class->title }}
                                        </h3>
                                        <x-teacher.status-pill :status="$class->status" size="sm" />
                                    </div>
                                    @if($class->description)
                                        <p class="text-xs text-slate-500 dark:text-zinc-400 mt-1 line-clamp-2">
                                            {{ $class->description }}
                                        </p>
                                    @endif
                                    <div class="mt-2 flex items-center gap-3 flex-wrap text-xs text-slate-500 dark:text-zinc-400">
                                        <span class="inline-flex items-center gap-1">
                                            <flux:icon name="users" class="w-3.5 h-3.5 text-violet-500 dark:text-violet-400" />
                                            {{ $class->active_students_count }} {{ Str::plural('student', $class->active_students_count) }}
                                        </span>
                                        <span class="inline-flex items-center gap-1">
                                            <flux:icon name="academic-cap" class="w-3.5 h-3.5 text-emerald-500 dark:text-emerald-400" />
                                            {{ $class->sessions_count }} {{ Str::plural('session', $class->sessions_count) }}
                                        </span>
                                        <span class="inline-flex items-center gap-1">
                                            <flux:icon name="clock" class="w-3.5 h-3.5 text-amber-500 dark:text-amber-400" />
                                            {{ $class->formatted_duration }}
                                        </span>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 sm:justify-end shrink-0">
                                    <a href="{{ route('teacher.classes.show', $class) }}" wire:navigate
                                       class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-semibold text-violet-700 dark:text-violet-300 ring-1 ring-violet-200 dark:ring-violet-800/50 hover:bg-violet-50 dark:hover:bg-violet-950/30 transition">
                                        <flux:icon name="eye" class="w-3.5 h-3.5" />
                                        View
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <x-teacher.empty-state
                        icon="academic-cap"
                        title="No classes yet"
                        message="You don't have any classes for this course yet."
                    />
                @endif
            </div>
        </div>

        {{-- Students sidebar --}}
        <div class="lg:col-span-1">
            <div class="teacher-card p-5 sm:p-6">
                <div class="flex items-start justify-between mb-5">
                    <div>
                        <h2 class="teacher-display text-xl font-bold text-slate-900 dark:text-white">Enrolled Students</h2>
                        <p class="text-sm text-slate-500 dark:text-zinc-400 mt-0.5">
                            {{ $students->count() }} unique {{ Str::plural('student', $students->count()) }}
                        </p>
                    </div>
                </div>

                @if($students->count() > 0)
                    <div class="space-y-2.5">
                        @foreach($students->take(10) as $studentData)
                            @php
                                $student = $studentData['student'];
                                $name = $student->user->name;
                                $email = $student->user->email;
                                $initials = collect(explode(' ', trim($name)))
                                    ->take(2)
                                    ->map(fn ($p) => strtoupper(substr($p, 0, 1)))
                                    ->join('');
                                $avatarVariant = ($loop->index % 6) + 1;
                            @endphp
                            <div wire:key="student-{{ $student->id }}"
                                 class="flex items-center gap-3 rounded-xl px-3 py-2.5 ring-1 ring-slate-200/70 dark:ring-zinc-800 bg-slate-50/60 dark:bg-zinc-800/40 hover:ring-violet-300 dark:hover:ring-violet-700/60 transition">
                                <div class="teacher-avatar teacher-avatar-{{ $avatarVariant }}">{{ $initials }}</div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white truncate">{{ $name }}</p>
                                    <p class="text-xs text-slate-500 dark:text-zinc-400 truncate">{{ $email }}</p>
                                </div>
                            </div>
                        @endforeach

                        @if($students->count() > 10)
                            <div class="pt-3 mt-2 border-t border-slate-100 dark:border-zinc-800 text-center">
                                <span class="text-xs font-semibold text-violet-600 dark:text-violet-400">
                                    +{{ $students->count() - 10 }} more {{ Str::plural('student', $students->count() - 10) }}
                                </span>
                            </div>
                        @endif
                    </div>
                @else
                    <x-teacher.empty-state
                        icon="users"
                        title="No students enrolled"
                        message="Students will appear here once enrolled."
                    />
                @endif
            </div>
        </div>
    </div>
</div>