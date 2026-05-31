<?php

namespace App\Services\Mentoring;

use App\Models\LiveHostMentoringActivity;

/**
 * Derives an at-a-glance "how active is this top host" indicator from the
 * coaching/meeting activities they log. There is no pre-existing host activity
 * log, so this is built entirely from live_host_mentoring_activities:
 *   green  — logged something within the last 7 days
 *   amber  — last activity 8–14 days ago
 *   red    — nothing in 14+ days (or never)
 *   none   — program has no leader assigned
 */
class MentorActivityIndicator
{
    private const GREEN_DAYS = 7;

    private const AMBER_DAYS = 14;

    /**
     * @return array{level: string, label: string, lastAt: ?string, count30: int}
     */
    public function forLeader(?int $leaderUserId): array
    {
        if ($leaderUserId === null) {
            return $this->shape('none', 'No leader', null, 0);
        }

        return $this->forLeaders([$leaderUserId])[$leaderUserId]
            ?? $this->shape('red', 'Inactive', null, 0);
    }

    /**
     * Batch variant for list views — one query for all leaders.
     *
     * @param  array<int>  $leaderIds
     * @return array<int, array{level: string, label: string, lastAt: ?string, count30: int}>
     */
    public function forLeaders(array $leaderIds): array
    {
        $leaderIds = array_values(array_unique(array_filter($leaderIds)));
        if ($leaderIds === []) {
            return [];
        }

        $cutoff = now()->subDays(30)->format('Y-m-d H:i:s');

        $rows = LiveHostMentoringActivity::query()
            ->whereIn('leader_user_id', $leaderIds)
            ->selectRaw('leader_user_id, MAX(occurred_at) as last_at, SUM(CASE WHEN occurred_at >= ? THEN 1 ELSE 0 END) as count30', [$cutoff])
            ->groupBy('leader_user_id')
            ->get()
            ->keyBy('leader_user_id');

        $out = [];
        foreach ($leaderIds as $id) {
            $row = $rows->get($id);
            if (! $row || $row->last_at === null) {
                $out[$id] = $this->shape('red', 'Inactive', null, 0);

                continue;
            }

            $last = \Illuminate\Support\Carbon::parse($row->last_at);
            $days = $last->diffInDays(now());
            $count30 = (int) $row->count30;

            if ($days <= self::GREEN_DAYS) {
                $out[$id] = $this->shape('green', 'Active', $last->toIso8601String(), $count30);
            } elseif ($days <= self::AMBER_DAYS) {
                $out[$id] = $this->shape('amber', 'Slowing', $last->toIso8601String(), $count30);
            } else {
                $out[$id] = $this->shape('red', 'Inactive', $last->toIso8601String(), $count30);
            }
        }

        return $out;
    }

    /**
     * @return array{level: string, label: string, lastAt: ?string, count30: int}
     */
    private function shape(string $level, string $label, ?string $lastAt, int $count30): array
    {
        return ['level' => $level, 'label' => $label, 'lastAt' => $lastAt, 'count30' => $count30];
    }
}
