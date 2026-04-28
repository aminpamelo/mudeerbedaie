<?php

use App\Models\ClassSession;
use App\Support\TeacherStartBriefing;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.teacher')] class extends Component
{
    public ClassSession $session;

    public string $sessionNotes = '';

    public bool $showAttendanceModal = false;

    public ?int $selectedStudentId = null;

    public string $attendanceStatus = 'present';

    public bool $showStartConfirmation = false;

    public function mount(ClassSession $session)
    {
        $this->session = $session->load(['class.course', 'class.teacher.user', 'attendances.student.user', 'starter']);
        $this->sessionNotes = $this->session->teacher_notes ?? '';
    }

    public function updateNotes()
    {
        $this->session->update([
            'teacher_notes' => $this->sessionNotes,
        ]);

        session()->flash('success', 'Session notes updated successfully.');
    }

    public function closeStartConfirmation()
    {
        $this->showStartConfirmation = false;
    }

    public function requestStartSession()
    {
        $this->showStartConfirmation = true;
    }

    public function confirmStartSession()
    {
        $this->startSession();
        $this->closeStartConfirmation();
    }

    public function getStartBriefingProperty(): ?array
    {
        $this->session->loadMissing(['class.course', 'class.pics', 'class.activeStudents']);

        return TeacherStartBriefing::build($this->session, $this->session->class);
    }

    public function startSession()
    {
        if ($this->session->isScheduled()) {
            $this->session->markAsOngoing();
            $this->session->refresh();
            session()->flash('success', 'Session started successfully.');
        }
    }

    public function completeSession()
    {
        if ($this->session->isOngoing()) {
            $this->session->markCompleted($this->sessionNotes);
            $this->session->refresh();
            session()->flash('success', 'Session completed successfully.');
        }
    }

    public function markAsNoShow()
    {
        if ($this->session->isScheduled() || $this->session->isOngoing()) {
            $this->session->markAsNoShow($this->sessionNotes);
            $this->session->refresh();
            session()->flash('success', 'Session marked as no-show.');
        }
    }

    public function cancelSession()
    {
        if ($this->session->isScheduled()) {
            $this->session->cancel();
            $this->session->refresh();
            session()->flash('success', 'Session cancelled.');
        }
    }

    public function showAttendanceModal($studentId)
    {
        $this->selectedStudentId = $studentId;
        $attendance = $this->session->attendances->firstWhere('student_id', $studentId);
        $this->attendanceStatus = $attendance ? $attendance->status : 'present';
        $this->showAttendanceModal = true;
    }

    public function updateAttendance()
    {
        if ($this->selectedStudentId) {
            $this->session->updateStudentAttendance($this->selectedStudentId, $this->attendanceStatus);
            $this->session->refresh();
            $this->showAttendanceModal = false;
            session()->flash('success', 'Attendance updated successfully.');
        }
    }

    public function getStatusBadgeColor()
    {
        return match ($this->session->status) {
            'scheduled' => 'blue',
            'ongoing' => 'yellow',
            'completed' => 'green',
            'cancelled' => 'red',
            'no_show' => 'orange',
            'rescheduled' => 'purple',
            default => 'gray'
        };
    }
}; ?>

@php
    $statusKey = $session->status;
    $statusBadge = match($statusKey) {
        'completed'   => ['bg' => 'bg-emerald-400/95', 'text' => 'text-emerald-950', 'icon' => 'check',                'label' => 'Completed'],
        'ongoing'     => ['bg' => 'bg-emerald-400/95', 'text' => 'text-emerald-950', 'icon' => 'bolt',                 'label' => 'Live now'],
        'cancelled'   => ['bg' => 'bg-rose-400/95',    'text' => 'text-rose-950',    'icon' => 'x-mark',               'label' => 'Cancelled'],
        'no_show'     => ['bg' => 'bg-amber-400/95',   'text' => 'text-amber-950',   'icon' => 'exclamation-triangle', 'label' => 'No-show'],
        'rescheduled' => ['bg' => 'bg-sky-400/95',     'text' => 'text-sky-950',     'icon' => 'calendar',             'label' => 'Rescheduled'],
        default       => ['bg' => 'bg-white/95',       'text' => 'text-violet-700',  'icon' => 'calendar',             'label' => 'Scheduled'],
    };
@endphp

