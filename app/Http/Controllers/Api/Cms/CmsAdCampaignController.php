<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\StoreAdCampaignRequest;
use App\Models\AdCampaign;
use App\Models\AdStat;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CmsAdCampaignController extends Controller
{
    /**
     * Paginated list with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AdCampaign::query()
            ->with([
                'content:id,title,stage',
                'assignedByEmployee:id,full_name,profile_photo',
                'stats' => function ($query) {
                    $query->latest('fetched_at')->limit(1);
                },
            ]);

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($platform = $request->get('platform')) {
            $query->where('platform', $platform);
        }

        $campaigns = $query->latest()->paginate($request->get('per_page', 15));

        return response()->json($campaigns);
    }

    /**
     * Create a new ad campaign.
     */
    public function store(StoreAdCampaignRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $employee = Employee::where('user_id', $request->user()->id)->firstOrFail();

        $campaign = AdCampaign::create(array_merge($validated, [
            'assigned_by' => $employee->id,
            'status' => $validated['status'] ?? 'pending',
        ]));

        $campaign->load([
            'content:id,title,stage',
            'assignedByEmployee:id,full_name,profile_photo',
        ]);

        return response()->json([
            'data' => $campaign,
            'message' => 'Ad campaign created successfully.',
        ], 201);
    }

    /**
     * Show ad campaign with relationships.
     */
    public function show(AdCampaign $adCampaign): JsonResponse
    {
        $adCampaign->load([
            'content.stats' => function ($query) {
                $query->latest('fetched_at')->limit(10);
            },
            'assignedByEmployee:id,full_name,profile_photo',
            'stats',
        ]);

        return response()->json(['data' => $adCampaign]);
    }

    /**
     * Update ad campaign fields.
     */
    public function update(Request $request, AdCampaign $adCampaign): JsonResponse
    {
        $validated = $request->validate([
            'platform' => ['sometimes', 'in:facebook,tiktok'],
            'ad_id' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'in:pending,running,paused,completed'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string'],
        ]);

        $adCampaign->update($validated);

        $adCampaign->load([
            'content:id,title,stage',
            'assignedByEmployee:id,full_name,profile_photo',
        ]);

        return response()->json([
            'data' => $adCampaign,
            'message' => 'Ad campaign updated successfully.',
        ]);
    }

    /**
     * Soft delete an ad campaign.
     */
    public function destroy(AdCampaign $adCampaign): JsonResponse
    {
        $adCampaign->delete();

        return response()->json(['message' => 'Ad campaign deleted successfully.']);
    }

    /**
     * Add performance stats to an ad campaign.
     */
    public function addStats(Request $request, AdCampaign $adCampaign): JsonResponse
    {
        $validated = $request->validate([
            'impressions' => ['required', 'integer', 'min:0'],
            'clicks' => ['required', 'integer', 'min:0'],
            'spend' => ['required', 'numeric', 'min:0'],
            'conversions' => ['required', 'integer', 'min:0'],
        ]);

        $stat = AdStat::create(array_merge($validated, [
            'ad_campaign_id' => $adCampaign->id,
            'fetched_at' => now(),
        ]));

        return response()->json([
            'data' => $stat,
            'message' => 'Ad stats added successfully.',
        ], 201);
    }
}
