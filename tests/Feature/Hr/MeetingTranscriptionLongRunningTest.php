<?php

declare(strict_types=1);

use App\Jobs\Hr\PollMeetingTranscription;
use App\Jobs\Hr\TranscribeMeetingRecording;
use App\Models\Meeting;
use App\Models\MeetingRecording;
use App\Models\MeetingTranscript;
use App\Services\Hr\MeetingTranscriptionService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\mock;

beforeEach(function () {
    config()->set('services.assemblyai.api_key', 'test-key');
    config()->set('services.assemblyai.base_url', 'https://api.assemblyai.com');
    config()->set('services.assemblyai.speech_models', ['universal-3-pro', 'universal-2']);
});

function createRecording(Meeting $meeting): MeetingRecording
{
    return MeetingRecording::create([
        'meeting_id' => $meeting->id,
        'file_name' => 'sample.webm',
        'file_path' => 'meetings/recordings/1/sample.webm',
        'file_size' => 1024,
        'file_type' => 'audio/webm',
        'source' => 'uploaded',
        'uploaded_by' => $meeting->organizer_id,
    ]);
}

it('starts an AssemblyAI transcript and dispatches the polling job', function () {
    Bus::fake();

    $meeting = Meeting::factory()->create();
    $recording = createRecording($meeting);

    mock(MeetingTranscriptionService::class)
        ->shouldReceive('startTranscription')
        ->once()
        ->andReturnUsing(function ($_, MeetingTranscript $transcript) {
            $transcript->forceFill([
                'provider' => 'assemblyai',
                'provider_reference' => 'ax-123',
                'operation_name' => 'ax-123',
                'started_at' => now(),
            ])->save();
        });

    (new TranscribeMeetingRecording($recording))->handle(app(MeetingTranscriptionService::class));

    Bus::assertDispatched(PollMeetingTranscription::class);
    expect(MeetingTranscript::query()->where('recording_id', $recording->id)->exists())->toBeTrue();
});

it('re-dispatches the poll job when AssemblyAI is still processing', function () {
    Bus::fake();

    $meeting = Meeting::factory()->create();
    $transcript = MeetingTranscript::create([
        'meeting_id' => $meeting->id,
        'recording_id' => createRecording($meeting)->id,
        'content' => '',
        'language' => 'en',
        'provider' => 'assemblyai',
        'status' => 'processing',
        'operation_name' => 'ax-123',
        'provider_reference' => 'ax-123',
    ]);

    mock(MeetingTranscriptionService::class)
        ->shouldReceive('pollOperation')
        ->once()
        ->andReturn(false);

    (new PollMeetingTranscription($transcript))->handle(app(MeetingTranscriptionService::class));

    Bus::assertDispatched(PollMeetingTranscription::class);
});

it('stops polling and marks failed when max attempts is exceeded', function () {
    Bus::fake();

    $meeting = Meeting::factory()->create();
    $transcript = MeetingTranscript::create([
        'meeting_id' => $meeting->id,
        'recording_id' => createRecording($meeting)->id,
        'content' => '',
        'language' => 'en',
        'provider' => 'assemblyai',
        'status' => 'processing',
        'operation_name' => 'ax-123',
        'provider_reference' => 'ax-123',
        'poll_attempts' => PollMeetingTranscription::MAX_POLL_ATTEMPTS,
    ]);

    $serviceMock = mock(MeetingTranscriptionService::class);
    $serviceMock->shouldNotReceive('pollOperation');
    $serviceMock->shouldReceive('markFailed')->once();

    (new PollMeetingTranscription($transcript))->handle(app(MeetingTranscriptionService::class));

    Bus::assertNotDispatched(PollMeetingTranscription::class);
});

it('does nothing when transcript is already completed', function () {
    Bus::fake();

    $meeting = Meeting::factory()->create();
    $transcript = MeetingTranscript::create([
        'meeting_id' => $meeting->id,
        'recording_id' => createRecording($meeting)->id,
        'content' => 'Done.',
        'language' => 'en',
        'provider' => 'assemblyai',
        'status' => 'completed',
        'operation_name' => 'ax-123',
    ]);

    $serviceMock = mock(MeetingTranscriptionService::class);
    $serviceMock->shouldNotReceive('pollOperation');

    (new PollMeetingTranscription($transcript))->handle(app(MeetingTranscriptionService::class));

    Bus::assertNotDispatched(PollMeetingTranscription::class);
});

it('saves a completed transcript with speaker labels when polling succeeds', function () {
    Http::fake([
        'https://api.assemblyai.com/v2/transcript/ax-123' => Http::response([
            'id' => 'ax-123',
            'status' => 'completed',
            'language_code' => 'ms',
            'text' => 'Hello dunia.',
            'utterances' => [
                ['speaker' => 'A', 'text' => 'Selamat pagi semua.'],
                ['speaker' => 'B', 'text' => 'Pagi! Lets start the meeting.'],
            ],
        ]),
    ]);

    $meeting = Meeting::factory()->create();
    $transcript = MeetingTranscript::create([
        'meeting_id' => $meeting->id,
        'recording_id' => createRecording($meeting)->id,
        'content' => '',
        'language' => 'en',
        'provider' => 'assemblyai',
        'status' => 'processing',
        'operation_name' => 'ax-123',
        'provider_reference' => 'ax-123',
    ]);

    $finished = app(MeetingTranscriptionService::class)->pollOperation($transcript);

    expect($finished)->toBeTrue();

    $transcript->refresh();
    expect($transcript->status)->toBe('completed')
        ->and($transcript->language)->toBe('ms')
        ->and($transcript->content)->toContain('Speaker A: Selamat pagi semua.')
        ->and($transcript->content)->toContain('Speaker B: Pagi! Lets start the meeting.');
});

it('marks transcript failed when AssemblyAI returns an error status', function () {
    Http::fake([
        'https://api.assemblyai.com/v2/transcript/ax-bad' => Http::response([
            'id' => 'ax-bad',
            'status' => 'error',
            'error' => 'Audio file is corrupted.',
        ]),
    ]);

    $meeting = Meeting::factory()->create();
    $transcript = MeetingTranscript::create([
        'meeting_id' => $meeting->id,
        'recording_id' => createRecording($meeting)->id,
        'content' => '',
        'language' => 'en',
        'provider' => 'assemblyai',
        'status' => 'processing',
        'operation_name' => 'ax-bad',
        'provider_reference' => 'ax-bad',
    ]);

    $finished = app(MeetingTranscriptionService::class)->pollOperation($transcript);

    expect($finished)->toBeTrue();
    $transcript->refresh();
    expect($transcript->status)->toBe('failed')
        ->and($transcript->error_message)->toBe('Audio file is corrupted.');
});
