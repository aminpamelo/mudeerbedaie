<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreAssetRequest;
use App\Models\Asset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrAssetController extends Controller
{
    /**
     * List all assets with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Asset::query()
            ->with(['category', 'currentAssignment.employee']);

        if ($categoryId = $request->get('category_id')) {
            $query->where('asset_category_id', $categoryId);
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($condition = $request->get('condition')) {
            $query->where('condition', $condition);
        }

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('asset_tag', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%");
            });
        }

        $assets = $query->orderBy('asset_tag')->paginate(15);

        return response()->json($assets);
    }

    /**
     * Create a new asset with auto-generated asset tag.
     */
    public function store(StoreAssetRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['asset_tag'] = Asset::generateAssetTag();
        $validated['status'] = 'available';

        $asset = Asset::create($validated);
        $asset->load('category');

        return response()->json([
            'data' => $asset,
            'message' => 'Asset created successfully.',
        ], 201);
    }

    /**
     * Show a single asset with assignment history.
     */
    public function show(Asset $asset): JsonResponse
    {
        $asset->load(['category', 'assignments.employee', 'currentAssignment.employee']);

        return response()->json(['data' => $asset]);
    }

    /**
     * Update an asset.
     */
    public function update(StoreAssetRequest $request, Asset $asset): JsonResponse
    {
        $validated = $request->validated();
        unset($validated['asset_tag']);

        $asset->update($validated);

        return response()->json([
            'data' => $asset->fresh('category'),
            'message' => 'Asset updated successfully.',
        ]);
    }

    /**
     * Dispose of an asset (sets status to disposed).
     */
    public function destroy(Asset $asset): JsonResponse
    {
        if ($asset->status === 'assigned') {
            return response()->json([
                'message' => 'Cannot dispose of an assigned asset. Return it first.',
            ], 422);
        }

        $asset->update(['status' => 'disposed']);

        return response()->json(['message' => 'Asset marked as disposed.']);
    }
}
