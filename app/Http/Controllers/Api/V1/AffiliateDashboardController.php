<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Funnel;
use App\Models\FunnelAffiliate;
use App\Models\FunnelSessionEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AffiliateDashboardController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string|max:50',
        ]);

        $phone = $this->normalizePhone($request->phone);
        $affiliate = FunnelAffiliate::where('phone', $phone)->first();

        if (! $affiliate) {
            return response()->json([
                'message' => 'No affiliate account found with this phone number.',
            ], 404);
        }

        if (! $affiliate->isActive()) {
            return response()->json([
                'message' => 'Your account is not active.',
            ], 403);
        }

        session(['affiliate_id' => $affiliate->id]);
        $affiliate->update(['last_login_at' => now()]);

        return response()->json([
            'affiliate' => $this->formatAffiliate($affiliate),
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'email' => 'nullable|email|max:255',
        ]);

        $phone = $this->normalizePhone($request->phone);

        if (FunnelAffiliate::where('phone', $phone)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'phone' => ['The phone has already been taken.'],
            ]);
        }

        $affiliate = FunnelAffiliate::create([
            'name' => $request->name,
            'phone' => $phone,
            'email' => $request->email,
            'last_login_at' => now(),
        ]);

        session(['affiliate_id' => $affiliate->id]);

        return response()->json([
            'affiliate' => $this->formatAffiliate($affiliate),
        ], 201);
    }

    public function logout(): JsonResponse
    {
        session()->forget('affiliate_id');

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request): JsonResponse
    {
        $affiliate = $request->affiliate;

        return response()->json([
            'affiliate' => $this->formatAffiliate($affiliate),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $affiliate = $request->affiliate;

        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'email' => 'nullable|email|max:255',
        ]);

        $phone = $this->normalizePhone($request->phone);

        $existing = FunnelAffiliate::where('phone', $phone)->where('id', '!=', $affiliate->id)->exists();
        if ($existing) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'phone' => ['The phone has already been taken.'],
            ]);
        }

        $affiliate->update([
            'name' => $request->name,
            'phone' => $phone,
            'email' => $request->email,
        ]);

        return response()->json([
            'affiliate' => $this->formatAffiliate($affiliate->fresh()),
        ]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $affiliate = $request->affiliate;

        $totalApproved = $affiliate->commissions()->approved()->sum('commission_amount');
        $totalPending = $affiliate->commissions()->pending()->sum('commission_amount');
        $totalPaid = $affiliate->commissions()->paid()->sum('commission_amount');
        $totalClicks = $affiliate->sessions()->count();
        $totalConversions = $affiliate->sessions()->converted()->count();

        $joinedFunnels = $affiliate->funnels()
            ->wherePivot('status', 'approved')
            ->where('funnels.status', 'published')
            ->get()
            ->map(function (Funnel $funnel) use ($affiliate) {
                $totalCommission = $affiliate->commissions()->where('funnel_id', $funnel->id)->approved()->sum('commission_amount');

                return [
                    'id' => $funnel->id,
                    'uuid' => $funnel->uuid,
                    'name' => $funnel->name,
                    'affiliate_url' => $affiliate->getAffiliateUrl($funnel),
                    'affiliate_path_url' => $affiliate->getAffiliatePathUrl($funnel),
                    'affiliate_custom_url' => $affiliate->getAffiliateCustomUrl($funnel),
                    'stats' => [
                        'total_commission' => (float) $totalCommission,
                    ],
                ];
            });

        return response()->json([
            'stats' => [
                'total_earned' => (float) $totalApproved,
                'pending' => (float) $totalPending,
                'total_approved' => (float) $totalApproved,
                'total_pending' => (float) $totalPending,
                'total_paid' => (float) $totalPaid,
                'total_clicks' => $totalClicks,
                'total_conversions' => $totalConversions,
            ],
            'joined_funnels' => $joinedFunnels,
        ]);
    }

    public function joinedFunnels(Request $request): JsonResponse
    {
        $affiliate = $request->affiliate;

        $funnels = $affiliate->funnels()
            ->wherePivot('status', 'approved')
            ->where('funnels.status', 'published')
            ->get()
            ->map(function (Funnel $funnel) use ($affiliate) {
                $sessionCount = $affiliate->sessions()->where('funnel_id', $funnel->id)->count();
                $conversions = $affiliate->sessions()->where('funnel_id', $funnel->id)->converted()->count();
                $totalCommission = $affiliate->commissions()->where('funnel_id', $funnel->id)->approved()->sum('commission_amount');
                $pendingCommission = $affiliate->commissions()->where('funnel_id', $funnel->id)->pending()->sum('commission_amount');

                return [
                    'id' => $funnel->id,
                    'uuid' => $funnel->uuid,
                    'name' => $funnel->name,
                    'slug' => $funnel->slug,
                    'description' => $funnel->description,
                    'affiliate_url' => $affiliate->getAffiliateUrl($funnel),
                    'affiliate_path_url' => $affiliate->getAffiliatePathUrl($funnel),
                    'stats' => [
                        'clicks' => $sessionCount,
                        'conversions' => $conversions,
                        'total_commission' => (float) $totalCommission,
                        'pending_commission' => (float) $pendingCommission,
                    ],
                ];
            });

        return response()->json(['funnels' => $funnels]);
    }

    public function discoverFunnels(Request $request): JsonResponse
    {
        $affiliate = $request->affiliate;

        $joinedFunnelIds = $affiliate->funnels()->pluck('funnels.id')->toArray();

        $funnels = Funnel::query()
            ->published()
            ->affiliateEnabled()
            ->get()
            ->map(function (Funnel $funnel) use ($joinedFunnelIds) {
                $rules = $funnel->affiliateCommissionRules()
                    ->with('funnelProduct')
                    ->get()
                    ->map(function ($rule) {
                        return [
                            'product_name' => $rule->funnelProduct?->getDisplayName() ?? 'Unknown',
                            'commission_type' => $rule->commission_type,
                            'commission_value' => (float) $rule->commission_value,
                        ];
                    });

                return [
                    'id' => $funnel->id,
                    'uuid' => $funnel->uuid,
                    'name' => $funnel->name,
                    'description' => $funnel->description,
                    'commission_rules' => $rules,
                    'joined' => in_array($funnel->id, $joinedFunnelIds),
                ];
            });

        return response()->json(['funnels' => $funnels]);
    }

    public function joinFunnel(Request $request, Funnel $funnel): JsonResponse
    {
        $affiliate = $request->affiliate;

        if (! $funnel->isPublished() || ! $funnel->isAffiliateEnabled()) {
            return response()->json([
                'message' => 'This funnel is not available for affiliates.',
            ], 403);
        }

        if ($affiliate->funnels()->where('funnels.id', $funnel->id)->exists()) {
            return response()->json([
                'message' => 'You have already joined this funnel.',
            ], 409);
        }

        $affiliate->funnels()->attach($funnel->id, [
            'status' => 'approved',
            'joined_at' => now(),
        ]);

        return response()->json([
            'message' => 'Successfully joined the affiliate program.',
            'affiliate_url' => $affiliate->getAffiliateUrl($funnel),
            'affiliate_path_url' => $affiliate->getAffiliatePathUrl($funnel),
        ]);
    }

    public function funnelStats(Request $request, Funnel $funnel): JsonResponse
    {
        $affiliate = $request->affiliate;

        if (! $affiliate->funnels()->where('funnels.id', $funnel->id)->exists()) {
            return response()->json(['message' => 'Not joined this funnel.'], 403);
        }

        $sessionIds = $affiliate->sessions()->where('funnel_id', $funnel->id)->pluck('id');
        $clicks = $sessionIds->count();
        $conversions = $clicks > 0
            ? $affiliate->sessions()->where('funnel_id', $funnel->id)->converted()->count()
            : 0;

        $thankyouClicks = $clicks > 0
            ? FunnelSessionEvent::whereIn('session_id', $sessionIds)
                ->where('event_type', 'page_view')
                ->whereHas('step', fn ($q) => $q->where('type', 'thankyou'))
                ->count()
            : 0;

        return response()->json([
            'funnel' => [
                'id' => $funnel->id,
                'name' => $funnel->name,
                'affiliate_url' => $affiliate->getAffiliateUrl($funnel),
                'affiliate_path_url' => $affiliate->getAffiliatePathUrl($funnel),
                'affiliate_custom_url' => $affiliate->getAffiliateCustomUrl($funnel),
            ],
            'stats' => [
                'clicks' => $clicks,
                'conversions' => $conversions,
                'thankyou_clicks' => $thankyouClicks,
            ],
        ]);
    }

    public function leaderboard(Request $request): JsonResponse
    {
        $affiliate = $request->affiliate;

        $joinedFunnelIds = $affiliate->funnels()
            ->wherePivot('status', 'approved')
            ->pluck('funnels.id')
            ->toArray();

        if (empty($joinedFunnelIds)) {
            return response()->json([
                'leaderboard' => [],
                'my_stats' => [
                    'views' => 0,
                    'checkout_fills' => 0,
                    'thankyou_clicks' => 0,
                    'rank' => null,
                ],
            ]);
        }

        $affiliates = FunnelAffiliate::whereHas('funnels', function ($q) use ($joinedFunnelIds) {
            $q->whereIn('funnels.id', $joinedFunnelIds)
                ->where('funnel_affiliate_funnels.status', 'approved');
        })->get();

        $leaderboard = $affiliates->map(function (FunnelAffiliate $aff) use ($joinedFunnelIds) {
            $sessionIds = $aff->sessions()
                ->whereIn('funnel_id', $joinedFunnelIds)
                ->pluck('id');

            $views = $sessionIds->count();

            $checkoutFills = $views > 0
                ? FunnelSessionEvent::whereIn('session_id', $sessionIds)
                    ->where('event_type', 'form_submit')
                    ->count()
                : 0;

            $thankyouClicks = $views > 0
                ? FunnelSessionEvent::whereIn('session_id', $sessionIds)
                    ->where('event_type', 'page_view')
                    ->whereHas('step', fn ($q) => $q->where('type', 'thankyou'))
                    ->count()
                : 0;

            return [
                'id' => $aff->id,
                'name' => $aff->name,
                'views' => $views,
                'checkout_fills' => $checkoutFills,
                'thankyou_clicks' => $thankyouClicks,
            ];
        })
            ->sortByDesc('views')
            ->values();

        $myRank = $leaderboard->search(fn ($item) => $item['id'] === $affiliate->id);
        $myEntry = $leaderboard->firstWhere('id', $affiliate->id);

        return response()->json([
            'leaderboard' => $leaderboard,
            'my_stats' => [
                'affiliate_id' => $affiliate->id,
                'views' => $myEntry['views'] ?? 0,
                'checkout_fills' => $myEntry['checkout_fills'] ?? 0,
                'thankyou_clicks' => $myEntry['thankyou_clicks'] ?? 0,
                'rank' => $myRank !== false ? $myRank + 1 : null,
            ],
        ]);
    }

    /**
     * Normalize a phone number to international format (+CCXXXXXXXXX).
     *
     * Accepts: "+60123456789", "60123456789", "0123456789" (defaults to +60).
     */
    private function normalizePhone(string $phone): string
    {
        $phone = trim($phone);

        // Already in international format with +
        if (str_starts_with($phone, '+')) {
            return $phone;
        }

        $digits = preg_replace('/\D/', '', $phone);

        // Starts with a known country code (2-3 digit codes starting with non-zero)
        // e.g. "60123456789", "65123456789", "1234567890"
        if ($digits !== '' && $digits[0] !== '0') {
            return '+'.$digits;
        }

        // Starts with 0 — assume Malaysian local number
        if (str_starts_with($digits, '0')) {
            return '+60'.substr($digits, 1);
        }

        return '+60'.$digits;
    }

    private function formatAffiliate(FunnelAffiliate $affiliate): array
    {
        return [
            'id' => $affiliate->id,
            'uuid' => $affiliate->uuid,
            'name' => $affiliate->name,
            'phone' => $affiliate->phone,
            'email' => $affiliate->email,
            'ref_code' => $affiliate->ref_code,
            'status' => $affiliate->status,
            'last_login_at' => $affiliate->last_login_at?->toISOString(),
            'created_at' => $affiliate->created_at->toISOString(),
        ];
    }
}
