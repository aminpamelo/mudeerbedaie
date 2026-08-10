<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\TaskRecurringConfig;
use Illuminate\Console\Command;

class GenerateRecurringTasks extends Command
{
    protected $signature = 'tms:generate-recurring';

    protected $description = 'Generate new tasks from active recurring configurations whose next_due_at has arrived.';

    public function handle(): int
    {
        $configs = TaskRecurringConfig::query()
            ->where('is_active', true)
            ->where('next_due_at', '<=', now())
            ->with('task')
            ->get();

        if ($configs->isEmpty()) {
            $this->info('No recurring tasks to generate.');

            return self::SUCCESS;
        }

        $generated = 0;

        foreach ($configs as $config) {
            $template = $config->task;

            if (! $template) {
                $this->warn("Config #{$config->id} has no parent task — skipped.");

                continue;
            }

            $newTask = Task::create([
                'title' => $template->title,
                'description' => $template->description,
                'priority' => $template->priority,
                'project_id' => $template->project_id,
                'assigned_to' => $template->assigned_to,
                'assigned_by' => $template->assigned_by,
                'estimated_minutes' => $template->estimated_minutes,
                'tags' => $template->tags,
                'category_id' => $template->category_id,
                'deadline' => $config->next_due_at,
                'status' => 'pending',
            ]);

            // Copy assignees from template
            if ($template->assignees()->count() > 0) {
                $newTask->assignees()->sync($template->assignees()->pluck('employees.id'));
            }

            $config->update([
                'last_generated_at' => now(),
                'next_due_at' => $this->calculateNextDue($config),
            ]);

            $generated++;
        }

        $this->info("Generated {$generated} recurring task(s).");

        return self::SUCCESS;
    }

    private function calculateNextDue(TaskRecurringConfig $config): \Carbon\Carbon
    {
        $from = $config->next_due_at->copy();
        $time = explode(':', $config->time_of_day ?? '09:00');
        $hour = (int) ($time[0] ?? 9);
        $minute = (int) ($time[1] ?? 0);

        return match ($config->frequency) {
            'daily' => $from->addDay()->setTime($hour, $minute),
            'weekly' => $from->addWeek()->next($config->day_of_week ?? 1)->setTime($hour, $minute),
            'monthly' => $from->addMonth()->day(min($config->day_of_month ?? 1, 28))->setTime($hour, $minute),
            'yearly' => $from->addYear()->setTime($hour, $minute),
            default => $from->addDay()->setTime($hour, $minute),
        };
    }
}
