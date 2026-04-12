<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Models\TiktokAffiliateOrder;
use App\Models\TiktokCreator;
use App\Models\TiktokCreatorContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CmsAffiliateController extends Controller
{
    /**
     * Creator leaderboard — all creators ranked by GMV.
     */
    public function creators(Request $request): JsonResponse
    {
        $query = TiktokCreator::query()
            ->with('platformAccount:id,name')
            ->when($request->search, fn ($q, $s) => $q->where('display_name', 'like', "%{$s}%")
                ->orWhere('handle', 'like', "%{$s}%"))
            ->when($request->sort === 'followers', fn ($q) => $q->orderByDesc('follower_count'),
                fn ($q) => $q->orderByDesc('total_gmv'))
            ->paginate($request->integer('per_page', 20));

        return response()->json($query);
    }

    /**
     * Single creator detail with recent content and orders.
     */
    public function creatorDetail(TiktokCreator $creator): JsonResponse
    {
        $creator->load([
            'creatorContents' => fn ($q) => $q->latest('fetched_at')->limit(20),
            'affiliateOrders' => fn ($q) => $q->latest('order_created_at')->limit(20),
        ]);

        return response()->json(['data' => $creator]);
    }

    /**
     * Creators who promoted a specific content item.
     */
    public function contentCreators(int $contentId): JsonResponse
    {
        $creators = TiktokCreatorContent::where('content_id', $contentId)
            ->with('creator:id,creator_user_id,handle,display_name,avatar_url,follower_count')
            ->latest('fetched_at')
            ->get();

        return response()->json(['data' => $creators]);
    }

    /**
     * Affiliate orders summary — recent orders with creator info.
     */
    public function affiliateOrders(Request $request): JsonResponse
    {
        $query = TiktokAffiliateOrder::query()
            ->with('creator:id,display_name,handle,avatar_url')
            ->when($request->creator_id, fn ($q, $id) => $q->where('tiktok_creator_id', $id))
            ->latest('order_created_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json($query);
    }
}
