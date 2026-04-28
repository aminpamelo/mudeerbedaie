<div class="teacher-app space-y-6">
    {{-- ─── STAT STRIP ─── --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <x-teacher.stat-card
            eyebrow="Total Students"
            :value="$this->enrolled_students_count"
            tone="violet"
            icon="users"
        >
            @if($class->max_capacity)
                <span class="text-violet-700/80 dark:text-violet-300/80 font-medium">
                    of {{ $class->max_capacity }} capacity
                </span>
            @else
                <span class="text-violet-700/80 dark:text-violet-300/80 font-medium">Currently enrolled</span>
            @endif
        </x-teacher.stat-card>

        <x-teacher.stat-card
            eyebrow="Avg Attendance"
            :value="$this->overall_attendance_rate.'%'"
            tone="emerald"
            icon="chart-pie"
        >
            <div class="mt-1 h-1.5 w-full rounded-full bg-emerald-200/50 dark:bg-emerald-900/40 overflow-hidden">
                <div class="h-full rounded-full bg-gradient-to-r from-emerald-500 to-teal-500" style="width: {{ min(100, $this->overall_attendance_rate) }}%"></div>
            </div>
        </x-teacher.stat-card>

        <x-teacher.stat-card
            eyebrow="Total Present"
            :value="$this->total_present_count"
            tone="emerald"
            icon="check-circle"
        >
            <span class="text-emerald-700/80 dark:text-emerald-300/80 font-medium">
                across {{ $this->completed_sessions_count }} {{ Str::plural('session', $this->completed_sessions_count) }}
            </span>
        </x-teacher.stat-card>

        <x-teacher.stat-card
            eyebrow="Total Absent"
            :value="$this->total_absent_count"
            tone="amber"
            icon="bolt"
        >
            <span class="text-amber-700/80 dark:text-amber-300/80 font-medium">
                @if($this->total_attendance_records > 0)
                    {{ round(($this->total_absent_count / $this->total_attendance_records) * 100, 1) }}% of records
                @else
                    No records yet
                @endif
            </span>
        </x-teacher.stat-card>
    </div>

    @if($class->activeStudents->count() > 0)
        {{-- ─── CAPACITY PROGRESS ─── --}}
        @if($class->max_capacity)
            @php
                $capacityPct = round(($this->enrolled_students_count / $class->max_capacity) * 100, 1);
                $capacityGradient = $capacityPct >= 90
                    ? 'from-rose-500 to-red-500'
                    : ($capacityPct >= 70 ? 'from-amber-400 to-orange-500' : 'from-violet-500 to-violet-600');
            @endphp
            <div class="teacher-card p-5">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <div class="shrink-0 inline-flex items-center justify-center w-10 h-10 rounded-xl bg-violet-500/10 dark:bg-violet-400/15 text-violet-600 dark:text-violet-300">
                            <flux:icon name="users" class="w-5 h-5" />
                        </div>
                        <div>
                            <div class="teacher-display font-bold text-slate-900 dark:text-white">Class Capacity</div>
                            <div class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5">
                                {{ $this->enrolled_students_count }} of {{ $class->max_capacity }} students enrolled
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 sm:min-w-[260px]">
                        <div class="flex-1 h-1.5 rounded-full bg-slate-100 dark:bg-zinc-800 overflow-hidden">
                            <div class="h-full rounded-full bg-gradient-to-r {{ $capacityGradient }}" style="width: {{ min(100, $capacityPct) }}%"></div>
                        </div>
                        <span class="teacher-num text-sm font-bold text-slate-900 dark:text-white whitespace-nowrap">{{ $capacityPct }}%</span>
                    </div>
                </div>
            </div>
        @endif

        {{-- ─── STUDENTS LIST ─── --}}
        <div>
            <div class="flex items-end justify-between mb-3">
                <div>
                    <h2 class="teacher-display text-lg font-bold text-slate-900 dark:text-white">Student Performance</h2>
                    <p class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5">
                        Individual attendance tracking across {{ $this->completed_sessions_count }} completed {{ Str::plural('session', $this->completed_sessions_count) }}
                    </p>
                </div>
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400">
                    {{ $class->activeStudents->count() }} {{ Str::plural('student', $class->activeStudents->count()) }}
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                @foreach($class->activeStudents->sortBy('student.user.name')->values() as $index => $classStudent)
                    @php
                        $student = $classStudent->student;

                        $studentAttendances = collect();
                        foreach($class->sessions as $session) {
                            $attendance = $session->attendances->where('student_id', $student->id)->first();
                            if($attendance) {
                                $studentAttendances->push($attendance);
                            }
                        }

                        $presentCount = $studentAttendances->where('status', 'present')->count();
                        $lateCount = $studentAttendances->where('status', 'late')->count();
                        $absentCount = $studentAttendances->where('status', 'absent')->count();
                        $excusedCount = $studentAttendances->where('status', 'excused')->count();
                        $totalRecords = $studentAttendances->count();
                        $attendanceRate = $totalRecords > 0 ? round(($presentCount / $totalRecords) * 100, 1) : 0;

                        $performanceText = $attendanceRate >= 90
                            ? 'Excellent'
                            : ($attendanceRate >= 80 ? 'Good' : ($attendanceRate >= 70 ? 'Needs work' : 'Poor'));

                        $progressGradient = $totalRecords === 0
                            ? 'from-slate-300 to-slate-400 dark:from-zinc-700 dark:to-zinc-600'
                            : ($attendanceRate >= 80
                                ? 'from-emerald-500 to-teal-500'
                                : ($attendanceRate >= 60 ? 'from-amber-400 to-orange-500' : 'from-rose-500 to-red-500'));

                        $rateTone = $totalRecords === 0
                            ? 'text-slate-500 dark:text-zinc-400'
                            : ($attendanceRate >= 80
                                ? 'text-emerald-600 dark:text-emerald-300'
                                : ($attendanceRate >= 60 ? 'text-amber-600 dark:text-amber-300' : 'text-rose-600 dark:text-rose-300'));

                        $initials = collect(explode(' ', trim($student->fullName ?? $student->user->name ?? '')))
                            ->take(2)
                            ->map(fn ($p) => strtoupper(substr($p, 0, 1)))
                            ->join('');
                        $avatarVariant = ($index % 6) + 1;
                    @endphp

                    <div wire:key="class-student-{{ $classStudent->id }}"
                         class="teacher-card teacher-card-hover p-5 flex flex-col">
                        {{-- Header: avatar + name --}}
                        <div class="flex items-start gap-3">
                            <div class="teacher-avatar teacher-avatar-{{ $avatarVariant }} shrink-0" style="width: 2.75rem; height: 2.75rem; font-size: 0.9rem;">
                                {{ $initials ?: '?' }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400">
                                    Student
                                </div>
                                <h3 class="teacher-display font-bold text-slate-900 dark:text-white truncate">
                                    {{ $student->fullName }}
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-zinc-400 truncate mt-0.5">
                                    @if($student->user?->email)
                                        {{ $student->user->email }}
                                    @else
                                        ID {{ $student->student_id }}
                                    @endif
                                </p>
                            </div>
                            <x-teacher.status-pill status="active" size="sm" />
                        </div>

                        {{-- Mini stats --}}
                        <div class="mt-4 grid grid-cols-2 gap-2">
                            <div class="rounded-xl bg-slate-50 dark:bg-zinc-800/50 px-3 py-2 text-center">
                                <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400">Attended</div>
                                <div class="teacher-num text-sm font-bold text-slate-900 dark:text-white mt-0.5">
                                    {{ $presentCount }}<span class="text-slate-400 dark:text-zinc-500">/{{ $totalRecords }}</span>
                                </div>
                            </div>
                            <div class="rounded-xl bg-slate-50 dark:bg-zinc-800/50 px-3 py-2 text-center">
                                <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400">Performance</div>
                                <div class="teacher-num text-sm font-bold {{ $rateTone }} mt-0.5">{{ $performanceText }}</div>
                            </div>
                        </div>

                        {{-- Attendance progress --}}
                        <div class="mt-3">
                            <div class="flex items-center justify-between text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400 mb-1.5">
                                <span>Attendance</span>
                                <span class="teacher-num {{ $rateTone }}">
                                    @if($totalRecords > 0)
                                        {{ $attendanceRate }}%
                                    @else
                                        No data
                                    @endif
                                </span>
                            </div>
                            <div class="h-1.5 rounded-full bg-slate-100 dark:bg-zinc-800 overflow-hidden">
                                <div style="width: {{ $totalRecords > 0 ? min(100, $attendanceRate) : 0 }}%" class="h-full rounded-full bg-gradient-to-r {{ $progressGradient }}"></div>
                            </div>
                        </div>

                        {{-- Status breakdown --}}
                        @if($totalRecords > 0)
                            <div class="mt-3 flex flex-wrap gap-1.5">
                                @if($presentCount > 0)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 px-2 py-0.5 text-[10px] font-semibold ring-1 ring-emerald-100 dark:ring-emerald-900/40">
                                        <flux:icon name="check-circle" class="w-3 h-3" />
                                        {{ $presentCount }} present
                                    </span>
                                @endif
                                @if($lateCount > 0)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-300 px-2 py-0.5 text-[10px] font-semibold ring-1 ring-amber-100 dark:ring-amber-900/40">
                                        <flux:icon name="clock" class="w-3 h-3" />
                                        {{ $lateCount }} late
                                    </span>
                                @endif
                                @if($absentCount > 0)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 dark:bg-rose-500/10 text-rose-700 dark:text-rose-300 px-2 py-0.5 text-[10px] font-semibold ring-1 ring-rose-100 dark:ring-rose-900/40">
                                        {{ $absentCount }} absent
                                    </span>
                                @endif
                                @if($excusedCount > 0)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-sky-50 dark:bg-sky-500/10 text-sky-700 dark:text-sky-300 px-2 py-0.5 text-[10px] font-semibold ring-1 ring-sky-100 dark:ring-sky-900/40">
                                        {{ $excusedCount }} excused
                                    </span>
                                @endif
                            </div>
                        @endif

                        {{-- Footer: enrolled date + view link --}}
                        <div class="mt-4 pt-3 border-t border-slate-100 dark:border-zinc-800 flex items-center justify-between gap-2">
                            <div class="flex items-center gap-1.5 text-xs text-slate-500 dark:text-zinc-400 min-w-0">
                                <flux:icon name="calendar" class="w-3.5 h-3.5 shrink-0" />
                                <span class="truncate">Joined {{ $classStudent->enrolled_at->diffForHumans() }}</span>
                            </div>
                            <a href="{{ route('teacher.students.show', $student) }}"
                               wire:navigate
                               class="inline-flex items-center gap-1 text-xs font-semibold text-violet-600 dark:text-violet-400 hover:text-violet-700 dark:hover:text-violet-300 whitespace-nowrap">
                                View
                                <flux:icon name="arrow-right" class="w-3.5 h-3.5" />
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- ─── ATTENDANCE BREAKDOWN ─── --}}
        @if($this->total_attendance_records > 0)
            <div>
                <div class="flex items-end justify-between mb-3">
                    <div>
                        <h2 class="teacher-display text-lg font-bold text-slate-900 dark:text-white">Class Attendance Summary</h2>
                        <p class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5">
                            Based on {{ $this->total_attendance_records }} attendance records
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    {{-- Present --}}
                    <div class="teacher-card p-4">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[10px] font-semibold uppercase tracking-wider text-emerald-700/80 dark:text-emerald-300/80">Present</span>
                            <div class="rounded-lg bg-emerald-500/10 dark:bg-emerald-400/15 p-1.5">
                                <flux:icon name="check-circle" class="w-4 h-4 text-emerald-600 dark:text-emerald-300" />
                            </div>
                        </div>
                        <div class="teacher-display teacher-num text-2xl font-bold text-slate-900 dark:text-white">{{ $this->total_present_count }}</div>
                        <div class="mt-1.5 text-xs text-emerald-700/80 dark:text-emerald-300/80 font-medium">
                            {{ round(($this->total_present_count / $this->total_attendance_records) * 100, 1) }}% of records
                        </div>
                    </div>

                    {{-- Late --}}
                    <div class="teacher-card p-4">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[10px] font-semibold uppercase tracking-wider text-amber-700/80 dark:text-amber-300/80">Late</span>
                            <div class="rounded-lg bg-amber-500/10 dark:bg-amber-400/15 p-1.5">
                                <flux:icon name="clock" class="w-4 h-4 text-amber-600 dark:text-amber-300" />
                            </div>
                        </div>
                        <div class="teacher-display teacher-num text-2xl font-bold text-slate-900 dark:text-white">{{ $this->total_late_count }}</div>
                        <div class="mt-1.5 text-xs text-amber-700/80 dark:text-amber-300/80 font-medium">
                            {{ round(($this->total_late_count / $this->total_attendance_records) * 100, 1) }}% of records
                        </div>
                    </div>

                    {{-- Absent --}}
                    <div class="teacher-card p-4">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[10px] font-semibold uppercase tracking-wider text-rose-700/80 dark:text-rose-300/80">Absent</span>
                            <div class="rounded-lg bg-rose-500/10 dark:bg-rose-400/15 p-1.5">
                                <flux:icon name="bolt" class="w-4 h-4 text-rose-600 dark:text-rose-300" />
                            </div>
                        </div>
                        <div class="teacher-display teacher-num text-2xl font-bold text-slate-900 dark:text-white">{{ $this->total_absent_count }}</div>
                        <div class="mt-1.5 text-xs text-rose-700/80 dark:text-rose-300/80 font-medium">
                            {{ round(($this->total_absent_count / $this->total_attendance_records) * 100, 1) }}% of records
                        </div>
                    </div>

                    {{-- Excused --}}
                    <div class="teacher-card p-4">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[10px] font-semibold uppercase tracking-wider text-sky-700/80 dark:text-sky-300/80">Excused</span>
                            <div class="rounded-lg bg-sky-500/10 dark:bg-sky-400/15 p-1.5">
                                <flux:icon name="sparkles" class="w-4 h-4 text-sky-600 dark:text-sky-300" />
                            </div>
                        </div>
                        <div class="teacher-display teacher-num text-2xl font-bold text-slate-900 dark:text-white">{{ $this->total_excused_count }}</div>
                        <div class="mt-1.5 text-xs text-sky-700/80 dark:text-sky-300/80 font-medium">
                            {{ round(($this->total_excused_count / $this->total_attendance_records) * 100, 1) }}% of records
                        </div>
                    </div>
                </div>

                {{-- Overall performance bar --}}
                <div class="teacher-card p-5 mt-4">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <div class="rounded-lg bg-violet-500/10 dark:bg-violet-400/15 p-1.5">
                                <flux:icon name="arrow-trending-up" class="w-4 h-4 text-violet-600 dark:text-violet-300" />
                            </div>
                            <div>
                                <div class="teacher-display font-bold text-slate-900 dark:text-white">Overall Performance</div>
                                <div class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5">
                                    Across {{ $this->completed_sessions_count }} completed {{ Str::plural('session', $this->completed_sessions_count) }}
                                </div>
                            </div>
                        </div>
                        <div class="teacher-display teacher-num text-2xl font-bold text-violet-600 dark:text-violet-300">{{ $this->overall_attendance_rate }}%</div>
                    </div>
                    <div class="h-2 rounded-full bg-slate-100 dark:bg-zinc-800 overflow-hidden">
                        <div class="h-full rounded-full bg-gradient-to-r from-violet-500 via-violet-500 to-emerald-500 transition-all duration-500"
                             style="width: {{ min(100, $this->overall_attendance_rate) }}%"></div>
                    </div>
                </div>
            </div>
        @endif
    @else
        {{-- ─── EMPTY STATE ─── --}}
        <x-teacher.empty-state
            icon="users"
            title="No students enrolled"
            message="Once students enroll in this class, you'll be able to track their attendance and monitor progress here."
        >
            <div class="flex flex-wrap items-center justify-center gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-violet-100 dark:bg-violet-500/15 text-violet-700 dark:text-violet-300 px-3 py-1 text-xs font-semibold ring-1 ring-violet-200 dark:ring-violet-900/40">
                    <flux:icon name="sparkles" class="w-3.5 h-3.5" />
                    {{ ucfirst($class->class_type) }} class
                </span>
                @if($class->max_capacity)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-300 px-3 py-1 text-xs font-semibold ring-1 ring-slate-200 dark:ring-zinc-700">
                        <flux:icon name="users" class="w-3.5 h-3.5" />
                        Max {{ $class->max_capacity }} students
                    </span>
                @endif
            </div>
        </x-teacher.empty-state>
    @endif
</div>
