<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AiController extends Controller
{
    public function decompose(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:500',
            'description' => 'nullable|string|max:2000',
        ]);

        $apiKey = config('services.openai.api_key');
        if (! $apiKey) {
            return response()->json(['error' => 'AI not configured. Set OPENAI_API_KEY.'], 422);
        }

        $prompt = "You are a project manager. Break down this project/task into actionable subtasks.\n\nProject: {$validated['title']}\n";
        if (! empty($validated['description'])) {
            $prompt .= "Description: {$validated['description']}\n";
        }
        $prompt .= "\nReturn a JSON array of objects with 'title' and 'priority' (low/medium/high) fields. Return ONLY the JSON array, no other text.";

        try {
            $response = Http::withToken($apiKey)
                ->timeout(30)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o-mini',
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                    'temperature' => 0.7,
                    'max_tokens' => 1000,
                ]);

            $content = $response->json('choices.0.message.content', '[]');
            $subtasks = json_decode($content, true) ?? [];

            return response()->json(['data' => $subtasks]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'AI request failed: '.$e->getMessage()], 500);
        }
    }

    public function summary(Request $request): JsonResponse
    {
        $employeeId = $request->user()->employee?->id;

        $today = now()->toDateString();
        $completedToday = $employeeId
            ? Task::query()->where('assigned_to', $employeeId)->where('status', 'completed')->whereDate('completed_at', $today)->count()
            : 0;
        $overdue = $employeeId
            ? Task::query()->where('assigned_to', $employeeId)->whereNotIn('status', ['completed', 'cancelled'])->whereNotNull('deadline')->where('deadline', '<', now())->count()
            : 0;
        $inProgress = $employeeId
            ? Task::query()->where('assigned_to', $employeeId)->where('status', 'in_progress')->count()
            : 0;
        $upcoming = $employeeId
            ? Task::query()->where('assigned_to', $employeeId)->whereNotIn('status', ['completed', 'cancelled'])->whereNotNull('deadline')->whereBetween('deadline', [now(), now()->addDays(3)])->count()
            : 0;

        $summary = [];
        if ($overdue > 0) {
            $summary[] = "{$overdue} task overdue — perlu diselesaikan segera.";
        }
        if ($completedToday > 0) {
            $summary[] = "{$completedToday} task siap hari ini.";
        }
        if ($inProgress > 0) {
            $summary[] = "{$inProgress} task sedang dijalankan.";
        }
        if ($upcoming > 0) {
            $summary[] = "{$upcoming} task akan due dalam 3 hari.";
        }
        if (empty($summary)) {
            $summary[] = 'Tiada tugasan mendesak. Kerja bagus!';
        }

        return response()->json(['data' => ['summary' => $summary, 'stats' => compact('completedToday', 'overdue', 'inProgress', 'upcoming')]]);
    }
}
