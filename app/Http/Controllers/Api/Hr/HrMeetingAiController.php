<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Jobs\Hr\AnalyzeMeetingTranscript;
use App\Jobs\Hr\TranscribeMeetingRecording;
use App\Models\Meeting;
use App\Models\MeetingAiSummary;
use App\Models\MeetingRecording;
use App\Models\MeetingTranscript;
use App\Models\Task;
use App\Services\Hr\MeetingAiAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrMeetingAiController extends Controller
{
    /**
     * Request transcription of a meeting recording.
     */
    public function transcribe(Meeting $meeting, MeetingRecording $recording): JsonResponse
    {
        $transcript = MeetingTranscript::create([
            'meeting_id' => $meeting->id,
            'recording_id' => $recording->id,
            'content' => null,
            'language' => 'en',
            'status' => 'processing',
        ]);

        TranscribeMeetingRecording::dispatch($recording);

        return response()->json([
            'data' => $transcript,
            'message' => 'Transcription has been queued for processing.',
        ], 202);
    }

    /**
     * Get the latest transcript for a meeting.
     */
    public function getTranscript(Meeting $meeting): JsonResponse
    {
        $transcript = $meeting->transcripts()
            ->latest()
            ->first();

        if (! $transcript) {
            return response()->json(['message' => 'No transcript found for this meeting.'], 404);
        }

        return response()->json(['data' => $transcript]);
    }

    /**
     * Request AI analysis of a meeting (from transcript or meeting data).
     */
    public function analyze(Meeting $meeting, MeetingAiAnalysisService $service): JsonResponse
    {
        $transcript = $meeting->transcripts()
            ->where('status', 'completed')
            ->latest()
            ->first();

        if ($transcript) {
            // Use transcript-based analysis via queue
            $summary = MeetingAiSummary::create([
                'meeting_id' => $meeting->id,
                'transcript_id' => $transcript->id,
                'summary' => null,
                'key_points' => null,
                'suggested_tasks' => null,
                'status' => 'processing',
            ]);

            AnalyzeMeetingTranscript::dispatch($transcript);

            return response()->json([
                'data' => $summary,
                'message' => 'AI analysis has been queued for processing.',
            ], 202);
        }

        // No transcript — analyze from meeting structured data (synchronous)
        $summary = $service->analyzeFromMeetingData($meeting);

        return response()->json([
            'data' => $summary,
            'message' => 'AI analysis completed.',
        ], $summary->status === 'completed' ? 200 : 500);
    }

    /**
     * Get the latest AI summary for a meeting.
     */
    public function getSummary(Meeting $meeting): JsonResponse
    {
        $summary = $meeting->aiSummaries()
            ->latest()
            ->first();

        if (! $summary) {
            return response()->json(['message' => 'No AI summary found for this meeting.'], 404);
        }

        return response()->json(['data' => $summary]);
    }

    /**
     * Approve and create tasks from AI-suggested tasks.
     */
    public function approveTasks(Request $request, Meeting $meeting): JsonResponse
    {
        $validated = $request->validate([
            'tasks' => ['required', 'array', 'min:1'],
            'tasks.*.title' => ['required', 'string', 'max:255'],
            'tasks.*.description' => ['nullable', 'string'],
            'tasks.*.assigned_to' => ['required', 'exists:employees,id'],
            'tasks.*.priority' => ['required', 'in:low,medium,high,urgent'],
            'tasks.*.deadline' => ['required', 'date'],
        ]);

        $employee = $request->user()->employee;
        $createdTasks = [];

        foreach ($validated['tasks'] as $taskData) {
            $createdTasks[] = Task::create([
                'taskable_type' => Meeting::class,
                'taskable_id' => $meeting->id,
                'title' => $taskData['title'],
                'description' => $taskData['description'] ?? null,
                'assigned_to' => $taskData['assigned_to'],
                'assigned_by' => $employee?->id,
                'priority' => $taskData['priority'],
                'deadline' => $taskData['deadline'],
                'status' => 'pending',
            ]);
        }

        // Mark the latest AI summary as reviewed
        $summary = $meeting->aiSummaries()->latest()->first();
        if ($summary) {
            $summary->update([
                'reviewed_by' => $employee?->id,
                'reviewed_at' => now(),
            ]);
        }

        return response()->json([
            'data' => $createdTasks,
            'message' => count($createdTasks).' task(s) created from AI suggestions.',
        ], 201);
    }
}
