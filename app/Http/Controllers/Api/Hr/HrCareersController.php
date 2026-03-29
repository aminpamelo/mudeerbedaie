<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\ApplicantStage;
use App\Models\JobPosting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrCareersController extends Controller
{
    public function index(): JsonResponse
    {
        $postings = JobPosting::query()
            ->published()
            ->with(['department:id,name', 'position:id,title'])
            ->select([
                'id', 'title', 'department_id', 'position_id', 'description', 'requirements',
                'employment_type', 'salary_range_min', 'salary_range_max', 'show_salary',
                'vacancies', 'published_at', 'closing_date',
            ])
            ->orderByDesc('published_at')
            ->get()
            ->map(function ($posting) {
                if (! $posting->show_salary) {
                    $posting->salary_range_min = null;
                    $posting->salary_range_max = null;
                }

                return $posting;
            });

        return response()->json(['data' => $postings]);
    }

    public function show(int $id): JsonResponse
    {
        $posting = JobPosting::query()
            ->published()
            ->with(['department:id,name', 'position:id,title'])
            ->findOrFail($id);

        if (! $posting->show_salary) {
            $posting->salary_range_min = null;
            $posting->salary_range_max = null;
        }

        return response()->json(['data' => $posting]);
    }

    public function apply(Request $request, int $id): JsonResponse
    {
        $posting = JobPosting::published()->findOrFail($id);

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'ic_number' => ['nullable', 'string', 'max:20'],
            'resume' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
            'cover_letter' => ['nullable', 'string'],
        ]);

        return DB::transaction(function () use ($validated, $posting, $request) {
            $resumePath = $request->file('resume')->store('resumes', 'public');

            $applicant = Applicant::create([
                'job_posting_id' => $posting->id,
                'applicant_number' => Applicant::generateApplicantNumber(),
                'full_name' => $validated['full_name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'ic_number' => $validated['ic_number'] ?? null,
                'resume_path' => $resumePath,
                'cover_letter' => $validated['cover_letter'] ?? null,
                'source' => 'website',
                'current_stage' => 'applied',
                'applied_at' => now(),
            ]);

            ApplicantStage::create([
                'applicant_id' => $applicant->id,
                'stage' => 'applied',
                'notes' => 'Applied via careers page',
                'changed_by' => 1,
            ]);

            return response()->json([
                'message' => 'Application submitted successfully.',
                'data' => [
                    'applicant_number' => $applicant->applicant_number,
                ],
            ], 201);
        });
    }
}
