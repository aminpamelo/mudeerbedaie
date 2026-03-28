<?php

namespace App\Jobs\Hr;

use App\Models\MeetingTranscript;
use App\Services\Hr\MeetingAiAnalysisService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AnalyzeMeetingTranscript implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        public MeetingTranscript $transcript
    ) {}

    public function handle(MeetingAiAnalysisService $service): void
    {
        $service->analyze($this->transcript);
    }
}
