<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Http\Requests\LiveHost\Mentoring\StoreMentoringActivityRequest;
use App\Models\LiveHostMentoringActivity;
use App\Models\LiveHostMentoringProgram;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

class MentoringActivityController extends Controller
{
    public function store(StoreMentoringActivityRequest $request, LiveHostMentoringProgram $program): RedirectResponse
    {
        $data = $request->validated();

        // A mentee, if given, must belong to this program.
        if (! empty($data['mentee_id'])) {
            abort_unless(
                $program->mentees()->whereKey($data['mentee_id'])->exists(),
                HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
                'That mentee does not belong to this program.'
            );
        }

        $program->activities()->create([
            'leader_user_id' => $program->leader_user_id,
            'mentee_id' => $data['mentee_id'] ?? null,
            'type' => $data['type'],
            'title' => $data['title'],
            'notes' => $data['notes'] ?? null,
            'occurred_at' => $data['occurred_at'],
            'created_by' => $request->user()?->id,
        ]);

        return back()->with('success', 'Activity logged.');
    }

    public function destroy(Request $request, LiveHostMentoringActivity $activity): RedirectResponse
    {
        $activity->delete();

        return back()->with('success', 'Activity removed.');
    }
}
