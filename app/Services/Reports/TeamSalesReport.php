<?php

namespace App\Services\Reports;

use App\Models\ProductOrder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Team Sales figures — the same rules as the admin "Sales Department Report →
 * Team Sales" tab (resources/views/livewire/admin/reports/sales-department):
 * orders carrying metadata.salesperson_id (set by POS), plus unassigned POS
 * orders; revenue sums total_amount and excludes cancelled orders.
 */
class TeamSalesReport
{
    public const PERIODS = ['today', 'this_week', 'this_month', 'last_month', 'this_year', 'custom', 'all'];

    public const STATUSES = ['all', 'paid', 'pending', 'cancelled'];

    /**
     * @param  array{period?: string, start_date?: ?string, end_date?: ?string, salesperson_id?: int|string|null, status?: string}  $filters
     */
    public function query(array $filters): Builder
    {
        $query = ProductOrder::query()->where(function (Builder $q): void {
            $q->where(fn (Builder $sub) => $sub->whereNotNull('metadata')->whereRaw("json_extract(metadata, '$.salesperson_id') IS NOT NULL"))
                ->orWhere(fn (Builder $sub) => $this->unassignedPos($sub));
        });

        $salesperson = $filters['salesperson_id'] ?? 'all';
        if ($salesperson === 'unassigned') {
            $this->unassignedPos($query);
        } elseif ($salesperson !== 'all' && $salesperson !== null && $salesperson !== '') {
            $query->whereJsonContains('metadata->salesperson_id', (int) $salesperson);
        }

        match ($filters['status'] ?? 'all') {
            'paid' => $query->whereNotNull('paid_time'),
            'pending' => $query->whereNull('paid_time')->where('status', '!=', 'cancelled'),
            'cancelled' => $query->where('status', 'cancelled'),
            default => null,
        };

        [$from, $to] = $this->range($filters);
        if ($from && $to) {
            $query->whereBetween('order_date', [$from, $to]);
        }

        return $query;
    }

    /**
     * The order_date window for a period, or [null, null] for "all".
     *
     * @param  array{period?: string, start_date?: ?string, end_date?: ?string}  $filters
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    public function range(array $filters): array
    {
        return match ($filters['period'] ?? 'this_month') {
            'today' => [today(), today()->endOfDay()],
            'this_week' => [now()->startOfWeek(), now()->endOfWeek()],
            'this_month' => [now()->startOfMonth(), now()->endOfMonth()],
            'last_month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            'this_year' => [now()->startOfYear(), now()->endOfYear()],
            'custom' => filled($filters['start_date'] ?? null) && filled($filters['end_date'] ?? null)
                ? [Carbon::parse($filters['start_date'])->startOfDay(), Carbon::parse($filters['end_date'])->endOfDay()]
                : [null, null],
            default => [null, null],
        };
    }

    /**
     * Totals, status split and per-salesperson ranking for the filters.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function summary(array $filters): array
    {
        $all = $this->query($filters)->with('items:id,order_id,quantity_ordered')->get();
        $active = $all->where('status', '!=', 'cancelled');
        $revenue = round((float) $active->sum('total_amount'), 2);

        $split = fn (Collection $orders) => ['count' => $orders->count(), 'revenue' => round((float) $orders->sum('total_amount'), 2)];

        return [
            'total_revenue' => $revenue,
            'total_orders' => $active->count(),
            'avg_order_value' => $active->count() > 0 ? round($revenue / $active->count(), 2) : 0,
            'total_items' => (int) $active->sum(fn (ProductOrder $o) => $o->items->sum('quantity_ordered')),
            'status_breakdown' => [
                'paid' => $split($all->filter(fn (ProductOrder $o) => $o->paid_time !== null)),
                'pending' => $split($all->filter(fn (ProductOrder $o) => $o->paid_time === null && $o->status !== 'cancelled')),
                'cancelled' => $split($all->where('status', 'cancelled')),
            ],
            'salespeople' => $this->bySalesperson($active),
        ];
    }

    /**
     * Revenue and order count per salesperson for each month of a year.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function monthly(array $filters, int $year): array
    {
        $orders = $this->query(['period' => 'all'] + $filters)
            ->where('status', '!=', 'cancelled')
            ->whereYear('order_date', $year)
            ->get();

        return collect(range(1, 12))->map(function (int $month) use ($orders, $year) {
            $inMonth = $orders->filter(fn (ProductOrder $o) => $o->order_date && (int) $o->order_date->format('n') === $month);

            return [
                'month' => sprintf('%d-%02d', $year, $month),
                'orders' => $inMonth->count(),
                'revenue' => round((float) $inMonth->sum('total_amount'), 2),
                'salespeople' => collect($this->bySalesperson($inMonth))
                    ->map(fn (array $row) => ['name' => $row['name'], 'orders' => $row['orders'], 'revenue' => $row['revenue']])
                    ->all(),
            ];
        })->all();
    }

    /**
     * Everyone who has ever been recorded as a salesperson on an order.
     *
     * @return Collection<int, array{id: int, name: string}>
     */
    public function salespeople(): Collection
    {
        $ids = ProductOrder::query()
            ->whereNotNull('metadata')
            ->whereRaw("json_extract(metadata, '$.salesperson_id') IS NOT NULL")
            ->get(['id', 'metadata'])
            ->pluck('metadata.salesperson_id')
            ->filter()
            ->unique()
            ->values();

        return User::query()->whereIn('id', $ids)->orderBy('name')->get(['id', 'name'])
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name]);
    }

    /**
     * @param  Collection<int, ProductOrder>  $orders
     * @return array<int, array<string, mixed>>
     */
    private function bySalesperson(Collection $orders): array
    {
        return $orders
            ->groupBy(fn (ProductOrder $o) => $o->metadata['salesperson_id'] ?? 'unassigned')
            ->map(function (Collection $group, $id) {
                $revenue = round((float) $group->sum('total_amount'), 2);

                return [
                    'salesperson_id' => $id === 'unassigned' ? null : (int) $id,
                    'name' => $id === 'unassigned' ? 'Unassigned' : ($group->first()->metadata['salesperson_name'] ?? 'Unknown'),
                    'orders' => $group->count(),
                    'revenue' => $revenue,
                    'avg_order_value' => $group->count() > 0 ? round($revenue / $group->count(), 2) : 0,
                    'last_sale' => $group->max('order_date')?->toDateString(),
                ];
            })
            ->sortByDesc('revenue')
            ->values()
            ->all();
    }

    private function unassignedPos(Builder $query): Builder
    {
        return $query->where('source', 'pos')
            ->where(fn (Builder $q) => $q->whereNull('metadata')->orWhereRaw("json_extract(metadata, '$.salesperson_id') IS NULL"));
    }
}
