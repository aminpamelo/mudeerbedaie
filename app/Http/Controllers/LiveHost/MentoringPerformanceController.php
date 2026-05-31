<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Models\LiveHostMentee;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

class MentoringPerformanceController extends Controller
{
    /**
     * Record (or clear) a mentee's monthly performance score. Upserts on the
     * unique (mentee, year, month) so re-saving the same month just updates it.
     */
    public function store(Request $request, LiveHostMentee $mentee): HttpResponse
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $mentee->monthlyScores()->updateOrCreate(
            ['year' => $data['year'], 'month' => $data['month']],
            [
                'score' => $data['score'] ?? null,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $request->user()?->id,
            ]
        );

        return response()->noContent();
    }
}
