<?php

namespace App\Services\Funnel;

use App\Models\FacebookAdAccount;
use App\Models\FacebookAdAction;
use App\Models\FacebookAdLaunch;
use App\Models\Funnel;
use App\Models\FunnelOrder;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Write side of the Facebook Ads integration: live campaign / ad set / ad
 * listing with performance, pause + resume, daily budget edits, image
 * upload and full campaign launches. Every write is recorded in
 * facebook_ad_actions. Requires a token with ads_management.
 *
 * Funnel attribution: ads launched from the Studio carry url_tags
 * utm_campaign={{campaign.id}}, utm_term={{adset.id}}, utm_content={{ad.id}},
 * so funnel_sessions can be joined back to each Facebook object.
 */
class FacebookAdsManagerService
{
    protected const API_VERSION = 'v21.0';

    protected const BASE_URL = 'https://graph.facebook.com';

    public const URL_TAGS = 'utm_source=facebook&utm_medium=paid&utm_campaign={{campaign.id}}&utm_term={{adset.id}}&utm_content={{ad.id}}';

    public const DATE_PRESETS = ['today', 'yesterday', 'last_3d', 'last_7d', 'last_14d', 'last_30d'];

    /** Currencies Facebook expresses without minor units. */
    protected const ZERO_DECIMAL_CURRENCIES = ['CLP', 'COP', 'CRC', 'HUF', 'ISK', 'IDR', 'JPY', 'KRW', 'PYG', 'TWD', 'VND'];

    protected const VISIBLE_STATUSES = ['ACTIVE', 'PAUSED', 'CAMPAIGN_PAUSED', 'ADSET_PAUSED', 'IN_PROCESS', 'WITH_ISSUES', 'PENDING_REVIEW', 'DISAPPROVED', 'PREAPPROVED', 'PENDING_BILLING_INFO'];

    protected const INSIGHT_FIELDS = 'spend,impressions,clicks,ctr,cpc,actions,action_values';

    protected const PURCHASE_ACTION_TYPES = ['omni_purchase', 'purchase', 'offsite_conversion.fb_pixel_purchase'];

    /** utm field each object type's id is tagged into. */
    protected const UTM_FIELD = ['campaign' => 'utm_campaign', 'adset' => 'utm_term', 'ad' => 'utm_content'];

    /**
     * Campaigns of an account, ad sets of a campaign, or ads of an ad set,
     * each with performance for the date preset (FB-reported and funnel).
     *
     * @return array<int, array<string, mixed>>
     */
    public function listEntities(FacebookAdAccount $account, string $level, ?string $parentId, string $datePreset = 'last_7d'): array
    {
        $datePreset = in_array($datePreset, self::DATE_PRESETS, true) ? $datePreset : 'last_7d';

        [$edge, $fields] = match ($level) {
            'campaign' => ["/act_{$account->account_id}/campaigns", 'id,name,status,effective_status,objective,daily_budget,lifetime_budget'],
            'adset' => ["/{$parentId}/adsets", 'id,name,status,effective_status,campaign_id,daily_budget,lifetime_budget,optimization_goal'],
            'ad' => ["/{$parentId}/ads", 'id,name,status,effective_status,adset_id,campaign_id,creative{thumbnail_url}'],
            default => throw new RuntimeException("Unknown level {$level}."),
        };

        $rows = $this->paginate($account, $edge, [
            'fields' => $fields.",insights.date_preset({$datePreset}){".self::INSIGHT_FIELDS.'}',
            'filtering' => json_encode([['field' => 'effective_status', 'operator' => 'IN', 'value' => self::VISIBLE_STATUSES]]),
            'limit' => 100,
        ]);

        $since = $this->presetSince($datePreset);
        $until = $datePreset === 'yesterday' ? now()->startOfDay() : null;
        $funnel = $this->funnelRevenue($level, collect($rows)->pluck('id')->map(fn ($id) => (string) $id)->all(), $since, $until);
        $divisor = $this->minorUnitDivisor($account);

        return collect($rows)
            ->map(function (array $row) use ($level, $funnel, $divisor): array {
                $metrics = $this->normaliseInsight(data_get($row, 'insights.data.0', []));
                $id = (string) $row['id'];
                $funnelRevenue = (float) ($funnel[$id]['revenue'] ?? 0);

                return array_merge([
                    'id' => $id,
                    'level' => $level,
                    'name' => $row['name'] ?? $id,
                    'status' => $row['status'] ?? null,
                    'effective_status' => $row['effective_status'] ?? null,
                    'objective' => $row['objective'] ?? null,
                    'optimization_goal' => $row['optimization_goal'] ?? null,
                    'daily_budget' => isset($row['daily_budget']) ? ((int) $row['daily_budget']) / $divisor : null,
                    'lifetime_budget' => isset($row['lifetime_budget']) ? ((int) $row['lifetime_budget']) / $divisor : null,
                    'thumbnail' => data_get($row, 'creative.thumbnail_url'),
                    'funnel_revenue' => $funnelRevenue,
                    'funnel_orders' => (int) ($funnel[$id]['orders'] ?? 0),
                    'funnel_roas' => $metrics['spend'] > 0 ? round($funnelRevenue / $metrics['spend'], 2) : null,
                ], $metrics);
            })
            ->sortBy([
                fn ($a, $b) => ($a['effective_status'] === 'ACTIVE' ? 0 : 1) <=> ($b['effective_status'] === 'ACTIVE' ? 0 : 1),
                fn ($a, $b) => $b['spend'] <=> $a['spend'],
            ])
            ->values()
            ->all();
    }

