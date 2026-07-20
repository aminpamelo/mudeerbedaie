<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Models\LiveHostMentee;
use App\Models\LiveHostMentoringProgram;
use App\Models\User;
use App\Services\Mentoring\MenteeDailySalesResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sales Leaderboard — ranks live hosts by their effective sales (Net GMV from
 * ended live sessions, with any PIC daily override) over a chosen month window.
 * The very same "effective sales" the Mentoring Overview grid shows, but rolled
 * up per host across a range and sorted into a competitive ranking.
 *
 * Two scopes: 'mentees' (only hosts enrolled in an active mentoring program) or
 * 'all' (every live host, whether mentored or not — non-mentees carry no PIC/
 * program and use raw session GMV). Grouping (by PIC or by program), ranking,
 * the overall podium, program filter and host search are all done client-side
 * from the flat `hosts` payload, so those controls never round-trip.
 */
class SalesLeaderboardController extends Controller
{
    public function index(Request $request, MenteeDailySalesResolver $resolver): Response
    {
        $scope = $request->string('scope')->toString();
        $scope = in_array($scope, ['mentees', 'all'], true) ? $scope : 'mentees';

        $window = $this->resolveMonthWindow($request);
        $from = CarbonImmutable::create($window['year'], $window['from'], 1)->startOfMonth();
        $to = CarbonImmutable::create($window['year'], $window['to'], 1)->endOfMonth();

        // Live programs only — active status and NOT archived. Everything below
        // (mentees, ranking, program filter, grouping) flows from this set, so an
        // archived program drops out of the leaderboard entirely.
        $programs = LiveHostMentoringProgram::query()
            ->where('status', 'active')
            ->archived(false)
            ->with('leader:id,name')
            ->orderBy('title')
            ->get(['id', 'title', 'leader_user_id', 'starts_at']);

        $programsById = $programs->keyBy('id');

        // Active + graduated mentees across every active program, most-active
        // first so the primary membership per host is the meaningful one.
        $mentees = LiveHostMentee::query()
            ->whereIn('program_id', $programs->pluck('id'))
            ->whereIn('status', ['active', 'graduated'])
            ->with(['menteeUser:id,name', 'mentor:id,name', 'level:id,name,color'])
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderByDesc('enrolled_at')
            ->get();

        // Effective sales per mentee (auto session GMV + PIC daily overrides).
        $menteeSales = $resolver->rangeTotals($mentees, $from, $to);

        // One primary mentee membership per host (first by the ordering above),
        // so a host enrolled in two programs is still a single leaderboard row.
        $primaryByHost = [];
        foreach ($mentees as $mentee) {
            $hostId = (int) $mentee->mentee_user_id;
            if ($hostId > 0 && ! isset($primaryByHost[$hostId])) {
                $primaryByHost[$hostId] = $mentee;
            }
        }

        // Participant hosts + a name lookup.
        if ($scope === 'all') {
            $hostUsers = User::query()
                ->where('role', 'live_host')
                ->orderBy('name')
                ->get(['id', 'name']);
        } else {
            $hostUsers = collect($primaryByHost)
                ->map(fn (LiveHostMentee $m) => $m->menteeUser)
                ->filter()
                ->unique('id')
                ->sortBy('name')
                ->values();
        }

        $hostIds = $hostUsers->pluck('id')->map(fn ($id) => (int) $id)->all();

        // Auto GMV + ended-session count per host per day — the source of session
        // counts (both scopes) and of raw sales for non-mentee hosts.
        $auto = $resolver->autoDailyGmv($hostIds, $from, $to);

        $hosts = $hostUsers->map(function (User $user) use ($primaryByHost, $menteeSales, $auto, $programsById) {
            $hostId = (int) $user->id;
            $mentee = $primaryByHost[$hostId] ?? null;

            $sessions = 0;
            $autoSales = 0.0;
            foreach ($auto[$hostId] ?? [] as $day) {
                $sessions += (int) ($day['sessions'] ?? 0);
                $autoSales += (float) ($day['gmv'] ?? 0);
            }

            $sales = $mentee
                ? (float) ($menteeSales[$mentee->id] ?? 0)
                : round($autoSales, 2);

            return [
                'host_id' => $hostId,
                'name' => $user->name,
                'initials' => self::initials($user->name),
                'sales' => round($sales, 2),
                'sessions' => $sessions,
                'is_mentee' => (bool) $mentee,
                'program' => $mentee && $programsById->has($mentee->program_id)
                    ? ['id' => (int) $mentee->program_id, 'title' => $programsById->get($mentee->program_id)->title]
                    : null,
                'pic' => $this->effectivePic($mentee, $programsById),
                'level' => $mentee && $mentee->level
                    ? ['id' => $mentee->level->id, 'name' => $mentee->level->name, 'color' => $mentee->level->color]
                    : null,
            ];
        })
            ->sortByDesc('sales')
            ->values()
            ->all();

        return Inertia::render('mentoring/Leaderboard', [
            'scope' => $scope,
            'hosts' => $hosts,
            'programs' => $programs->map(fn (LiveHostMentoringProgram $p) => [
                'id' => $p->id,
                'title' => $p->title,
            ])->values(),
            'window' => [
                'year' => $window['year'],
                'range' => ['from' => $window['from'], 'to' => $window['to']],
                'months' => $window['months'],
                'years' => $this->selectableYears($programs, $window['year']),
            ],
        ]);
    }

