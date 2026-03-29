<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\EmployeeCertification;
use App\Models\TrainingBudget;
use App\Models\TrainingCost;
use App\Models\TrainingProgram;
use Illuminate\Http\JsonResponse;

class HrTrainingDashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        $currentYear = now()->year;

        return response()->json([
            'data' => [
                'upcoming_trainings' => TrainingProgram::where('start_date', '>', now())
                    ->where('status', 'planned')
                    ->count(),
                'completed_this_year' => TrainingProgram::where('status', 'completed')
                    ->whereYear('end_date', $currentYear)
                    ->count(),
                'total_spend' => TrainingCost::whereHas('trainingProgram', fn ($q) => $q->whereYear('start_date', $currentYear))->sum('amount'),
                'certs_expiring_soon' => EmployeeCertification::expiringSoon(90)->count(),
                'budget_utilization' => TrainingBudget::where('year', $currentYear)->get()->map(fn ($b) => [
                    'department_id' => $b->department_id,
                    'allocated' => $b->allocated_amount,
                    'spent' => $b->spent_amount,
                    'percentage' => $b->utilization_percentage,
                ]),
            ],
        ]);
    }
}
