<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\MeetingAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class HrMeetingAttachmentController extends Controller
{
    /**
     * Upload an attachment to a meeting.
     */
    public function store(Request $request, Meeting $meeting): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:20480'],
        ]);

        $file = $request->file('file');
        $path = $file->store("meetings/attachments/{$meeting->id}", 'public');

        $employee = $request->user()->employee;

        $attachment = MeetingAttachment::create([
            'meeting_id' => $meeting->id,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'file_type' => $file->getMimeType(),
            'uploaded_by' => $employee?->id,
        ]);

        $attachment->load('uploader:id,full_name');

        return response()->json([
            'data' => $attachment,
            'message' => 'Attachment uploaded successfully.',
        ], 201);
    }

    /**
     * Delete a meeting attachment.
     */
    public function destroy(Meeting $meeting, MeetingAttachment $attachment): JsonResponse
    {
        Storage::disk('public')->delete($attachment->file_path);

        $attachment->delete();

        return response()->json(['message' => 'Attachment deleted successfully.']);
    }
}
