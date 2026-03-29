<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\MeetingRecording;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class HrMeetingRecordingController extends Controller
{
    /**
     * Upload a recording to a meeting.
     */
    public function store(Request $request, Meeting $meeting): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:512000'],
            'source' => ['nullable', 'string', 'in:upload,zoom,teams,google_meet'],
        ]);

        try {
            $file = $request->file('file');
            $path = $file->store("meetings/recordings/{$meeting->id}", 'public');

            $employee = $request->user()->employee;

            $recording = MeetingRecording::create([
                'meeting_id' => $meeting->id,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_size' => $file->getSize(),
                'file_type' => $file->getMimeType(),
                'duration_seconds' => null,
                'source' => 'uploaded',
                'uploaded_by' => $employee?->id ?? $meeting->organizer_id,
            ]);

            $recording->load('uploader:id,full_name');

            return response()->json([
                'data' => $recording,
                'message' => 'Recording uploaded successfully.',
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Recording upload failed', [
                'meeting_id' => $meeting->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Upload failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a meeting recording.
     */
    public function destroy(Meeting $meeting, MeetingRecording $recording): JsonResponse
    {
        Storage::disk('public')->delete($recording->file_path);

        $recording->delete();

        return response()->json(['message' => 'Recording deleted successfully.']);
    }
}