    /**
     * Turn a raw insights row into flat numbers (spend, purchases, FB ROAS…).
     *
     * @param  array<string, mixed>  $row
     * @return array{spend: float, impressions: int, clicks: int, ctr: ?float, cpc: ?float, purchases: int, purchase_value: float, fb_roas: ?float, cpa: ?float}
     */
    public function normaliseInsight(array $row): array
    {
        $spend = (float) ($row['spend'] ?? 0);
        $purchases = (int) $this->pickAction($row['actions'] ?? []);
        $value = (float) $this->pickAction($row['action_values'] ?? []);

        return [
            'spend' => $spend,
            'impressions' => (int) ($row['impressions'] ?? 0),
            'clicks' => (int) ($row['clicks'] ?? 0),
            'ctr' => isset($row['ctr']) ? round((float) $row['ctr'], 2) : null,
            'cpc' => isset($row['cpc']) ? round((float) $row['cpc'], 2) : null,
            'purchases' => $purchases,
            'purchase_value' => $value,
            'fb_roas' => $spend > 0 ? round($value / $spend, 2) : null,
            'cpa' => $purchases > 0 ? round($spend / $purchases, 2) : null,
        ];
    }

    /**
     * Funnel revenue + orders attributed (via UTM tags) to each object id.
     *
     * @param  array<int, string>  $ids
     * @return array<string, array{revenue: float, orders: int}>
     */
    public function funnelRevenue(string $level, array $ids, Carbon $since, ?Carbon $until = null): array
    {
        $field = self::UTM_FIELD[$level] ?? null;

        if (! $field || empty($ids)) {
            return [];
        }

        return FunnelOrder::query()
            ->join('funnel_sessions', 'funnel_sessions.id', '=', 'funnel_orders.session_id')
            ->whereIn("funnel_sessions.{$field}", $ids)
            ->where('funnel_orders.created_at', '>=', $since)
            ->when($until, fn ($q) => $q->where('funnel_orders.created_at', '<', $until))
            ->selectRaw("funnel_sessions.{$field} as object_id, SUM(funnel_orders.funnel_revenue) as revenue, COUNT(*) as orders")
            ->groupBy("funnel_sessions.{$field}")
            ->get()
            ->mapWithKeys(fn ($row) => [(string) $row->object_id => ['revenue' => (float) $row->revenue, 'orders' => (int) $row->orders]])
            ->all();
    }

