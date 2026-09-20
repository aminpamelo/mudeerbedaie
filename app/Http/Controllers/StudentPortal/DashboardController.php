<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use App\Models\ClassAnnouncement;
use App\Models\ClassSession;
use App\Models\ClassStudent;
use App\Models\Course;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const MS_MONTHS = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Mac', 4 => 'April', 5 => 'Mei', 6 => 'Jun',
        7 => 'Julai', 8 => 'Ogos', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Disember',
    ];

    private const MS_DAYS = [
        'Sunday' => 'Ahad', 'Monday' => 'Isnin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
        'Thursday' => 'Khamis', 'Friday' => 'Jumaat', 'Saturday' => 'Sabtu',
    ];

    public function __invoke(Request $request): Response
    {
        $student = $request->user()->student;
        $today = Carbon::today();
        $monthLabel = self::MS_MONTHS[$today->month].' '.$today->year;

        if (! $student) {
            return Inertia::render('Dashboard', [
                'greeting' => $this->greeting(),
                'dateLabel' => $this->malayDate($today),
                'stats' => ['active' => 0, 'completed' => 0, 'tamat' => 0, 'total' => 0],
                'overallProgress' => 0,
                'activeClassProgress' => [],
                'nextClass' => null,
                'ongoingSession' => null,
                'announcements' => [],
                'calendar' => ['monthLabel' => $monthLabel, 'days' => $this->monthGrid($today, [])],
                'monthActivities' => [],
                'popularCourses' => $this->popularCourses(),
                'quote' => null,
            ]);
        }

        $activeClasses = $student->activeClasses()
            ->with(['course', 'teacher.user', 'timetable'])
            ->withCount([
                'sessions as total_sessions_count',
                'sessions as completed_sessions_count' => fn ($q) => $q->whereIn('status', ['completed', 'no_show']),
            ])
            ->get();

        $activeClassIds = $activeClasses->pluck('id');

        /* ---- progress ------------------------------------------------ */
        $totalAll = 0;
        $completedAll = 0;
        $progressList = collect();

        foreach ($activeClasses as $class) {
            $total = (int) $class->total_sessions_count;
            $done = (int) $class->completed_sessions_count;
            $totalAll += $total;
            $completedAll += $done;

            $progressList->push([
                'id' => $class->id,
                'title' => $class->title,
                'courseName' => $class->course?->name,
                'thumbnail' => $class->course?->thumbnail_url,
                'moduleDone' => $done,
                'moduleTotal' => $total,
                'progress' => $total > 0 ? (int) round($done / $total * 100) : 0,
            ]);
        }

        $overallProgress = $totalAll > 0 ? (int) round($completedAll / $totalAll * 100) : 0;

        /* ---- enrolment-status stats ---------------------------------- */
        $completedCount = ClassStudent::where('student_id', $student->id)->where('status', 'completed')->count();
        $tamatCount = ClassStudent::where('student_id', $student->id)->whereIn('status', ['transferred', 'quit'])->count();

        $stats = [
            'active' => $activeClasses->count(),
            'completed' => $completedCount,
            'tamat' => $tamatCount,
            'total' => $activeClasses->count() + $completedCount + $tamatCount,
        ];

        /* ---- ongoing / next class ------------------------------------ */
        $ongoingSession = ClassSession::whereIn('class_id', $activeClassIds)
            ->where('status', 'ongoing')
            ->with(['class.course'])
            ->first();

        $nextClass = $this->findNextClass($activeClasses);

        /* ---- month calendar + activities ----------------------------- */
        [$eventDates, $monthActivities] = $this->monthEvents($activeClasses, $activeClassIds, $today);

        /* ---- announcements ------------------------------------------- */
        $announcementColors = ['amber', 'sky', 'emerald', 'violet'];
        $announcements = ClassAnnouncement::whereIn('class_id', $activeClassIds)
            ->where('published_at', '<=', now())
            ->with('class')
            ->orderByDesc('published_at')
            ->limit(4)
            ->get()
            ->values()
            ->map(fn ($a, $i) => [
                'id' => $a->id,
                'classId' => $a->class_id,
                'title' => $a->title,
                'classTitle' => $a->class?->title,
                'dateLabel' => $this->malayDate($a->published_at, false),
                'color' => $announcementColors[$i % count($announcementColors)],
            ]);

        return Inertia::render('Dashboard', [
            'greeting' => $this->greeting(),
            'dateLabel' => $this->malayDate($today),
            'stats' => $stats,
            'overallProgress' => $overallProgress,
            'activeClassProgress' => $progressList->take(4)->values(),
            'nextClass' => $nextClass,
            'ongoingSession' => $ongoingSession ? [
                'classId' => $ongoingSession->class_id,
                'classTitle' => $ongoingSession->class->title,
                'meetingUrl' => $ongoingSession->class->meeting_url,
            ] : null,
            'announcements' => $announcements,
            'calendar' => [
                'monthLabel' => $monthLabel,
                'days' => $this->monthGrid($today, $eventDates),
            ],
            'monthActivities' => $monthActivities,
            'popularCourses' => $this->popularCourses(),
        ]);
    }

    /* ------------------------------------------------------------------
     |  Helpers
     | ------------------------------------------------------------------ */

    private function greeting(): string
    {
        $hour = now()->hour;

        if ($hour < 12) {
            return __('student.dashboard.greeting.morning');
        }

        if ($hour < 17) {
            return __('student.dashboard.greeting.afternoon');
        }

        return __('student.dashboard.greeting.evening');
    }

    private function malayDate(Carbon $date, bool $withDay = true): string
    {
        $core = $date->day.' '.self::MS_MONTHS[$date->month].' '.$date->year;

        return $withDay ? self::MS_DAYS[$date->format('l')].', '.$core : $core;
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

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\ClassModel>  $classes
     * @return array<string, mixed>|null
     */
    private function findNextClass(Collection $classes): ?array
    {
        $now = Carbon::now();

        for ($i = 0; $i <= 30; $i++) {
            $date = Carbon::today()->addDays($i);
            $dayName = strtolower($date->format('l'));
            $best = null;

            foreach ($classes as $class) {
                $timetable = $class->timetable;
                if (! $timetable || ! $timetable->is_active || ! $timetable->isDateWithinRange($date)) {
                    continue;
                }

                foreach ($this->timesForDate($timetable, $date, $dayName) as $time) {
                    $dt = Carbon::parse($date->toDateString().' '.$time);
                    if ($dt->lt($now)) {
                        continue;
                    }
                    if (! $best || $dt->lt($best['dt'])) {
                        $duration = $class->duration_minutes ?: 60;
                        $best = [
                            'dt' => $dt,
                            'classId' => $class->id,
                            'title' => $class->title,
                            'courseName' => $class->course?->name,
                            'teacherName' => $class->teacher?->user?->name,
                            'timeStart' => $time,
                            'timeEnd' => $dt->copy()->addMinutes($duration)->format('H:i'),
                        ];
                    }
                }
            }

            if ($best) {
                return [
                    'classId' => $best['classId'],
                    'title' => $best['title'],
                    'courseName' => $best['courseName'],
                    'teacherName' => $best['teacherName'],
                    'dateDay' => $best['dt']->day,
                    'dateMonth' => mb_substr(self::MS_MONTHS[$best['dt']->month], 0, 3),
                    'dayLabel' => self::MS_DAYS[$best['dt']->format('l')],
                    'timeRange' => $this->msTime($best['timeStart']).' – '.$this->msTime($best['timeEnd']),
                ];
            }
        }

        return null;
    }

    /**
     * Build the set of event dates for the month + a list of upcoming activities.
     *
     * @return array{0: array<string, bool>, 1: \Illuminate\Support\Collection<int, array<string, mixed>>}
     */
    private function monthEvents(Collection $classes, Collection $classIds, Carbon $today): array
    {
        $monthStart = $today->copy()->startOfMonth();
        $monthEnd = $today->copy()->endOfMonth();
        $eventDates = [];
        $activities = collect();

        for ($d = $monthStart->copy(); $d <= $monthEnd; $d->addDay()) {
            $dayName = strtolower($d->format('l'));

            foreach ($classes as $class) {
                $timetable = $class->timetable;
                if (! $timetable || ! $timetable->is_active || ! $timetable->isDateWithinRange($d)) {
                    continue;
                }

                foreach ($this->timesForDate($timetable, $d, $dayName) as $time) {
                    $eventDates[$d->toDateString()] = true;

                    if ($d->gte($today)) {
                        $activities->push([
                            'sort' => $d->toDateString().' '.$time,
                            'dateLabel' => $d->day.' '.mb_substr(self::MS_MONTHS[$d->month], 0, 3),
                            'title' => $class->title,
                            'time' => $this->msTime($time),
                        ]);
                    }
                }
            }
        }

        // Fold in real recorded sessions as calendar dots too.
        ClassSession::whereIn('class_id', $classIds)
            ->whereBetween('session_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->pluck('session_date')
            ->each(function ($date) use (&$eventDates) {
                $eventDates[Carbon::parse($date)->toDateString()] = true;
            });

        $activities = $activities->sortBy('sort')->take(5)->values()
            ->map(fn ($a) => ['dateLabel' => $a['dateLabel'], 'title' => $a['title'], 'time' => $a['time']]);

        return [$eventDates, $activities];
    }

    /**
     * A Monday-first 6-week grid for the given month.
     *
     * @param  array<string, bool>  $eventDates
     * @return array<int, array<string, mixed>>
     */
    private function monthGrid(Carbon $today, array $eventDates): array
    {
        $gridStart = $today->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $today->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $days = [];
        for ($d = $gridStart->copy(); $d <= $gridEnd; $d->addDay()) {
            $days[] = [
                'day' => $d->day,
                'date' => $d->toDateString(),
                'inMonth' => $d->month === $today->month,
                'isToday' => $d->isToday(),
                'hasEvent' => isset($eventDates[$d->toDateString()]),
            ];
        }

        return $days;
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function popularCourses(): Collection
    {
        return Course::where('status', 'active')
            ->where('show_on_storefront', true)
            ->withCount('enrollments')
            ->orderByDesc('enrollments_count')
            ->limit(4)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'thumbnail' => $c->thumbnail_url,
                'shortDescription' => $c->short_description,
            ]);
    }

    /** @return array<int, string> */
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
