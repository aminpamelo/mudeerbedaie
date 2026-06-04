<?php

namespace App\Console\Commands;

use App\Models\ActualLiveRecord;
use App\Models\LiveAccount;
use App\Models\LiveHostPlatformAccount;
use App\Models\TiktokLiveReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ConsolidateLiveAccounts extends Command
{
    protected $signature = 'livehost:consolidate-live-accounts {--dry-run : Preview the result without writing anything}';

    protected $description = 'Build canonical live_accounts (creator nicknames) from actual_live_records, tiktok_live_reports and the live_host_platform_account pivot, deduping by creator id with a normalized-handle fallback.';

    /**
     * Aggregated per-account state, keyed by a synthetic bucket id.
     *
     * @var array<string, array{
     *     creator_user_id: ?string,
     *     nicknames: array<string,int>,
     *     displays: array<string,int>,
     *     shops: array<int,int>,
     *     hosts: array<int,bool>,
     *     handleOnly: bool
     * }>
     */
    private array $buckets = [];

    /**
     * normalized handle => creator_user_id, learned from rows that carry both.
     *
     * @var array<string, array<string,int>>
     */
    private array $handleToId = [];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $this->learnHandleToIdMap();
        $this->collectSightings();

        if (empty($this->buckets)) {
            $this->warn('No creator sightings found in any source. Nothing to consolidate.');

            return self::SUCCESS;
        }

        $resolved = $this->resolveBuckets();

        $this->renderPreview($resolved);

        if ($dry) {
            $this->newLine();
            $this->info('Dry run — no changes written. Re-run without --dry-run to persist.');

            return self::SUCCESS;
        }

        DB::transaction(fn () => $this->persist($resolved));

        $this->newLine();
        $this->info('Consolidation complete.');

        return self::SUCCESS;
    }

    /**
     * Pass 1: from every source row that has BOTH a numeric creator id and a
     * handle, record which creator id each normalized handle maps to (with a
     * vote count, so we can resolve conflicts in favour of the majority).
     */
    private function learnHandleToIdMap(): void
    {
        $vote = function (?string $id, ?string $handle): void {
            $id = $this->str($id);
            $norm = LiveAccount::normalizeHandle($handle);
            if ($id === null || $norm === null) {
                return;
            }
            $this->handleToId[$norm][$id] = ($this->handleToId[$norm][$id] ?? 0) + 1;
        };

        ActualLiveRecord::query()
            ->selectRaw('creator_platform_user_id as id, creator_handle as handle, COUNT(*) as c')
            ->groupBy('creator_platform_user_id', 'creator_handle')
            ->get()
            ->each(fn ($r) => $vote($r->id, $r->handle));

        TiktokLiveReport::query()
            ->selectRaw('tiktok_creator_id as id, creator_nickname as handle, COUNT(*) as c')
            ->groupBy('tiktok_creator_id', 'creator_nickname')
            ->get()
            ->each(fn ($r) => $vote($r->id, $r->handle));

        LiveHostPlatformAccount::query()
            ->get(['creator_platform_user_id', 'creator_handle'])
            ->each(fn ($r) => $vote($r->creator_platform_user_id, $r->creator_handle));
    }

    /**
     * Pass 2: walk all source rows and accumulate each sighting into a bucket
     * keyed by creator id (resolving handle-only rows through the learned map,
     * else bucketing under the normalized handle).
     */
    private function collectSightings(): void
    {
        ActualLiveRecord::query()
            ->selectRaw('creator_platform_user_id as id, creator_handle as nickname, NULL as display, platform_account_id as shop, NULL as host, COUNT(*) as c')
            ->groupBy('creator_platform_user_id', 'creator_handle', 'platform_account_id')
            ->get()
            ->each(fn ($r) => $this->addSighting($r->id, $r->nickname, null, $r->shop, null, (int) $r->c));

        TiktokLiveReport::query()
            ->selectRaw('tiktok_creator_id as id, creator_nickname as nickname, creator_display_name as display, platform_account_id as shop, NULL as host, COUNT(*) as c')
            ->groupBy('tiktok_creator_id', 'creator_nickname', 'creator_display_name', 'platform_account_id')
            ->get()
            ->each(fn ($r) => $this->addSighting($r->id, $r->nickname, $r->display, $r->shop, null, (int) $r->c));

        LiveHostPlatformAccount::query()
            ->get(['creator_platform_user_id', 'creator_handle', 'platform_account_id', 'user_id'])
            ->each(fn ($r) => $this->addSighting(
                $r->creator_platform_user_id,
                $r->creator_handle,
                $r->creator_handle,
                $r->platform_account_id,
                $r->user_id,
                1
            ));
    }

    private function addSighting(?string $id, ?string $nickname, ?string $display, ?int $shop, ?int $host, int $count): void
    {
        $id = $this->str($id);
        $norm = LiveAccount::normalizeHandle($nickname);

        $handleOnly = false;
        if ($id !== null) {
            $key = 'id:'.$id;
        } elseif ($norm !== null && isset($this->handleToId[$norm])) {
            $id = $this->majority($this->handleToId[$norm]);
            $key = 'id:'.$id;
        } elseif ($norm !== null) {
            $key = 'handle:'.$norm;
            $handleOnly = true;
        } else {
            return; // no usable identity
        }

        if (! isset($this->buckets[$key])) {
            $this->buckets[$key] = [
                'creator_user_id' => $id,
                'nicknames' => [],
                'displays' => [],
                'shops' => [],
                'hosts' => [],
                'handleOnly' => $handleOnly,
            ];
        }

        $bucket = &$this->buckets[$key];

        if ($bucket['creator_user_id'] === null && $id !== null) {
            $bucket['creator_user_id'] = $id;
            $bucket['handleOnly'] = false;
        }
        if ($nickname !== null && trim($nickname) !== '') {
            $bucket['nicknames'][trim($nickname)] = ($bucket['nicknames'][trim($nickname)] ?? 0) + $count;
        }
        if ($display !== null && trim($display) !== '') {
            $bucket['displays'][trim($display)] = ($bucket['displays'][trim($display)] ?? 0) + $count;
        }
        if ($shop !== null) {
            $bucket['shops'][$shop] = ($bucket['shops'][$shop] ?? 0) + $count;
        }
        if ($host !== null) {
            $bucket['hosts'][$host] = true;
        }
        unset($bucket);
    }

    /**
     * Turn raw buckets into the final account shape (chosen labels, primary
     * shop, review flags, alternate-spelling notes).
     *
     * @return array<int, array<string,mixed>>
     */
    private function resolveBuckets(): array
    {
        $out = [];

        foreach ($this->buckets as $bucket) {
            arsort($bucket['nicknames']);
            arsort($bucket['displays']);
            arsort($bucket['shops']);

            $nickname = array_key_first($bucket['nicknames']) ?: null;
            $display = array_key_first($bucket['displays']) ?: $nickname;
            $shops = array_keys($bucket['shops']);
            $primaryShop = $shops[0] ?? null;

            $altNicknames = array_slice(array_keys($bucket['nicknames']), 1);
            $handleToIdConflict = false;
            $norm = LiveAccount::normalizeHandle($nickname);
            if ($norm !== null && isset($this->handleToId[$norm]) && count($this->handleToId[$norm]) > 1) {
                $handleToIdConflict = true;
            }

            $needsReview = $bucket['handleOnly'] || $handleToIdConflict;

            $out[] = [
                'creator_user_id' => $bucket['creator_user_id'],
                'nickname' => $nickname,
                'display_name' => $display,
                'normalized_handle' => $norm,
                'needs_review' => $needsReview,
                'shops' => $shops,
                'primary_shop' => $primaryShop,
                'hosts' => array_keys($bucket['hosts']),
                'metadata' => array_filter([
                    'alternate_nicknames' => $altNicknames ?: null,
                    'review_reason' => $bucket['handleOnly']
                        ? 'no_creator_id'
                        : ($handleToIdConflict ? 'handle_maps_to_multiple_ids' : null),
                ]),
            ];
        }

        usort($out, fn ($a, $b) => count($b['shops']) <=> count($a['shops']));

        return $out;
    }

    /**
     * @param  array<int, array<string,mixed>>  $resolved
     */
    private function renderPreview(array $resolved): void
    {
        $rows = array_map(fn ($a) => [
            $a['nickname'] ?? '—',
            $a['creator_user_id'] ?? '(none)',
            count($a['shops']),
            count($a['hosts']),
            $a['needs_review'] ? 'REVIEW' : '',
        ], $resolved);

        $this->table(['Nickname', 'Creator ID', 'Shops', 'Hosts', 'Flag'], $rows);

        $review = count(array_filter($resolved, fn ($a) => $a['needs_review']));
        $withId = count(array_filter($resolved, fn ($a) => $a['creator_user_id'] !== null));

        $this->info(sprintf(
            '%d account(s): %d with a Creator ID, %d handle-only, %d flagged for review.',
            count($resolved),
            $withId,
            count($resolved) - $withId,
            $review
        ));
    }

    /**
     * @param  array<int, array<string,mixed>>  $resolved
     */
    private function persist(array $resolved): void
    {
        foreach ($resolved as $a) {
            $lookup = $a['creator_user_id'] !== null
                ? ['creator_user_id' => $a['creator_user_id']]
                : ['creator_user_id' => null, 'normalized_handle' => $a['normalized_handle']];

            $account = LiveAccount::updateOrCreate($lookup, [
                'nickname' => $a['nickname'],
                'display_name' => $a['display_name'],
                'normalized_handle' => $a['normalized_handle'],
                'is_active' => true,
                'needs_review' => $a['needs_review'],
                'metadata' => $a['metadata'] ?: null,
            ]);

            foreach ($a['shops'] as $shopId) {
                $account->shops()->syncWithoutDetaching([
                    $shopId => ['is_primary' => $shopId === $a['primary_shop']],
                ]);
            }

            foreach ($a['hosts'] as $hostId) {
                $account->hosts()->syncWithoutDetaching([$hostId => []]);
            }
        }
    }

    /**
     * @param  array<string,int>  $votes
     */
    private function majority(array $votes): string
    {
        arsort($votes);

        return (string) array_key_first($votes);
    }

    private function str(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
