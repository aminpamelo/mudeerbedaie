<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreJobPostingRequest;
use App\Http\Requests\Hr\UpdateJobPostingRequest;
use App\Models\JobPosting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrJobPostingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = JobPosting::query()
            ->with(['department:id,name', 'position:id,title'])
            ->withCount('applicants');

        if ($search = $request->get('search')) {
            $query->where('title', 'like', "%{$search}%");
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($departmentId = $request->get('department_id')) {
            $query->where('department_id', $departmentId);
        }

        $postings = $query->orderByDesc('created_at')->paginate($request->get('per_page', 15));

        return response()->json($postings);
    }

    public function store(StoreJobPostingRequest $request): JsonResponse
    {
        $posting = JobPosting::create(array_merge(
            $request->validated(),
            ['created_by' => $request->user()->id]
        ));

        return response()->json([
            'message' => 'Job posting created successfully.',
            'data' => $posting->load(['department:id,name', 'position:id,title']),
        ], 201);
    }

    public function show(JobPosting $jobPosting): JsonResponse
    {
        return response()->json([
            'data' => $jobPosting->load([
                'department:id,name',
                'position:id,title',
                'applicants' => fn ($q) => $q->latest()->limit(50),
            ])->loadCount('applicants'),
        ]);
    }

    public function update(UpdateJobPostingRequest $request, JobPosting $jobPosting): JsonResponse
    {
        $jobPosting->update($request->validated());

        return response()->json([
            'message' => 'Job posting updated successfully.',
            'data' => $jobPosting->fresh(['department:id,name', 'position:id,title']),
        ]);
    }

    public function destroy(JobPosting $jobPosting): JsonResponse
    {
        if ($jobPosting->status !== 'draft') {
            return response()->json(['message' => 'Only draft postings can be deleted.'], 422);
        }

        $jobPosting->delete();

        return response()->json(['message' => 'Job posting deleted successfully.']);
    }

    public function publish(JobPosting $jobPosting): JsonResponse
    {
        $jobPosting->update([
            'status' => 'open',
            'published_at' => now(),
        ]);

        return response()->json([
            'message' => 'Job posting published successfully.',
            'data' => $jobPosting,
        ]);
    }

    public function close(JobPosting $jobPosting): JsonResponse
    {
        $jobPosting->update(['status' => 'closed']);

        return response()->json([
            'message' => 'Job posting closed.',
            'data' => $jobPosting,
        ]);
    }
}
