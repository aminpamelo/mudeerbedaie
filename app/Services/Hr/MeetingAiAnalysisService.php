<?php

namespace App\Services\Hr;

use App\Models\MeetingAiSummary;
use App\Models\MeetingTranscript;
use Illuminate\Support\Facades\Http;

class MeetingAiAnalysisService
{
    public function analyze(MeetingTranscript $transcript): MeetingAiSummary
    {
        $summary = MeetingAiSummary::create([
            'meeting_id' => $transcript->meeting_id,
            'transcript_id' => $transcript->id,
            'summary' => '',
            'status' => 'processing',
        ]);

        try {
            $prompt = $this->buildPrompt($transcript->content);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post(
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
                $summary->update(['status' => 'failed']);
            }
        } catch (\Exception $e) {
            $summary->update(['status' => 'failed']);
            report($e);
        }

        return $summary->refresh();
    }

    private function buildPrompt(string $transcript): string
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
}
