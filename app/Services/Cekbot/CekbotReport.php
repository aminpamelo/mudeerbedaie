<?php

namespace App\Services\Cekbot;

use App\Models\CekbotConversation;
use App\Models\CekbotFlow;
use App\Models\CekbotFlowEnrollment;
use App\Models\CekbotLeadCategory;
use App\Models\CekbotMessage;
use App\Models\ProductOrder;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only Cekbot (WhatsApp chatbot) figures for reporting: conversations,
 * leads, flow funnels and the orders the bot created.
 */
class CekbotReport
{
    public const PERIODS = ['today', '7d', '30d', 'this_month', 'last_month', 'custom', 'all'];

    /**
     * @param  array{period?: string, start_date?: ?string, end_date?: ?string}  $filters
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    public function range(array $filters): array
    {
        return match ($filters['period'] ?? '30d') {
            'today' => [today(), today()->endOfDay()],
            '7d' => [now()->subDays(7), now()],
            '30d' => [now()->subDays(30), now()],
            'this_month' => [now()->startOfMonth(), now()->endOfMonth()],
            'last_month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            'custom' => filled($filters['start_date'] ?? null) && filled($filters['end_date'] ?? null)
                ? [Carbon::parse($filters['start_date'])->startOfDay(), Carbon::parse($filters['end_date'])->endOfDay()]
                : [null, null],
            default => [null, null],
        };
    }

    /**
     * Headline numbers for a period plus a per-flow funnel.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function overview(array $filters): array
    {
        [$from, $to] = $this->range($filters);
        $within = fn (Builder $q, string $column) => $from && $to ? $q->whereBetween($column, [$from, $to]) : $q;

        $orders = $within(ProductOrder::query()->where('source', 'whatsapp_bot'), 'order_date')->get(['id', 'total_amount', 'status', 'paid_time', 'metadata']);
        $activeOrders = $orders->where('status', '!=', 'cancelled');

        $leadCounts = $within(CekbotConversation::query()->where('is_group', false), 'created_at')
            ->selectRaw('lead_category_id, COUNT(*) as total')
            ->groupBy('lead_category_id')
            ->pluck('total', 'lead_category_id');
        $categories = CekbotLeadCategory::query()->whereIn('id', $leadCounts->keys()->filter())->pluck('name', 'id');

        return [
            'period' => ['from' => $from?->toDateString(), 'to' => $to?->toDateString()],
            'new_conversations' => (int) $leadCounts->sum(),
            'inbound_messages' => $within(CekbotMessage::query()->where('direction', CekbotMessage::DIRECTION_IN), 'created_at')->count(),
            'leads_by_category' => $leadCounts
                ->map(fn ($total, $id) => ['category' => $categories[$id] ?? 'Tiada kategori', 'conversations' => (int) $total])
                ->values()
                ->all(),
            'bot_orders' => [
                'orders' => $activeOrders->count(),
                'revenue' => round((float) $activeOrders->sum('total_amount'), 2),
                'paid_orders' => $activeOrders->filter(fn (ProductOrder $o) => $o->paid_time !== null)->count(),
                'cancelled_orders' => $orders->where('status', 'cancelled')->count(),
            ],
            'flows' => $this->flowStats($filters),
        ];
    }

    /**
     * Every flow with its setup and funnel numbers for the period.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function flowStats(array $filters): array
    {
        [$from, $to] = $this->range($filters);

        $enrollments = CekbotFlowEnrollment::query()
            ->when($from && $to, fn (Builder $q) => $q->whereBetween('started_at', [$from, $to]))
            ->get(['id', 'cekbot_flow_id', 'status', 'product_order_id'])
            ->groupBy('cekbot_flow_id');

        $revenue = ProductOrder::query()
            ->where('source', 'whatsapp_bot')
            ->where('status', '!=', 'cancelled')
            ->when($from && $to, fn (Builder $q) => $q->whereBetween('order_date', [$from, $to]))
            ->get(['total_amount', 'metadata'])
            ->groupBy(fn (ProductOrder $o) => $o->metadata['cekbot_flow_id'] ?? 0)
            ->map(fn ($group) => round((float) $group->sum('total_amount'), 2));

        return CekbotFlow::query()
            ->with('session:id,label,phone_number')
            ->withCount('packages')
            ->orderBy('cekbot_session_id')
            ->orderBy('sort_order')
            ->get()
            ->map(function (CekbotFlow $flow) use ($enrollments, $revenue) {
                $rows = $enrollments->get($flow->id, collect());
                $converted = $rows->whereNotNull('product_order_id')->count();

                return [
                    'id' => $flow->id,
                    'name' => $flow->name,
                    'number' => $flow->session?->label,
                    'is_active' => (bool) $flow->is_active,
                    'mode' => $flow->packages_count === 0 ? 'info' : ($flow->use_ai ? 'ai' : 'menu'),
                    'trigger_keywords' => $flow->trigger_keywords ?? [],
                    'trigger_ads' => count($flow->trigger_ads ?? []),
                    'packages' => $flow->packages_count,
                    'started' => $rows->count(),
                    'active' => $rows->where('status', CekbotFlowEnrollment::STATUS_ACTIVE)->count(),
                    'abandoned' => $rows->where('status', CekbotFlowEnrollment::STATUS_ABANDONED)->count(),
                    'orders' => $converted,
                    'conversion_rate' => $rows->count() > 0 ? round($converted / $rows->count() * 100, 1) : 0,
                    'revenue' => $revenue->get($flow->id, 0.0),
                ];
            })
            ->all();
    }

    /**
     * Recent orders created by the bot.
     *
     * @param  array{period?: string, start_date?: ?string, end_date?: ?string, flow_id?: ?int, payment?: ?string, limit?: int}  $filters
     * @return array<int, array<string, mixed>>
     */
    public function orders(array $filters): array
    {
        [$from, $to] = $this->range($filters);

        return ProductOrder::query()
            ->where('source', 'whatsapp_bot')
            ->when($from && $to, fn (Builder $q) => $q->whereBetween('order_date', [$from, $to]))
            ->when($filters['flow_id'] ?? null, fn (Builder $q, $id) => $q->whereJsonContains('metadata->cekbot_flow_id', (int) $id))
            ->when(($filters['payment'] ?? null) === 'paid', fn (Builder $q) => $q->whereNotNull('paid_time'))
            ->when(($filters['payment'] ?? null) === 'pending', fn (Builder $q) => $q->whereNull('paid_time')->where('status', '!=', 'cancelled'))
            ->with('items:id,order_id,product_name,quantity_ordered')
            ->latest('order_date')
            ->limit(min((int) ($filters['limit'] ?? 50), 100))
            ->get()
            ->map(fn (ProductOrder $o) => [
                'order_number' => $o->order_number,
                'date' => $o->order_date?->toDateTimeString(),
                'customer' => $o->customer_name,
                'phone' => $o->customer_phone,
                'flow' => $o->metadata['cekbot_flow_name'] ?? null,
                'items' => $o->items->map(fn ($i) => trim($i->product_name.' x'.$i->quantity_ordered))->implode(', '),
                'total' => (float) $o->total_amount,
                'payment_method' => $o->payment_method,
                'paid' => $o->paid_time !== null,
                'status' => $o->status,
            ])
            ->all();
    }
}
