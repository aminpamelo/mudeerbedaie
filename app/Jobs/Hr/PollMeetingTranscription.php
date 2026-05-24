<?php

namespace App\Jobs\Hr;

use App\Models\MeetingTranscript;
use App\Services\Hr\MeetingTranscriptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PollMeetingTranscription implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 120;

    public const MAX_POLL_ATTEMPTS = 120;

    public const POLL_DELAY_SECONDS = 30;

    public function __construct(
        public MeetingTranscript $transcript
    ) {}

    public function handle(MeetingTranscriptionService $service): void
    {
        $this->transcript->refresh();

        if ($this->transcript->status !== 'processing') {
            return;
        }

        if ($this->transcript->poll_attempts >= self::MAX_POLL_ATTEMPTS) {
            $service->markFailed(
                $this->transcript,
                'Transcription timed out after '.self::MAX_POLL_ATTEMPTS.' polling attempts.'
            );

            return;
        }

        $finished = $service->pollOperation($this->transcript);

        if (! $finished) {
            self::dispatch($this->transcript)->delay(now()->addSeconds(self::POLL_DELAY_SECONDS));
        }
    }
}
