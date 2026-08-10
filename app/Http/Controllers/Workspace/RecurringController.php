<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskRecurringConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecurringController extends Controller
{
    public function store(Request $request, Task $task): JsonResponse
    {
        $validated = $request->validate([
            'frequency' => 'required|in:daily,weekly,monthly,yearly',
            'day_of_week' => 'nullable|integer|between:0,6',
            'day_of_month' => 'nullable|integer|between:1,31',
            'time_of_day' => 'nullable|date_format:H:i',
        ]);

        $nextDue = $this->calculateNextDue(
            $validated['frequency'],
            $validated['day_of_week'] ?? null,
            $validated['day_of_month'] ?? null,
            $validated['time_of_day'] ?? '09:00',
        );

        $config = $task->recurringConfig()->create([
            ...$validated,
            'next_due_at' => $nextDue,
            'is_active' => true,
        ]);

        $task->update(['is_recurring' => true, 'recurring_config_id' => $config->id]);

        return response()->json(['data' => $config, 'message' => 'Recurring config created.'], 201);
    }

    public function update(Request $request, Task $task, TaskRecurringConfig $config): JsonResponse
    {
        $validated = $request->validate([
            'frequency' => 'sometimes|in:daily,weekly,monthly,yearly',
            'day_of_week' => 'nullable|integer|between:0,6',
            'day_of_month' => 'nullable|integer|between:1,31',
            'time_of_day' => 'nullable|date_format:H:i',
            'is_active' => 'sometimes|boolean',
        ]);

        $config->update($validated);

        if (isset($validated['frequency']) || isset($validated['day_of_week']) || isset($validated['day_of_month'])) {
            $config->update([
                'next_due_at' => $this->calculateNextDue(
                    $config->frequency,
                    $config->day_of_week,
                    $config->day_of_month,
                    $config->time_of_day ?? '09:00',
                ),
            ]);
        }

        return response()->json(['data' => $config->fresh(), 'message' => 'Recurring config updated.']);
    }

    public function destroy(Task $task, TaskRecurringConfig $config): JsonResponse
    {
        $config->delete();
        $task->update(['is_recurring' => false, 'recurring_config_id' => null]);

        return response()->json(['message' => 'Recurring config deleted.']);
    }

    private function calculateNextDue(string $frequency, ?int $dayOfWeek, ?int $dayOfMonth, string $timeOfDay): \Carbon\Carbon
    {
        $now = now();
        $time = explode(':', $timeOfDay);
        $hour = (int) ($time[0] ?? 9);
        $minute = (int) ($time[1] ?? 0);

        return match ($frequency) {
            'daily' => $now->copy()->addDay()->setTime($hour, $minute),
            'weekly' => $now->copy()->next($dayOfWeek ?? 1)->setTime($hour, $minute),
            'monthly' => $now->copy()->addMonth()->day(min($dayOfMonth ?? 1, 28))->setTime($hour, $minute),
            'yearly' => $now->copy()->addYear()->setTime($hour, $minute),
        };
    }
}
