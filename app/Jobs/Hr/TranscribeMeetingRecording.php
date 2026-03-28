<?php

namespace App\Jobs\Hr;

use App\Models\MeetingRecording;
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

    public int $timeout = 600;

    public function __construct(
        public MeetingRecording $recording
    ) {}

    public function handle(MeetingTranscriptionService $service): void
    {
        $service->transcribe($this->recording);
    }
}