    /**
     * Pause or resume a campaign / ad set / ad.
     *
     * @param  array{source?: string, rule_id?: int|null, dry_run?: bool}  $context
     * @return array{success: bool, message: string}
     */
    public function updateStatus(FacebookAdAccount $account, string $objectType, string $objectId, string $status, ?User $user, ?string $name = null, array $context = []): array
    {
        $status = strtoupper($status);
        if (! in_array($status, ['ACTIVE', 'PAUSED'], true)) {
            return ['success' => false, 'message' => 'Status must be ACTIVE or PAUSED.'];
        }

        $verb = $status === 'ACTIVE' ? 'Activated' : 'Paused';

        return $this->write($account, $objectType, $objectId, 'status', $user, $name, $context,
            before: null,
            after: ['status' => $status],
            params: ['status' => $status],
            successMessage: "{$verb} {$objectType} \"".($name ?? $objectId).'".',
        );
    }

    /**
     * Change the daily budget (in major units, e.g. RM) of a campaign or ad set.
     *
     * @param  array{source?: string, rule_id?: int|null, dry_run?: bool}  $context
     * @return array{success: bool, message: string}
     */
    public function updateDailyBudget(FacebookAdAccount $account, string $objectType, string $objectId, float $dailyBudget, ?User $user, ?string $name = null, ?float $currentBudget = null, array $context = []): array
    {
        if (! in_array($objectType, ['campaign', 'adset'], true)) {
            return ['success' => false, 'message' => 'Only campaigns and ad sets have budgets.'];
        }

        if ($dailyBudget < 1) {
            return ['success' => false, 'message' => 'Daily budget must be at least 1.'];
        }

        $minor = (int) round($dailyBudget * $this->minorUnitDivisor($account));
        $currency = $account->currency ?: 'MYR';

        return $this->write($account, $objectType, $objectId, 'budget', $user, $name, $context,
            before: $currentBudget !== null ? ['daily_budget' => $currentBudget] : null,
            after: ['daily_budget' => $dailyBudget],
            params: ['daily_budget' => $minor],
            successMessage: 'Daily budget of "'.($name ?? $objectId)."\" set to {$currency} ".number_format($dailyBudget, 2).'.',
        );
    }

    /**
     * Current daily budget (major units) of one object; null when it has none
     * (e.g. an ad set under a campaign-budget campaign).
     */
    public function currentDailyBudget(FacebookAdAccount $account, string $objectId): ?float
    {
        $response = $this->client($account)->get($this->url("/{$objectId}"), ['fields' => 'daily_budget']);

        if (! $response->successful() || ! $response->json('daily_budget')) {
            return null;
        }

        return ((int) $response->json('daily_budget')) / $this->minorUnitDivisor($account);
    }

    /**
     * Pages + pixels the account can use, for the launch wizard.
     *
     * @return array{pages: array<int, array{id: string, name: string, picture: ?string}>, pixels: array<int, array{id: string, name: string}>}
     */
    public function launchOptions(FacebookAdAccount $account): array
    {
        $pages = $this->client($account)->get($this->url("/act_{$account->account_id}/promote_pages"), [
            'fields' => 'id,name,picture{url}',
            'limit' => 100,
        ]);

        $pixels = $this->client($account)->get($this->url("/act_{$account->account_id}/adspixels"), [
            'fields' => 'id,name',
            'limit' => 100,
        ]);

        return [
            'pages' => collect($pages->successful() ? $pages->json('data', []) : [])
                ->map(fn ($p) => ['id' => (string) $p['id'], 'name' => $p['name'] ?? $p['id'], 'picture' => data_get($p, 'picture.data.url')])
                ->values()->all(),
            'pixels' => collect($pixels->successful() ? $pixels->json('data', []) : [])
                ->map(fn ($p) => ['id' => (string) $p['id'], 'name' => $p['name'] ?? $p['id']])
                ->values()->all(),
        ];
    }

