<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\JobPosting;
use Illuminate\Http\JsonResponse;

class HrRecruitmentDashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        return response()->json([
            'data' => [
                'open_positions' => JobPosting::where('status', 'open')->count(),
                'total_applicants' => Applicant::count(),
                'active_applicants' => Applicant::whereNotIn('current_stage', ['hired', 'rejected', 'withdrawn'])->count(),
                'hired_this_month' => Applicant::where('current_stage', 'hired')
                    ->whereMonth('updated_at', now()->month)
                    ->whereYear('updated_at', now()->year)
                    ->count(),
                'pipeline' => [
                    'applied' => Applicant::where('current_stage', 'applied')->count(),
                    'screening' => Applicant::where('current_stage', 'screening')->count(),
                    'interview' => Applicant::where('current_stage', 'interview')->count(),
                    'assessment' => Applicant::where('current_stage', 'assessment')->count(),
                    'offer' => Applicant::where('current_stage', 'offer')->count(),
                ],
            ],
        ]);
    }
}
