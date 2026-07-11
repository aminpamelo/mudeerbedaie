<?php

declare(strict_types=1);

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Models\LiveAccount;
use App\Models\PlatformAccount;
use App\Models\User;
use App\Services\LiveHost\SessionCoverageMatrix;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The "Session Coverage" matrix view of the Session Slots surface: one row per
 * creator account, months that expand into days, each cell showing how much of
 * that account's live activity is still un-settled (needs upload / needs verify
 * / verified / TikTok suggestion). Sits alongside the Table and Calendar views.
 */
class SessionCoverageController extends Controller
{
    public function __construct(private readonly SessionCoverageMatrix $matrix) {}

    public function index(Request $request): Response
    {
        $currentYear = (int) now()->format('Y');
        $currentMonth = (int) now()->format('n');

        $year = $request->integer('year') ?: $currentYear;
        $defaultTo = $year === $currentYear ? $currentMonth : 12;
        $to = $request->integer('to') ?: $defaultTo;
        $from = $request->integer('from') ?: max(1, $to - 5);

        $from = max(1, min(12, $from));
        $to = max(1, min(12, $to));

        $filters = $this->matrix->filters($request->all());

        return Inertia::render('session-slots/Matrix', [
            'coverage' => $this->matrix->monthly($year, $from, $to, $filters),
            'filters' => [
                'host' => $request->query('host', ''),
                'platform_account' => $request->query('platform_account', ''),
                'live_account' => $request->query('live_account', ''),
                'include_unlinked' => (bool) $request->boolean('include_unlinked'),
            ],
            'hosts' => $this->hostOptions(),
            'liveAccounts' => $this->liveAccountOptions(),
            'platformAccounts' => $this->platformAccountOptions(),
        ]);
    }

    public function daily(Request $request): JsonResponse
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $filters = $this->matrix->filters($request->all());

        return response()->json($this->matrix->daily((int) $data['year'], (int) $data['month'], $filters));
    }

    public function day(Request $request): JsonResponse
    {
        $data = $request->validate([
            'account' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $filters = $this->matrix->filters($request->all());

        return response()->json($this->matrix->dayDetail((int) $data['account'], $data['date'], $filters));
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function hostOptions(): array
    {
        return User::query()
            ->where('role', 'live_host')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])
            ->all();
    }

    /**
     * @return array<int, array{id: int, label: string}>
     */
    private function liveAccountOptions(): array
    {
        return LiveAccount::query()
            ->where('is_active', true)
            ->linked()
            ->orderByRaw('COALESCE(nickname, display_name)')
            ->get(['id', 'nickname', 'display_name', 'creator_user_id'])
            ->map(fn (LiveAccount $a) => ['id' => $a->id, 'label' => $a->label])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string, platform: ?string}>
     */
    private function platformAccountOptions(): array
    {
        return PlatformAccount::query()
            ->with('platform:id,name,display_name,slug')
            ->orderBy('name')
            ->get(['id', 'name', 'platform_id'])
            ->map(fn (PlatformAccount $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'platform' => $a->platform?->display_name ?? $a->platform?->name,
            ])
            ->all();
    }
}
