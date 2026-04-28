<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ClassModel;
use App\Models\ClassSession;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TeacherStartBriefing
{
    /**
     * Build the briefing array consumed by the
     * `livewire.teacher._partials.start-session-briefing` blade partial.
     *
     * @return array{
     *     class: ClassModel|null,
     *     session: ClassSession|null,
     *     when: Carbon,
     *     duration_minutes: int,
     *     student_count: int,
     *     syllabus: Collection,
     *     upsell_funnels: Collection,
     *     pics: Collection,
     * }|null
     */
    public static function build(?ClassSession $session, ?ClassModel $class, ?Carbon $when = null, ?int $durationMinutes = null): ?array
    {
        $class = $class ?? $session?->class;

        if (! $class) {
            return null;
        }

        $when = $when ?? ($session
            ? $session->session_date->copy()->setTimeFromTimeString($session->session_time->format('H:i:s'))
            : Carbon::now());

        $duration = $durationMinutes
            ?? ($session?->duration_minutes ?? $class->duration_minutes ?? 60);

        return [
            'class' => $class,
            'session' => $session,
            'when' => $when,
            'duration_minutes' => (int) $duration,
            'student_count' => $class->activeStudents()->count(),
            'syllabus' => $session?->syllabusItems() ?? new Collection,
            'upsell_funnels' => $session?->upsellFunnels() ?? new Collection,
            'pics' => $class->pics ?? new Collection,
        ];
    }
}
