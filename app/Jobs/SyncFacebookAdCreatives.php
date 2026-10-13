<?php

namespace App\Jobs;

use App\Models\FacebookAdAccount;
use App\Models\FacebookAdCreative;
use App\Models\FacebookAdLevelInsight;
use App\Services\Funnel\Monitor\MetaAdLevelApi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Fetches thumbnail, copy and format for ads of one ad account that have spend in the last
 * 30 days but no creative stored yet (best effort; thumbnails older than a week refresh).
 */
class SyncFacebookAdCreatives implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $uniqueFor = 900;

    public function __construct(public int $accountId) {}

    public function uniqueId(): string
    {
        return 'creatives-'.$this->accountId;
    }

    public function handle(MetaAdLevelApi $api): void
    {
        $account = FacebookAdAccount::query()->with('connection')->find($this->accountId);

        if (! $account || blank($account->getRelationValue('connection')?->access_token)) {
            return;
        }

        $recentAds = FacebookAdLevelInsight::query()
            ->where('facebook_ad_account_id', $account->id)
            ->where('date', '>=', now()->subDays(30)->toDateString())
            ->distinct()->pluck('ad_id')->map(fn ($id) => (string) $id);

        $fresh = FacebookAdCreative::query()
            ->whereIn('ad_id', $recentAds)
            ->where('updated_at', '>=', now()->subDays(7))
            ->whereNotNull('thumbnail_url')
            ->pluck('ad_id')->map(fn ($id) => (string) $id);

        $missing = $recentAds->diff($fresh)->values()->all();

        if ($missing === []) {
            return;
        }

        foreach ($api->creatives($account, $missing) as $adId => $c) {
            FacebookAdCreative::updateOrCreate(['ad_id' => (string) $adId], [
                'facebook_ad_account_id' => $account->id,
                'creative_id' => $c['creative_id'],
                'asset_key' => $c['asset_key'],
                'format' => $c['format'],
                'status' => $c['status'],
                'title' => $c['title'],
                'body' => $c['body'],
                'cta' => $c['cta'],
                'permalink' => $c['permalink'],
                'thumbnail_url' => $c['image_url'],
            ]);
        }
    }
}