    /**
     * Upload an image to the account's ad image library.
     *
     * @return array{success: bool, message: string, hash?: string, url?: string}
     */
    public function uploadImage(FacebookAdAccount $account, UploadedFile $file): array
    {
        $response = $this->client($account)->asForm()->post($this->url("/act_{$account->account_id}/adimages"), [
            'bytes' => base64_encode((string) file_get_contents($file->getRealPath())),
        ]);

        if (! $response->successful()) {
            return ['success' => false, 'message' => $this->graphError($response, 'Image upload failed.')];
        }

        $image = collect($response->json('images', []))->first();

        if (! $image || empty($image['hash'])) {
            return ['success' => false, 'message' => 'Facebook did not return an image hash.'];
        }

        return ['success' => true, 'message' => 'Image uploaded.', 'hash' => $image['hash'], 'url' => $image['url'] ?? null];
    }

    /**
     * Create campaign (CBO) → ad set → creative → ad, all PAUSED. If any
     * step fails the campaign is deleted so nothing half-built is left behind.
     *
     * @param  array{name: string, objective: string, funnel_id?: int|null, link: string, daily_budget: float, page_id: string, pixel_id?: string|null, countries: array<int, string>, age_min: int, age_max: int, advantage_audience: bool, primary_text: string, headline: string, description?: string|null, call_to_action: string, image_hash: string}  $data
     * @return array{success: bool, message: string, launch: FacebookAdLaunch}
     */
    public function launch(FacebookAdAccount $account, array $data, ?User $user): array
    {
        $launch = FacebookAdLaunch::create([
            'facebook_ad_account_id' => $account->id,
            'funnel_id' => $data['funnel_id'] ?? null,
            'facebook_ad_strategy_id' => $data['facebook_ad_strategy_id'] ?? null,
            'user_id' => $user?->id,
            'name' => $data['name'],
            'objective' => $data['objective'],
            'status' => 'pending',
            'payload' => $data,
        ]);

        $isSales = $data['objective'] === 'OUTCOME_SALES';
        $act = "/act_{$account->account_id}";

        try {
            $campaignId = $this->createNode($account, "{$act}/campaigns", [
                'name' => $data['name'],
                'objective' => $data['objective'],
                'status' => 'PAUSED',
                'special_ad_categories' => json_encode([]),
                'daily_budget' => (int) round($data['daily_budget'] * $this->minorUnitDivisor($account)),
                'bid_strategy' => 'LOWEST_COST_WITHOUT_CAP',
            ], 'campaign');
            $launch->update(['campaign_id' => $campaignId]);

            $targeting = [
                'geo_locations' => ['countries' => array_values($data['countries'])],
                'age_min' => (int) $data['age_min'],
                'age_max' => (int) $data['age_max'],
                'targeting_automation' => ['advantage_audience' => $data['advantage_audience'] ? 1 : 0],
            ];

            $adsetParams = [
                'name' => $data['name'].' — Ad Set',
                'campaign_id' => $campaignId,
                'status' => 'PAUSED',
                'billing_event' => 'IMPRESSIONS',
                'optimization_goal' => $isSales ? 'OFFSITE_CONVERSIONS' : 'LANDING_PAGE_VIEWS',
                'destination_type' => 'WEBSITE',
                'targeting' => json_encode($targeting),
            ];
            if ($isSales) {
                $adsetParams['promoted_object'] = json_encode(['pixel_id' => $data['pixel_id'], 'custom_event_type' => 'PURCHASE']);
            }
            $adsetId = $this->createNode($account, "{$act}/adsets", $adsetParams, 'ad set');
            $launch->update(['adset_id' => $adsetId]);

            $creativeId = $this->createNode($account, "{$act}/adcreatives", [
                'name' => $data['name'].' — Creative',
                'object_story_spec' => json_encode([
                    'page_id' => $data['page_id'],
                    'link_data' => array_filter([
                        'link' => $data['link'],
                        'message' => $data['primary_text'],
                        'name' => $data['headline'],
                        'description' => $data['description'] ?? null,
                        'image_hash' => $data['image_hash'],
                        'call_to_action' => ['type' => $data['call_to_action'], 'value' => ['link' => $data['link']]],
                    ]),
                ]),
                'url_tags' => self::URL_TAGS,
            ], 'creative');
            $launch->update(['creative_id' => $creativeId]);

            $adId = $this->createNode($account, "{$act}/ads", [
                'name' => $data['name'].' — Ad',
                'adset_id' => $adsetId,
                'creative' => json_encode(['creative_id' => $creativeId]),
                'status' => 'PAUSED',
            ], 'ad');
            $launch->update(['ad_id' => $adId, 'status' => 'created']);
        } catch (RuntimeException $e) {
            if ($launch->campaign_id) {
                $this->client($account)->delete($this->url("/{$launch->campaign_id}"));
            }
            $launch->update(['status' => 'failed', 'error' => $e->getMessage()]);
            $this->log($account, $user, 'campaign', (string) ($launch->campaign_id ?? 'new'), $data['name'], 'launch', null, null, false, $e->getMessage(), ['source' => 'launch']);

            return ['success' => false, 'message' => $e->getMessage(), 'launch' => $launch];
        }

        $this->log($account, $user, 'campaign', $campaignId, $data['name'], 'launch', null, [
            'campaign_id' => $campaignId, 'adset_id' => $adsetId, 'ad_id' => $adId, 'daily_budget' => (float) $data['daily_budget'],
        ], true, 'Campaign created (paused) — review and activate it in Ads Manager.', ['source' => 'launch']);

        return ['success' => true, 'message' => "Campaign \"{$data['name']}\" created as PAUSED. Review it, then switch it on.", 'launch' => $launch];
    }

