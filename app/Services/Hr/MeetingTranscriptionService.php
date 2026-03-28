<?php

namespace App\Services\Hr;

use App\Models\MeetingRecording;
use App\Models\MeetingTranscript;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class MeetingTranscriptionService
{
    public function transcribe(MeetingRecording $recording): MeetingTranscript
    {
        $transcript = MeetingTranscript::create([
            'meeting_id' => $recording->meeting_id,
            'recording_id' => $recording->id,
            'content' => '',
            'language' => 'en',
            'status' => 'processing',
        ]);

        try {
            $audioContent = Storage::get($recording->file_path);

            // Google Cloud Speech-to-Text API
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post(
                'https://speech.googleapis.com/v1/speech:recognize?key='.config('services.google.speech_api_key'),
                [
                    'config' => [
                        'encoding' => $this->getEncoding($recording->file_type),
                        'languageCode' => 'en-US',
                        'alternativeLanguageCodes' => ['ms-MY'],
                        'enableAutomaticPunctuation' => true,
                        'model' => 'latest_long',
                    ],
                    'audio' => [
                        'content' => base64_encode($audioContent),
                    ],
                ]
            );

            if ($response->successful()) {
                $results = $response->json('results', []);
                $transcriptText = collect($results)
                    ->pluck('alternatives.0.transcript')
                    ->implode("\n");

                $transcript->update([
                    'content' => $transcriptText,
                    'status' => 'completed',
                    'processed_at' => now(),
                ]);
            } else {
                $transcript->update(['status' => 'failed']);
            }
        } catch (\Exception $e) {
            $transcript->update(['status' => 'failed']);
            report($e);
        }

        return $transcript->refresh();
    }

    private function getEncoding(string $mimeType): string
    {
        return match (true) {
            str_contains($mimeType, 'webm') => 'WEBM_OPUS',
            str_contains($mimeType, 'ogg') => 'OGG_OPUS',
            str_contains($mimeType, 'flac') => 'FLAC',
            str_contains($mimeType, 'wav') => 'LINEAR16',
            str_contains($mimeType, 'mp3') => 'MP3',
            default => 'ENCODING_UNSPECIFIED',
        };
    }
}
