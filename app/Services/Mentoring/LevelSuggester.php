<?php

namespace App\Services\Mentoring;

use App\Models\LiveHostMentoringLevel;
use Illuminate\Support\Collection;

/**
 * Maps a mentee KPI snapshot onto the customizable level ladder. Returns the
 * highest active level whose every non-null threshold is satisfied. The PIC's
 * manual assignment always overrides this — the suggestion is advisory.
 */
class LevelSuggester
{
    /**
     * @param  array{sessions:int, hours:float, gmv:float, attendancePct:int}  $kpis
     */
    public function suggest(array $kpis): ?LiveHostMentoringLevel
    {
        /** @var Collection<int, LiveHostMentoringLevel> $levels */
        $levels = LiveHostMentoringLevel::query()
            ->active()
            ->orderByDesc('position')
            ->get();

        foreach ($levels as $level) {
            if ($this->qualifies($kpis, $level)) {
                return $level;
            }
        }

        // No threshold met — fall back to the lowest (entry) level if any exist.
        return $levels->last();
    }

    /**
     * @param  array{sessions:int, hours:float, gmv:float, attendancePct:int}  $kpis
     */
    private function qualifies(array $kpis, LiveHostMentoringLevel $level): bool
    {
        if ($level->min_sessions !== null && $kpis['sessions'] < (int) $level->min_sessions) {
            return false;
        }
        if ($level->min_hours !== null && $kpis['hours'] < (float) $level->min_hours) {
            return false;
        }
        if ($level->min_gmv_myr !== null && $kpis['gmv'] < (float) $level->min_gmv_myr) {
            return false;
        }
        if ($level->min_attendance_pct !== null && $kpis['attendancePct'] < (int) $level->min_attendance_pct) {
            return false;
        }

        return true;
    }
}
