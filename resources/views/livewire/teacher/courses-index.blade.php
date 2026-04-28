<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Course;
use Illuminate\Support\Str;

new #[Layout('components.layouts.teacher')] class extends Component {
    public function with()
    {
        // Get the teacher model for the current user
        $teacher = auth()->user()->teacher;
        
        if (!$teacher) {
            return ['courses' => collect()];
        }

        // Get courses where this teacher has classes
        $courses = Course::whereHas('classes', function ($query) use ($teacher) {
                $query->where('teacher_id', $teacher->id);
            })
            ->withCount([
                'enrollments', 
                'activeEnrollments',
                'classes as classes_count' => function ($query) use ($teacher) {
                    $query->where('teacher_id', $teacher->id);
                }
            ])
            ->latest()
            ->get();

        return [
            'courses' => $courses
        ];
    }
}; ?>

<div class="teacher-app w-full">
    <x-teacher.page-header
        title="My Courses"
        subtitle="Courses you teach this semester"
    />

    @if($courses->count() > 0)
        {{-- Stat strip --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <x-teacher.stat-card
                eyebrow="Total Courses"
                :value="$courses->count()"
                tone="indigo"
                icon="book-open"
            >
                <span class="text-slate-500 dark:text-zinc-400 font-medium">All assigned courses</span>
            </x-teacher.stat-card>

            <x-teacher.stat-card
                eyebrow="Active Courses"
                :value="$courses->where('status', 'active')->count()"
                tone="emerald"
                icon="check-circle"
            >
                <span class="text-emerald-700/80 dark:text-emerald-300/80 font-medium">Currently running</span>
            </x-teacher.stat-card>

            <x-teacher.stat-card
                eyebrow="Total Students"
                :value="$courses->sum('enrollments_count')"
                tone="violet"
                icon="users"
            >
                <span class="text-violet-700/80 dark:text-violet-300/80 font-medium">Across all courses</span>
            </x-teacher.stat-card>
        </div>

        {{-- Course card grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($courses as $course)
                <a
                    href="{{ route('teacher.courses.show', $course) }}"
                    wire:navigate
                    wire:key="course-{{ $course->id }}"
                    class="teacher-card teacher-card-hover group flex flex-col p-5 relative overflow-hidden"
                >
                    {{-- Decorative corner glow --}}
                    <div class="absolute -top-12 -right-12 w-32 h-32 rounded-full bg-gradient-to-br from-violet-400/15 to-violet-500/5 dark:from-violet-400/15 dark:to-violet-500/5 blur-2xl pointer-events-none"></div>

                    <div class="relative flex items-start justify-between gap-3 mb-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 mb-1.5">
                                <span class="text-[11px] font-bold uppercase tracking-[0.16em] text-violet-600 dark:text-violet-400">
                                    @if($course->code)
                                        {{ $course->code }}
                                    @else
                                        Course
                                    @endif
                                </span>
                            </div>
                            <h3 class="teacher-display text-lg font-bold text-slate-900 dark:text-white leading-snug line-clamp-2">
                                {{ $course->name }}
                            </h3>
                        </div>

                        {{-- Status pill --}}
                        @if($course->status === 'active')
                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 px-2 py-0.5 text-[11px] font-semibold ring-1 ring-emerald-200/60 dark:ring-emerald-800/40 shrink-0">
                                <flux:icon name="check-circle" class="w-3 h-3" />
                                Active
                            </span>
                        @elseif($course->status === 'draft')
                            <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 dark:bg-zinc-700/50 text-slate-600 dark:text-zinc-300 px-2 py-0.5 text-[11px] font-semibold ring-1 ring-slate-200/70 dark:ring-zinc-700 shrink-0">
                                Draft
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 rounded-full bg-rose-100 dark:bg-rose-500/15 text-rose-700 dark:text-rose-300 px-2 py-0.5 text-[11px] font-semibold ring-1 ring-rose-200/60 dark:ring-rose-800/40 shrink-0">
                                Inactive
                            </span>
                        @endif
                    </div>

                    @if($course->description)
                        <p class="relative text-sm text-slate-600 dark:text-zinc-400 leading-relaxed line-clamp-2 mb-4">
                            {{ $course->description }}
                        </p>
                    @endif

                    {{-- Quick stats --}}
                    <div class="relative grid grid-cols-2 gap-2 mb-4">
                        <div class="rounded-xl bg-slate-50 dark:bg-zinc-800/50 px-3 py-2.5 ring-1 ring-slate-100 dark:ring-zinc-800">
                            <div class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400">
                                <flux:icon name="academic-cap" class="w-3.5 h-3.5" />
                                Classes
                            </div>
                            <div class="teacher-num text-lg font-bold text-slate-900 dark:text-white mt-0.5">
                                {{ $course->classes_count }}
                            </div>
                        </div>
                        <div class="rounded-xl bg-slate-50 dark:bg-zinc-800/50 px-3 py-2.5 ring-1 ring-slate-100 dark:ring-zinc-800">
                            <div class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400">
                                <flux:icon name="users" class="w-3.5 h-3.5" />
                                Students
                            </div>
                            <div class="teacher-num text-lg font-bold text-slate-900 dark:text-white mt-0.5">
                                {{ $course->enrollments_count }}
                            </div>
                        </div>
                    </div>

                    {{-- Footer: fee + view link --}}
                    <div class="relative mt-auto flex items-center justify-between gap-2 pt-3 border-t border-slate-100 dark:border-zinc-800">
                        @if($course->feeSettings && $course->feeSettings->fee_amount)
                            <span class="inline-flex items-center text-xs font-semibold text-emerald-700 dark:text-emerald-300">
                                {{ $course->formatted_fee }}
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 rounded-full bg-sky-100 dark:bg-sky-500/15 text-sky-700 dark:text-sky-300 px-2 py-0.5 text-[11px] font-semibold ring-1 ring-sky-200/60 dark:ring-sky-800/40">
                                <flux:icon name="sparkles" class="w-3 h-3" />
                                Free
                            </span>
                        @endif

                        <span class="inline-flex items-center gap-1 text-sm font-semibold text-violet-600 dark:text-violet-400 group-hover:text-violet-700 dark:group-hover:text-violet-300 transition">
                            View course
                            <flux:icon name="arrow-right" class="w-4 h-4 transition-transform group-hover:translate-x-0.5" />
                        </span>
                    </div>
                </a>
            @endforeach
        </div>
    @else
        <x-teacher.empty-state
            icon="book-open"
            title="No courses yet"
            message="When you're assigned a course, it will appear here."
        />
    @endif
</div>