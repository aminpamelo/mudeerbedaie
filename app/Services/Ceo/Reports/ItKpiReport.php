<?php

namespace App\Services\Ceo\Reports;

use App\Models\ItTicket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Builds the "IT KPI" matrix: one row per IT staff member (anyone assigned IT
 * Board tickets) × Jan–Dec columns for a year. Each cell shows tickets resolved
 * that month and their on-time rate ("3 · 100%"); best/worst months are tinted
 * by resolved count. Each row also carries the staff member's tickets for the
 * row drill-down (title + status + deadline).
 *
 * Mirrors StaffKpiReport but scoped to the IT Board: a ticket is credited to its
 * single assignee, "resolved" means status=done with a completed_at, and
 * "on-time" means it was completed on or before its due date.
 */
class ItKpiReport
{
    private const DONE = 'done';

    private const STATUS_TONE = [
        'backlog' => 'muted',
        'todo' => 'info',
        'in_progress' => 'warning',
        'review' => 'warning',
        'testing' => 'warning',
        'done' => 'positive',
    ];

    /** Cap drill-down tickets shown per staff row. */
    private const TICKETS_PER_STAFF = 60;

    /** Safety cap on the single ticket fetch backing every row's drill-down. */
    private const TICKET_FETCH_CAP = 2000;

    /**
     * @return array<string, mixed>
     */
    public function build(int $year): array
    {
        $now = CarbonImmutable::now();
        $todayDate = $now->startOfDay()->toDateString();
        $maxMonth = $year >= $now->year ? $now->month : 12;
        $locale = app()->getLocale();

        $staff = $this->staffRoster();
        $monthly = $this->monthlyCompletions($year);
        $ticketsByStaff = $this->drillTickets($year, $todayDate);

        $rows = [];
        $heroTrend = array_fill(0, 12, 0);
        $totalCompleted = 0;
        $totalDated = 0;
        $totalOnTime = 0;

        foreach ($staff as $person) {
            $perMonth = $monthly[$person->id] ?? [];
            $display = [];
            $trend = [];
            $values = []; // resolved per month, or null for inactive/empty months
            $sumCompleted = 0;
            $sumDated = 0;
            $sumOnTime = 0;

            for ($m = 1; $m <= 12; $m++) {
                $active = $m <= $maxMonth;
                $completed = $active ? (int) ($perMonth[$m]['completed'] ?? 0) : 0;
                $dated = $active ? (int) ($perMonth[$m]['dated'] ?? 0) : 0;
                $onTime = $active ? (int) ($perMonth[$m]['ontime'] ?? 0) : 0;

                $trend[] = $completed;
                $values[] = ($active && $completed > 0) ? $completed : null;
                // On-time % is measured over tickets that carried a due date; a
                // month whose resolved tickets had no deadlines shows just the
                // count (no rate), so undated work never looks "late".
                $display[] = $completed > 0
                    ? ($dated > 0 ? $completed.' · '.(int) round($onTime / $dated * 100).'%' : (string) $completed)
                    : '';

                $sumCompleted += $completed;
                $sumDated += $dated;
                $sumOnTime += $onTime;
                $heroTrend[$m - 1] += $completed;
            }

            $totalCompleted += $sumCompleted;
            $totalDated += $sumDated;
            $totalOnTime += $sumOnTime;

            $rows[] = [
                'key' => $person->id,
                'label' => $person->name,
                'display' => $display,
                'trend' => $trend,
                'ytdTotal' => number_format($sumCompleted),
                'ytdOnTime' => $sumDated > 0 ? (int) round($sumOnTime / $sumDated * 100).'%' : __('ceo.itkpi.na'),
                'bestIndex' => $this->extremeIndex($values, true),
                'worstIndex' => $this->extremeIndex($values, false),
                'mom' => $this->momChange($values),
                'tasks' => $ticketsByStaff[$person->id] ?? [],
            ];
        }

        $heroOnTime = $totalDated > 0 ? (int) round($totalOnTime / $totalDated * 100) : null;

        return [
            'year' => $year,
            'prevYear' => $year - 1,
            'nextYear' => $year < $now->year ? $year + 1 : null,
            'label' => (string) $year,
            'accent' => 'violet',
            'backHref' => '/ceo',
            'moduleHref' => '/admin/it-board',
            'moduleLabel' => __('ceo.itkpi.module'),
            'months' => $this->monthHeaders($locale),
            'columns' => [
                'staff' => __('ceo.itkpi.col_staff'),
                'total' => __('ceo.itkpi.col_total'),
                'onTime' => __('ceo.itkpi.col_ontime'),
                'trend' => __('ceo.itkpi.col_trend'),
            ],
            'rows' => $rows,
            'summary' => [
                'heroLabel' => __('ceo.itkpi.hero_label', ['year' => $year]),
                'heroValue' => number_format($totalCompleted),
                'heroSub' => $heroOnTime === null ? null : __('ceo.itkpi.hero_ontime', ['pct' => $heroOnTime]),
                'trend' => $heroTrend,
            ],
        ];
    }