    /**
     * Public URL a launched ad should point at for a funnel.
     */
    public function funnelLink(Funnel $funnel): string
    {
        return $funnel->getPublicUrl();
    }

    public function minorUnitDivisor(FacebookAdAccount $account): int
    {
        return in_array(strtoupper((string) $account->currency), self::ZERO_DECIMAL_CURRENCIES, true) ? 1 : 100;
    }

    public function presetSince(string $datePreset): Carbon
    {
        return match ($datePreset) {
            'today' => now()->startOfDay(),
            'yesterday' => now()->subDay()->startOfDay(),
            'last_3d' => now()->subDays(3)->startOfDay(),
            'last_14d' => now()->subDays(14)->startOfDay(),
            'last_30d' => now()->subDays(30)->startOfDay(),
            default => now()->subDays(7)->startOfDay(),
        };
    }

    /**
     * Insights rows for every object at a level, for rule evaluation.
     *
     * @param  array<int, string>  $effectiveStatuses
     * @return array<int, array<string, mixed>>
     */
    public function levelInsights(FacebookAdAccount $account, string $level, Carbon $since, array $effectiveStatuses): array
    {
        return $this->paginate($account, "/act_{$account->account_id}/insights", [
            'level' => $level,
            'fields' => ($level === 'campaign' ? '' : 'campaign_id,')."{$level}_id,{$level}_name,".self::INSIGHT_FIELDS,
            'time_range' => json_encode(['since' => $since->toDateString(), 'until' => now()->toDateString()]),
            'filtering' => json_encode([['field' => "{$level}.effective_status", 'operator' => 'IN', 'value' => $effectiveStatuses]]),
            'limit' => 500,
        ]);
    }

