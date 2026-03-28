<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreMeetingSeriesRequest;
use App\Models\MeetingSeries;
use Illuminate\Http\JsonResponse;

class HrMeetingSeriesController extends Controller
{
    /**
     * List all meeting series with meeting count.
     */
    public function index(): JsonResponse
    {
        $series = MeetingSeries::query()
            ->withCount('meetings')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $series]);
    }

    /**
     * Create a new meeting series.
     */
    public function store(StoreMeetingSeriesRequest $request): JsonResponse
    {
        $series = MeetingSeries::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'data' => $series,
            'message' => 'Meeting series created successfully.',
        ], 201);
    }

    /**
     * Show a meeting series with its meetings.
     */
    public function show(MeetingSeries $series): JsonResponse
    {
        $series->load([
            'meetings' => fn ($q) => $q->with(['organizer:id,full_name'])
                ->withCount('attendees')
                ->orderBy('meeting_date', 'desc'),
        ]);

        return response()->json(['data' => $series]);
    }
}
