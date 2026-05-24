<?php

namespace App\Jobs\Hr;

use App\Models\MeetingRecording;
use App\Models\MeetingTranscript;
use App\Services\Hr\MeetingTranscriptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TranscribeMeetingRecording implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        public MeetingRecording $recording
    ) {}

    public function handle(MeetingTranscriptionService $service): void
    {
        $transcript = MeetingTranscript::query()
            ->where('meeting_id', $this->recording->meeting_id)
            ->where('recording_id', $this->recording->id)
            ->where('status', 'processing')
            ->latest()
            ->first();

        if (! $transcript) {
            $transcript = MeetingTranscript::create([
                'meeting_id' => $this->recording->meeting_id,
                'recording_id' => $this->recording->id,
                'content' => '',
                'language' => 'en',
                'status' => 'processing',
            ]);
        }

        try {
            $service->startTranscription($this->recording, $transcript);
        } catch (\Throwable $e) {
            $service->markFailed($transcript, $e->getMessage());
            throw $e;
        }

        PollMeetingTranscription::dispatch($transcript)->delay(now()->addSeconds(30));
    }
}
