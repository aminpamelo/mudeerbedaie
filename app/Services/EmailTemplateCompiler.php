<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ClassModel;
use App\Models\ClassSession;
use App\Models\Student;
use App\Models\Teacher;
use Carbon\Carbon;

class EmailTemplateCompiler
{
    /**
     * Replace placeholders in HTML content with actual values.
     */
    public function replacePlaceholders(
        string $content,
        ClassSession $session,
        ?Student $student = null,
        ?Teacher $teacher = null
    ): string {
        $class = $session->class;
        $course = $class->course;

        $placeholders = [
            '{{student_name}}' => $student?->name ?? 'Pelajar',
            '{{teacher_name}}' => $teacher?->name ?? ($class->teacher?->name ?? 'Guru'),
            '{{class_name}}' => $class->title,
            '{{course_name}}' => $course?->name ?? 'N/A',
            '{{session_date}}' => Carbon::parse($session->session_date)->format('d M Y'),
            '{{session_time}}' => Carbon::parse($session->start_time)->format('g:i A'),
            '{{session_datetime}}' => Carbon::parse($session->session_date)->format('d M Y').', '.Carbon::parse($session->start_time)->format('g:i A'),
            '{{location}}' => $class->location ?? 'Akan dimaklumkan',
            '{{meeting_url}}' => $class->meeting_url ?? '#',
            '{{whatsapp_link}}' => $class->whatsapp_link ?? '#',
            '{{duration}}' => $this->calculateDuration($session),
            '{{remaining_sessions}}' => (string) $this->getRemainingSessionCount($class),
            '{{total_sessions}}' => (string) $class->total_sessions,
            '{{attendance_rate}}' => $student ? (string) $this->getAttendanceRate($student, $class) : '0',
        ];

        return str_replace(array_keys($placeholders), array_values($placeholders), $content);
    }

    /**
     * Replace placeholders for class-only context (no specific session).
     */
    public function replacePlaceholdersForClass(
        string $content,
        ClassModel $class,
        ?Student $student = null,
        ?Teacher $teacher = null
    ): string {
        $course = $class->course;

        $placeholders = [
            '{{student_name}}' => $student?->name ?? 'Pelajar',
            '{{teacher_name}}' => $teacher?->name ?? ($class->teacher?->name ?? 'Guru'),
            '{{class_name}}' => $class->title,
            '{{course_name}}' => $course?->name ?? 'N/A',
            '{{session_date}}' => 'N/A',
            '{{session_time}}' => 'N/A',
            '{{session_datetime}}' => 'N/A',
            '{{location}}' => $class->location ?? 'Akan dimaklumkan',
            '{{meeting_url}}' => $class->meeting_url ?? '#',
            '{{whatsapp_link}}' => $class->whatsapp_link ?? '#',
            '{{duration}}' => 'N/A',
            '{{remaining_sessions}}' => (string) $this->getRemainingSessionCount($class),
            '{{total_sessions}}' => (string) $class->total_sessions,
            '{{attendance_rate}}' => $student ? (string) $this->getAttendanceRate($student, $class) : '0',
        ];

        return str_replace(array_keys($placeholders), array_values($placeholders), $content);
    }

    /**
     * Replace placeholders for timetable-based notifications.
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
        $timetable = $class->timetable;

        // Parse time safely
        $parsedTime = $this->parseTime($sessionTime);

        $placeholders = [
            '{{student_name}}' => $student?->name ?? 'Pelajar',
            '{{teacher_name}}' => $teacher?->name ?? ($class->teacher?->name ?? 'Guru'),
            '{{class_name}}' => $class->title,
            '{{course_name}}' => $course?->name ?? 'N/A',
            '{{session_date}}' => $sessionDate->format('d M Y'),
            '{{session_time}}' => $parsedTime->format('g:i A'),
            '{{session_datetime}}' => $sessionDate->format('d M Y').', '.$parsedTime->format('g:i A'),
            '{{location}}' => $class->location ?? 'Akan dimaklumkan',
            '{{meeting_url}}' => $class->meeting_url ?? '#',
            '{{whatsapp_link}}' => $class->whatsapp_link ?? '#',
            '{{duration}}' => $timetable ? $timetable->duration.' minit' : 'N/A',
            '{{remaining_sessions}}' => (string) $this->getRemainingSessionCount($class),
            '{{total_sessions}}' => (string) $class->total_sessions,
            '{{attendance_rate}}' => $student ? (string) $this->getAttendanceRate($student, $class) : '0',
        ];

        return str_replace(array_keys($placeholders), array_values($placeholders), $content);
    }

    /**
     * Inline CSS into HTML for better email client compatibility.
     */
    public function inlineCss(string $html, string $css): string
    {
        // For now, CSS is already embedded in the HTML via the template builder
        // In a production environment, you might want to use a library like
        // TijsVerkoyen/CssToInlineStyles for more robust CSS inlining
        return $html;
    }

