<?php

declare(strict_types=1);

namespace App\Services\LiveHost;

use App\Models\ActualLiveRecord;
use App\Models\LiveAccount;
use App\Models\LiveScheduleAssignment;
use App\Models\PlatformAccount;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Surfaces TikTok "actual live" records that happened in a calendar week but
 * have no recorded session yet — the gaps the PIC never scheduled, plus lives
 * sitting next to an existing-but-unverified slot. Each record is resolved onto
 * its canonical creator account (the punca kuasa) so the calendar can drop it on
 * the right swim lane, and positioned by its real launch time.
 *
 * By default only lives from the shop's own LINKED creator accounts are
 * returned — the TikTok shop_lives/performance API also reports affiliate
 * creators' lives, which would otherwise flood the timetable. Pass
 * $includeUnlinked = true to also surface affiliate/unknown/unregistered lives
 * (records whose creator isn't a linked LiveAccount are flagged
 * `isRegistered = false`) so the PIC can review and register the real ones.
 *
 * This is the "unassigned side" mirror of ActualLiveRecordCandidateFinder: that
 * one answers "which live matches THIS session"; this one answers "which lives
 * have NO session at all this week".
 */
class SuggestedSlotFinder
{
    private const TIMEZONE = 'Asia/Kuala_Lumpur';

    private const MAX_SUGGESTIONS = 300;

    /**
     * @param  array<int, array{id:int,label:string,dayOfWeek:?int,platformAccountId:?int,startTime:string,endTime:string}>  $timeSlots
     * @return array<int, array<string, mixed>>
     */
    public function forWeek(
        CarbonImmutable $weekStart,
        CarbonImmutable $weekEnd,
        ?int $platformAccountId,
        ?int $liveAccountId,
        array $timeSlots,
        bool $includeUnlinked = false
    ): array {
        $records = ActualLiveRecord::query()
            ->with(['platformAccount:id,name,platform_id', 'platformAccount.platform:id,slug'])
            ->when($platformAccountId !== null, fn ($q) => $q->where('platform_account_id', $platformAccountId))
            ->whereBetween('launched_time', [$weekStart->startOfDay(), $weekEnd->endOfDay()])
            ->whereNotIn('id', function ($q) {
                $q->select('matched_actual_live_record_id')
                    ->from('live_sessions')
                    ->whereNotNull('matched_actual_live_record_id');
            })
            ->orderBy('launched_time')
            ->limit(self::MAX_SUGGESTIONS)
            ->get();

        if ($records->isEmpty()) {
            return [];
        }

        [$byCreatorId, $byHandle] = $this->accountLookups($records);
        $assignmentIndex = $this->assignmentIndex($weekStart, $weekEnd);

        $suggestions = [];

        foreach ($records as $record) {
            $account = $this->resolveAccount($record, $byCreatorId, $byHandle);

            if ($liveAccountId !== null && (int) $account?->id !== $liveAccountId) {
                continue;
            }

            // Linked-only by default: hide affiliate/unknown/unregistered lives
            // so the timetable shows only the shop's own creators. An explicit
            // single-account filter ($liveAccountId) or the "show all" toggle
            // ($includeUnlinked) bypasses this.
            $isLinked = $account !== null && $account->account_type === LiveAccount::TYPE_LINKED;
            if (! $isLinked && ! $includeUnlinked && $liveAccountId === null) {
                continue;
            }

            $kl = CarbonImmutable::instance($record->launched_time)->setTimezone(self::TIMEZONE);
            $dow = (int) $kl->dayOfWeek;
            $startMin = $kl->hour * 60 + $kl->minute;
            $endKl = $this->endTime($record, $kl);
            $endMin = $endKl->hour * 60 + $endKl->minute;
            if ($endMin <= $startMin) {
                $endMin = min(24 * 60, $startMin + 60);
            }

            $liveGmv = (float) $record->live_attributed_gmv_myr;

            $suggestions[] = [
                'id' => $record->id,
                'isRegistered' => $account !== null,
                'liveAccountId' => $account?->id,
                'liveAccountLabel' => $account?->label ?? ($record->creator_handle ?: 'Unknown creator'),
                'creatorHandle' => $record->creator_handle,
                'creatorUserId' => $record->creator_platform_user_id,
                'platformAccountId' => $record->platform_account_id,
                'platformAccount' => $record->platformAccount?->name,
                'platformType' => $record->platformAccount?->platform?->slug,
                'dayOfWeek' => $dow,
                'scheduleDate' => $kl->toDateString(),
                'startTime' => $kl->format('H:i'),
                'endTime' => $endKl->format('H:i'),
                'launchedAt' => $record->launched_time->toIso8601String(),
                'endedAt' => $record->ended_time?->toIso8601String(),
                'durationSeconds' => $record->duration_seconds !== null ? (int) $record->duration_seconds : null,
                'gmv' => (float) $record->gmv_myr,
                'liveAttributedGmv' => $liveGmv >= 0 ? $liveGmv : null,
                'viewers' => (int) $record->viewers,
                'itemsSold' => (int) $record->items_sold,
                'source' => $record->source,
                'matchType' => $this->matchType($assignmentIndex, $account?->id, $dow, $kl->toDateString(), $startMin, $endMin),
                'suggestedTimeSlotId' => $this->nearestTimeSlotId($timeSlots, (int) $record->platform_account_id, $dow, $startMin),
            ];
        }

        return $suggestions;
    }