    /**
     * The IT roster: every user assigned at least one IT Board ticket, ordered by
     * name. Members with no resolved tickets this year still appear (as an
     * all-zero row), mirroring the staff KPI roster behaviour.
     *
     * Soft-deleted staff are included (withTrashed): a soft delete does not null
     * their tickets' assignee_id, so excluding them would silently drop their
     * resolved tickets from the hero total while still counting nowhere else.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    private function staffRoster()
    {
        $assigneeIds = ItTicket::query()
            ->whereNotNull('assignee_id')
            ->distinct()
            ->pluck('assignee_id');

        return User::withTrashed()
            ->whereIn('id', $assigneeIds)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Per-assignee, per-month resolved ticket counts for the year: total resolved
     * (completed), how many carried a due date (dated), and how many of those met
     * it (ontime). On-time % is ontime/dated so undated tickets never count as late.
     *
     * @return array<int, array<int, array{completed: int, dated: int, ontime: int}>>
     */
    private function monthlyCompletions(int $year): array
    {
        $monthExpr = $this->monthExpr('completed_at');

        $rows = ItTicket::query()
            ->where('status', self::DONE)
            ->whereNotNull('assignee_id')
            ->whereNotNull('completed_at')
            ->whereYear('completed_at', $year)
            ->selectRaw("assignee_id as aid, $monthExpr as m,
                COUNT(*) as completed,
                SUM(CASE WHEN due_date IS NOT NULL THEN 1 ELSE 0 END) as dated,
                SUM(CASE WHEN due_date IS NOT NULL AND date(completed_at) <= due_date THEN 1 ELSE 0 END) as ontime")
            ->groupBy('assignee_id', 'm')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->aid][(int) $row->m] = [
                'completed' => (int) $row->completed,
                'dated' => (int) $row->dated,
                'ontime' => (int) $row->ontime,
            ];
        }

        return $out;
    }

    /**
     * The tickets each staff member can drill into: anything resolved or due in
     * the year, plus anything still open. Bucketed by assignee, overdue-first.
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function drillTickets(int $year, string $todayDate): array
    {
        $tickets = ItTicket::query()
            ->with(['type:id,name,color'])
            ->whereNotNull('assignee_id')
            ->where(function ($query) use ($year) {
                $query->whereYear('completed_at', $year)
                    ->orWhereYear('due_date', $year)
                    ->orWhere('status', '!=', self::DONE);
            })
            ->orderByRaw('CASE WHEN status != ? AND due_date IS NOT NULL AND due_date < ? THEN 0 ELSE 1 END', [self::DONE, $todayDate])
            ->orderBy('due_date')
            ->limit(self::TICKET_FETCH_CAP)
            ->get();

        $byStaff = [];
        $counts = [];

        foreach ($tickets as $ticket) {
            $assigneeId = (int) $ticket->assignee_id;
            if (($counts[$assigneeId] ?? 0) >= self::TICKETS_PER_STAFF) {
                continue;
            }
            $byStaff[$assigneeId][] = $this->ticketPayload($ticket, $todayDate);
            $counts[$assigneeId] = ($counts[$assigneeId] ?? 0) + 1;
        }

        return $byStaff;
    }

    /**
     * @return array<string, mixed>
     */
    private function ticketPayload(ItTicket $ticket, string $todayDate): array
    {
        $overdue = $ticket->status !== self::DONE
            && $ticket->due_date
            && $ticket->due_date->toDateString() < $todayDate;

        return [
            'id' => $ticket->id,
            'title' => $ticket->title,
            'status' => $ticket->status,
            'statusLabel' => __('ceo.itkpi.status_'.$ticket->status),
            'tone' => self::STATUS_TONE[$ticket->status] ?? 'muted',
            'deadline' => $ticket->due_date?->isoFormat('D MMM YYYY'),
            'source' => $ticket->ticket_number,
            'category' => $ticket->type ? ['name' => $ticket->type->name, 'color' => $ticket->type->color] : null,
            'overdue' => $overdue,
        ];
    }

    /**
     * Index (0-based) of the best/worst active month by resolved count. Null when
     * there are fewer than two months to compare.
     *
     * @param  array<int, int|null>  $values
     */
    private function extremeIndex(array $values, bool $best): ?int
    {
        $candidates = [];
        foreach ($values as $i => $v) {
            if (is_numeric($v)) {
                $candidates[$i] = $v;
            }
        }
        if (count($candidates) < 2) {
            return null;
        }

        $target = $best ? max($candidates) : min($candidates);

        return array_search($target, $candidates, true);
    }

    /**
     * Month-over-month resolved change between the two most recent active months
     * (more resolved = better).
     *
     * @param  array<int, int|null>  $values
     * @return array{text: string, tone: string}|null
     */
    private function momChange(array $values): ?array
    {
        $idx = [];
        foreach ($values as $i => $v) {
            if (is_numeric($v)) {
                $idx[] = $i;
            }
        }
        if (count($idx) < 2) {
            return null;
        }

        $last = $values[$idx[count($idx) - 1]];
        $prev = $values[$idx[count($idx) - 2]];
        if ($prev == 0) {
            return null;
        }

        $change = ($last - $prev) / abs($prev) * 100;
        $tone = abs($change) < 0.5 ? 'muted' : ($change > 0 ? 'positive' : 'negative');

        return ['text' => sprintf('%+d%%', (int) round($change)), 'tone' => $tone];
    }

    private function monthExpr(string $column): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "CAST(strftime('%m', $column) AS INTEGER)"
            : "MONTH($column)";
    }

    /**
     * Localized short month names (Jan..Dec).
     *
     * @return array<int, string>
     */
    private function monthHeaders(string $locale): array
    {
        $headers = [];
        for ($m = 1; $m <= 12; $m++) {
            $headers[] = ucfirst(CarbonImmutable::create(2000, $m, 1)->locale($locale)->isoFormat('MMM'));
        }

        return $headers;
    }
}