    /**
     * Get sample placeholders for preview.
     */
    public static function getSamplePlaceholders(): array
    {
        return [
            '{{student_name}}' => 'Ahmad bin Abdullah',
            '{{teacher_name}}' => 'Ustaz Muhammad',
            '{{class_name}}' => 'Kelas Tajwid Asas',
            '{{course_name}}' => 'Kursus Al-Quran',
            '{{session_date}}' => now()->addDay()->format('d M Y'),
            '{{session_time}}' => '10:00 AM',
            '{{session_datetime}}' => now()->addDay()->format('d M Y, g:i A'),
            '{{location}}' => 'Bilik 101, Bangunan A',
            '{{meeting_url}}' => 'https://meet.google.com/abc-defg-hij',
            '{{whatsapp_link}}' => 'https://chat.whatsapp.com/invite/abc123',
            '{{duration}}' => '2 jam',
            '{{remaining_sessions}}' => '8',
            '{{total_sessions}}' => '12',
            '{{attendance_rate}}' => '85',
        ];
    }

    /**
     * Apply sample placeholders for preview purposes.
     */
    public function applySamplePlaceholders(string $content): string
    {
        $placeholders = self::getSamplePlaceholders();

        return str_replace(array_keys($placeholders), array_values($placeholders), $content);
    }

    /**
     * Calculate session duration.
     */
    protected function calculateDuration(ClassSession $session): string
    {
        if ($session->start_time && $session->end_time) {
            $start = Carbon::parse($session->start_time);
            $end = Carbon::parse($session->end_time);
            $minutes = $start->diffInMinutes($end);

            if ($minutes >= 60) {
                $hours = floor($minutes / 60);
                $remainingMinutes = $minutes % 60;

                if ($remainingMinutes > 0) {
                    return "{$hours} jam {$remainingMinutes} minit";
                }

                return "{$hours} jam";
            }

            return "{$minutes} minit";
        }

        return 'N/A';
    }

    /**
     * Get remaining session count for a class.
     */
    protected function getRemainingSessionCount(ClassModel $class): int
    {
        return $class->sessions()
            ->where('status', '!=', 'completed')
            ->where('session_date', '>=', now()->toDateString())
            ->count();
    }

    /**
     * Get attendance rate for a student in a class.
     */
    protected function getAttendanceRate(Student $student, ClassModel $class): int
    {
        $attendanceRecords = $class->attendances()
            ->where('student_id', $student->id)
            ->get();

        if ($attendanceRecords->isEmpty()) {
            return 0;
        }

        $presentCount = $attendanceRecords->where('status', 'present')->count();
        $totalCount = $attendanceRecords->count();

        return $totalCount > 0 ? (int) round(($presentCount / $totalCount) * 100) : 0;
    }

    /**
     * Parse time string to Carbon instance.
     */
    protected function parseTime(string $time): Carbon
    {
        // Handle various time formats
        if (str_contains($time, ':')) {
            // Check if it's already a full datetime or just time
            if (strlen($time) > 8) {
                return Carbon::parse($time);
            }

            return Carbon::createFromFormat('H:i:s', strlen($time) === 5 ? $time.':00' : $time);
        }

        return Carbon::parse($time);
    }
}
