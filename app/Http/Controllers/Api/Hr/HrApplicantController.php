<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreApplicantRequest;
use App\Models\Applicant;
use App\Models\ApplicantStage;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrApplicantController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Applicant::query()
            ->with(['jobPosting:id,title']);

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('applicant_number', 'like', "%{$search}%");
            });
        }

        if ($stage = $request->get('stage')) {
            $query->where('current_stage', $stage);
        }

        if ($jobPostingId = $request->get('job_posting_id')) {
            $query->where('job_posting_id', $jobPostingId);
        }

        if ($source = $request->get('source')) {
            $query->where('source', $source);
        }

        $applicants = $query->orderByDesc('applied_at')->paginate($request->get('per_page', 15));

        return response()->json($applicants);
    }

    public function store(StoreApplicantRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $resumePath = $request->file('resume')->store('resumes', 'public');

            $applicant = Applicant::create([
                'job_posting_id' => $request->job_posting_id,
                'applicant_number' => Applicant::generateApplicantNumber(),
                'full_name' => $request->full_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'ic_number' => $request->ic_number,
                'resume_path' => $resumePath,
                'cover_letter' => $request->cover_letter,
                'source' => $request->source,
                'current_stage' => 'applied',
                'notes' => $request->notes,
                'applied_at' => now(),
            ]);

            ApplicantStage::create([
                'applicant_id' => $applicant->id,
                'stage' => 'applied',
                'notes' => 'Application received',
                'changed_by' => $request->user()->id,
            ]);

            return response()->json([
                'message' => 'Applicant added successfully.',
                'data' => $applicant,
            ], 201);
        });
    }

    public function show(Applicant $applicant): JsonResponse
    {
        return response()->json([
            'data' => $applicant->load([
                'jobPosting:id,title,department_id',
                'jobPosting.department:id,name',
                'stages' => fn ($q) => $q->latest(),
                'stages.changedByUser:id,name',
                'interviews.interviewer:id,full_name',
                'offerLetter',
            ]),
        ]);
    }

    public function update(Request $request, Applicant $applicant): JsonResponse
    {
        $validated = $request->validate([
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'notes' => ['nullable', 'string'],
        ]);

        $applicant->update($validated);

        return response()->json([
            'message' => 'Applicant updated successfully.',
            'data' => $applicant,
        ]);
    }

    public function moveStage(Request $request, Applicant $applicant): JsonResponse
    {
        $validated = $request->validate([
            'stage' => ['required', 'in:applied,screening,interview,assessment,offer,hired,rejected,withdrawn'],
            'notes' => ['nullable', 'string'],
        ]);

        return DB::transaction(function () use ($validated, $applicant, $request) {
            $applicant->update(['current_stage' => $validated['stage']]);

            ApplicantStage::create([
                'applicant_id' => $applicant->id,
                'stage' => $validated['stage'],
                'notes' => $validated['notes'] ?? null,
                'changed_by' => $request->user()->id,
            ]);

            return response()->json([
                'message' => 'Applicant moved to '.$validated['stage'].' stage.',
                'data' => $applicant->fresh(),
            ]);
        });
    }

    public function hire(Request $request, Applicant $applicant): JsonResponse
    {
        $validated = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'position_id' => ['required', 'exists:positions,id'],
            'employment_type' => ['required', 'in:full_time,part_time,contract,intern'],
            'join_date' => ['required', 'date'],
            'basic_salary' => ['required', 'numeric', 'min:0'],
        ]);

        return DB::transaction(function () use ($validated, $applicant, $request) {
            /** @var User $user */
            $user = User::create([
                'name' => $applicant->full_name,
                'email' => $applicant->email,
                'password' => bcrypt('password'),
                'role' => 'employee',
            ]);

            $lastEmployee = Employee::orderByDesc('employee_id')->first();
            $lastNum = $lastEmployee ? (int) substr($lastEmployee->employee_id, 4) : 0;
            $employeeId = 'BDE-'.str_pad($lastNum + 1, 4, '0', STR_PAD_LEFT);

            $employee = Employee::create([
                'user_id' => $user->id,
                'employee_id' => $employeeId,
                'full_name' => $applicant->full_name,
                'ic_number' => $applicant->ic_number ?? 'PENDING',
                'date_of_birth' => '1990-01-01',
                'gender' => 'male',
                'religion' => 'islam',
                'race' => 'malay',
                'marital_status' => 'single',
                'phone' => $applicant->phone,
                'personal_email' => $applicant->email,
                'address_line_1' => 'PENDING',
                'city' => 'PENDING',
                'state' => 'PENDING',
                'postcode' => '00000',
                'department_id' => $validated['department_id'],
                'position_id' => $validated['position_id'],
                'employment_type' => $validated['employment_type'],
                'join_date' => $validated['join_date'],
                'status' => 'probation',
            ]);

            $applicant->update(['current_stage' => 'hired']);
            ApplicantStage::create([
                'applicant_id' => $applicant->id,
                'stage' => 'hired',
                'notes' => 'Converted to employee: '.$employeeId,
                'changed_by' => $request->user()->id,
            ]);

            return response()->json([
                'message' => 'Applicant hired successfully. Employee record created.',
                'data' => [
                    'applicant' => $applicant->fresh(),
                    'employee' => $employee,
                ],
            ], 201);
        });
    }
}
