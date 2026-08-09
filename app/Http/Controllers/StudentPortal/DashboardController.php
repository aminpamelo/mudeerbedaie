<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use App\Models\ClassAnnouncement;
use App\Models\ClassSession;
use App\Models\ClassStudent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $student = $request->user()->student;

        if (! $student) {
            return Inertia::render('Dashboard', [
                'greeting' => $this->greeting(),
                'dateLabel' => now()->format('l, F j, Y'),
                'todaySchedule' => [],
                'upcomingSchedule' => [],
                'ongoingSession' => null,
                'stats' => ['activeClasses' => 0, 'thisWeekSessions' => 0, 'completedSessions' => 0],
                'recentActivity' => [],
                'unreadAnnouncements' => [],
                'unreadAnnouncementCount' => 0,
            ]);
        }

        $activeClasses = $student->activeClasses()
            ->with(['course', 'teacher.user', 'timetable', 'sessions' => fn ($q) => $q->whereDate('session_date', today())])
            ->get();

        $activeClassIds = $activeClasses->pluck('id');

        $todaySchedule = $this->todaySchedule($activeClasses);
        $upcomingSchedule = $this->upcomingSchedule($activeClasses);

        $ongoingSession = ClassSession::whereIn('class_id', $activeClassIds)
            ->where('status', 'ongoing')
            ->with(['class.course', 'class.teacher.user'])
            ->first();

        $stats = [
            'activeClasses' => $activeClasses->count(),
            'thisWeekSessions' => $this->thisWeekCount($activeClasses),
            'completedSessions' => ClassSession::whereIn('class_id', $activeClassIds)
                ->whereIn('status', ['completed', 'no_show'])
                ->count(),
        ];

        $recentActivity = ClassSession::whereIn('class_id', $activeClassIds)
            ->whereIn('status', ['completed', 'no_show'])
            ->with('class.course')
            ->orderByDesc('completed_at')
            ->limit(5)
            ->get()
            ->map(fn ($s) => [
                'title' => $s->class->title,
                'description' => __('student.dashboard.session_completed'),
                'date' => ($s->completed_at ?? $s->session_date)?->toIso8601String(),
                'dateHuman' => ($s->completed_at ?? $s->session_date)?->diffForHumans(short: true),
            ]);

        // Unread announcements
        $classIds = ClassStudent::where('student_id', $student->id)
            ->where('status', 'active')
            ->pluck('class_id');

        $unreadAnnouncements = collect();
        $unreadCount = 0;

        if ($classIds->isNotEmpty()) {
            $baseQuery = ClassAnnouncement::whereIn('class_id', $classIds)
                ->where('published_at', '<=', now())
                ->whereDoesntHave('reads', fn ($q) => $q->where('student_id', $student->id));

            $unreadCount = $baseQuery->count();

            $unreadAnnouncements = (clone $baseQuery)
                ->with(['class', 'author'])
                ->orderByDesc('published_at')
                ->limit(3)
                ->get()
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'title' => $a->title,
                    'class_id' => $a->class_id,
                    'class_title' => $a->class?->title ?? 'Kelas',
                ]);
        }

        return Inertia::render('Dashboard', [
            'greeting' => $this->greeting(),
            'dateLabel' => now()->format('l, F j, Y'),
            'todaySchedule' => $todaySchedule->values(),
            'upcomingSchedule' => $upcomingSchedule->values(),
            'ongoingSession' => $ongoingSession ? [
                'id' => $ongoingSession->id,
                'classTitle' => $ongoingSession->class->title,
                'courseName' => $ongoingSession->class->course->name ?? '',
                'meetingUrl' => $ongoingSession->class->meeting_url,
            ] : null,
            'stats' => $stats,
            'recentActivity' => $recentActivity->values(),
            'unreadAnnouncements' => $unreadAnnouncements->values(),
            'unreadAnnouncementCount' => $unreadCount,
        ]);
    }

    /* ------------------------------------------------------------------
     |  Private helpers
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

    private function todaySchedule($classes): Collection
    {
        $schedule = collect();
        $today = Carbon::today();
        $todayName = strtolower($today->format('l'));

        foreach ($classes as $class) {
            $timetable = $class->timetable;

            if (! $timetable || ! $timetable->is_active || ! $timetable->isDateWithinRange($today)) {
                continue;
            }

            $timesForToday = $this->timesForDate($timetable, $today, $todayName);

            $todaySessions = $class->sessions->keyBy(fn ($s) => $s->session_time->format('H:i'));

            foreach ($timesForToday as $time) {
                $session = $todaySessions->get($time);

                $schedule->push([
                    'classId' => $class->id,
                    'classTitle' => $class->title,
                    'courseName' => $class->course->name,
                    'time' => $time,
                    'status' => $session ? $session->status : 'scheduled',
                    'isPast' => Carbon::createFromFormat('H:i', $time)->isPast(),
                ]);
            }
        }

        return $schedule->sortBy('time')->values();
    }

    private function upcomingSchedule($classes): Collection
    {
        $schedule = collect();
        $today = Carbon::today();

        for ($i = 1; $i <= 7; $i++) {
            $date = $today->copy()->addDays($i);
            $dayName = strtolower($date->format('l'));

            foreach ($classes as $class) {
                $timetable = $class->timetable;

                if (! $timetable || ! $timetable->is_active || ! $timetable->isDateWithinRange($date)) {
                    continue;
                }

                foreach ($this->timesForDate($timetable, $date, $dayName) as $time) {
                    $schedule->push([
                        'classId' => $class->id,
                        'classTitle' => $class->title,
                        'courseName' => $class->course->name ?? '',
                        'date' => $date->toDateString(),
                        'dateFormatted' => $date->format('M j'),
                        'dateMonth' => $date->format('M'),
                        'dateDay' => $date->format('j'),
                        'time' => $time,
                        'durationMinutes' => $class->duration_minutes,
                    ]);
                }
            }
        }

        return $schedule->sortBy([['date', 'asc'], ['time', 'asc']])->take(5)->values();
    }

    private function thisWeekCount($classes): int
    {
        $count = 0;
        $today = Carbon::today();
        $weekStart = $today->copy()->startOfWeek();
        $weekEnd = $today->copy()->endOfWeek();

        foreach ($classes as $class) {
            $timetable = $class->timetable;

            if (! $timetable || ! $timetable->is_active) {
                continue;
            }

            $currentDate = $weekStart->copy();
            while ($currentDate <= $weekEnd) {
                if ($timetable->isDateWithinRange($currentDate)) {
                    $dayName = strtolower($currentDate->format('l'));
                    $count += count($this->timesForDate($timetable, $currentDate, $dayName));
                }
                $currentDate->addDay();
            }
        }

        return $count;
    }

    /** Get the time-slot strings for a given date based on the timetable recurrence. */
    private function timesForDate($timetable, Carbon $date, string $dayName): array
    {
        if ($timetable->recurrence_pattern === 'monthly') {
            $weekOfMonth = $timetable->getWeekOfMonth($date);
            $weekKey = 'week_'.$weekOfMonth;

            return $timetable->weekly_schedule[$weekKey][$dayName] ?? [];
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
