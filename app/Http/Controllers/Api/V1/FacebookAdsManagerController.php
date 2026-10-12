<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Funnel\FacebookAds\LaunchCampaignRequest;
use App\Http\Requests\Funnel\FacebookAds\SaveAdRuleRequest;
use App\Http\Requests\Funnel\FacebookAds\UpdateAdBudgetRequest;
use App\Http\Requests\Funnel\FacebookAds\UpdateAdStatusRequest;
use App\Models\FacebookAdAccount;
use App\Models\FacebookAdAction;
use App\Models\FacebookAdRule;
use App\Models\Funnel;
use App\Services\Funnel\FacebookAdRuleEngine;
use App\Services\Funnel\FacebookAdsManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Funnel Studio "Ads Manager": run Facebook ads from the platform — browse
 * campaigns/ad sets/ads with FB + funnel ROAS, pause/resume, edit budgets,
 * launch new campaigns, and manage automation rules. Admin + employee only;
 * writes need a connection token with ads_management.
 */
class FacebookAdsManagerController extends Controller
{
    public function __construct(protected FacebookAdsManagerService $manager) {}

    protected function authorizeManage(Request $request): void
    {
        if (! in_array($request->user()?->role, ['admin', 'employee'], true)) {
            abort(403, 'Only admin and staff can manage Facebook ads.');
        }
    }

    public function accounts(Request $request): JsonResponse
    {
        $this->authorizeManage($request);

        $accounts = FacebookAdAccount::query()
            ->with('connection:id,name,can_manage_ads,status')
            ->orderBy('name')
            ->get()
            ->map(fn (FacebookAdAccount $account) => [
                'id' => $account->id,
                'account_id' => $account->account_id,
                'name' => $account->name,
                'currency' => $account->currency ?: 'MYR',
                'account_status' => $account->account_status,
                'connection_name' => $account->connection?->name,
                'can_manage' => $account->canManageAds(),
            ]);

        return response()->json(['data' => $accounts]);
    }

    public function entities(Request $request, FacebookAdAccount $account): JsonResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate([
            'level' => ['required', 'in:campaign,adset,ad'],
            'parent_id' => ['required_unless:level,campaign', 'nullable', 'string', 'regex:/^\d{5,30}$/'],
            'date_preset' => ['nullable', 'in:'.implode(',', FacebookAdsManagerService::DATE_PRESETS)],
        ]);

