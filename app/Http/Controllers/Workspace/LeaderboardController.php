<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\TmsBadgeAward;
use App\Models\TmsUserStat;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaderboardController extends Controller
{
    public function index(Request $request): Response
    {
        $period = $request->input('period', 'month');
        $startDate = match ($period) {
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'year' => now()->startOfYear(),
            default => now()->startOfMonth(),
        };

        $leaderboard = TmsUserStat::query()
            ->selectRaw('user_id, SUM(tasks_completed) as total_completed, SUM(total_points) as total_points, MAX(streak_days) as max_streak, SUM(time_tracked_seconds) as total_time')
            ->where('date', '>=', $startDate->toDateString())
            ->groupBy('user_id')
            ->orderByDesc('total_points')
            ->with('user')
            ->limit(50)
            ->get()
            ->map(fn ($s, $i) => [
                'rank' => $i + 1,
                'user_id' => $s->user_id,
                'name' => $s->user?->name ?? 'Unknown',
                'total_completed' => (int) $s->total_completed,
                'total_points' => (int) $s->total_points,
                'max_streak' => (int) $s->max_streak,
                'total_time_hours' => round($s->total_time / 3600, 1),
            ]);

        $badges = TmsBadgeAward::query()
            ->where('user_id', $request->user()->id)
            ->with('badge')
            ->get()
            ->map(fn ($a) => [
                'name' => $a->badge->name,
                'description' => $a->badge->description,
                'icon' => $a->badge->icon,
                'color' => $a->badge->color,
                'awarded_at' => $a->awarded_at->toIso8601String(),
            ]);

        return Inertia::render('Leaderboard', [
            'leaderboard' => $leaderboard,
            'myBadges' => $badges,
            'period' => $period,
        ]);
    }
}
