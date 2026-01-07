<?php

namespace App\Services;

use App\Models\ClassModel;
use App\Models\ClassNotificationSetting;
use App\Models\ClassSession;
use App\Models\ClassTimetable;
use App\Models\NotificationTemplate;
use App\Models\ScheduledNotification;
use App\Models\Student;
use App\Models\Teacher;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class NotificationService
{
    public function replacePlaceholders(
        string $content,
        ClassSession $session,
        ?Student $student = null,
        ?Teacher $teacher = null
    ): string {
        $class = $session->class;
        $course = $class->course;
        $classTeacher = $teacher ?? $class->teacher;

        $replacements = [
            '{{student_name}}' => $student?->user?->name ?? '',
            '{{teacher_name}}' => $classTeacher?->user?->name ?? '',
            '{{class_name}}' => $class->title,
            '{{course_name}}' => $course?->name ?? '',
            '{{session_date}}' => $session->session_date->format('d M Y'),
            '{{session_time}}' => $session->session_time->format('g:i A'),
            '{{session_datetime}}' => $session->formatted_date_time,
            '{{location}}' => $class->location ?? 'TBA',
            '{{meeting_url}}' => $class->meeting_url ?? '',
            '{{whatsapp_link}}' => $class->whatsapp_group_link ?? '',
            '{{duration}}' => $session->formatted_duration,
            '{{remaining_sessions}}' => (string) $class->upcoming_sessions,
            '{{total_sessions}}' => (string) $class->total_sessions,
            '{{attendance_rate}}' => $student ? $this->calculateAttendanceRate($student, $class).'%' : '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    public function replacePlaceholdersForClass(
        string $content,
        ClassModel $class,
        ?Student $student = null,
        ?Teacher $teacher = null
    ): string {
        $course = $class->course;
        $classTeacher = $teacher ?? $class->teacher;

        $replacements = [
            '{{student_name}}' => $student?->user?->name ?? '',
            '{{teacher_name}}' => $classTeacher?->user?->name ?? '',
            '{{class_name}}' => $class->title,
            '{{course_name}}' => $course?->name ?? '',
            '{{session_date}}' => '',
            '{{session_time}}' => '',
            '{{session_datetime}}' => '',
            '{{location}}' => $class->location ?? 'TBA',
            '{{meeting_url}}' => $class->meeting_url ?? '',
            '{{whatsapp_link}}' => $class->whatsapp_group_link ?? '',
            '{{duration}}' => $class->formatted_duration,
            '{{remaining_sessions}}' => (string) $class->upcoming_sessions,
            '{{total_sessions}}' => (string) $class->total_sessions,
            '{{attendance_rate}}' => $student ? $this->calculateAttendanceRate($student, $class).'%' : '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    public function getRecipients(ClassNotificationSetting $setting): Collection
    {
        $recipients = collect();
        $class = $setting->class;

        if ($setting->send_to_students) {
            $students = $class->activeStudents()
                ->with('student.user')
                ->get()
                ->pluck('student');

            foreach ($students as $student) {
                if ($student?->user?->email) {
                    $recipients->push([
                        'type' => 'student',
                        'model' => $student,
                        'email' => $student->user->email,
                        'name' => $student->user->name,
                    ]);
                }
            }
        }

        if ($setting->send_to_teacher && $class->teacher) {
            $teacher = $class->teacher;
            if ($teacher->user?->email) {
                $recipients->push([
                    'type' => 'teacher',
                    'model' => $teacher,
                    'email' => $teacher->user->email,
                    'name' => $teacher->user->name,
                ]);
            }
        }

        return $recipients;
    }

    public function scheduleSessionNotifications(ClassSession $session): array
    {
        $scheduled = [];
        $class = $session->class;

        $settings = $class->enabledNotificationSettings()
            ->where('notification_type', 'like', 'session_reminder_%')
            ->get();

        foreach ($settings as $setting) {
            $scheduledAt = $this->calculateScheduledTime($session, $setting);

            if ($scheduledAt && $scheduledAt->isFuture()) {
                // Check if already scheduled
                $exists = ScheduledNotification::where('session_id', $session->id)
                    ->where('class_notification_setting_id', $setting->id)
                    ->whereIn('status', ['pending', 'processing'])
                    ->exists();

                if (! $exists) {
                    $notification = ScheduledNotification::create([
                        'class_id' => $class->id,
                        'session_id' => $session->id,
                        'class_notification_setting_id' => $setting->id,
                        'status' => 'pending',
                        'scheduled_at' => $scheduledAt,
                        'total_recipients' => $this->getRecipients($setting)->count(),
                    ]);

                    $scheduled[] = $notification;
                }
            }
        }

        return $scheduled;
    }

    public function scheduleFollowupNotifications(ClassSession $session): array
    {
        $scheduled = [];
        $class = $session->class;

        $settings = $class->enabledNotificationSettings()
            ->where('notification_type', 'like', 'session_followup_%')
            ->get();

        foreach ($settings as $setting) {
            $scheduledAt = $this->calculateFollowupTime($session, $setting);

            if ($scheduledAt) {
                // Check if already scheduled
                $exists = ScheduledNotification::where('session_id', $session->id)
                    ->where('class_notification_setting_id', $setting->id)
                    ->whereIn('status', ['pending', 'processing'])
                    ->exists();

                if (! $exists) {
                    $notification = ScheduledNotification::create([
                        'class_id' => $class->id,
                        'session_id' => $session->id,
                        'class_notification_setting_id' => $setting->id,
                        'status' => 'pending',
                        'scheduled_at' => $scheduledAt,
                        'total_recipients' => $this->getRecipients($setting)->count(),
                    ]);

                    $scheduled[] = $notification;
                }
            }
        }

        return $scheduled;
    }

    /**
     * Schedule notifications based on class timetable (without pre-generated sessions).
     * This generates future session slots from the timetable and schedules reminders.
     *
     * @param  int  $daysAhead  How many days ahead to schedule notifications
     */
    public function scheduleNotificationsFromTimetable(ClassModel $class, int $daysAhead = 7): array
    {
        $scheduled = [];
        $timetable = $class->timetable;

        if (! $timetable || ! $timetable->is_active) {
            return $scheduled;
        }

        // Get enabled reminder settings for this class
        $settings = $class->enabledNotificationSettings()
            ->where('notification_type', 'like', 'session_reminder_%')
            ->get();

        if ($settings->isEmpty()) {
            return $scheduled;
        }

        // Generate upcoming session slots from timetable
        $upcomingSlots = $this->generateUpcomingSessionSlots($timetable, $daysAhead);

        foreach ($upcomingSlots as $slot) {
            $sessionDate = Carbon::parse($slot['session_date']);
            $sessionTime = $slot['session_time'];
            $sessionDateTime = Carbon::parse($slot['session_date'].' '.$sessionTime);

            foreach ($settings as $setting) {
                $scheduledAt = $sessionDateTime->copy()->subMinutes($setting->getMinutesBefore());

                // Only schedule if the notification time is in the future
                if ($scheduledAt->isFuture()) {
                    // Check if already scheduled for this date/time/setting combo
                    // Use scheduled_at (datetime) for uniqueness check to avoid timezone issues with date-only comparison
                    $exists = ScheduledNotification::where('class_id', $class->id)
                        ->where('scheduled_at', $scheduledAt)
                        ->where('class_notification_setting_id', $setting->id)
                        ->whereIn('status', ['pending', 'processing'])
                        ->exists();

                    if (! $exists) {
                        $notification = ScheduledNotification::create([
                            'class_id' => $class->id,
                            'session_id' => null, // No session yet - timetable-based
                            'scheduled_session_date' => $sessionDate,
                            'scheduled_session_time' => $sessionTime,
                            'class_notification_setting_id' => $setting->id,
                            'status' => 'pending',
                            'scheduled_at' => $scheduledAt,
                            'total_recipients' => $this->getRecipients($setting)->count(),
                        ]);

                        $scheduled[] = $notification;
                    }
                }
            }
        }

        return $scheduled;
    }

    /**
     * Generate upcoming session slots from a timetable for the next N days.
     */
    public function generateUpcomingSessionSlots(ClassTimetable $timetable, int $daysAhead = 7): array
    {
        if (! $timetable->weekly_schedule || empty($timetable->weekly_schedule)) {
            return [];
        }

        $slots = [];
        $startDate = Carbon::today();
        $endDate = Carbon::today()->addDays($daysAhead);

        // Respect timetable's end_date if set
        if ($timetable->end_date && $timetable->end_date->lt($endDate)) {
            $endDate = $timetable->end_date;
        }

        // Don't generate slots before timetable start_date
        if ($timetable->start_date && $timetable->start_date->gt($startDate)) {
            $startDate = $timetable->start_date;
        }

        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $dayOfWeek = strtolower($currentDate->format('l'));
            $timesForDay = [];

            if ($timetable->recurrence_pattern === 'monthly') {
                $weekOfMonth = $timetable->getWeekOfMonth($currentDate);
                $weekKey = 'week_'.$weekOfMonth;

                if (isset($timetable->weekly_schedule[$weekKey][$dayOfWeek])) {
                    $timesForDay = $timetable->weekly_schedule[$weekKey][$dayOfWeek];
                }
            } else {
                if (isset($timetable->weekly_schedule[$dayOfWeek])) {
                    $timesForDay = $timetable->weekly_schedule[$dayOfWeek];
                }
            }

            foreach ($timesForDay as $time) {
                $sessionDateTime = Carbon::parse($currentDate->toDateString().' '.$time);

                // Only include future sessions
                if ($sessionDateTime->isFuture()) {
                    $slots[] = [
                        'class_id' => $timetable->class_id,
                        'session_date' => $currentDate->toDateString(),
                        'session_time' => $time,
                    ];
                }
            }

            // Handle bi-weekly pattern
            if ($timetable->recurrence_pattern === 'bi_weekly' && $currentDate->dayOfWeek === 0) {
                $currentDate->addWeek();
            }

            $currentDate->addDay();
        }

        return $slots;
    }

    /**
     * Replace placeholders for timetable-based notifications (no session object).
     */
    public function replacePlaceholdersForTimetable(
        string $content,
        ClassModel $class,
        Carbon $sessionDate,
        string $sessionTime,
        ?Student $student = null,
        ?Teacher $teacher = null
    ): string {
        $course = $class->course;
        $classTeacher = $teacher ?? $class->teacher;
        $sessionDateTime = Carbon::parse($sessionDate->toDateString().' '.$sessionTime);

        $replacements = [
            '{{student_name}}' => $student?->user?->name ?? '',
            '{{teacher_name}}' => $classTeacher?->user?->name ?? '',
            '{{class_name}}' => $class->title,
            '{{course_name}}' => $course?->name ?? '',
            '{{session_date}}' => $sessionDate->format('d M Y'),
            '{{session_time}}' => $sessionDateTime->format('g:i A'),
            '{{session_datetime}}' => $sessionDate->format('d M Y').' '.$sessionDateTime->format('g:i A'),
            '{{location}}' => $class->location ?? 'TBA',
            '{{meeting_url}}' => $class->meeting_url ?? '',
            '{{whatsapp_link}}' => $class->whatsapp_group_link ?? '',
            '{{duration}}' => $class->formatted_duration ?? '',
            '{{remaining_sessions}}' => '',
            '{{total_sessions}}' => (string) ($class->timetable?->total_sessions ?? ''),
            '{{attendance_rate}}' => $student ? $this->calculateAttendanceRate($student, $class).'%' : '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    private function calculateScheduledTime(ClassSession $session, ClassNotificationSetting $setting): ?\Carbon\Carbon
    {
        $sessionDateTime = $session->getSessionDateTime();

        return $sessionDateTime->copy()->subMinutes($setting->getMinutesBefore());
    }

    private function calculateFollowupTime(ClassSession $session, ClassNotificationSetting $setting): ?\Carbon\Carbon
    {
        // For followups, use the completed_at time if available, otherwise session datetime
        $baseTime = $session->completed_at ?? $session->getSessionDateTime();

        return $baseTime->copy()->addMinutes($setting->getMinutesAfter());
    }

    private function calculateAttendanceRate(Student $student, ClassModel $class): float
    {
        $totalAttendances = $class->attendances()
            ->where('student_id', $student->id)
            ->count();

        $presentAttendances = $class->attendances()
            ->where('student_id', $student->id)
            ->whereIn('status', ['present', 'late'])
            ->count();

        return $totalAttendances > 0
            ? round(($presentAttendances / $totalAttendances) * 100, 1)
            : 0;
    }

    public function cancelSessionNotifications(ClassSession $session): int
    {
        return $session->pendingNotifications()->update(['status' => 'cancelled']);
    }

    public static function getDefaultTemplateForType(string $notificationType, string $language = 'ms'): ?NotificationTemplate
    {
        $type = match (true) {
            str_starts_with($notificationType, 'session_reminder_') => 'session_reminder',
            str_starts_with($notificationType, 'session_followup_') => 'session_followup',
            $notificationType === 'enrollment_welcome' => 'enrollment_welcome',
            $notificationType === 'class_completed' => 'class_completed',
            default => null,
        };

        if (! $type) {
            return null;
        }

        return NotificationTemplate::active()
            ->where('type', $type)
            ->where('language', $language)
            ->first();
    }
}