        try {
            $rows = $this->manager->listEntities($account->loadMissing('connection'), $validated['level'], $validated['parent_id'] ?? null, $validated['date_preset'] ?? 'last_7d');
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $rows]);
    }

    public function updateStatus(UpdateAdStatusRequest $request, FacebookAdAccount $account): JsonResponse
    {
        $validated = $request->validated();
        $result = $this->manager->updateStatus($account->loadMissing('connection'), $validated['object_type'], $validated['object_id'], $validated['status'], $request->user(), $validated['object_name'] ?? null);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function updateBudget(UpdateAdBudgetRequest $request, FacebookAdAccount $account): JsonResponse
    {
        $validated = $request->validated();
        $result = $this->manager->updateDailyBudget(
            $account->loadMissing('connection'),
            $validated['object_type'],
            $validated['object_id'],
            (float) $validated['daily_budget'],
            $request->user(),
            $validated['object_name'] ?? null,
            isset($validated['current_budget']) ? (float) $validated['current_budget'] : null,
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function launchOptions(Request $request, FacebookAdAccount $account): JsonResponse
    {
        $this->authorizeManage($request);

        $options = $this->manager->launchOptions($account->loadMissing('connection'));

        $funnels = Funnel::query()
            ->where('status', 'published')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'settings'])
            ->map(fn (Funnel $funnel) => [
                'id' => $funnel->id,
                'name' => $funnel->name,
                'url' => $this->manager->funnelLink($funnel),
                'pixel_id' => data_get($funnel->settings, 'pixel_settings.facebook.pixel_id'),
            ]);

        return response()->json(['data' => array_merge($options, [
            'funnels' => $funnels,
            'can_manage' => $account->canManageAds(),
            'currency' => $account->currency ?: 'MYR',
        ])]);
    }

    public function uploadImage(Request $request, FacebookAdAccount $account): JsonResponse
    {
        $this->authorizeManage($request);

        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:8192'],
        ]);

        if (! $account->loadMissing('connection')->canManageAds()) {
            return response()->json(['success' => false, 'message' => 'This Business Manager token is read-only (needs ads_management).'], 422);
        }

        $result = $this->manager->uploadImage($account, $request->file('image'));

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function launch(LaunchCampaignRequest $request, FacebookAdAccount $account): JsonResponse
    {
        if (! $account->loadMissing('connection')->canManageAds()) {
            return response()->json(['success' => false, 'message' => 'This Business Manager token is read-only (needs ads_management).'], 422);
        }

        $data = $request->validated();
        $data['advantage_audience'] = (bool) ($data['advantage_audience'] ?? true);

        $result = $this->manager->launch($account, $data, $request->user());

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'launch' => $result['launch']->only(['id', 'campaign_id', 'adset_id', 'ad_id', 'status']),
        ], $result['success'] ? 201 : 422);
    }

    /**
     * Activity log of every platform write (manual, launch, rule, dry-run).
     */
    public function actions(Request $request): JsonResponse
    {
        $this->authorizeManage($request);

        $actions = FacebookAdAction::query()
            ->with(['user:id,name', 'rule:id,name', 'adAccount:id,name'])
            ->when($request->integer('account_id'), fn ($q, $id) => $q->where('facebook_ad_account_id', $id))
            ->when($request->integer('rule_id'), fn ($q, $id) => $q->where('facebook_ad_rule_id', $id))
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (FacebookAdAction $action) => [
                'id' => $action->id,
                'created_at' => $action->created_at?->toIso8601String(),
                'source' => $action->source,
                'object_type' => $action->object_type,
                'object_id' => $action->object_id,
                'object_name' => $action->object_name,
                'action' => $action->action,
                'before' => $action->before,
                'after' => $action->after,
                'dry_run' => $action->dry_run,
                'success' => $action->success,
                'message' => $action->message,
                'user' => $action->user?->name,
                'rule' => $action->rule?->name,
                'account' => $action->adAccount?->name,
            ]);

        return response()->json(['data' => $actions]);
    }

    public function rules(Request $request): JsonResponse
    {
        $this->authorizeManage($request);

        $rules = FacebookAdRule::query()
            ->with(['adAccount:id,name,currency', 'creator:id,name'])
            ->latest('id')
            ->get()
            ->map(fn (FacebookAdRule $rule) => $this->ruleResource($rule));

        return response()->json(['data' => $rules]);
    }

    public function storeRule(SaveAdRuleRequest $request): JsonResponse
    {
        $rule = FacebookAdRule::create(array_merge($this->ruleAttributes($request), ['created_by' => $request->user()->id]));

        return response()->json(['success' => true, 'data' => $this->ruleResource($rule->load('adAccount', 'creator'))], 201);
    }

    public function updateRule(SaveAdRuleRequest $request, FacebookAdRule $rule): JsonResponse
    {
        $rule->update($this->ruleAttributes($request));

        return response()->json(['success' => true, 'data' => $this->ruleResource($rule->fresh(['adAccount', 'creator']))]);
    }

    public function destroyRule(Request $request, FacebookAdRule $rule): JsonResponse
    {
        $this->authorizeManage($request);

        $rule->delete();

        return response()->json(['success' => true]);
    }

    public function runRule(Request $request, FacebookAdRule $rule, FacebookAdRuleEngine $engine): JsonResponse
    {
        $this->authorizeManage($request);

        $result = $engine->run($rule);

        return response()->json(['success' => $result['errors'] === 0, 'message' => $result['summary'], 'result' => $result]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function ruleAttributes(SaveAdRuleRequest $request): array
    {
        $data = $request->validated();
        $data['min_spend'] = $data['min_spend'] ?? 0;
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['dry_run'] = (bool) ($data['dry_run'] ?? true);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected function ruleResource(FacebookAdRule $rule): array
    {
        return array_merge($rule->only([
            'id', 'facebook_ad_account_id', 'name', 'level', 'metric', 'operator', 'threshold', 'min_spend',
            'lookback_days', 'action', 'action_value', 'max_daily_budget', 'min_daily_budget', 'cooldown_hours',
            'is_active', 'dry_run', 'last_run_summary',
        ]), [
            'description' => $rule->describe(),
            'account_name' => $rule->adAccount?->name,
            'currency' => $rule->adAccount?->currency ?: 'MYR',
            'created_by' => $rule->creator?->name,
            'last_run_at' => $rule->last_run_at?->toIso8601String(),
        ]);
    }
}
