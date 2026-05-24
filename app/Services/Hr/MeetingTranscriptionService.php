<?php

namespace App\Services\Hr;

use App\Models\MeetingRecording;
use App\Models\MeetingTranscript;
use Google\Cloud\Speech\V1\RecognitionAudio;
use Google\Cloud\Speech\V1\RecognitionConfig;
use Google\Cloud\Speech\V1\RecognitionConfig\AudioEncoding;
use Google\Cloud\Speech\V1\SpeechClient;
use Google\Cloud\Storage\StorageClient;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class MeetingTranscriptionService
{
    /**
     * Upload the audio to GCS and start a long-running transcription operation.
     */
    public function startTranscription(MeetingRecording $recording, MeetingTranscript $transcript): void
    {
        $bucketName = $this->bucketName();
        $localPath = Storage::disk('public')->path($recording->file_path);

        if (! is_file($localPath)) {
            throw new RuntimeException("Recording file not found at {$localPath}");
        }

        $objectName = sprintf(
            'meeting-recordings/%d/%s-%s',
            $recording->meeting_id,
            Str::uuid(),
            basename($recording->file_path)
        );

        $storage = $this->storageClient();
        $storage->bucket($bucketName)->upload(
            fopen($localPath, 'r'),
            ['name' => $objectName]
        );

        $gcsUri = "gs://{$bucketName}/{$objectName}";

        $speech = $this->speechClient();

        try {
            $config = (new RecognitionConfig)
                ->setEncoding($this->encoding($recording->file_type))
                ->setLanguageCode('en-US')
                ->setAlternativeLanguageCodes(['ms-MY'])
                ->setEnableAutomaticPunctuation(true)
                ->setModel('latest_long');

            $audio = (new RecognitionAudio)->setUri($gcsUri);

            $operation = $speech->longRunningRecognize($config, $audio);

            $transcript->forceFill([
                'status' => 'processing',
                'operation_name' => $operation->getName(),
                'gcs_uri' => $gcsUri,
                'started_at' => now(),
                'poll_attempts' => 0,
                'error_message' => null,
            ])->save();
        } finally {
            $speech->close();
        }
    }

    /**
     * Poll the operation. Returns true when complete (success or failure), false if still running.
     */
    public function pollOperation(MeetingTranscript $transcript): bool
    {
        if (! $transcript->operation_name) {
            throw new RuntimeException('Transcript has no operation_name to poll.');
        }

        $speech = $this->speechClient();

        try {
            $operation = $speech->resumeOperation($transcript->operation_name, 'longRunningRecognize');
            $operation->reload();

            $transcript->increment('poll_attempts');

            if (! $operation->done()) {
                return false;
            }

            if ($operation->operationFailed()) {
                $error = $operation->getError();
                $transcript->forceFill([
                    'status' => 'failed',
                    'error_message' => $error ? $error->getMessage() : 'Operation failed without error message.',
                ])->save();
                $this->cleanupGcs($transcript);

                return true;
            }

            $response = $operation->getResult();
            $lines = [];

            foreach ($response->getResults() as $result) {
                $alternatives = $result->getAlternatives();
                if (count($alternatives) > 0) {
                    $lines[] = $alternatives[0]->getTranscript();
                }
            }

            $transcript->forceFill([
                'content' => implode("\n", $lines),
                'status' => 'completed',
                'processed_at' => now(),
            ])->save();

            $this->cleanupGcs($transcript);

            return true;
        } finally {
            $speech->close();
        }
    }

    /**
     * Mark a transcript as failed and clean up the GCS object.
     */
    public function markFailed(MeetingTranscript $transcript, string $reason): void
    {
        $transcript->forceFill([
            'status' => 'failed',
            'error_message' => $reason,
        ])->save();

        $this->cleanupGcs($transcript);
    }

    /**
     * Remove the audio object from GCS once we no longer need it.
     */
    public function cleanupGcs(MeetingTranscript $transcript): void
    {
        if (! $transcript->gcs_uri) {
            return;
        }

        $parts = explode('/', preg_replace('#^gs://#', '', $transcript->gcs_uri), 2);
        if (count($parts) !== 2) {
            return;
        }

        [$bucket, $object] = $parts;

        try {
            $this->storageClient()->bucket($bucket)->object($object)->delete();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function bucketName(): string
    {
        $bucket = config('services.google.speech_bucket');
        if (! $bucket) {
            throw new RuntimeException('GOOGLE_CLOUD_SPEECH_BUCKET is not configured.');
        }

        return $bucket;
    }

    private function storageClient(): StorageClient
    {
        return new StorageClient([
            'projectId' => config('services.google.project_id'),
            'keyFilePath' => config('services.google.credentials_path'),
        ]);
    }

    private function speechClient(): SpeechClient
    {
        $credentials = config('services.google.credentials_path');

        return new SpeechClient($credentials ? ['credentials' => $credentials] : []);
    }

    private function encoding(string $mimeType): int
    {
        return match (true) {
            str_contains($mimeType, 'webm') => AudioEncoding::WEBM_OPUS,
            str_contains($mimeType, 'ogg') => AudioEncoding::OGG_OPUS,
            str_contains($mimeType, 'flac') => AudioEncoding::FLAC,
            str_contains($mimeType, 'wav') => AudioEncoding::LINEAR16,
            str_contains($mimeType, 'mp3') => AudioEncoding::MP3,
            default => AudioEncoding::ENCODING_UNSPECIFIED,
        };
    }
}
