<?php

declare(strict_types=1);

use App\Jobs\Hr\PollMeetingTranscription;
use App\Jobs\Hr\TranscribeMeetingRecording;
use App\Models\Meeting;
use App\Models\MeetingRecording;
use App\Models\MeetingTranscript;
use App\Services\Hr\MeetingTranscriptionService;
use Illuminate\Support\Facades\Bus;

use function Pest\Laravel\mock;

it('uploads to GCS and dispatches the polling job', function () {
    Bus::fake();

    $meeting = Meeting::factory()->create();
    $recording = MeetingRecording::create([
        'meeting_id' => $meeting->id,
        'file_name' => 'sample.webm',
        'file_path' => 'meetings/recordings/1/sample.webm',
        'file_size' => 1024,
        'file_type' => 'audio/webm',
        'source' => 'uploaded',
        'uploaded_by' => $meeting->organizer_id,
    ]);

    mock(MeetingTranscriptionService::class)
        ->shouldReceive('startTranscription')
        ->once()
        ->andReturnUsing(function ($_, MeetingTranscript $transcript) {
            $transcript->forceFill([
                'operation_name' => 'projects/p/operations/abc',
                'gcs_uri' => 'gs://bucket/file.webm',
                'started_at' => now(),
            ])->save();
        });

    (new TranscribeMeetingRecording($recording))->handle(app(MeetingTranscriptionService::class));

    Bus::assertDispatched(PollMeetingTranscription::class);
    expect(MeetingTranscript::query()->where('recording_id', $recording->id)->exists())->toBeTrue();
});

it('re-dispatches the poll job when the operation is still running', function () {
    Bus::fake();

    $meeting = Meeting::factory()->create();
    $transcript = MeetingTranscript::create([
        'meeting_id' => $meeting->id,
        'recording_id' => MeetingRecording::create([
            'meeting_id' => $meeting->id,
            'file_name' => 'sample.webm',
            'file_path' => 'meetings/recordings/1/sample.webm',
            'file_size' => 1024,
            'file_type' => 'audio/webm',
            'source' => 'uploaded',
            'uploaded_by' => $meeting->organizer_id,
        ])->id,
        'content' => '',
        'language' => 'en',
        'status' => 'processing',
        'operation_name' => 'projects/p/operations/abc',
        'gcs_uri' => 'gs://bucket/file.webm',
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
        'recording_id' => MeetingRecording::create([
            'meeting_id' => $meeting->id,
            'file_name' => 'sample.webm',
            'file_path' => 'meetings/recordings/1/sample.webm',
            'file_size' => 1024,
            'file_type' => 'audio/webm',
            'source' => 'uploaded',
            'uploaded_by' => $meeting->organizer_id,
        ])->id,
        'content' => '',
        'language' => 'en',
        'status' => 'processing',
        'operation_name' => 'projects/p/operations/abc',
        'gcs_uri' => 'gs://bucket/file.webm',
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
        'recording_id' => MeetingRecording::create([
            'meeting_id' => $meeting->id,
            'file_name' => 'sample.webm',
            'file_path' => 'meetings/recordings/1/sample.webm',
            'file_size' => 1024,
            'file_type' => 'audio/webm',
            'source' => 'uploaded',
            'uploaded_by' => $meeting->organizer_id,
        ])->id,
        'content' => 'Done.',
        'language' => 'en',
        'status' => 'completed',
        'operation_name' => 'projects/p/operations/abc',
    ]);

    $serviceMock = mock(MeetingTranscriptionService::class);
    $serviceMock->shouldNotReceive('pollOperation');

    (new PollMeetingTranscription($transcript))->handle(app(MeetingTranscriptionService::class));

    Bus::assertNotDispatched(PollMeetingTranscription::class);
});
