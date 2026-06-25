<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Models\LiveHostMentee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MentoringPerformanceController extends Controller
{
    /**
     * Record (or clear) a mentee's monthly KPI metrics — Attitude (0–100) and
     * Sales (monthly RM value). Upserts on the unique (mentee, year, month)
     * so re-saving the same month just updates it. The Overall KPI is computed
     * from these against the mentee's level target, never stored.
     */
    public function store(Request $request, LiveHostMentee $mentee): RedirectResponse
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'attitude_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'sales_quantity' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $mentee->monthlyScores()->updateOrCreate(
            ['year' => $data['year'], 'month' => $data['month']],
            [
                'attitude_score' => $data['attitude_score'] ?? null,
                'sales_quantity' => $data['sales_quantity'] ?? null,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $request->user()?->id,
            ]
        );

        // Return an Inertia-friendly redirect rather than 204 No Content. The
        // editor saves via Inertia's router.patch; a 204 has no Inertia payload,
        // so Inertia treats it as an invalid response and renders a blank modal.
        return back();
    }
}
