<?php

namespace App\Services\Hr;

use App\Models\Meeting;
use App\Models\MeetingAiSummary;
use App\Models\MeetingTranscript;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MeetingAiAnalysisService
{
    /**
     * Analyze a meeting transcript.
     */
    public function analyze(MeetingTranscript $transcript): MeetingAiSummary
    {
        $summary = MeetingAiSummary::create([
            'meeting_id' => $transcript->meeting_id,
            'transcript_id' => $transcript->id,
            'summary' => '',
            'status' => 'processing',
        ]);

        $prompt = $this->buildTranscriptPrompt($transcript->content);

        return $this->callGemini($summary, $prompt);
    }

    /**
     * Analyze a meeting using its structured data (agenda, decisions, attendees, etc.).
     */
    public function analyzeFromMeetingData(Meeting $meeting): MeetingAiSummary
    {
        $meeting->load([
            'agendaItems',
            'decisions.decidedBy:id,full_name',
            'attendees.employee:id,full_name',
            'tasks.assignee:id,full_name',
            'organizer:id,full_name',
            'noteTaker:id,full_name',
        ]);

        $summary = MeetingAiSummary::create([
            'meeting_id' => $meeting->id,
            'transcript_id' => null,
            'summary' => '',
            'status' => 'processing',
        ]);

        $content = $this->buildMeetingContent($meeting);
        $prompt = $this->buildMeetingDataPrompt($content);

        return $this->callGemini($summary, $prompt);
    }

    private function callGemini(MeetingAiSummary $summary, string $prompt): MeetingAiSummary
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->timeout(60)->post(
                'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key='.config('services.google.gemini_api_key'),
                [
                    'contents' => [
                        ['parts' => [['text' => $prompt]]],
                    ],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                    ],
                ]
            );

            if ($response->successful()) {
                $text = $response->json('candidates.0.content.parts.0.text', '{}');
                $parsed = json_decode($text, true);

                $summary->update([
                    'summary' => $parsed['summary'] ?? 'No summary generated.',
                    'key_points' => $parsed['key_points'] ?? [],
                    'suggested_tasks' => $parsed['action_items'] ?? [],
                    'status' => 'completed',
                ]);
            } else {
                Log::error('Gemini API failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                $summary->update(['status' => 'failed']);
            }
        } catch (\Exception $e) {
            $summary->update(['status' => 'failed']);
            report($e);
        }

        return $summary->refresh();
    }

    private function buildMeetingContent(Meeting $meeting): string
    {
        $lines = [];
        $lines[] = "Meeting: {$meeting->title}";
        $lines[] = "Date: {$meeting->meeting_date->format('Y-m-d')}";
        $lines[] = "Time: {$meeting->start_time} - {$meeting->end_time}";
        $lines[] = "Location: {$meeting->location}";
        $lines[] = "Status: {$meeting->status}";

        if ($meeting->organizer) {
            $lines[] = "Organizer: {$meeting->organizer->full_name}";
        }
        if ($meeting->noteTaker) {
            $lines[] = "Note Taker: {$meeting->noteTaker->full_name}";
        }
        if ($meeting->description) {
            $lines[] = "\nDescription: {$meeting->description}";
        }

        if ($meeting->attendees->isNotEmpty()) {
            $lines[] = "\nAttendees:";
            foreach ($meeting->attendees as $att) {
                $name = $att->employee?->full_name ?? 'Unknown';
                $lines[] = "- {$name} (Role: {$att->role}, Status: {$att->rsvp_status})";
            }
        }

        if ($meeting->agendaItems->isNotEmpty()) {
            $lines[] = "\nAgenda Items:";
            foreach ($meeting->agendaItems as $item) {
                $lines[] = "- [{$item->order_index}] {$item->title}";
                if ($item->description) {
                    $lines[] = "  Description: {$item->description}";
                }
                if ($item->notes) {
                    $lines[] = "  Notes: {$item->notes}";
                }
            }
        }

        if ($meeting->decisions->isNotEmpty()) {
            $lines[] = "\nDecisions Made:";
            foreach ($meeting->decisions as $dec) {
                $decider = $dec->decidedBy?->full_name ?? 'Unknown';
                $lines[] = "- {$dec->title} (Decided by: {$decider})";
                if ($dec->description) {
                    $lines[] = "  Details: {$dec->description}";
                }
            }
        }

        if ($meeting->tasks->isNotEmpty()) {
            $lines[] = "\nExisting Tasks:";
            foreach ($meeting->tasks as $task) {
                $assignee = $task->assignee?->full_name ?? 'Unassigned';
                $lines[] = "- {$task->title} (Assigned to: {$assignee}, Priority: {$task->priority}, Status: {$task->status})";
                if ($task->description) {
                    $lines[] = "  Description: {$task->description}";
                }
            }
        }

        return implode("\n", $lines);
    }

    private function buildTranscriptPrompt(string $transcript): string
    {
        return <<<PROMPT
Analyze this meeting transcript and return a JSON object with these fields:

1. "summary": A concise executive summary (2-3 paragraphs)
2. "key_points": An array of strings, each being a key discussion point
3. "action_items": An array of objects, each with:
   - "title": Task title
   - "description": Brief description
   - "assignee_name": Name of person responsible (if mentioned), or null
   - "deadline_mentioned": Any deadline mentioned (as string), or null
   - "priority": "low", "medium", "high", or "urgent" based on context

Return ONLY valid JSON, no markdown.

TRANSCRIPT:
{$transcript}
PROMPT;
    }

    private function buildMeetingDataPrompt(string $meetingContent): string
    {
        return <<<PROMPT
Analyze this meeting data and return a JSON object with these fields:

1. "summary": A concise executive summary (2-3 paragraphs) of what was discussed, decided, and what needs follow-up
2. "key_points": An array of strings, each being a key discussion point or takeaway from the meeting
3. "action_items": An array of objects for any NEW suggested follow-up tasks (beyond existing tasks), each with:
   - "title": Task title
   - "description": Brief description
   - "assignee_name": Name of person who should be responsible (based on attendees/context), or null
   - "deadline_mentioned": Any suggested deadline, or null
   - "priority": "low", "medium", "high", or "urgent" based on context

Return ONLY valid JSON, no markdown.

MEETING DATA:
{$meetingContent}
PROMPT;
    }
}
