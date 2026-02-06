<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Funnel;
use App\Models\FunnelAffiliate;
use App\Models\FunnelAffiliateCommission;
use App\Models\FunnelAffiliateCommissionRule;
use App\Models\FunnelSessionEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FunnelAffiliateController extends Controller
{
    public function index(Request $request, string $uuid): JsonResponse
    {
        $funnel = Funnel::where('uuid', $uuid)->firstOrFail();

        $affiliates = $funnel->affiliates()
            ->wherePivot('status', 'approved')
            ->get()
            ->map(function (FunnelAffiliate $affiliate) use ($funnel) {
                $sessionIds = $affiliate->sessions()
                    ->where('funnel_id', $funnel->id)
                    ->pluck('id');

                $views = $sessionIds->count();

                $checkoutFills = FunnelSessionEvent::whereIn('session_id', $sessionIds)
                    ->where('event_type', 'form_submit')
                    ->count();

                // TY Views: when thank you page is loaded
                $thankyouViews = FunnelSessionEvent::whereIn('session_id', $sessionIds)
                    ->where('event_type', 'page_view')
                    ->whereHas('step', function ($q) {
                        $q->where('type', 'thankyou');
                    })
                    ->count();

                // TY Clicks: when button on thank you page is clicked
                $thankyouClicks = FunnelSessionEvent::whereIn('session_id', $sessionIds)
                    ->where('event_type', 'thankyou_button_click')
                    ->count();

                $totalCommission = $affiliate->commissions()
                    ->where('funnel_id', $funnel->id)
                    ->whereIn('status', ['approved', 'paid'])
                    ->sum('commission_amount');

                $pendingCommission = $affiliate->commissions()
                    ->where('funnel_id', $funnel->id)
                    ->pending()
                    ->sum('commission_amount');

                return [
                    'id' => $affiliate->id,
                    'name' => $affiliate->name,
                    'phone' => $affiliate->phone,
                    'email' => $affiliate->email,
                    'ref_code' => $affiliate->ref_code,
                    'status' => $affiliate->status,
                    'joined_at' => $affiliate->pivot->joined_at,
                    'stats' => [
                        'views' => $views,
                        'checkout_fills' => $checkoutFills,
                        'thankyou_views' => $thankyouViews,
                        'thankyou_clicks' => $thankyouClicks,
                        'total_commission' => (float) $totalCommission,
                        'pending_commission' => (float) $pendingCommission,
                    ],
                ];
            });

        return response()->json(['affiliates' => $affiliates]);
    }

    public function affiliateStats(Request $request, string $uuid, int $affiliateId): JsonResponse
    {
        $funnel = Funnel::where('uuid', $uuid)->firstOrFail();
        $affiliate = FunnelAffiliate::findOrFail($affiliateId);

        $sessionIds = $affiliate->sessions()
            ->where('funnel_id', $funnel->id)
            ->pluck('id');

        $views = $sessionIds->count();

        $checkoutFills = FunnelSessionEvent::whereIn('session_id', $sessionIds)
            ->where('event_type', 'form_submit')
            ->count();

        // TY Views: when thank you page is loaded
        $thankyouViews = FunnelSessionEvent::whereIn('session_id', $sessionIds)
            ->where('event_type', 'page_view')
            ->whereHas('step', function ($q) {
                $q->where('type', 'thankyou');
            })
            ->count();

        // TY Clicks: when button on thank you page is clicked
        $thankyouClicks = FunnelSessionEvent::whereIn('session_id', $sessionIds)
            ->where('event_type', 'thankyou_button_click')
            ->count();

        $commissions = $affiliate->commissions()
            ->where('funnel_id', $funnel->id)
            ->with('funnelOrder')
            ->latest()
            ->get()
            ->map(function (FunnelAffiliateCommission $commission) {
                return [
                    'id' => $commission->id,
                    'order_amount' => (float) $commission->order_amount,
                    'commission_amount' => (float) $commission->commission_amount,
                    'commission_type' => $commission->commission_type,
                    'status' => $commission->status,
                    'created_at' => $commission->created_at->toISOString(),
                ];
            });

        return response()->json([
            'affiliate' => [
                'id' => $affiliate->id,
                'name' => $affiliate->name,
                'phone' => $affiliate->phone,
                'ref_code' => $affiliate->ref_code,
            ],
            'stats' => [
                'views' => $views,
                'checkout_fills' => $checkoutFills,
                'thankyou_views' => $thankyouViews,
                'thankyou_clicks' => $thankyouClicks,
                'total_commission' => (float) $affiliate->commissions()->where('funnel_id', $funnel->id)->whereIn('status', ['approved', 'paid'])->sum('commission_amount'),
                'pending_commission' => (float) $affiliate->commissions()->where('funnel_id', $funnel->id)->pending()->sum('commission_amount'),
            ],
            'commissions' => $commissions,
        ]);
    }

    public function settings(string $uuid): JsonResponse
    {
        $funnel = Funnel::where('uuid', $uuid)->firstOrFail();

        $rules = $funnel->affiliateCommissionRules()
            ->with('funnelProduct')
            ->get()
            ->map(function (FunnelAffiliateCommissionRule $rule) {
                return [
                    'id' => $rule->id,
                    'funnel_product_id' => $rule->funnel_product_id,
                    'product_name' => $rule->funnelProduct?->getDisplayName() ?? 'Unknown',
                    'commission_type' => $rule->commission_type,
                    'commission_value' => (float) $rule->commission_value,
                ];
            });

        // Get all products for this funnel (across all steps)
        $products = $funnel->steps()
            ->with('products')
            ->get()
            ->flatMap(function ($step) {
                return $step->products->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->getDisplayName(),
                        'price' => (float) $product->funnel_price,
                        'type' => $product->type,
                    ];
                });
            });

        return response()->json([
            'affiliate_enabled' => $funnel->affiliate_enabled,
            'affiliate_custom_url' => $funnel->affiliate_custom_url,
            'commission_rules' => $rules,
            'products' => $products,
        ]);
    }

    public function updateSettings(Request $request, string $uuid): JsonResponse
    {
        $funnel = Funnel::where('uuid', $uuid)->firstOrFail();

        $request->validate([
            'affiliate_enabled' => 'sometimes|boolean',
            'affiliate_custom_url' => 'sometimes|nullable|url|max:2048',
            'commission_rules' => 'sometimes|array',
            'commission_rules.*.funnel_product_id' => 'required_with:commission_rules|exists:funnel_products,id',
            'commission_rules.*.commission_type' => 'required_with:commission_rules|in:fixed,percentage',
            'commission_rules.*.commission_value' => 'required_with:commission_rules|numeric|min:0',
        ]);

        if ($request->has('affiliate_enabled')) {
            $funnel->update(['affiliate_enabled' => $request->affiliate_enabled]);
        }

        if ($request->has('affiliate_custom_url')) {
            $funnel->update(['affiliate_custom_url' => $request->affiliate_custom_url]);
        }

        if ($request->has('commission_rules')) {
            foreach ($request->commission_rules as $ruleData) {
                FunnelAffiliateCommissionRule::updateOrCreate(
                    [
                        'funnel_id' => $funnel->id,
                        'funnel_product_id' => $ruleData['funnel_product_id'],
                    ],
                    [
                        'commission_type' => $ruleData['commission_type'],
                        'commission_value' => $ruleData['commission_value'],
                    ]
                );
            }
        }

        return response()->json(['message' => 'Settings updated successfully.']);
    }

    public function commissions(Request $request, string $uuid): JsonResponse
    {
        $funnel = Funnel::where('uuid', $uuid)->firstOrFail();

        $query = $funnel->affiliateCommissions()->with(['affiliate', 'funnelOrder']);

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $commissions = $query->latest()->paginate(20);

        $commissions->getCollection()->transform(function (FunnelAffiliateCommission $commission) {
            return [
                'id' => $commission->id,
                'affiliate_name' => $commission->affiliate?->name,
                'affiliate_phone' => $commission->affiliate?->phone,
                'affiliate_ref_code' => $commission->affiliate?->ref_code,
                'order_amount' => (float) $commission->order_amount,
                'commission_amount' => (float) $commission->commission_amount,
                'commission_type' => $commission->commission_type,
                'commission_rate' => (float) $commission->commission_rate,
                'status' => $commission->status,
                'approved_at' => $commission->approved_at?->toISOString(),
                'paid_at' => $commission->paid_at?->toISOString(),
                'notes' => $commission->notes,
                'created_at' => $commission->created_at->toISOString(),
            ];
        });

        return response()->json($commissions);
    }

    public function approveCommission(Request $request, string $uuid, int $commissionId): JsonResponse
    {
        $funnel = Funnel::where('uuid', $uuid)->firstOrFail();
        $commission = FunnelAffiliateCommission::where('funnel_id', $funnel->id)->findOrFail($commissionId);

        if (! $commission->isPending()) {
            return response()->json(['message' => 'Commission is not pending.'], 422);
        }

        $commission->approve($request->user()->id);

        return response()->json(['message' => 'Commission approved.']);
    }

    public function rejectCommission(Request $request, string $uuid, int $commissionId): JsonResponse
    {
        $funnel = Funnel::where('uuid', $uuid)->firstOrFail();
        $commission = FunnelAffiliateCommission::where('funnel_id', $funnel->id)->findOrFail($commissionId);

        if (! $commission->isPending()) {
            return response()->json(['message' => 'Commission is not pending.'], 422);
        }

        $commission->reject($request->user()->id, $request->input('notes'));

        return response()->json(['message' => 'Commission rejected.']);
    }

    public function bulkApprove(Request $request, string $uuid): JsonResponse
    {
        $funnel = Funnel::where('uuid', $uuid)->firstOrFail();

        $request->validate([
            'commission_ids' => 'required|array',
            'commission_ids.*' => 'integer',
        ]);

        $userId = $request->user()->id;

        FunnelAffiliateCommission::where('funnel_id', $funnel->id)
            ->whereIn('id', $request->commission_ids)
            ->where('status', 'pending')
            ->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $userId,
            ]);

        return response()->json(['message' => 'Commissions approved.']);
    }
}
