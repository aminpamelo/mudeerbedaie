<?php

namespace App\Http\Controllers\Fighter;

use App\Http\Controllers\Controller;
use App\Models\FacebookAdAccount;
use App\Models\FacebookAdConnection;
use App\Models\Funnel;
use App\Services\Funnel\FunnelStudioReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The fighter's daily media-buying P&L: spend from the ad accounts under
 * their own Business Managers against the sales of their own funnels, with
 * 8% SST, ROAS and net — the same math as the Funnel Studio Daily Reporting.
 */
class DailyReportingController extends Controller
{
    public function index(Request $request, FunnelStudioReportService $reports): Response
    {
        $userId = (int) $request->user()->id;
        $days = (int) $request->query('days', 30);

        $funnelIds = Funnel::query()->forUser($userId)->pluck('id')->all();
        $adAccountIds = FacebookAdAccount::query()->ownedBy($userId)->pluck('id')->all();

        $report = $reports->dailyReport($funnelIds, $adAccountIds, $days);

        $connections = FacebookAdConnection::query()->where('user_id', $userId);

        return Inertia::render('DailyReporting', [
            'report' => array_merge($report, [
                'by_funnel' => $reports->byFunnel($funnelIds, $adAccountIds, $report['days']),
            ]),
            'connectionsCount' => (clone $connections)->count(),
            'lastSyncedAt' => (clone $connections)->max('last_synced_at'),
        ]);
    }
}