    /**
     * Count unlinked TikTok lives (the same "suggestion" set forWeek() surfaces)
     * per resolved creator account per day over an arbitrary window — the
     * month-grid analogue used by the Session Coverage matrix. Counts are keyed
     * by live_account_id then Y-m-d (KL date); lives whose creator isn't a
     * registered account collapse under the special key 0 ("unregistered").
     *
     * @return array<int, array<string, int>>
     */
    public function countByAccountAndDay(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?int $platformAccountId = null,
        ?int $liveAccountId = null,
        bool $includeUnlinked = false
    ): array {
        $records = ActualLiveRecord::query()
            ->when($platformAccountId !== null, fn ($q) => $q->where('platform_account_id', $platformAccountId))
            ->whereBetween('launched_time', [$from->startOfDay(), $to->endOfDay()])
            ->whereNotIn('id', function ($q) {
                $q->select('matched_actual_live_record_id')
                    ->from('live_sessions')
                    ->whereNotNull('matched_actual_live_record_id');
            })
            ->orderBy('launched_time')
            ->get(['id', 'platform_account_id', 'creator_platform_user_id', 'creator_handle', 'launched_time']);

        if ($records->isEmpty()) {
            return [];
        }

        [$byCreatorId, $byHandle] = $this->accountLookups($records);

        $counts = [];

        foreach ($records as $record) {
            $account = $this->resolveAccount($record, $byCreatorId, $byHandle);

            if ($liveAccountId !== null && (int) $account?->id !== $liveAccountId) {
                continue;
            }

            $isLinked = $account !== null && $account->account_type === LiveAccount::TYPE_LINKED;
            if (! $isLinked && ! $includeUnlinked && $liveAccountId === null) {
                continue;
            }

            $key = $account?->id ?? 0;
            $date = CarbonImmutable::instance($record->launched_time)->setTimezone(self::TIMEZONE)->toDateString();
            $counts[$key][$date] = ($counts[$key][$date] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * The DISTINCT creators who went live in the window but are NOT yet
     * classified — the "belum diklasifikasi" list on the Creators page. Includes
     * `unknown` accounts and creators with no account at all; excludes both
     * `linked` and `affiliate` (those are already decided, so marking a creator
     * either way removes it from this list). Grouped onto the resolved account
     * (or normalized handle / creator id when unresolved), with a live count and
     * last-live time so the PIC can spot the real creators to register.
     *
     * Each group also carries the TikTok Shop(s) the creator actually went live
     * on (from the records' platform_account_id) so the PIC knows which shop to
     * link the account to; `platformAccountId`/`platformAccount` name the shop
     * with the most lives.
     *
     * @return array<int, array{
     *     creatorHandle: ?string,
     *     creatorUserId: ?string,
     *     liveAccountId: ?int,
     *     accountType: ?string,
     *     liveCount: int,
     *     lastLiveAt: ?string,
     *     attributedGmv: float,
     *     platformAccountId: ?int,
     *     platformAccount: ?string,
     *     shops: array<int, array{id: int, name: ?string, liveCount: int}>
     * }>
     */
    public function unclassifiedCreators(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?int $platformAccountId = null
    ): array {
        $records = ActualLiveRecord::query()
            ->when($platformAccountId !== null, fn ($q) => $q->where('platform_account_id', $platformAccountId))
            ->whereBetween('launched_time', [$from->startOfDay(), $to->endOfDay()])
            ->orderByDesc('launched_time')
            ->get(['id', 'platform_account_id', 'creator_platform_user_id', 'creator_handle', 'launched_time', 'live_attributed_gmv_myr']);

        if ($records->isEmpty()) {
            return [];
        }

        [$byCreatorId, $byHandle] = $this->accountLookups($records);

        $shopNames = PlatformAccount::query()
            ->whereIn('id', $records->pluck('platform_account_id')->filter()->unique()->all())
            ->pluck('name', 'id');

        /** @var array<string, array<string, mixed>> $groups */
        $groups = [];

        foreach ($records as $record) {
            $account = $this->resolveAccount($record, $byCreatorId, $byHandle);

            // Already-classified accounts (linked or affiliate) are settled — only
            // unknown accounts and creators with no account at all need review.
            if ($account !== null && $account->account_type !== LiveAccount::TYPE_UNKNOWN) {
                continue;
            }

            $key = $account !== null
                ? 'account:'.$account->id
                : (LiveAccount::normalizeHandle($record->creator_handle)
                    ?? ($record->creator_platform_user_id !== null ? 'creator:'.$record->creator_platform_user_id : null));

            if ($key === null) {
                continue;
            }

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'creatorHandle' => $account?->label ?? $record->creator_handle,
                    'creatorUserId' => $record->creator_platform_user_id ?? $account?->creator_user_id,
                    'liveAccountId' => $account?->id,
                    'accountType' => $account?->account_type,
                    'liveCount' => 0,
                    'lastLiveAt' => null,
                    'attributedGmv' => 0.0,
                    'shops' => [],
                ];
            }

            $groups[$key]['liveCount']++;
            $groups[$key]['attributedGmv'] += max(0.0, (float) $record->live_attributed_gmv_myr);

            $shopId = $record->platform_account_id !== null ? (int) $record->platform_account_id : null;
            if ($shopId !== null) {
                if (! isset($groups[$key]['shops'][$shopId])) {
                    $groups[$key]['shops'][$shopId] = ['id' => $shopId, 'name' => $shopNames[$shopId] ?? null, 'liveCount' => 0];
                }
                $groups[$key]['shops'][$shopId]['liveCount']++;
            }

            $launchedIso = $record->launched_time?->toIso8601String();
            if ($launchedIso !== null && ($groups[$key]['lastLiveAt'] === null || $launchedIso > $groups[$key]['lastLiveAt'])) {
                $groups[$key]['lastLiveAt'] = $launchedIso;
            }
        }

        return array_map(function (array $group): array {
            $shops = array_values($group['shops']);
            usort($shops, fn ($a, $b) => $b['liveCount'] <=> $a['liveCount']);
            $group['shops'] = $shops;
            $group['platformAccountId'] = $shops[0]['id'] ?? null;
            $group['platformAccount'] = $shops[0]['name'] ?? null;

            return $group;
        }, array_values($groups));
    }

    /**
     * Bulk-resolve every referenced creator (by numeric id and normalized
     * handle) in two queries so we never N+1 the resolver per record.
     *
     * @param  Collection<int, ActualLiveRecord>  $records
     * @return array{0: array<string, LiveAccount>, 1: array<string, LiveAccount>}
     */
    private function accountLookups(Collection $records): array
    {
        $creatorIds = $records
            ->pluck('creator_platform_user_id')
            ->filter(fn ($v) => $v !== null && trim((string) $v) !== '')
            ->map(fn ($v) => trim((string) $v))
            ->unique()
            ->values();

        $handles = $records
            ->pluck('creator_handle')
            ->map(fn ($v) => LiveAccount::normalizeHandle($v))
            ->filter()
            ->unique()
            ->values();

        if ($creatorIds->isEmpty() && $handles->isEmpty()) {
            return [[], []];
        }

        $accounts = LiveAccount::query()
            ->where(function ($q) use ($creatorIds, $handles) {
                if ($creatorIds->isNotEmpty()) {
                    $q->orWhereIn('creator_user_id', $creatorIds->all());
                }
                if ($handles->isNotEmpty()) {
                    $q->orWhereIn('normalized_handle', $handles->all());
                }
            })
            ->get(['id', 'creator_user_id', 'nickname', 'display_name', 'normalized_handle', 'account_type']);

        $byCreatorId = [];
        $byHandle = [];

        foreach ($accounts as $account) {
            if ($account->creator_user_id !== null && trim((string) $account->creator_user_id) !== '') {
                $byCreatorId[trim((string) $account->creator_user_id)] = $account;
            }
            if ($account->normalized_handle !== null && $account->normalized_handle !== '') {
                $byHandle[$account->normalized_handle] = $account;
            }
        }

        return [$byCreatorId, $byHandle];
    }

    /**
     * @param  array<string, LiveAccount>  $byCreatorId
     * @param  array<string, LiveAccount>  $byHandle
     */
    private function resolveAccount(ActualLiveRecord $record, array $byCreatorId, array $byHandle): ?LiveAccount
    {
        $creatorId = $record->creator_platform_user_id !== null
            ? trim((string) $record->creator_platform_user_id)
            : null;

        if ($creatorId !== null && $creatorId !== '' && isset($byCreatorId[$creatorId])) {
            return $byCreatorId[$creatorId];
        }

        $handle = LiveAccount::normalizeHandle($record->creator_handle);

        return $handle !== null ? ($byHandle[$handle] ?? null) : null;
    }

    private function endTime(ActualLiveRecord $record, CarbonImmutable $klStart): CarbonImmutable
    {
        if ($record->ended_time !== null) {
            return CarbonImmutable::instance($record->ended_time)->setTimezone(self::TIMEZONE);
        }

        if ($record->duration_seconds !== null && (int) $record->duration_seconds > 0) {
            return $klStart->addSeconds((int) $record->duration_seconds);
        }

        return $klStart->addHour();
    }

    /**
     * Existing assignments this week, indexed by creator account, so a
     * suggestion can be tagged "gap" (nothing scheduled) vs "near_slot" (a slot
     * exists at that time but the live was never linked/verified).
     *
     * @return array<int, array<int, array{isTemplate:bool,dow:int,date:?string,startMin:int,endMin:int}>>
     */
    private function assignmentIndex(CarbonImmutable $weekStart, CarbonImmutable $weekEnd): array
    {
        $rows = LiveScheduleAssignment::query()
            ->whereNotNull('live_account_id')
            ->with('timeSlot:id,start_time,end_time')
            ->where(function ($q) use ($weekStart, $weekEnd) {
                $q->where('is_template', true)
                    ->orWhereBetween('schedule_date', [$weekStart->toDateString(), $weekEnd->toDateString()]);
            })
            ->get(['id', 'live_account_id', 'day_of_week', 'schedule_date', 'is_template', 'time_slot_id']);

        $index = [];

        foreach ($rows as $row) {
            if ($row->timeSlot === null) {
                continue;
            }

            $index[(int) $row->live_account_id][] = [
                'isTemplate' => (bool) $row->is_template,
                'dow' => (int) $row->day_of_week,
                'date' => $row->schedule_date?->format('Y-m-d'),
                'startMin' => $this->toMinutes((string) $row->timeSlot->start_time),
                'endMin' => $this->toMinutes((string) $row->timeSlot->end_time),
            ];
        }

        return $index;
    }

    /**
     * @param  array<int, array<int, array{isTemplate:bool,dow:int,date:?string,startMin:int,endMin:int}>>  $index
     */
    private function matchType(array $index, ?int $accountId, int $dow, string $date, int $startMin, int $endMin): string
    {
        if ($accountId === null) {
            return 'gap';
        }

        foreach ($index[$accountId] ?? [] as $slot) {
            $applies = ($slot['isTemplate'] && $slot['dow'] === $dow)
                || ($slot['date'] !== null && $slot['date'] === $date);

            if ($applies && $startMin < $slot['endMin'] && $endMin > $slot['startMin']) {
                return 'near_slot';
            }
        }

        return 'gap';
    }

    /**
     * The existing reusable time window closest to the live's launch time,
     * limited to windows for this shop (or global) and this day (or any day),
     * so the assign form can be pre-filled with a sensible slot.
     *
     * @param  array<int, array{id:int,label:string,dayOfWeek:?int,platformAccountId:?int,startTime:string,endTime:string}>  $timeSlots
     */
    private function nearestTimeSlotId(array $timeSlots, int $shopId, int $dow, int $startMin): ?int
    {
        $best = null;
        $bestDiff = null;

        foreach ($timeSlots as $slot) {
            $slotShop = $slot['platformAccountId'] ?? null;
            if ($slotShop !== null && (int) $slotShop !== $shopId) {
                continue;
            }
            $slotDow = $slot['dayOfWeek'] ?? null;
            if ($slotDow !== null && (int) $slotDow !== $dow) {
                continue;
            }

            $diff = abs($this->toMinutes((string) $slot['startTime']) - $startMin);
            if ($bestDiff === null || $diff < $bestDiff) {
                $bestDiff = $diff;
                $best = (int) $slot['id'];
            }
        }

        return $best;
    }

    private function toMinutes(string $time): int
    {
        $parts = explode(':', $time);

        return ((int) ($parts[0] ?? 0)) * 60 + ((int) ($parts[1] ?? 0));
    }
}
