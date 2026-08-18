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
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class ClassController extends Controller
{
    public function index(Request $request): Response
    {
        $student = $request->user()->student;

        if (! $student) {
            return Inertia::render('Classes', [
                'classStudents' => new LengthAwarePaginator([], 0, 12, 1, ['path' => $request->url()]),
                'courses' => [],
                'statusCounts' => ['active' => 0, 'completed' => 0, 'transferred' => 0, 'quit' => 0],
                'totalClasses' => 0,
                'filters' => [
                    'search' => $request->input('search', ''),
                    'status' => $request->input('status', ''),
                    'course' => $request->input('course', ''),
                ],
            ]);
        }

        $query = ClassStudent::where('student_id', $student->id)
            ->with(['class.course', 'class.teacher.user', 'class.sessions'])
            ->when($request->input('search'), function ($q, $search) {
                $q->whereHas('class', function ($cq) use ($search) {
                    $cq->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('course', fn ($c) => $c->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('teacher.user', fn ($t) => $t->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('course'), function ($q, $courseId) {
                $q->whereHas('class', fn ($c) => $c->where('course_id', $courseId));
            })
            ->orderByDesc('enrolled_at');

        $classStudents = $query->paginate(12)->withQueryString();

        $classStudents->getCollection()->transform(function (ClassStudent $cs) {
            $class = $cs->class;
            $totalSessions = $class->sessions->count();
            $completedSessions = $class->sessions->whereIn('status', ['completed', 'no_show'])->count();

            return [
                'id' => $cs->id,
                'class_id' => $class->id,
                'title' => $class->title,
                'course_name' => $class->course?->name,
                'teacher_name' => $class->teacher?->user?->name,
                'status' => $cs->status,
                'total_sessions' => $totalSessions,
                'completed_sessions' => $completedSessions,
                'progress' => $totalSessions > 0 ? round(($completedSessions / $totalSessions) * 100) : 0,
            ];
        });

        $courses = ClassModel::whereHas('classStudents', fn ($q) => $q->where('student_id', $student->id))
            ->with('course')
            ->get()
            ->pluck('course')
            ->filter()
            ->unique('id')
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])
            ->values();

        $statusCounts = [
            'active' => ClassStudent::where('student_id', $student->id)->where('status', 'active')->count(),
            'completed' => ClassStudent::where('student_id', $student->id)->where('status', 'completed')->count(),
            'transferred' => ClassStudent::where('student_id', $student->id)->where('status', 'transferred')->count(),
            'quit' => ClassStudent::where('student_id', $student->id)->where('status', 'quit')->count(),
        ];

        return Inertia::render('Classes', [
            'classStudents' => $classStudents,
            'courses' => $courses,
            'statusCounts' => $statusCounts,
            'totalClasses' => array_sum($statusCounts),
            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $request->input('status', ''),
                'course' => $request->input('course', ''),
            ],
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
