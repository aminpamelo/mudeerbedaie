<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\SyncFacebookAdMonitor;
use App\Models\FacebookAdAccount;
use App\Models\FacebookAdLevelInsight;
use App\Models\FunnelOrder;
use App\Services\Funnel\Monitor\AdMonitor;
use App\Services\Funnel\Monitor\AdSales;
use App\Services\Funnel\Monitor\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Funnel Studio "Monitor Iklan" (ported from Dana Ummah's Monitor Klien): every Facebook
 * ad account's spend, real funnel sales, ROAS, funnel conversion and diagnosis side by side,
 * plus "Laporan Harian" (cost vs income per account per day). Admin + employee only.
 */
class AdMonitorController extends Controller
{
    /** Mirrors routes/console.php: monitor sync every 5 minutes (3 days) + nightly 30-day backfill. */
    private const SYNC_EVERY_MINUTES = 5;

    private const SYNC_DAYS = 3;

    protected function authorizeView(Request $request): void
    {
        if (! in_array($request->user()?->role, ['admin', 'employee'], true)) {
            abort(403, 'Hanya admin dan staf boleh melihat Monitor Iklan.');
        }
    }

    public function index(Request $request, AdMonitor $monitor): JsonResponse
    {
        $this->authorizeView($request);

        $range = DateRange::fromRequest($request, 'today');

        return response()->json([
            'range' => $range->toArray(),
            ...$monitor->report($range->from, $range->to, ...$range->previous()),
            'sync' => $this->syncInfo(),
            'diagnosis_rules' => AdMonitor::rules(),
        ]);
    }

    /**
     * Pull fresh Meta numbers for every monitored account now, instead of waiting for the
     * five-minute schedule. Runs in-request so the page reloads with the new data.
     */
    public function sync(Request $request): JsonResponse
    {
        $this->authorizeView($request);

        $ok = 0;
        $failed = [];

        foreach (FacebookAdAccount::query()->monitorable()->get() as $account) {
            try {
                SyncFacebookAdMonitor::dispatchSync($account->id, self::SYNC_DAYS);
            } catch (Throwable $e) {
                report($e);
            }

            $account->refresh();
            if (filled($account->monitor_sync_error)) {
                $failed[] = $account->name;
            } else {
                $ok++;
            }
        }

        return response()->json([
            'success' => $failed === [],
            'message' => $failed === []
                ? "{$ok} akaun iklan disync."
                : "{$ok} berjaya. Gagal: ".implode(', ', $failed).'.',
        ]);
    }

    /**
     * "Laporan Harian": for a year, every ad account's daily cost (Meta spend) and income
     * (funnel sales from its ads, or all sales of its funnels), one compact cell per day.
     */
    public function daily(Request $request, AdSales $sales): JsonResponse
    {
        $this->authorizeView($request);

        $currentYear = (int) now()->year;
        $year = max(2020, min($request->integer('year') ?: $currentYear, $currentYear));
        $source = $request->string('source')->value() === 'all' ? 'all' : 'ads';

        $start = CarbonImmutable::create($year, 1, 1)->startOfDay();
        $end = $start->endOfYear();

        // days[date][account] = [cost, income, count]
        $days = [];
        FacebookAdLevelInsight::query()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('facebook_ad_account_id as a, date as d, sum(spend) as total')
            ->groupBy('facebook_ad_account_id', 'date')
            ->toBase()->get()
            ->each(function ($row) use (&$days) {
                $days[substr((string) $row->d, 0, 10)][(int) $row->a][0] = round((float) $row->total, 2);
            });

        $orders = $sales->orders($start, $end)->where('is_sale', true);
        if ($source === 'all') {
            $linked = $sales->linkedFunnels();
            $orders = $orders->map(function ($o) use ($linked) {
                $o->account_id ??= $linked[(int) $o->funnel_id] ?? null;

                return $o;
            });
        }

        $orders->filter(fn ($o) => $o->account_id !== null)
            ->each(function ($o) use (&$days) {
                $date = substr((string) $o->created_at, 0, 10);
                $cell = $days[$date][(int) $o->account_id] ?? [];
                $cell[1] = round(($cell[1] ?? 0) + (float) $o->funnel_revenue, 2);
                $cell[2] = ($cell[2] ?? 0) + 1;
                $days[$date][(int) $o->account_id] = $cell;
            });

        foreach ($days as &$byAccount) {
            foreach ($byAccount as &$cell) {
                $cell = [$cell[0] ?? 0, $cell[1] ?? 0, $cell[2] ?? 0];
            }
        }
        unset($byAccount, $cell);
        ksort($days);

        $firstYear = collect([
            FacebookAdLevelInsight::query()->min('date'),
            FunnelOrder::query()->min('created_at'),
        ])->filter()->map(fn ($d) => (int) substr((string) $d, 0, 4))->min() ?? $currentYear;

        $activeIds = collect($days)->flatMap(fn ($byAccount) => array_keys($byAccount))->unique()->all();

        return response()->json([
            'year' => $year,
            'source' => $source,
            'years' => range($currentYear, max(2020, min($firstYear, $currentYear))),
            'today' => now()->toDateString(),
            'clients' => FacebookAdAccount::query()->whereIn('id', $activeIds)->orderBy('name')->get(['id', 'name', 'account_id'])
                ->map(fn (FacebookAdAccount $a) => ['id' => $a->id, 'name' => $a->name, 'account_id' => $a->account_id])->values(),
            'days' => (object) $days,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function syncInfo(): array
    {
        $accounts = FacebookAdAccount::query()->monitorable()->get(['id', 'monitor_synced_at', 'monitor_sync_error']);
        $synced = $accounts->pluck('monitor_synced_at')->filter();
        $now = CarbonImmutable::now();
        $next = $now->startOfMinute()->addMinutes(self::SYNC_EVERY_MINUTES - ($now->minute % self::SYNC_EVERY_MINUTES));

        return [
            'every_minutes' => self::SYNC_EVERY_MINUTES,
            'days' => self::SYNC_DAYS,
            'backfill' => '30 hari setiap hari, 4:00 pagi',
            'last_synced_at' => $synced->max()?->toIso8601String(),
            'oldest_synced_at' => $synced->min()?->toIso8601String(),
            'next_run_at' => $next->toIso8601String(),
            'connected' => $accounts->count(),
            'failing' => $accounts->filter(fn ($a) => filled($a->monitor_sync_error))->count(),
        ];
    }
}