    /**
     * The mentee's effective PIC: the per-mentee mentor override when set, else
     * the program leader. Non-mentees have none.
     *
     * @param  Collection<int, LiveHostMentoringProgram>  $programsById
     * @return array{id: int, name: string, initials: string}|null
     */
    private function effectivePic(?LiveHostMentee $mentee, Collection $programsById): ?array
    {
        if (! $mentee) {
            return null;
        }

        $pic = $mentee->mentor ?: $programsById->get($mentee->program_id)?->leader;

        return $pic ? ['id' => $pic->id, 'name' => $pic->name, 'initials' => self::initials($pic->name)] : null;
    }

    /**
     * The chosen year + month window, derived from perf_year / perf_from /
     * perf_to (defaulting to the last six months up to the current month) — the
     * same shape the Mentoring Overview filter produces.
     *
     * @return array{year: int, from: int, to: int, months: Collection<int, array<string, mixed>>}
     */
    private function resolveMonthWindow(Request $request): array
    {
        $currentYear = (int) now()->format('Y');
        $currentMonth = (int) now()->format('n');

        $year = $request->integer('perf_year') ?: $currentYear;
        $defaultTo = $year === $currentYear ? $currentMonth : 12;
        $to = $request->integer('perf_to') ?: $defaultTo;
        $from = $request->integer('perf_from') ?: max(1, $to - 5);

        $from = max(1, min(12, $from));
        $to = max(1, min(12, $to));
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $months = collect(range($from, $to))
            ->map(fn ($m) => [
                'value' => sprintf('%04d-%02d', $year, $m),
                'year' => $year,
                'month' => (int) $m,
                'label' => CarbonImmutable::create($year, $m, 1)->format('M Y'),
            ])->values();

        return ['year' => $year, 'from' => $from, 'to' => $to, 'months' => $months];
    }

    /**
     * Selectable years for the month filter: from the earliest active program's
     * start year (or a year before now) through the current year, always
     * including the selected one.
     *
     * @param  Collection<int, LiveHostMentoringProgram>  $programs
     * @return array<int, int>
     */
    private function selectableYears(Collection $programs, int $selected): array
    {
        $current = (int) now()->format('Y');
        $starts = $programs
            ->map(fn (LiveHostMentoringProgram $p) => $p->starts_at ? (int) $p->starts_at->format('Y') : $current)
            ->push($current - 1)
            ->push($selected);

        return range((int) $starts->min(), max($current, $selected));
    }

    private static function initials(?string $name): string
    {
        if (! $name) {
            return '?';
        }
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $first = mb_substr($parts[0] ?? '', 0, 1);
        $last = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';

        return mb_strtoupper($first.$last);
    }
}
