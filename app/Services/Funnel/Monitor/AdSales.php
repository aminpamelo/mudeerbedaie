<?php

namespace App\Services\Funnel\Monitor;

use App\Models\FacebookAdInsight;
use App\Models\FacebookAdLevelInsight;
use App\Models\FunnelOrder;
use App\Models\FunnelSession;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The single definition of a Meta-ad sale for the monitor, and the mapping from funnel
 * traffic to ad accounts.
 *
 * - A session is Meta traffic when utm_source is a Meta source (fb, ig, …).
 * - It belongs to the ad account that owns its utm_campaign id; untagged Meta traffic falls
 *   back to the ad account the funnel is linked to (settings.ads.facebook_ad_account_id).
 * - A sale is a funnel order that is paid, or a COD order still open (not cancelled /
 *   returned / refunded) — COD is collected on delivery.
 */
final class AdSales
{
    public const META_SOURCES = ['facebook', 'fb', 'instagram', 'ig', 'meta', 'an', 'msg'];

    public const DEAD_STATUSES = ['cancelled', 'returned', 'refunded', 'draft'];

    /** @var array<string, int>|null */
    private ?array $campaignAccounts = null;

    /** @var array<string, Collection<int, object>> */
    private array $memo = [];

    /**
     * Campaign id → ad account id, from every synced insight (ad-level and campaign-level).
     *
     * @return array<string, int>
     */
    public function campaignAccounts(): array
    {
        if ($this->campaignAccounts !== null) {
            return $this->campaignAccounts;
        }

        $map = [];
        FacebookAdInsight::query()->whereNotNull('campaign_id')->distinct()->get(['campaign_id', 'facebook_ad_account_id'])
            ->each(function ($r) use (&$map) {
                $map[(string) $r->campaign_id] = (int) $r->facebook_ad_account_id;
            });
        FacebookAdLevelInsight::query()->distinct()->get(['campaign_id', 'facebook_ad_account_id'])
            ->each(function ($r) use (&$map) {
                $map[(string) $r->campaign_id] = (int) $r->facebook_ad_account_id;
            });

        return $this->campaignAccounts = $map;
    }

    /**
     * Funnel id → linked ad account id.
     *
     * @return array<int, int>
     */
    public function linkedFunnels(): array
    {
        return \App\Models\Funnel::query()
            ->whereNotNull('settings->ads->facebook_ad_account_id')
            ->get(['id', 'settings'])
            ->mapWithKeys(fn ($f) => [(int) $f->id => (int) data_get($f->settings, 'ads.facebook_ad_account_id')])
            ->filter()
            ->all();
    }

    /**
     * Ad account a session belongs to, or null when it is not Meta traffic we can place.
     *
     * @param  array<int, int>  $linked
     */
    public function accountFor(?string $utmSource, ?string $utmCampaign, int $funnelId, array $linked): ?int
    {
        if (! in_array(mb_strtolower(trim((string) $utmSource)), self::META_SOURCES, true)) {
            return null;
        }

        $map = $this->campaignAccounts();
        $campaign = trim((string) $utmCampaign);

        if ($campaign !== '' && isset($map[$campaign])) {
            return $map[$campaign];
        }

        return $linked[$funnelId] ?? null;
    }

    /**
     * Meta-traffic sessions in a window, each tagged with its ad account.
     *
     * @return Collection<int, object>
     */
    public function sessions(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return $this->memo['s|'.$from->toDateTimeString().'|'.$to->toDateTimeString()] ??= $this->loadSessions($from, $to);
    }

    /**
     * @return Collection<int, object>
     */
    private function loadSessions(CarbonInterface $from, CarbonInterface $to): Collection
    {
        $linked = $this->linkedFunnels();

        return FunnelSession::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereIn(\Illuminate\Support\Facades\DB::raw('lower(utm_source)'), self::META_SOURCES)
            ->toBase()
            ->get(['id', 'funnel_id', 'visitor_id', 'utm_source', 'utm_campaign', 'utm_term', 'utm_content'])
            ->map(function ($s) use ($linked) {
                $s->account_id = $this->accountFor($s->utm_source, $s->utm_campaign, (int) $s->funnel_id, $linked);

                return $s;
            })
            ->filter(fn ($s) => $s->account_id !== null)
            ->values();
    }

    /**
     * Funnel orders in a window with their session's UTM tags, order status and a person key.
     * `account_id` is set for Meta traffic; `is_sale` / `is_online` describe the payment.
     *
     * @param  array<int, int>|null  $funnelIds  limit to these funnels (all orders, any source)
     * @return Collection<int, object>
     */
    public function orders(CarbonInterface $from, CarbonInterface $to, ?array $funnelIds = null): Collection
    {
        if ($funnelIds !== null) {
            return $this->loadOrders($from, $to, $funnelIds);
        }

        return $this->memo['o|'.$from->toDateTimeString().'|'.$to->toDateTimeString()] ??= $this->loadOrders($from, $to, null);
    }

    /**
     * @param  array<int, int>|null  $funnelIds
     * @return Collection<int, object>
     */
    private function loadOrders(CarbonInterface $from, CarbonInterface $to, ?array $funnelIds): Collection
    {
        $linked = $this->linkedFunnels();

        return FunnelOrder::query()
            ->leftJoin('funnel_sessions', 'funnel_sessions.id', '=', 'funnel_orders.session_id')
            ->leftJoin('product_orders', 'product_orders.id', '=', 'funnel_orders.product_order_id')
            ->whereBetween('funnel_orders.created_at', [$from, $to])
            ->when($funnelIds !== null, fn ($q) => $q->whereIn('funnel_orders.funnel_id', $funnelIds))
            ->toBase()
            ->get([
                'funnel_orders.id', 'funnel_orders.funnel_id', 'funnel_orders.funnel_revenue', 'funnel_orders.created_at',
                'funnel_sessions.utm_source', 'funnel_sessions.utm_campaign', 'funnel_sessions.utm_term', 'funnel_sessions.utm_content',
                'product_orders.payment_status', 'product_orders.payment_method', 'product_orders.status',
                'product_orders.customer_phone', 'product_orders.guest_email',
            ])
            ->map(function ($o) use ($linked) {
                $isCod = in_array(mb_strtolower((string) $o->payment_method), ['cod', 'cash on delivery'], true);
                $alive = ! in_array($o->status, self::DEAD_STATUSES, true);
                $o->is_online = ! $isCod;
                $o->is_sale = $o->payment_status === 'paid' && $alive
                    || ($isCod && $o->payment_status === 'pending' && $alive);
                $o->person = $o->customer_phone ?: ($o->guest_email ?: 'o'.$o->id);
                $o->account_id = $this->accountFor($o->utm_source, $o->utm_campaign, (int) $o->funnel_id, $linked);

                return $o;
            });
    }
}
