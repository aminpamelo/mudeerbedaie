<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Models\LiveHostMentee;
use App\Models\LiveHostMenteeDisciplinaryRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * PIC-recorded disciplinary / conduct log for a mentee. This is a record, not a
 * workflow — the PIC logs an incident with a category, severity, date, and note.
 * Mentoring scoped and lightweight; distinct from HR's Employee-based module.
 */
class MentoringDisciplinaryController extends Controller
{
    /**
     * The mentee's disciplinary log as JSON — used by the modal to show existing
     * records live (and refresh after add/remove) from anywhere it's opened.
     */
    public function index(LiveHostMentee $mentee): JsonResponse
    {
        return response()->json([
            'records' => $mentee->disciplinaryRecords()
                ->with('recordedByUser:id,name')
                ->get()
                ->map(fn (LiveHostMenteeDisciplinaryRecord $r) => [
                    'id' => $r->id,
                    'incident_date' => $r->incident_date?->toDateString(),
                    'incident_date_human' => $r->incident_date?->format('M j, Y'),
                    'category' => $r->category,
                    'severity' => $r->severity,
                    'description' => $r->description,
                    'recorded_by' => $r->recordedByUser?->name,
                ])->values(),
        ]);
    }

    public function store(Request $request, LiveHostMentee $mentee): RedirectResponse
    {
        $mentee->disciplinaryRecords()->create([
            ...$this->validateData($request),
            'recorded_by' => $request->user()?->id,
        ]);

        return back()->with('success', 'Disciplinary record added.');
    }

    public function update(Request $request, LiveHostMenteeDisciplinaryRecord $record): RedirectResponse
    {
        $record->update($this->validateData($request));

        return back()->with('success', 'Disciplinary record updated.');
    }

    public function destroy(LiveHostMenteeDisciplinaryRecord $record): RedirectResponse
    {
        $record->delete();

        return back()->with('success', 'Disciplinary record removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request): array
    {
        return $request->validate([
            'incident_date' => ['required', 'date'],
            'category' => ['required', Rule::in(LiveHostMenteeDisciplinaryRecord::CATEGORIES)],
            'severity' => ['required', Rule::in(LiveHostMenteeDisciplinaryRecord::SEVERITIES)],
            'description' => ['required', 'string', 'max:5000'],
        ]);
    }
}
