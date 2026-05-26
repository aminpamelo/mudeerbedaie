<?php

namespace App\Services\Hr;

use App\Models\MeetingRecording;
use App\Models\MeetingTranscript;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class MeetingTranscriptionService
{
    /**
     * Upload the audio to AssemblyAI and queue a transcription job tuned for Malaysian meetings.
     */
    public function startTranscription(MeetingRecording $recording, MeetingTranscript $transcript): void
    {
        $localPath = Storage::disk('public')->path($recording->file_path);

        if (! is_file($localPath)) {
            throw new RuntimeException("Recording file not found at {$localPath}");
        }

        $uploadUrl = $this->uploadAudio($localPath);
        $transcriptId = $this->requestTranscript($uploadUrl);

        $transcript->forceFill([
            'status' => 'processing',
            'provider' => 'assemblyai',
            'provider_reference' => $transcriptId,
            'operation_name' => $transcriptId,
            'started_at' => now(),
            'poll_attempts' => 0,
            'error_message' => null,
        ])->save();
    }

    /**
     * Poll AssemblyAI for the transcript status.
     * Returns true when finished (success or failure), false if still processing.
     */
    public function pollOperation(MeetingTranscript $transcript): bool
    {
        if (! $transcript->provider_reference) {
            throw new RuntimeException('Transcript has no provider_reference to poll.');
        }

        $transcript->increment('poll_attempts');

        $response = $this->client()->get("/v2/transcript/{$transcript->provider_reference}");

        if ($response->failed()) {
            $transcript->forceFill([
                'status' => 'failed',
                'error_message' => 'AssemblyAI poll failed: '.$response->status().' '.$response->body(),
            ])->save();

            return true;
        }

        $status = $response->json('status');

        if ($status === 'queued' || $status === 'processing') {
            return false;
        }

        if ($status === 'error') {
            $transcript->forceFill([
                'status' => 'failed',
                'error_message' => $response->json('error') ?? 'AssemblyAI returned error status.',
            ])->save();

            return true;
        }

        $content = $this->formatTranscript($response->json());

        $transcript->forceFill([
            'content' => $content,
            'status' => 'completed',
            'processed_at' => now(),
            'language' => $response->json('language_code') ?? $transcript->language,
        ])->save();

        return true;
    }

    /**
     * Mark a transcript as failed with a custom reason.
     */
    public function markFailed(MeetingTranscript $transcript, string $reason): void
    {
        $transcript->forceFill([
            'status' => 'failed',
            'error_message' => $reason,
        ])->save();
    }

    private function uploadAudio(string $localPath): string
    {
        $response = Http::withHeaders([
            'authorization' => $this->apiKey(),
            'content-type' => 'application/octet-stream',
        ])
            ->withBody(file_get_contents($localPath), 'application/octet-stream')
            ->timeout(300)
            ->post($this->baseUrl().'/v2/upload');

        if ($response->failed()) {
            throw new RuntimeException('AssemblyAI upload failed: '.$response->status().' '.$response->body());
        }

        $url = $response->json('upload_url');

        if (! $url) {
            throw new RuntimeException('AssemblyAI upload did not return an upload_url.');
        }

        return $url;
    }

    private function requestTranscript(string $audioUrl): string
    {
        $response = $this->client()->post('/v2/transcript', [
            'audio_url' => $audioUrl,
            'speech_models' => config('services.assemblyai.speech_models'),
            'language_detection' => true,
            'language_detection_options' => [
                'code_switching' => true,
            ],
            'speaker_labels' => true,
            'auto_chapters' => false,
            'punctuate' => true,
            'format_text' => true,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('AssemblyAI transcript request failed: '.$response->status().' '.$response->body());
        }

        $id = $response->json('id');

        if (! $id) {
            throw new RuntimeException('AssemblyAI transcript request did not return an id.');
        }

        return $id;
    }

    /**
     * Build a human-readable transcript with speaker labels when available.
     */
    private function formatTranscript(array $payload): string
    {
        $utterances = $payload['utterances'] ?? null;

        if (is_array($utterances) && count($utterances) > 0) {
            $lines = [];
            foreach ($utterances as $utt) {
                $speaker = $utt['speaker'] ?? '?';
                $text = trim($utt['text'] ?? '');
                if ($text !== '') {
                    $lines[] = "Speaker {$speaker}: {$text}";
                }
            }

            return implode("\n", $lines);
        }

        return (string) ($payload['text'] ?? '');
    }

    private function client(): PendingRequest
    {
        return Http::withHeaders([
            'authorization' => $this->apiKey(),
            'content-type' => 'application/json',
        ])
            ->baseUrl($this->baseUrl())
            ->timeout(60);
    }

    private function apiKey(): string
    {
        $key = config('services.assemblyai.api_key');

        if (! $key) {
            throw new RuntimeException('ASSEMBLYAI_API_KEY is not configured.');
        }

        return $key;
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.assemblyai.base_url'), '/');
    }
}