    /**
     * Shared write path: dry-run short-circuit, POST, audit log.
     *
     * @param  array{source?: string, rule_id?: int|null, dry_run?: bool}  $context
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     * @param  array<string, mixed>  $params
     * @return array{success: bool, message: string}
     */
    protected function write(FacebookAdAccount $account, string $objectType, string $objectId, string $action, ?User $user, ?string $name, array $context, ?array $before, ?array $after, array $params, string $successMessage): array
    {
        if (! empty($context['dry_run'])) {
            $message = '[Dry run] Would have: '.$successMessage;
            $this->log($account, $user, $objectType, $objectId, $name, $action, $before, $after, true, $message, $context);

            return ['success' => true, 'message' => $message];
        }

        if (! $account->canManageAds()) {
            $message = 'This Business Manager token is read-only. Re-issue the System User token with ads_management, then edit the connection.';
            $this->log($account, $user, $objectType, $objectId, $name, $action, $before, $after, false, $message, $context);

            return ['success' => false, 'message' => $message];
        }

        try {
            $response = $this->client($account)->asForm()->post($this->url("/{$objectId}"), $params);
        } catch (\Throwable $e) {
            $response = null;
            $error = $e->getMessage();
        }

        $success = $response?->successful() && $response->json('success') !== false;
        $message = $success ? $successMessage : ($response ? $this->graphError($response, 'Facebook rejected the change.') : ($error ?? 'Request failed.'));

        $this->log($account, $user, $objectType, $objectId, $name, $action, $before, $after, $success, $message, $context);

        return ['success' => $success, 'message' => $message];
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected function createNode(FacebookAdAccount $account, string $edge, array $params, string $label): string
    {
        $response = $this->client($account)->asForm()->post($this->url($edge), $params);

        if (! $response->successful() || ! $response->json('id')) {
            $detail = $response->json('error.error_user_msg') ?: $this->graphError($response, 'unknown error');

            throw new RuntimeException("Could not create {$label}: {$detail}");
        }

        return (string) $response->json('id');
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     * @param  array{source?: string, rule_id?: int|null, dry_run?: bool}  $context
     */
    protected function log(FacebookAdAccount $account, ?User $user, string $objectType, string $objectId, ?string $name, string $action, ?array $before, ?array $after, bool $success, string $message, array $context): void
    {
        FacebookAdAction::create([
            'facebook_ad_account_id' => $account->id,
            'user_id' => $user?->id,
            'facebook_ad_rule_id' => $context['rule_id'] ?? null,
            'source' => $context['source'] ?? 'manual',
            'object_type' => $objectType,
            'object_id' => $objectId,
            'object_name' => $name,
            'action' => $action,
            'before' => $before,
            'after' => $after,
            'dry_run' => (bool) ($context['dry_run'] ?? false),
            'success' => $success,
            'message' => $message,
        ]);
    }

    /**
     * Follow Graph paging (capped) and return every data row.
     *
     * @param  array<string, mixed>  $params
     * @return array<int, array<string, mixed>>
     */
    protected function paginate(FacebookAdAccount $account, string $edge, array $params, int $maxPages = 10): array
    {
        $rows = [];
        $after = null;
        $page = 0;

        do {
            $response = $this->client($account)->get($this->url($edge), array_filter(array_merge($params, ['after' => $after])));

            if (! $response->successful()) {
                throw new RuntimeException($this->graphError($response, 'Facebook request failed.'));
            }

            array_push($rows, ...$response->json('data', []));
            $after = $response->json('paging.cursors.after');
            $hasNext = (bool) $response->json('paging.next');
            $page++;
        } while ($after && $hasNext && $page < $maxPages);

        return $rows;
    }

    /**
     * @param  array<int, array{action_type?: string, value?: string|float}>  $actions
     */
    protected function pickAction(array $actions): float
    {
        $byType = collect($actions)->keyBy('action_type');

        foreach (self::PURCHASE_ACTION_TYPES as $type) {
            if ($byType->has($type)) {
                return (float) $byType[$type]['value'];
            }
        }

        return 0.0;
    }

    protected function client(FacebookAdAccount $account): PendingRequest
    {
        return Http::timeout(60)->withQueryParameters(['access_token' => $account->connection?->access_token]);
    }

    protected function url(string $path): string
    {
        return self::BASE_URL.'/'.self::API_VERSION.$path;
    }

    protected function graphError(Response $response, string $fallback): string
    {
        return $response->json('error.error_user_msg') ?: ($response->json('error.message') ?? $fallback);
    }
}
