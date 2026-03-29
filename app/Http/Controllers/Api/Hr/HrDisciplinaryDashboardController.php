<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\DisciplinaryAction;
use Illuminate\Http\JsonResponse;

class HrDisciplinaryDashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        return response()->json([
            'data' => [
                'active_cases' => DisciplinaryAction::whereNotIn('status', ['closed'])->count(),
                'warnings_this_month' => DisciplinaryAction::whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->count(),
                'pending_responses' => DisciplinaryAction::where('status', 'pending_response')->count(),
                'cases_by_type' => [
                    'verbal_warning' => DisciplinaryAction::where('type', 'verbal_warning')->count(),
                    'first_written' => DisciplinaryAction::where('type', 'first_written')->count(),
                    'second_written' => DisciplinaryAction::where('type', 'second_written')->count(),
                    'show_cause' => DisciplinaryAction::where('type', 'show_cause')->count(),
                    'suspension' => DisciplinaryAction::where('type', 'suspension')->count(),
                    'termination' => DisciplinaryAction::where('type', 'termination')->count(),
                ],
            ],
        ]);
    }
}
