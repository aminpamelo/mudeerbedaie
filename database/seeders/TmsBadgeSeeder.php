<?php

namespace Database\Seeders;

use App\Models\TmsBadge;
use Illuminate\Database\Seeder;

class TmsBadgeSeeder extends Seeder
{
    public function run(): void
    {
        $badges = [
            ['name' => 'First Task', 'description' => 'Completed your first task', 'icon' => 'rocket-launch', 'color' => '#6366f1', 'criteria_type' => 'first_task', 'criteria_value' => 1, 'points' => 5],
            ['name' => '10 Tasks', 'description' => 'Completed 10 tasks', 'icon' => 'check-badge', 'color' => '#3b82f6', 'criteria_type' => 'tasks_completed', 'criteria_value' => 10, 'points' => 20],
            ['name' => '50 Tasks', 'description' => 'Completed 50 tasks', 'icon' => 'star', 'color' => '#8b5cf6', 'criteria_type' => 'tasks_completed', 'criteria_value' => 50, 'points' => 50],
            ['name' => '100 Tasks', 'description' => 'Completed 100 tasks', 'icon' => 'trophy', 'color' => '#f59e0b', 'criteria_type' => 'tasks_completed', 'criteria_value' => 100, 'points' => 100],
            ['name' => '7 Day Streak', 'description' => '7 consecutive days completing tasks', 'icon' => 'fire', 'color' => '#ef4444', 'criteria_type' => 'streak', 'criteria_value' => 7, 'points' => 30],
            ['name' => '30 Day Streak', 'description' => '30 consecutive days completing tasks', 'icon' => 'fire', 'color' => '#dc2626', 'criteria_type' => 'streak', 'criteria_value' => 30, 'points' => 100],
            ['name' => 'Speed Demon', 'description' => 'Completed a task faster than estimated', 'icon' => 'bolt', 'color' => '#eab308', 'criteria_type' => 'speed', 'criteria_value' => 1, 'points' => 15],
            ['name' => 'Top Performer', 'description' => 'Most tasks completed in a month', 'icon' => 'crown', 'color' => '#f59e0b', 'criteria_type' => 'top_performer', 'criteria_value' => 0, 'points' => 200],
        ];

        foreach ($badges as $badge) {
            TmsBadge::updateOrCreate(
                ['name' => $badge['name']],
                $badge
            );
        }
    }
}
