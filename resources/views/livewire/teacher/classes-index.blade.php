<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\ClassModel;

new #[Layout('components.layouts.teacher')] class extends Component {
    public function with()
    {
        // Get the teacher model for the current user
        $teacher = auth()->user()->teacher;
        
        if (!$teacher) {
            return ['classes' => collect()];
        }

        // Get classes assigned to this teacher
        $classes = ClassModel::where('teacher_id', $teacher->id)
            ->with(['course', 'activeStudents'])
            ->withCount(['sessions', 'activeStudents'])
            ->latest()
            ->get();

        return [
            'classes' => $classes
        ];
    }
}; ?>

<div class="teacher-app w-full">
    <x-teacher.page-header
        title="My Classes"
        subtitle="All classes you teach"
    />

    @if($classes->count() > 0)
        {{-- ──────────────────────────────────────────────────────────
             STAT STRIP
             ────────────────────────────────────────────────────────── --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <x-teacher.stat-card
                eyebrow="Total Classes"
                :value="$classes->count()"
                tone="indigo"
                icon="academic-cap"
            >
                <span class="text-violet-700/80 dark:text-violet-300/80 font-medium">All assigned</span>
            </x-teacher.stat-card>

            <x-teacher.stat-card
                eyebrow="Active"
                :value="$classes->where('status', 'active')->count()"
                tone="emerald"
                icon="check-circle"
            >
                <span class="text-emerald-700/80 dark:text-emerald-300/80 font-medium">Currently running</span>
            </x-teacher.stat-card>

            <x-teacher.stat-card
                eyebrow="Individual"
                :value="$classes->where('class_type', 'individual')->count()"
                tone="violet"
                icon="user"
            >
                <span class="text-violet-700/80 dark:text-violet-300/80 font-medium">1-on-1 sessions</span>
            </x-teacher.stat-card>

            <x-teacher.stat-card
                eyebrow="Group"
                :value="$classes->where('class_type', 'group')->count()"
                tone="amber"
                icon="users"
            >
                <span class="text-amber-700/80 dark:text-amber-300/80 font-medium">Group classes</span>
            </x-teacher.stat-card>
        </div>

        {{-- ──────────────────────────────────────────────────────────
             CLASS CARD GRID
             ────────────────────────────────────────────────────────── --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($classes as $class)
                @php
                    $accentBorder = match($class->status) {
                        'active'    => 'border-l-emerald-500',
                        'draft'     => 'border-l-violet-500',
                        'completed' => 'border-l-slate-300 dark:border-l-zinc-700',
                        'suspended' => 'border-l-amber-500',
                        'cancelled' => 'border-l-rose-500',
                        default     => 'border-l-violet-500',
                    };

                    $statusKey = match($class->status) {
                        'active'    => 'active',
                        'completed' => 'completed',
                        'cancelled' => 'cancelled',
                        'suspended' => 'no_show',
                        'draft'     => 'scheduled',
                        default     => 'inactive',
                    };
                    $statusLabel = ucfirst($class->status);

                    $typeIcon = $class->class_type === 'individual' ? 'user' : 'users';
                    $studentLabel = $class->class_type === 'individual' ? 'student' : 'students';
                @endphp

                <div
                    wire:key="class-{{ $class->id }}"
                    class="teacher-card teacher-card-hover border-l-4 {{ $accentBorder }} p-5 flex flex-col"
                >
                    {{-- Header: course eyebrow + status pill --}}
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <span class="text-xs font-semibold uppercase tracking-wider text-violet-700/80 dark:text-violet-300/90 truncate">
                            {{ $class->course->name }}
                        </span>
                        <x-teacher.status-pill :status="$statusKey" :label="$statusLabel" size="sm" />
                    </div>

                    {{-- Title --}}
                    <h3 class="teacher-display text-lg font-bold text-slate-900 dark:text-white leading-snug mb-2">
                        {{ $class->title }}
                    </h3>

                    {{-- Description --}}
                    @if($class->description)
                        <p class="text-sm text-slate-500 dark:text-zinc-400 line-clamp-2 mb-4">
                            {{ $class->description }}
                        </p>
                    @endif

                    {{-- Quick stats row --}}
                    <div class="grid grid-cols-2 gap-2 mb-3">
                        <div class="rounded-xl bg-slate-50 dark:bg-zinc-800/50 px-3 py-2.5">
                            <div class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400">
                                <flux:icon name="users" class="w-3.5 h-3.5" />
                                Students
                            </div>
                            <div class="teacher-num text-base font-bold text-slate-900 dark:text-white mt-0.5">
                                {{ $class->active_students_count }}
                                <span class="text-xs font-medium text-slate-500 dark:text-zinc-400">{{ $studentLabel }}</span>
                            </div>
                        </div>
                        <div class="rounded-xl bg-slate-50 dark:bg-zinc-800/50 px-3 py-2.5">
                            <div class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400">
                                <flux:icon name="{{ $typeIcon }}" class="w-3.5 h-3.5" />
                                Type
                            </div>
                            <div class="text-sm font-bold text-slate-900 dark:text-white mt-0.5 capitalize">
                                {{ $class->class_type }}
                            </div>
                        </div>
                    </div>

                    {{-- Schedule preview --}}
                    <div class="space-y-1.5 mb-4 text-xs text-slate-500 dark:text-zinc-400">
                        <div class="flex items-center gap-1.5">
                            <flux:icon name="clock" class="w-3.5 h-3.5 text-violet-500 dark:text-violet-400" />
                            <span class="font-medium">{{ $class->formatted_duration }}</span>
                            <span class="text-slate-400 dark:text-zinc-500">·</span>
                            <flux:icon name="calendar" class="w-3.5 h-3.5 text-violet-500 dark:text-violet-400" />
                            <span class="font-medium">{{ $class->sessions_count }} {{ Str::plural('session', $class->sessions_count) }}</span>
                        </div>

                        @if($class->date_time)
                            <div class="flex items-center gap-1.5">
                                <flux:icon name="sparkles" class="w-3.5 h-3.5 text-violet-500 dark:text-violet-400" />
                                <span class="truncate">{{ $class->formatted_date_time }}</span>
                            </div>
                        @endif
                    </div>

                    {{-- Footer link --}}
                    <div class="mt-auto pt-3 border-t border-slate-100 dark:border-zinc-800 flex items-center justify-end">
                        <a
                            href="{{ route('teacher.classes.show', $class) }}"
                            wire:navigate
                            class="inline-flex items-center gap-1 text-sm font-semibold text-violet-600 dark:text-violet-400 hover:text-violet-700 dark:hover:text-violet-300 transition"
                        >
                            View class
                            <flux:icon name="arrow-right" class="w-4 h-4" />
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <x-teacher.empty-state
            icon="academic-cap"
            title="No classes yet"
            message="You don't have any classes assigned. Contact your administrator to get started."
        />
    @endif
</div>