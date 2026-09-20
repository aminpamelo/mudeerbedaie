<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use App\Models\ClassAnnouncement;
use App\Models\ClassAnnouncementRead;
use App\Models\ClassModel;
use App\Models\ClassResource;
use App\Models\ClassSession;
use App\Models\ClassStudent;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClassController extends Controller
{
    private const MS_MONTHS = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Mac', 4 => 'April', 5 => 'Mei', 6 => 'Jun',
        7 => 'Julai', 8 => 'Ogos', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Disember',
    ];

    private const MS_DAYS = [
        'Sunday' => 'Ahad', 'Monday' => 'Isnin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
        'Thursday' => 'Khamis', 'Friday' => 'Jumaat', 'Saturday' => 'Sabtu',
    ];

    public function index(Request $request): Response
    {
        $student = $request->user()->student;

        if (! $student) {
            return Inertia::render('Classes', [
                'stats' => ['active' => 0, 'completed' => 0, 'tamat' => 0, 'total' => 0, 'activeProgress' => 0],
                'aktif' => [],
                'lengkap' => [],
                'tamat' => [],
            ]);
        }

        $enrollments = ClassStudent::where('student_id', $student->id)
            ->with(['class' => function ($q) {
                $q->with(['course', 'teacher.user', 'timetable'])
                    ->withCount([
                        'sessions as total_sessions_count',
                        'sessions as completed_sessions_count' => fn ($s) => $s->whereIn('status', ['completed', 'no_show']),
                        'sessions as recorded_sessions_count' => fn ($s) => $s->whereNotNull('recording_url'),
                    ]);
            }])
            ->orderByDesc('enrolled_at')
            ->get();

        $aktif = collect();
        $lengkap = collect();
        $tamat = collect();
        $activeTotal = 0;
        $activeDone = 0;

        foreach ($enrollments as $cs) {
            $class = $cs->class;
            if (! $class) {
                continue;
            }

            $total = (int) $class->total_sessions_count;
            $done = (int) $class->completed_sessions_count;

            $base = [
                'classId' => $class->id,
                'title' => $class->title,
                'courseName' => $class->course?->name,
                'teacherName' => $class->teacher?->user?->name,
                'thumbnail' => $class->course?->thumbnail_url,
                'moduleDone' => $done,
                'moduleTotal' => $total,
                'progress' => $total > 0 ? (int) round($done / $total * 100) : 0,
                'hasRecording' => (int) $class->recorded_sessions_count > 0,
            ];

            if ($cs->status === 'active') {
                $activeTotal += $total;
                $activeDone += $done;
                $aktif->push($base + [
                    'startLabel' => $class->timetable?->start_date
                        ? 'Bermula '.$this->malayDate($class->timetable->start_date)
                        : null,
                    'scheduleLabel' => $this->scheduleLabel($class->timetable),
                ]);
            } elseif ($cs->status === 'completed') {
                $lengkap->push($base + ['endLabel' => $this->endLabel($cs, $class)]);
            } else {
                $tamat->push($base + [
                    'endLabel' => $this->endLabel($cs, $class),
                    'statusLabel' => $cs->status === 'transferred' ? 'Ditukar' : 'Tamat',
                ]);
            }
        }

        return Inertia::render('Classes', [
            'stats' => [
                'active' => $aktif->count(),
                'completed' => $lengkap->count(),
                'tamat' => $tamat->count(),
                'total' => $aktif->count() + $lengkap->count() + $tamat->count(),
                'activeProgress' => $activeTotal > 0 ? (int) round($activeDone / $activeTotal * 100) : 0,
            ],
            'aktif' => $aktif->values(),
            'lengkap' => $lengkap->values(),
            'tamat' => $tamat->values(),
        ]);
    }

    public function show(Request $request, ClassModel $class): Response
    {
        $student = $request->user()->student;

        if (! $student) {
            abort(403, 'You do not have access to this class.');
        }

        $classStudent = ClassStudent::where('class_id', $class->id)
            ->where('student_id', $student->id)
            ->first();

        if (! $classStudent) {
            abort(403, 'You do not have access to this class.');
        }

        $class->load(['course', 'teacher.user', 'timetable']);

        // Sessions with attendance for this student
        $sessions = ClassSession::where('class_id', $class->id)
            ->with(['attendances' => fn ($q) => $q->where('student_id', $student->id)])
            ->orderByDesc('session_date')
            ->get();

        $totalSessions = $sessions->count();
        $completedSessions = $sessions->whereIn('status', ['completed', 'no_show'])->count();

        $attended = $sessions->filter(fn ($s) => $s->attendances->where('status', 'present')->isNotEmpty())->count();
        $attendanceRate = $completedSessions > 0 ? round(($attended / $completedSessions) * 100) : 0;

        // Resources
        $resources = [];
        if (class_exists(ClassResource::class)) {
            $resources = ClassResource::where('class_id', $class->id)
                ->where('is_published', true)
                ->orderBy('sort_order')
                ->get()
                ->map(fn ($r) => [
                    'id' => $r->id,
                    'title' => $r->title,
                    'type' => $r->type,
                    'url' => $r->url,
                    'file_size' => $r->file_size,
                    'created_at' => $r->created_at->format('M j, Y'),
                ]);
        }

        // Announcements
        $classIds = [$class->id];
        $announcements = ClassAnnouncement::whereIn('class_id', $classIds)
            ->where('published_at', '<=', now())
            ->with('author')
            ->orderByDesc('published_at')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'body' => $a->body,
                'author_name' => $a->author?->name,
                'published_at' => $a->published_at->format('M j, Y'),
                'is_read' => ClassAnnouncementRead::where('announcement_id', $a->id)
                    ->where('student_id', $student->id)
                    ->exists(),
            ]);

        $unreadCount = $announcements->where('is_read', false)->count();

        // Week data for timetable tab
        $weekData = $this->buildWeekData($class, $sessions, Carbon::now());

        return Inertia::render('ClassShow', [
            'class' => [
                'id' => $class->id,
                'title' => $class->title,
                'description' => $class->description,
                'course_name' => $class->course?->name,
                'teacher_name' => $class->teacher?->user?->name,
                'meeting_url' => $class->meeting_url,
                'duration_minutes' => $class->duration_minutes,
                'enrollment_status' => $classStudent->status,
            ],
            'stats' => [
                'totalSessions' => $totalSessions,
                'completedSessions' => $completedSessions,
                'attendanceRate' => $attendanceRate,
                'attended' => $attended,
            ],
            'sessions' => $sessions->map(fn ($s) => [
                'id' => $s->id,
                'session_date' => $s->session_date->format('M j, Y'),
                'session_time' => $s->session_time?->format('g:i A'),
                'status' => $s->status,
                'duration_minutes' => $s->duration_minutes ?? $class->duration_minutes,
                'notes' => $s->notes,
                'attended' => $s->attendances->where('status', 'present')->isNotEmpty(),
                'recording_url' => $s->recording_url,
            ])->values(),
            'resources' => $resources,
            'announcements' => $announcements->values(),
            'unreadAnnouncementCount' => $unreadCount,
            'weekData' => $weekData,
        ]);
    }

    /** JSON endpoint for timetable week navigation in ClassShow */
    public function classTimetableSessions(Request $request, ClassModel $class): JsonResponse
    {
        $student = $request->user()->student;

        if (! $student) {
            abort(403);
        }

        $classStudent = ClassStudent::where('class_id', $class->id)
            ->where('student_id', $student->id)
            ->first();

        if (! $classStudent) {
            abort(403);
        }

        $date = Carbon::parse($request->input('date', now()));
        $sessions = ClassSession::where('class_id', $class->id)
            ->with(['attendances' => fn ($q) => $q->where('student_id', $student->id)])
            ->get();

        return response()->json($this->buildWeekData($class, $sessions, $date));
    }

    public function timetable(Request $request): Response
    {
        $student = $request->user()->student;

        if (! $student) {
            $now = Carbon::now();
            $weekStart = $now->copy()->startOfWeek();
            $weekEnd = $now->copy()->endOfWeek();

            return Inertia::render('Timetable', [
                'weekData' => $this->emptyWeekData($weekStart, $weekEnd),
                'periodLabel' => $weekStart->format('M j').' - '.$weekEnd->format('M j, Y'),
                'currentDate' => $now->toDateString(),
                'stats' => ['sessionsThisWeek' => 0, 'upcomingSessions' => 0],
                'classOptions' => [],
            ]);
        }

        $activeClasses = $student->activeClasses()
            ->with(['course', 'teacher.user', 'timetable'])
            ->get();

        $activeClassIds = $activeClasses->pluck('id');

        $now = Carbon::now();
        $weekStart = $now->copy()->startOfWeek();
        $weekEnd = $now->copy()->endOfWeek();

        $sessions = ClassSession::whereIn('class_id', $activeClassIds)
            ->with(['class.course', 'class.teacher.user', 'attendances' => fn ($q) => $q->where('student_id', $student->id)])
            ->get();

        $weekData = [];
        for ($d = $weekStart->copy(); $d <= $weekEnd; $d->addDay()) {
            $dayName = strtolower($d->format('l'));
            $daySessions = $sessions->filter(fn ($s) => $s->session_date->isSameDay($d));

            // Merge with scheduled slots from all timetables
            $scheduledSlots = [];
            foreach ($activeClasses as $class) {
                $timetable = $class->timetable;
                if (! $timetable || ! $timetable->is_active || ! $timetable->isDateWithinRange($d)) {
                    continue;
                }

                $times = $this->timesForDate($timetable, $d, $dayName);
                foreach ($times as $time) {
                    $existing = $daySessions->first(fn ($s) => $s->session_time?->format('H:i') === $time);
                    if (! $existing) {
                        $scheduledSlots[] = [
                            'time' => $time,
                            'classTitle' => $class->title,
                            'courseName' => $class->course?->name,
                            'status' => 'scheduled',
                            'isSlot' => true,
                        ];
                    }
                }
            }

            $dayItems = $daySessions->map(fn ($s) => [
                'id' => $s->id,
                'time' => $s->session_time?->format('H:i'),
                'classTitle' => $s->class->title,
                'courseName' => $s->class->course?->name ?? '',
                'teacherName' => $s->class->teacher?->user?->name ?? '',
                'status' => $s->status,
                'durationMinutes' => $s->duration_minutes,
                'notes' => $s->notes,
                'attended' => $s->attendances->where('status', 'present')->isNotEmpty(),
                'isSlot' => false,
            ])->values()->toArray();

            $allItems = array_merge($dayItems, $scheduledSlots);
            usort($allItems, fn ($a, $b) => strcmp($a['time'] ?? '', $b['time'] ?? ''));

            $weekData[] = [
                'date' => $d->toDateString(),
                'dayName' => $d->format('D'),
                'dayNumber' => $d->format('j'),
                'isToday' => $d->isToday(),
                'items' => $allItems,
            ];
        }

        $sessionsThisWeek = $sessions->filter(fn ($s) => $s->session_date->between($weekStart, $weekEnd))->count();
        $upcomingSessions = $sessions->filter(fn ($s) => $s->session_date->isAfter(now()) && $s->status === 'scheduled')->count();

        $classOptions = $activeClasses->map(fn ($c) => ['id' => $c->id, 'title' => $c->title])->values();

        return Inertia::render('Timetable', [
            'weekData' => $weekData,
            'periodLabel' => $weekStart->format('M j').' - '.$weekEnd->format('M j, Y'),
            'currentDate' => $now->toDateString(),
            'stats' => [
                'sessionsThisWeek' => $sessionsThisWeek,
                'upcomingSessions' => $upcomingSessions,
            ],
            'classOptions' => $classOptions,
        ]);
    }

    /** JSON endpoint for timetable week navigation */
    public function timetableSessions(Request $request): JsonResponse
    {
        $student = $request->user()->student;
        $date = Carbon::parse($request->input('date', now()));
        $weekStart = $date->copy()->startOfWeek();
        $weekEnd = $date->copy()->endOfWeek();

        if (! $student) {
            return response()->json([
                'weekData' => $this->emptyWeekData($weekStart, $weekEnd),
                'periodLabel' => $weekStart->format('M j').' - '.$weekEnd->format('M j, Y'),
            ]);
        }

        $activeClasses = $student->activeClasses()
            ->with(['course', 'teacher.user', 'timetable'])
            ->get();

        $activeClassIds = $activeClasses->pluck('id');
        $classFilter = $request->input('class');

        if ($classFilter && $classFilter !== 'all') {
            $activeClassIds = $activeClassIds->filter(fn ($id) => $id == $classFilter);
        }

        $sessions = ClassSession::whereIn('class_id', $activeClassIds)
            ->with(['class.course', 'class.teacher.user', 'attendances' => fn ($q) => $q->where('student_id', $student->id)])
            ->get();

        $weekData = [];
        for ($d = $weekStart->copy(); $d <= $weekEnd; $d->addDay()) {
            $dayName = strtolower($d->format('l'));
            $daySessions = $sessions->filter(fn ($s) => $s->session_date->isSameDay($d));

            $scheduledSlots = [];
            foreach ($activeClasses as $class) {
                if ($classFilter && $classFilter !== 'all' && $class->id != $classFilter) {
                    continue;
                }
                $timetable = $class->timetable;
                if (! $timetable || ! $timetable->is_active || ! $timetable->isDateWithinRange($d)) {
                    continue;
                }
                foreach ($this->timesForDate($timetable, $d, $dayName) as $time) {
                    $existing = $daySessions->first(fn ($s) => $s->session_time?->format('H:i') === $time);
                    if (! $existing) {
                        $scheduledSlots[] = [
                            'time' => $time,
                            'classTitle' => $class->title,
                            'courseName' => $class->course?->name,
                            'status' => 'scheduled',
                            'isSlot' => true,
                        ];
                    }
                }
            }

            $dayItems = $daySessions->map(fn ($s) => [
                'id' => $s->id,
                'time' => $s->session_time?->format('H:i'),
                'classTitle' => $s->class->title,
                'courseName' => $s->class->course?->name ?? '',
                'teacherName' => $s->class->teacher?->user?->name ?? '',
                'status' => $s->status,
                'durationMinutes' => $s->duration_minutes,
                'notes' => $s->notes,
                'attended' => $s->attendances->where('status', 'present')->isNotEmpty(),
                'isSlot' => false,
            ])->values()->toArray();

            $allItems = array_merge($dayItems, $scheduledSlots);
            usort($allItems, fn ($a, $b) => strcmp($a['time'] ?? '', $b['time'] ?? ''));

            $weekData[] = [
                'date' => $d->toDateString(),
                'dayName' => $d->format('D'),
                'dayNumber' => $d->format('j'),
                'isToday' => $d->isToday(),
                'items' => $allItems,
            ];
        }

        return response()->json([
            'weekData' => $weekData,
            'periodLabel' => $weekStart->format('M j').' - '.$weekEnd->format('M j, Y'),
        ]);
    }

    /* ------------------------------------------------------------------
     |  Private helpers
     | ------------------------------------------------------------------ */

    /**
     * Build a blank 7-day week (no sessions/slots) for students without a profile.
     *
     * @return array<int, array<string, mixed>>
     */
    private function emptyWeekData(Carbon $weekStart, Carbon $weekEnd): array
    {
        $weekData = [];
        for ($d = $weekStart->copy(); $d <= $weekEnd; $d->addDay()) {
            $weekData[] = [
                'date' => $d->toDateString(),
                'dayName' => $d->format('D'),
                'dayNumber' => $d->format('j'),
                'isToday' => $d->isToday(),
                'items' => [],
            ];
        }

        return $weekData;
    }

    private function buildWeekData(ClassModel $class, $sessions, Carbon $date): array
    {
        $weekStart = $date->copy()->startOfWeek();
        $weekEnd = $date->copy()->endOfWeek();
        $timetable = $class->timetable;

        $weekData = [];
        for ($d = $weekStart->copy(); $d <= $weekEnd; $d->addDay()) {
            $dayName = strtolower($d->format('l'));
            $daySessions = $sessions->filter(fn ($s) => $s->session_date->isSameDay($d));

            $scheduledSlots = [];
            if ($timetable && $timetable->is_active && $timetable->isDateWithinRange($d)) {
                foreach ($this->timesForDate($timetable, $d, $dayName) as $time) {
                    $existing = $daySessions->first(fn ($s) => $s->session_time?->format('H:i') === $time);
                    if (! $existing) {
                        $scheduledSlots[] = ['time' => $time, 'status' => 'scheduled', 'isSlot' => true];
                    }
                }
            }

            $dayItems = $daySessions->map(fn ($s) => [
                'id' => $s->id,
                'time' => $s->session_time?->format('H:i'),
                'status' => $s->status,
                'durationMinutes' => $s->duration_minutes ?? $class->duration_minutes,
                'notes' => $s->notes,
                'attended' => $s->attendances?->where('status', 'present')->isNotEmpty() ?? false,
                'isSlot' => false,
            ])->values()->toArray();

            $allItems = array_merge($dayItems, $scheduledSlots);
            usort($allItems, fn ($a, $b) => strcmp($a['time'] ?? '', $b['time'] ?? ''));

            $weekData[] = [
                'date' => $d->toDateString(),
                'dayName' => $d->format('D'),
                'dayNumber' => $d->format('j'),
                'isToday' => $d->isToday(),
                'items' => $allItems,
            ];
        }

        return $weekData;
    }

    private function malayDate($date): string
    {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date);

        return $date->day.' '.self::MS_MONTHS[$date->month].' '.$date->year;
    }

    private function msTime(string $hi): string
    {
        [$h, $m] = array_map('intval', array_pad(explode(':', $hi), 2, 0));
        $period = match (true) {
            $h < 5 => 'malam',
            $h < 12 => 'pagi',
            $h === 12 => 'tengah hari',
            $h < 19 => 'petang',
            default => 'malam',
        };
        $h12 = $h % 12 === 0 ? 12 : $h % 12;

        return $h12.'.'.str_pad((string) $m, 2, '0', STR_PAD_LEFT).' '.$period;
    }

    private function endLabel(ClassStudent $cs, ClassModel $class): ?string
    {
        $date = $cs->left_at ?? $class->timetable?->end_date;

        return $date ? 'Tamat pada '.$this->malayDate($date) : null;
    }

    private function scheduleLabel($timetable): ?string
    {
        if (! $timetable) {
            return null;
        }

        $schedule = $timetable->weekly_schedule ?? [];
        $dayTimes = [];

        if ($timetable->recurrence_pattern === 'monthly') {
            foreach ($schedule as $days) {
                if (! is_array($days)) {
                    continue;
                }
                foreach ($days as $day => $times) {
                    if (! empty($times) && ! isset($dayTimes[$day])) {
                        $dayTimes[$day] = $times[0];
                    }
                }
            }
        } else {
            foreach ($schedule as $day => $times) {
                if (is_array($times) && ! empty($times)) {
                    $dayTimes[$day] = $times[0];
                }
            }
        }

        if (empty($dayTimes)) {
            return null;
        }

        $order = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        uksort($dayTimes, fn ($a, $b) => array_search($a, $order) <=> array_search($b, $order));

        $dayNames = array_map(fn ($d) => self::MS_DAYS[ucfirst($d)] ?? ucfirst($d), array_keys($dayTimes));
        $firstTime = reset($dayTimes);
        $prefix = $timetable->recurrence_pattern === 'bi_weekly' ? 'Dua minggu sekali,' : 'Setiap';

        return $prefix.' '.implode(', ', $dayNames).', '.$this->msTime($firstTime);
    }

    private function timesForDate($timetable, Carbon $date, string $dayName): array
    {
        if ($timetable->recurrence_pattern === 'monthly') {
            $weekOfMonth = $timetable->getWeekOfMonth($date);

            return $timetable->weekly_schedule['week_'.$weekOfMonth][$dayName] ?? [];
        }

        $times = $timetable->weekly_schedule[$dayName] ?? [];

        if ($timetable->recurrence_pattern === 'bi_weekly') {
            $weeksSinceStart = $timetable->start_date->diffInWeeks($date);
            if ($weeksSinceStart % 2 !== 0) {
                return [];
            }
        }

        return $times;
    }
}
