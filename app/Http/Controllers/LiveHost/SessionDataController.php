<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Models\LiveAccount;
use App\Models\LiveSession;
use App\Models\PlatformAccount;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class SessionDataController extends Controller
{
    /**
     * Combined "Session Data" list: every live session (the manual record)
     * merged with its linked TikTok ActualLiveRecord (the API data). Surfaces
     * GMV, viewers, items, the creator handle, and whether the session has been
     * linked to an actual record yet — all filterable. Read-oriented; rows link
     * through to the existing Session detail page for management actions.
     */
    public function index(Request $request): Response
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);

        $search = trim($request->string('search')->toString());
        $status = $request->string('status')->toString();
        $platformAccount = $request->string('platform_account')->toString();
        $host = $request->string('host')->toString();
        $liveAccount = $request->string('live_account')->toString();
        $link = $request->string('link')->toString();
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();
        $minGmv = $request->string('min_gmv')->toString();

        $searchId = preg_match('/(\d+)/', $search, $m) === 1 ? (int) $m[1] : null;

        // Every column is qualified with `live_sessions.` so the same closure can
        // be reused both on the plain paginated query and on the joined aggregate
        // query (which also pulls columns from actual_live_records).
        $applyFilters = function ($q) use (
            $status,
            $platformAccount,
            $host,
            $liveAccount,
            $link,
            $from,
            $to,
            $minGmv,
            $search,
            $searchId
        ) {
            return $q
                ->when($status !== '', fn ($q) => $q->where('live_sessions.status', $status))
                ->when(
                    $platformAccount !== '',
                    fn ($q) => $q->where('live_sessions.platform_account_id', $platformAccount)
                )
                ->when($host !== '', fn ($q) => $q->where('live_sessions.live_host_id', $host))
                ->when(
                    $liveAccount !== '',
                    fn ($q) => $q->where('live_sessions.live_account_id', $liveAccount)
                )
                ->when(
                    $link === 'linked',
                    fn ($q) => $q->whereNotNull('live_sessions.matched_actual_live_record_id')
                )
                ->when(
                    $link === 'unlinked',
                    fn ($q) => $q->whereNull('live_sessions.matched_actual_live_record_id')
                )
                ->when($from !== '', fn ($q) => $q->whereDate('live_sessions.scheduled_start_at', '>=', $from))
                ->when($to !== '', fn ($q) => $q->whereDate('live_sessions.scheduled_start_at', '<=', $to))
                ->when(
                    $minGmv !== '' && is_numeric($minGmv),
                    fn ($q) => $q->where('live_sessions.gmv_amount', '>=', (float) $minGmv)
                )
                ->when($search !== '', function ($q) use ($search, $searchId) {
                    $q->where(function ($qq) use ($search, $searchId) {
                        $qq->where('live_sessions.title', 'like', "%{$search}%")
                            ->orWhereHas('liveHost', fn ($h) => $h->where('name', 'like', "%{$search}%"))
                            ->orWhereHas(
                                'matchedActualLiveRecord',
                                fn ($r) => $r->where('creator_handle', 'like', "%{$search}%")
                            )
                            ->orWhereHas('liveAccount', function ($a) use ($search) {
                                $a->where('nickname', 'like', "%{$search}%")
                                    ->orWhere('display_name', 'like', "%{$search}%");
                            });

                        if ($searchId !== null) {
                            $qq->orWhere('live_sessions.id', $searchId);
                        }
                    });
                });
        };

        $sessions = LiveSession::query()
            ->with([
                'platformAccount:id,name,platform_id',
                'platformAccount.platform:id,name,display_name,slug',
                'liveHost:id,name,email',
                'liveAccount:id,nickname,display_name',
                'matchedActualLiveRecord:id,launched_time,ended_time,duration_seconds,gmv_myr,live_attributed_gmv_myr,viewers,items_sold,creator_handle,source',
            ])
            // Aggregate across ALL attributed records so split lives report totals,
            // not just the primary segment.
            ->withSum('actualLiveRecords as records_total_gmv', 'gmv_myr')
            ->withSum('actualLiveRecords as records_items_sold', 'items_sold')
            ->withSum('actualLiveRecords as records_duration', 'duration_seconds')
            ->withMax('actualLiveRecords as records_viewers', 'viewers')
            ->withMax('actualLiveRecords as records_ended', 'ended_time')
            ->withCount('actualLiveRecords as records_count')
            ->tap($applyFilters)
            ->orderByDesc('live_sessions.scheduled_start_at')
            ->orderByDesc('live_sessions.id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (LiveSession $s) => $this->mapRow($s));

        return Inertia::render('sessions/Data', [
            'sessions' => $sessions,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'platform_account' => $platformAccount,
                'host' => $host,
                'live_account' => $liveAccount,
                'link' => $link,
                'from' => $from,
                'to' => $to,
                'min_gmv' => $minGmv,
            ],
            'summary' => $this->summary($applyFilters),
            'hosts' => $this->hostOptions(),
            'platformAccounts' => $this->platformAccountOptions(),
            'liveAccounts' => $this->liveAccountOptions(),
        ]);
    }

    /**
     * One combined row: the manual session record plus, when linked, the
     * numbers locked from its TikTok actual record.
     *
     * @return array<string, mixed>
     */
    private function mapRow(LiveSession $s): array
    {
        $record = $s->matchedActualLiveRecord;
        $recordCount = (int) ($s->records_count ?? 0);
        $linked = $record !== null || $recordCount > 0;
        // For split lives the live spans from the first segment's launch to the
        // last segment's end.
        $liveEnd = $s->records_ended !== null
            ? Carbon::parse($s->records_ended)->toIso8601String()
            : $record?->ended_time?->toIso8601String();

        return [
            'id' => $s->id,
            'sessionId' => 'LS-'.str_pad((string) $s->id, 5, '0', STR_PAD_LEFT),
            'title' => $s->title,
            'status' => $s->status,
            'hostName' => $s->liveHost?->name,
            'hostEmail' => $s->liveHost?->email,
            'accountName' => $s->platformAccount?->name,
            'platformType' => $s->platformAccount?->platform?->slug,
            // Prefer the API record's creator handle (e.g. "@amarmirzabedaie");
            // fall back to the scheduled creator account's nickname.
            'creatorHandle' => $record?->creator_handle
                ?? $s->liveAccount?->nickname,
            'creatorAccount' => $s->liveAccount?->nickname
                ?? $s->liveAccount?->display_name,
            'linked' => $linked,
            'source' => $linked ? ($record->source ?: 'api') : 'manual',
            'scheduledStart' => $s->scheduled_start_at?->toIso8601String(),
            'actualStart' => $s->actual_start_at?->toIso8601String(),
            'liveStart' => $record?->launched_time?->toIso8601String(),
            'liveEnd' => $liveEnd,
            'durationSeconds' => $s->records_duration !== null
                ? (int) $s->records_duration
                : $record?->duration_seconds,
            // Live-attributed GMV = session.gmv_amount, which is the SUM across
            // every attributed record (payroll's number). Same for manual sessions.
            'liveAttribGmv' => (float) ($s->gmv_amount ?? 0),
            'totalGmv' => $linked ? (float) ($s->records_total_gmv ?? 0) : null,
            'gmvSource' => $s->gmv_source,
            'recordCount' => $recordCount,
            'viewers' => $linked ? (int) ($s->records_viewers ?? 0) : null,
            'itemsSold' => $linked ? (int) ($s->records_items_sold ?? 0) : null,
        ];
    }

    /**
     * Aggregate totals over the *filtered* set (not just the current page) so
     * the KPI cards reflect what the user has narrowed to.
     *
     * @return array{total: int, linked: int, unlinked: int, liveAttribGmv: float, totalGmv: float, viewers: int, items: int}
     */
    private function summary(\Closure $applyFilters): array
    {
        $total = LiveSession::query()->tap($applyFilters)->count();

        $linked = LiveSession::query()
            ->tap($applyFilters)
            ->whereNotNull('live_sessions.matched_actual_live_record_id')
            ->count();

        $liveAttribGmv = (float) LiveSession::query()
            ->tap($applyFilters)
            ->sum('live_sessions.gmv_amount');

        // gmv_myr / viewers / items_sold live on the actual records; join through
        // the pivot so EVERY attributed record (split lives included) is summed,
        // scoped to linked sessions only.
        $recordTotals = LiveSession::query()
            ->tap($applyFilters)
            ->join('live_session_actual_live_record', 'live_session_actual_live_record.live_session_id', '=', 'live_sessions.id')
            ->join('actual_live_records', 'actual_live_records.id', '=', 'live_session_actual_live_record.actual_live_record_id')
            ->selectRaw('COALESCE(SUM(actual_live_records.gmv_myr), 0) as total_gmv')
            ->selectRaw('COALESCE(SUM(actual_live_records.viewers), 0) as viewers')
            ->selectRaw('COALESCE(SUM(actual_live_records.items_sold), 0) as items')
            ->first();

        return [
            'total' => $total,
            'linked' => $linked,
            'unlinked' => $total - $linked,
            'liveAttribGmv' => round($liveAttribGmv, 2),
            'totalGmv' => round((float) ($recordTotals->total_gmv ?? 0), 2),
            'viewers' => (int) ($recordTotals->viewers ?? 0),
            'items' => (int) ($recordTotals->items ?? 0),
        ];
    }

    /**
     * @return Collection<int, array{id: int, name: string, email: ?string}>
     */
    private function hostOptions(): Collection
    {
        return User::query()
            ->where('role', 'live_host')
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email]);
    }

    /**
     * @return Collection<int, array{id: int, name: string, platform: ?string}>
     */
    private function platformAccountOptions(): Collection
    {
        return PlatformAccount::query()
            ->with('platform:id,name,display_name,slug')
            ->orderBy('name')
            ->get(['id', 'name', 'platform_id'])
            ->map(fn (PlatformAccount $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'platform' => $a->platform?->display_name ?? $a->platform?->name,
            ]);
    }

    /**
     * Only creator accounts that actually have sessions — keeps the filter
     * dropdown relevant rather than listing every account in the system.
     *
     * @return Collection<int, array{id: int, label: string, hint: ?string}>
     */
    private function liveAccountOptions(): Collection
    {
        return LiveAccount::query()
            ->whereHas('liveSessions')
            ->orderBy('nickname')
            ->get(['id', 'nickname', 'display_name'])
            ->map(fn (LiveAccount $a) => [
                'id' => $a->id,
                'label' => $a->nickname ?: ($a->display_name ?: "Account {$a->id}"),
                'hint' => $a->display_name,
            ]);
    }
}