<div class="teacher-app w-full">
    {{-- ──────────────────────────────────────────────────────────
         FLASH SUCCESS
         ────────────────────────────────────────────────────────── --}}
    @if(session('success'))
        <div class="mb-5 rounded-2xl bg-emerald-50 dark:bg-emerald-950/30 ring-1 ring-emerald-200/70 dark:ring-emerald-800/50 px-4 py-3 flex items-center gap-2.5">
            <div class="shrink-0 w-7 h-7 rounded-full bg-emerald-500 text-white flex items-center justify-center">
                <flux:icon name="check" class="w-4 h-4" />
            </div>
            <p class="text-sm font-medium text-emerald-800 dark:text-emerald-200">{{ session('success') }}</p>
        </div>
    @endif

    {{-- ──────────────────────────────────────────────────────────
         HERO HEADER  -  gradient session header
         ────────────────────────────────────────────────────────── --}}
    <div class="teacher-modal-hero relative overflow-hidden rounded-2xl mb-6 px-6 py-7 sm:px-8 sm:py-8 text-white">
        <div class="teacher-grain absolute inset-0 pointer-events-none"></div>

        <div class="relative">
            {{-- Back link --}}
            <a href="{{ route('teacher.sessions.index') }}" wire:navigate class="inline-flex items-center gap-1 text-xs font-semibold text-white/80 hover:text-white mb-3 transition">
                <flux:icon name="arrow-left" class="w-3.5 h-3.5" />
                Back to sessions
            </a>

            {{-- Status pill --}}
            <span class="inline-flex items-center gap-1.5 rounded-full {{ $statusBadge['bg'] }} {{ $statusBadge['text'] }} px-3 py-1 text-xs font-bold ring-1 ring-white/40">
                @if($statusKey === 'ongoing')
                    <span class="teacher-live-dot bg-emerald-700 !shadow-none"></span>
                @else
                    <flux:icon name="{{ $statusBadge['icon'] }}" class="w-3 h-3" />
                @endif
                {{ $statusBadge['label'] }}
            </span>

            {{-- Title --}}
            <h1 class="teacher-display mt-3 text-2xl sm:text-3xl font-bold leading-tight">
                {{ $session->class->course->title ?? $session->class->course->name }}
            </h1>
            <p class="text-white/80 text-sm sm:text-base mt-1">{{ $session->class->title }}</p>

            {{-- Meta chips --}}
            <div class="mt-4 flex flex-wrap gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 ring-1 ring-white/20 backdrop-blur px-3 py-1 text-xs font-medium">
                    <flux:icon name="calendar" class="w-3.5 h-3.5" />
                    {{ $session->session_date->format('D, j M Y') }}
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 ring-1 ring-white/20 backdrop-blur px-3 py-1 text-xs font-medium">
                    <flux:icon name="clock" class="w-3.5 h-3.5" />
                    {{ $session->session_time->format('g:i A') }}
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 ring-1 ring-white/20 backdrop-blur px-3 py-1 text-xs font-medium">
                    <flux:icon name="bolt" class="w-3.5 h-3.5" />
                    {{ $session->formatted_duration }}
                </span>
                @if($session->class->location)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 ring-1 ring-white/20 backdrop-blur px-3 py-1 text-xs font-medium">
                        <flux:icon name="map-pin" class="w-3.5 h-3.5" />
                        {{ $session->class->location }}
                    </span>
                @endif
                @if($session->allowance_amount)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-400/95 text-emerald-950 ring-1 ring-white/30 px-3 py-1 text-xs font-bold">
                        <flux:icon name="sparkles" class="w-3.5 h-3.5" />
                        RM {{ number_format($session->allowance_amount, 2) }}
                    </span>
                @endif
            </div>
        </div>
    </div>

    {{-- ──────────────────────────────────────────────────────────
         LIVE TIMER  -  only when ongoing
         ────────────────────────────────────────────────────────── --}}
    @if($session->isOngoing())
        <div class="teacher-modal-timer rounded-2xl mb-6 px-5 py-5 sm:px-6"
             x-data="{
                 modalTimer: 0,
                 modalInterval: null,
                 initModalTimer() {
                     const startedAt = '{{ $session->started_at ? $session->started_at->toISOString() : now()->toISOString() }}';
                     if (startedAt) {
                         const startTime = new Date(startedAt).getTime();
                         this.modalTimer = Math.floor((Date.now() - startTime) / 1000);
                         this.modalInterval = setInterval(() => {
                             this.modalTimer = Math.floor((Date.now() - startTime) / 1000);
                         }, 1000);
                     }
                 },
                 stopModalTimer() {
                     if (this.modalInterval) {
                         clearInterval(this.modalInterval);
                         this.modalInterval = null;
                     }
                 },
                 formatTime(seconds) {
                     const hours = Math.floor(seconds / 3600);
                     const minutes = Math.floor((seconds % 3600) / 60);
                     const secs = seconds % 60;
                     if (hours > 0) {
                         return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
                     } else {
                         return `${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
                     }
                 }
             }"
             x-init="initModalTimer()"
             x-destroy="stopModalTimer()">
            <div class="relative flex items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 text-emerald-100/95 text-xs font-bold uppercase tracking-[0.2em]">
                        <span class="teacher-live-dot bg-emerald-300"></span>
                        Session live
                    </div>
                    <p class="mt-1 text-emerald-50/80 text-sm">
                        @if($session->starter)
                            Started by {{ $session->starter->name }} — focus mode on.
                        @else
                            Timer running — focus mode on.
                        @endif
                    </p>
                </div>
                <div class="text-right">
                    <div class="teacher-num text-3xl sm:text-4xl font-mono font-bold text-white tracking-tight" x-text="formatTime(modalTimer)"></div>
                    <div class="text-emerald-200/80 text-[10px] font-bold uppercase tracking-[0.18em] mt-0.5">Elapsed</div>
                </div>
            </div>
        </div>
    @endif

    {{-- ──────────────────────────────────────────────────────────
         BODY GRID
         ────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- LEFT (2 cols) --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Attendance --}}
            <div class="teacher-card p-5 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <h2 class="teacher-display text-base sm:text-lg font-bold text-slate-900 dark:text-white">Students</h2>
                        @if($session->attendances->count() > 0)
                            <span class="rounded-full bg-violet-100 dark:bg-violet-500/15 text-violet-700 dark:text-violet-300 px-2 py-0.5 text-[11px] font-bold">
                                {{ $session->attendances->count() }}
                            </span>
                        @endif
                    </div>
                    <flux:icon name="users" class="w-4 h-4 text-slate-400 dark:text-zinc-500" />
                </div>

                @if($session->attendances->count() > 0)
                    <div class="grid sm:grid-cols-2 gap-2">
                        @foreach($session->attendances as $i => $attendance)
                            @php
                                $statusTone = match($attendance->status) {
                                    'present' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
                                    'absent'  => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
                                    'late'    => 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
                                    'excused' => 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
                                    default   => 'bg-slate-100 text-slate-600 dark:bg-zinc-700/50 dark:text-zinc-300',
                                };
                                $name = $attendance->student?->user?->name ?? 'Unknown Student';
                                $initials = collect(explode(' ', trim($name)))
                                    ->take(2)
                                    ->map(fn ($p) => strtoupper(substr($p, 0, 1)))
                                    ->join('');
                                $avatarVariant = ($i % 6) + 1;
                                $canEdit = in_array($session->status, ['ongoing', 'scheduled']);
                            @endphp
                            <div wire:key="attendance-{{ $attendance->student_id }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 ring-1 ring-slate-200/70 dark:ring-zinc-800 bg-slate-50/60 dark:bg-zinc-800/40 hover:ring-violet-300 dark:hover:ring-violet-700/60 transition">
                                <div class="teacher-avatar teacher-avatar-{{ $avatarVariant }}">{{ $initials ?: '?' }}</div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white truncate">{{ $name }}</p>
                                    @if($attendance->student?->user?->email)
                                        <p class="text-[11px] text-slate-500 dark:text-zinc-400 truncate">{{ $attendance->student->user->email }}</p>
                                    @endif
                                </div>
                                <span class="rounded-full px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wider {{ $statusTone }}">
                                    {{ ucfirst($attendance->status) }}
                                </span>
                                @if($canEdit)
                                    <button
                                        type="button"
                                        wire:click="showAttendanceModal({{ $attendance->student_id }})"
                                        class="shrink-0 w-7 h-7 rounded-lg ring-1 ring-slate-200 dark:ring-zinc-700 hover:bg-violet-50 dark:hover:bg-violet-500/10 hover:ring-violet-300 dark:hover:ring-violet-700/60 text-slate-500 dark:text-zinc-400 hover:text-violet-600 dark:hover:text-violet-300 flex items-center justify-center transition"
                                        aria-label="Edit attendance"
                                    >
                                        <flux:icon name="pencil-square" class="w-3.5 h-3.5" />
                                    </button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8 rounded-xl bg-gradient-to-br from-violet-50 to-violet-50/40 dark:from-violet-950/30 dark:to-violet-950/10 ring-1 ring-violet-100 dark:ring-violet-900/30">
                        <div class="inline-flex w-12 h-12 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-600 to-violet-400 text-white shadow-lg shadow-violet-500/30 mb-3">
                            <flux:icon name="users" class="w-5 h-5" />
                        </div>
                        <p class="text-sm font-semibold text-slate-900 dark:text-white">No attendance records</p>
                        <p class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5">Attendance will appear once the session begins.</p>
                    </div>
                @endif
            </div>

            {{-- Notes --}}
            <div class="teacher-card p-5 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <flux:icon name="document-text" class="w-4 h-4 text-violet-500" />
                        <h2 class="teacher-display text-base sm:text-lg font-bold text-slate-900 dark:text-white">Session Notes</h2>
                    </div>
                </div>

                <div class="space-y-3">
                    {{-- Existing notes preview --}}
                    @if(trim($sessionNotes) !== '' && $session->teacher_notes)
                        <div class="rounded-xl px-4 py-3 bg-gradient-to-br from-violet-50/70 to-violet-50/40 dark:from-violet-950/30 dark:to-violet-950/20 ring-1 ring-violet-100/80 dark:ring-violet-900/40">
                            <div class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.18em] text-violet-700 dark:text-violet-300 mb-1.5">
                                <flux:icon name="sparkles" class="w-3 h-3" />
                                Saved
                            </div>
                            <p class="text-sm text-slate-700 dark:text-zinc-200 whitespace-pre-wrap leading-relaxed">{{ $session->teacher_notes }}</p>
                        </div>
                    @endif

                    {{-- Editor --}}
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-zinc-300 mb-1.5">
                            {{ $session->teacher_notes ? 'Update notes' : 'Add notes' }}
                        </label>
                        <textarea
                            wire:model="sessionNotes"
                            placeholder="Summary, what was covered, next steps, anything worth remembering…"
                            rows="4"
                            class="w-full rounded-xl border-0 ring-1 ring-slate-200 dark:ring-zinc-700 bg-white dark:bg-zinc-900 text-sm text-slate-900 dark:text-zinc-100 placeholder:text-slate-400 dark:placeholder:text-zinc-500 px-4 py-3 focus:ring-2 focus:ring-violet-500 dark:focus:ring-violet-400 transition"
                        ></textarea>
                    </div>

                    <div class="flex justify-end">
                        <button type="button" wire:click="updateNotes" class="teacher-cta">
                            <flux:icon name="check" class="w-4 h-4" />
                            Save Notes
                        </button>
                    </div>
                </div>
            </div>

            {{-- Overview --}}
            <div class="teacher-card p-5 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="teacher-display text-base sm:text-lg font-bold text-slate-900 dark:text-white">Overview</h2>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="rounded-xl bg-slate-50 dark:bg-zinc-800/50 px-3 py-2.5">
                        <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400">Class</div>
                        <div class="text-sm font-semibold text-slate-900 dark:text-white mt-0.5 truncate">{{ $session->class->title }}</div>
                    </div>
                    <div class="rounded-xl bg-slate-50 dark:bg-zinc-800/50 px-3 py-2.5">
                        <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400">Course</div>
                        <div class="text-sm font-semibold text-slate-900 dark:text-white mt-0.5 truncate">{{ $session->class->course->title ?? $session->class->course->name }}</div>
                    </div>
                    <div class="rounded-xl bg-slate-50 dark:bg-zinc-800/50 px-3 py-2.5">
                        <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400">Date &amp; Time</div>
                        <div class="text-sm font-semibold text-slate-900 dark:text-white mt-0.5">{{ $session->formatted_date_time }}</div>
                    </div>
                    <div class="rounded-xl bg-slate-50 dark:bg-zinc-800/50 px-3 py-2.5">
                        <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400">Duration</div>
                        <div class="text-sm font-semibold text-slate-900 dark:text-white mt-0.5">{{ $session->formatted_duration }}</div>
                    </div>
                    @if($session->started_by)
                        <div class="rounded-xl bg-slate-50 dark:bg-zinc-800/50 px-3 py-2.5">
                            <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400">Started By</div>
                            <div class="text-sm font-semibold text-slate-900 dark:text-white mt-0.5 truncate">{{ $session->starter->name ?? 'Unknown' }}</div>
                        </div>
                    @endif
                    @if($session->isOngoing())
                        <div class="rounded-xl bg-emerald-50 dark:bg-emerald-950/30 ring-1 ring-emerald-200/70 dark:ring-emerald-800/40 px-3 py-2.5">
                            <div class="text-[11px] font-semibold uppercase tracking-wider text-emerald-700/80 dark:text-emerald-300/90">Elapsed</div>
                            <div class="teacher-num text-sm font-bold text-emerald-700 dark:text-emerald-300 mt-0.5">{{ $session->formatted_elapsed_time }}</div>
                        </div>
                    @endif
                    @if($session->completed_at)
                        <div class="rounded-xl bg-slate-50 dark:bg-zinc-800/50 px-3 py-2.5">
                            <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400">Completed At</div>
                            <div class="text-sm font-semibold text-slate-900 dark:text-white mt-0.5">{{ $session->completed_at->format('j M Y, g:i A') }}</div>
                        </div>
                    @endif
                    @if($session->allowance_amount)
                        <div class="rounded-xl bg-emerald-50 dark:bg-emerald-950/30 ring-1 ring-emerald-200/70 dark:ring-emerald-800/40 px-3 py-2.5">
                            <div class="text-[11px] font-semibold uppercase tracking-wider text-emerald-700/80 dark:text-emerald-300/90">Allowance</div>
                            <div class="teacher-num text-sm font-bold text-emerald-700 dark:text-emerald-300 mt-0.5">RM {{ number_format($session->allowance_amount, 2) }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- RIGHT (1 col) --}}
        <div class="space-y-6">

            {{-- Actions --}}
            <div class="teacher-card p-5 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="teacher-display text-base font-bold text-slate-900 dark:text-white">Actions</h3>
                    <flux:icon name="bolt" class="w-4 h-4 text-violet-500" />
                </div>

                <div class="space-y-2.5">
                    @if($session->status === 'scheduled')
                        <button type="button" wire:click="requestStartSession" class="teacher-cta w-full justify-center">
                            <flux:icon name="play" class="w-4 h-4" />
                            Start Session
                        </button>

                        <button type="button" wire:click="markAsNoShow"
                                class="w-full inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-amber-700 dark:text-amber-300 ring-1 ring-amber-200 dark:ring-amber-800/50 bg-amber-50/60 dark:bg-amber-950/20 hover:bg-amber-100 dark:hover:bg-amber-950/40 transition">
                            <flux:icon name="exclamation-triangle" class="w-4 h-4" />
                            Mark as No-Show
                        </button>

                        <button type="button" wire:click="cancelSession"
                                class="w-full inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-rose-700 dark:text-rose-300 ring-1 ring-rose-200 dark:ring-rose-800/50 bg-rose-50/60 dark:bg-rose-950/20 hover:bg-rose-100 dark:hover:bg-rose-950/40 transition">
                            <flux:icon name="x-circle" class="w-4 h-4" />
                            Cancel Session
                        </button>
                    @endif

                    @if($session->status === 'ongoing')
                        <button type="button" wire:click="completeSession" class="teacher-cta w-full justify-center">
                            <flux:icon name="check-circle" class="w-4 h-4" />
                            Complete Session
                        </button>

                        <button type="button" wire:click="markAsNoShow"
                                class="w-full inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-amber-700 dark:text-amber-300 ring-1 ring-amber-200 dark:ring-amber-800/50 bg-amber-50/60 dark:bg-amber-950/20 hover:bg-amber-100 dark:hover:bg-amber-950/40 transition">
                            <flux:icon name="exclamation-triangle" class="w-4 h-4" />
                            Mark as No-Show
                        </button>
                    @endif

                    <a href="{{ route('teacher.classes.show', $session->class) }}" wire:navigate
                       class="w-full inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-violet-700 dark:text-violet-300 ring-1 ring-violet-200 dark:ring-violet-800/50 bg-violet-50/60 dark:bg-violet-950/20 hover:bg-violet-100 dark:hover:bg-violet-950/40 transition">
                        <flux:icon name="eye" class="w-4 h-4" />
                        View Class Details
                    </a>
                </div>
            </div>

            {{-- Timeline --}}
            <div class="teacher-card p-5 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="teacher-display text-base font-bold text-slate-900 dark:text-white">Timeline</h3>
                    <flux:icon name="clock" class="w-4 h-4 text-slate-400 dark:text-zinc-500" />
                </div>

                <div class="space-y-4">
                    {{-- Created --}}
                    <div class="flex items-start gap-3">
                        <div class="shrink-0 w-9 h-9 rounded-xl bg-gradient-to-br from-violet-500 to-violet-400 text-white shadow-sm flex items-center justify-center">
                            <flux:icon name="calendar" class="w-4 h-4" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">Session scheduled</p>
                            <p class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5">{{ $session->created_at->format('j M Y, g:i A') }}</p>
                            <p class="text-[11px] text-slate-400 dark:text-zinc-500 mt-0.5">{{ $session->created_at->diffForHumans() }}</p>
                        </div>
                    </div>

                    {{-- Started --}}
                    @if($session->started_at)
                        <div class="flex items-start gap-3">
                            <div class="shrink-0 w-9 h-9 rounded-xl bg-gradient-to-br from-emerald-400 to-teal-500 text-white shadow-sm flex items-center justify-center">
                                <flux:icon name="play" class="w-4 h-4" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-slate-900 dark:text-white">Session started</p>
                                <p class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5">{{ $session->started_at->format('j M Y, g:i A') }}</p>
                                @if($session->starter)
                                    <p class="text-[11px] text-slate-400 dark:text-zinc-500 mt-0.5">by {{ $session->starter->name }}</p>
                                @endif
                            </div>
                        </div>
                    @endif

                    {{-- Completed --}}
                    @if($session->completed_at)
                        <div class="flex items-start gap-3">
                            <div class="shrink-0 w-9 h-9 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white shadow-sm flex items-center justify-center">
                                <flux:icon name="check-circle" class="w-4 h-4" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-slate-900 dark:text-white">Session completed</p>
                                <p class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5">{{ $session->completed_at->format('j M Y, g:i A') }}</p>
                                <p class="text-[11px] text-slate-400 dark:text-zinc-500 mt-0.5">{{ $session->completed_at->diffForHumans() }}</p>
                            </div>
                        </div>
                    @endif

                    {{-- Cancelled / No-show --}}
                    @if(in_array($session->status, ['cancelled', 'no_show']) && !$session->completed_at)
                        <div class="flex items-start gap-3">
                            @php
                                $isNoShow = $session->status === 'no_show';
                                $iconBg = $isNoShow
                                    ? 'bg-gradient-to-br from-amber-400 to-orange-500'
                                    : 'bg-gradient-to-br from-rose-400 to-red-500';
                                $iconName = $isNoShow ? 'exclamation-triangle' : 'x-mark';
                                $label = $isNoShow ? 'Marked as no-show' : 'Session cancelled';
                            @endphp
                            <div class="shrink-0 w-9 h-9 rounded-xl {{ $iconBg }} text-white shadow-sm flex items-center justify-center">
                                <flux:icon name="{{ $iconName }}" class="w-4 h-4" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $label }}</p>
                                <p class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5">{{ $session->updated_at->format('j M Y, g:i A') }}</p>
                                <p class="text-[11px] text-slate-400 dark:text-zinc-500 mt-0.5">{{ $session->updated_at->diffForHumans() }}</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ──────────────────────────────────────────────────────────
         ATTENDANCE EDIT MODAL
         ────────────────────────────────────────────────────────── --}}
    @if($showAttendanceModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center px-4 py-6 bg-slate-900/60 dark:bg-black/70 backdrop-blur-sm">
            <div class="teacher-app w-full max-w-md">
                <div class="relative overflow-hidden rounded-2xl bg-white dark:bg-zinc-900 shadow-2xl ring-1 ring-slate-200/80 dark:ring-zinc-800">
                    {{-- top stripe --}}
                    <div class="teacher-modal-stripe"></div>

                    <div class="px-6 pt-7 pb-6">
                        <div class="flex items-center gap-3 mb-5">
                            <div class="shrink-0 w-11 h-11 rounded-xl bg-gradient-to-br from-violet-600 to-violet-400 text-white shadow-lg shadow-violet-500/30 flex items-center justify-center">
                                <flux:icon name="pencil-square" class="w-5 h-5" />
                            </div>
                            <div>
                                <h2 class="teacher-display text-lg font-bold text-slate-900 dark:text-white">Update Attendance</h2>
                                <p class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5">Set the student's status for this session.</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 dark:text-zinc-300 mb-1.5">Status</label>
                            <select wire:model="attendanceStatus"
                                    class="w-full rounded-xl border-0 ring-1 ring-slate-200 dark:ring-zinc-700 bg-white dark:bg-zinc-900 text-sm text-slate-900 dark:text-zinc-100 px-4 py-2.5 focus:ring-2 focus:ring-violet-500 dark:focus:ring-violet-400 transition">
                                <option value="present">Present</option>
                                <option value="late">Late</option>
                                <option value="absent">Absent</option>
                            </select>
                        </div>
                    </div>

                    <div class="bg-slate-50/80 dark:bg-zinc-950/60 px-6 py-4 border-t border-slate-200/70 dark:border-zinc-800 flex gap-2 justify-end">
                        <button type="button" wire:click="$set('showAttendanceModal', false)" class="teacher-cta-ghost">
                            Cancel
                        </button>
                        <button type="button" wire:click="updateAttendance" class="teacher-cta">
                            <flux:icon name="check" class="w-4 h-4" />
                            Save
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ──────────────────────────────────────────────────────────
         START SESSION CONFIRMATION MODAL
         ────────────────────────────────────────────────────────── --}}
    <flux:modal wire:model="showStartConfirmation" class="max-w-md !p-0 overflow-hidden">
        <div class="teacher-app">
            {{-- top gradient stripe --}}
            <div class="teacher-modal-stripe"></div>

            <div class="bg-white dark:bg-zinc-900 px-6 pt-8 pb-6 text-center">
                {{-- gradient orb --}}
                <div class="flex justify-center mb-5">
                    <div class="teacher-modal-orb">
                        <flux:icon name="play" class="w-9 h-9" variant="solid" />
                    </div>
                </div>

                <h2 class="teacher-display text-2xl font-bold text-slate-900 dark:text-white">
                    Start Session?
                </h2>
                <p class="mt-2 text-sm text-slate-500 dark:text-zinc-400 leading-relaxed">
                    Once you start, the timer begins and you'll be able to manage attendance and notes.
                </p>

                {{-- briefing: syllabus, upsell, PIC, class context --}}
                @include('livewire.teacher._partials.start-session-briefing', ['briefing' => $this->startBriefing])

                {{-- info card --}}
                <div class="mt-5 rounded-2xl bg-gradient-to-br from-violet-50 via-violet-100/60 to-violet-200/30 dark:from-violet-950/50 dark:via-violet-900/30 dark:to-violet-800/20 ring-1 ring-violet-100 dark:ring-violet-900/40 px-4 py-4 text-left">
                    <div class="flex items-start gap-3">
                        <div class="shrink-0 w-9 h-9 rounded-xl bg-gradient-to-br from-violet-600 to-violet-500 text-white shadow-lg shadow-violet-500/30 flex items-center justify-center">
                            <flux:icon name="bolt" class="w-4 h-4" variant="solid" />
                        </div>
                        <div class="flex-1 text-xs leading-relaxed">
                            <p class="font-semibold text-violet-900 dark:text-violet-200 text-[13px] mb-0.5">You're all set</p>
                            <p class="text-violet-700/80 dark:text-violet-300/80">Timer starts immediately and runs in real-time.</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- footer actions --}}
            <div class="bg-slate-50/80 dark:bg-zinc-950/60 px-6 py-4 border-t border-slate-200/70 dark:border-zinc-800 flex gap-2 justify-end">
                <button type="button" wire:click="closeStartConfirmation" class="teacher-cta-ghost">
                    Cancel
                </button>
                <button type="button" wire:click="confirmStartSession" class="teacher-cta">
                    <flux:icon name="play" class="w-4 h-4" variant="solid" />
                    Yes, Start Session
                </button>
            </div>
        </div>
    </flux:modal>
</div>