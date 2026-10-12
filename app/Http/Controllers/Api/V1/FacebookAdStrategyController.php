<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Funnel\FacebookAds\SaveAdStrategyRequest;
use App\Models\FacebookAdAccount;
use App\Models\FacebookAdStrategy;
use App\Services\Funnel\FacebookAdStrategyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ad strategy ("skill") library for the Studio Ads Manager: playbooks,
 * launch presets, bundled rules, campaign tagging and per-strategy results.
 */
class FacebookAdStrategyController extends Controller
{
    public function __construct(protected FacebookAdStrategyService $strategies) {}

    protected function authorizeManage(Request $request): void
    {
        if (! in_array($request->user()?->role, ['admin', 'employee'], true)) {
            abort(403, 'Only admin and staff can manage ad strategies.');
        }
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeManage($request);

        $performance = $this->strategies->performance(30);

        $data = FacebookAdStrategy::query()
            ->withCount('rules')
            ->with('creator:id,name')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (FacebookAdStrategy $strategy) => array_merge(
                $strategy->only(['id', 'name', 'color', 'description', 'playbook', 'launch_preset', 'rule_presets', 'is_active', 'rules_count']),
                [
                    'created_by' => $strategy->creator?->name,
                    'stats_30d' => $performance[$strategy->id] ?? ['campaigns' => 0, 'spend' => 0, 'funnel_revenue' => 0, 'funnel_orders' => 0, 'funnel_roas' => null],
                ],
            ));

        return response()->json(['data' => $data]);
    }

    public function store(SaveAdStrategyRequest $request): JsonResponse
    {
        $strategy = FacebookAdStrategy::create(array_merge($request->validated(), ['created_by' => $request->user()->id]));

        return response()->json(['success' => true, 'data' => $strategy], 201);
    }

    public function update(SaveAdStrategyRequest $request, FacebookAdStrategy $strategy): JsonResponse
    {
        $strategy->update($request->validated());

        return response()->json(['success' => true, 'data' => $strategy->fresh()]);
    }

    public function destroy(Request $request, FacebookAdStrategy $strategy): JsonResponse
    {
        $this->authorizeManage($request);

        $strategy->delete();

        return response()->json(['success' => true]);
    }

    public function seedStarters(Request $request): JsonResponse
    {
        $this->authorizeManage($request);

        $created = $this->strategies->seedStarters($request->user());

        return response()->json(['success' => true, 'message' => $created ? "Added {$created} starter strateg".($created === 1 ? 'y' : 'ies').'.' : 'Starter strategies already exist.']);
    }

    /**
     * Create the strategy's bundled rules (dry run) on one ad account.
     */
    public function applyRules(Request $request, FacebookAdStrategy $strategy): JsonResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate(['account_id' => ['required', 'integer', 'exists:facebook_ad_accounts,id']]);
        $account = FacebookAdAccount::findOrFail($validated['account_id']);

        $created = $this->strategies->applyRules($strategy, $account, $request->user());

        return response()->json([
            'success' => true,
            'message' => $created
                ? "Created {$created} rule(s) in dry run for {$account->name}. They only act on campaigns tagged \"{$strategy->name}\"."
                : 'These rules already exist on this ad account.',
        ]);
    }

    /**
     * Tag (or untag) a campaign with a strategy.
     */
    public function tagCampaign(Request $request, FacebookAdAccount $account): JsonResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate([
            'campaign_id' => ['required', 'string', 'regex:/^\d{5,30}$/'],
            'campaign_name' => ['nullable', 'string', 'max:255'],
            'strategy_id' => ['nullable', 'integer', 'exists:facebook_ad_strategies,id'],
        ]);

        $this->strategies->tagCampaign($account, $validated['campaign_id'], $validated['strategy_id'] ?? null, $validated['campaign_name'] ?? null, $request->user());

        return response()->json([
            'success' => true,
            'strategy' => $this->strategies->strategiesFor([$validated['campaign_id']])[$validated['campaign_id']] ?? null,
        ]);
    }
}
