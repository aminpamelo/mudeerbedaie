import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import {
  Layers,
  CheckCircle2,
  ShoppingBag,
  Wallet,
  ExternalLink,
  PencilRuler,
  TrendingUp,
  Rocket,
  Trash2,
  CalendarDays,
  CreditCard,
  Receipt,
  ChevronRight,
} from 'lucide-react';
import FighterLayout from '@/fighter/layouts/FighterLayout';
import StatTile from '@/fighter/components/StatTile';
import WeeklyChart from '@/fighter/components/WeeklyChart';
import CreateFunnelButton from '@/fighter/components/CreateFunnelButton';
import { cn, formatMoney, formatNumber, statusMeta, deleteFunnel } from '@/fighter/lib/utils';

function FunnelCard({ funnel }) {
  const status = statusMeta(funnel.status);
  const [deleting, setDeleting] = useState(false);

  const handleDelete = async () => {
    if (deleting) return;
    if (!window.confirm(`Delete “${funnel.name}”? This can't be undone.`)) return;
    setDeleting(true);
    try {
      await deleteFunnel(funnel.uuid);
      router.reload({ only: ['funnels', 'stats', 'analytics', 'funnelOptions'] });
    } catch {
      setDeleting(false);
      window.alert('Could not delete this funnel. Please try again.');
    }
  };

  return (
    <div className="fade-up flex flex-col rounded-2xl bg-white p-5 ring-1 ring-line/70">
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0">
          <h3 className="truncate text-[15.5px] font-semibold tracking-[-0.01em] text-ink">{funnel.name}</h3>
          <div className="mt-0.5 truncate text-[12px] text-muted">/{funnel.slug}</div>
        </div>
        <span className={cn('shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1', status.className)}>
          {status.label}
        </span>
      </div>

      <div className="mt-4 grid grid-cols-4 gap-2">
        <Metric label="Steps" value={formatNumber(funnel.steps_count)} />
        <Metric label="Visitors" value={formatNumber(funnel.sessions_count)} />
        <Metric label="Orders" value={formatNumber(funnel.orders_count)} />
        <Metric label="Conv." value={`${funnel.conversion_rate}%`} />
      </div>

      <div className="mt-4 flex items-center justify-between border-t border-line/70 pt-3">
        <div>
          <div className="text-[11px] font-medium uppercase tracking-[0.04em] text-muted-2">Revenue</div>
          <div className="text-[16px] font-bold text-ink">{formatMoney(funnel.revenue)}</div>
        </div>
        <div className="flex items-center gap-1.5">
          <button
            type="button"
            onClick={handleDelete}
            disabled={deleting}
            className="flex items-center justify-center rounded-lg bg-slate-100 p-2 text-muted transition-colors hover:bg-rose-50 hover:text-rose-600 disabled:opacity-50"
            title="Delete funnel"
            aria-label="Delete funnel"
          >
            <Trash2 className="h-3.5 w-3.5" strokeWidth={2.2} />
          </button>
          <a
            href={funnel.public_url}
            target="_blank"
            rel="noreferrer"
            className="flex items-center gap-1.5 rounded-lg bg-slate-100 px-2.5 py-2 text-[12.5px] font-semibold text-ink-2 transition-colors hover:bg-slate-200"
            title="Preview (Mula)"
          >
            <ExternalLink className="h-3.5 w-3.5" strokeWidth={2.2} />
            <span className="hidden sm:inline">Preview</span>
          </a>
          <a
            href={`${funnel.builder_url}?from=fighter`}
            className="flex items-center gap-1.5 rounded-lg bg-[var(--color-brand)] px-2.5 py-2 text-[12.5px] font-semibold text-white transition-colors hover:bg-[var(--color-brand-ink)]"
            title="Build / edit funnel"
          >
            <PencilRuler className="h-3.5 w-3.5" strokeWidth={2.2} />
            <span className="hidden sm:inline">Build</span>
          </a>
        </div>
      </div>
    </div>
  );
}

function Metric({ label, value }) {
  return (
    <div className="rounded-xl bg-surface px-2 py-2 text-center">
      <div className="text-[15px] font-bold tabular-nums text-ink">{value}</div>
      <div className="text-[10.5px] font-medium uppercase tracking-[0.03em] text-muted-2">{label}</div>
    </div>
  );
}

/** Section wrapper card shared by the analytics widgets. */
function Panel({ icon: Icon, title, right, children, className }) {
  return (
    <div className={cn('rounded-2xl bg-white p-5 ring-1 ring-line/70', className)}>
      <div className="flex items-center justify-between gap-2">
        <div className="flex items-center gap-2">
          {Icon && <Icon className="h-4 w-4 text-muted" strokeWidth={2.2} />}
          <h3 className="text-[13.5px] font-semibold text-ink">{title}</h3>
        </div>
        {right}
      </div>
      <div className="mt-4">{children}</div>
    </div>
  );
}

/** Big "today" hero card (orders + revenue). */
function TodayCard({ today }) {
  return (
    <div className="flex flex-col justify-between rounded-2xl bg-gradient-to-br from-[var(--color-brand)] to-[var(--color-brand-ink,#c2410c)] p-5 text-white ring-1 ring-black/5">
      <div className="flex items-center gap-2 text-white/85">
        <CalendarDays className="h-4 w-4" strokeWidth={2.2} />
        <span className="text-[12px] font-semibold uppercase tracking-[0.05em]">Today</span>
      </div>
      <div className="mt-6">
        <div className="text-[34px] font-bold leading-none tracking-[-0.02em]">{formatNumber(today?.orders ?? 0)}</div>
        <div className="mt-1 text-[12.5px] font-medium text-white/80">orders today</div>
      </div>
      <div className="mt-5 border-t border-white/20 pt-3">
        <div className="text-[11px] font-semibold uppercase tracking-[0.05em] text-white/70">Sales</div>
        <div className="mt-0.5 text-[20px] font-bold tracking-[-0.01em]">{formatMoney(today?.revenue ?? 0)}</div>
      </div>
    </div>
  );
}

const PAY_STATUS = {
  paid: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
  pending: 'bg-amber-50 text-amber-700 ring-amber-600/20',
  failed: 'bg-rose-50 text-rose-700 ring-rose-600/20',
  refunded: 'bg-slate-100 text-slate-600 ring-slate-500/20',
};

const BAR_COLORS = ['#ea580c', '#0ea5e9', '#10b981', '#f59e0b', '#8b5cf6', '#f43f5e'];

/** Ranked list of buckets with a proportional bar (for method / status). */
function Breakdown({ rows, colorByStatus = false }) {
  if (!rows?.length) {
    return <p className="py-6 text-center text-[12.5px] text-muted">No orders this month yet.</p>;
  }
  const max = Math.max(...rows.map((r) => r.revenue), 1);
  return (
    <div className="space-y-3.5">
      {rows.map((row, i) => {
        const color = colorByStatus
          ? { paid: '#10b981', pending: '#f59e0b', failed: '#f43f5e', refunded: '#94a3b8' }[row.key] ?? '#94a3b8'
          : BAR_COLORS[i % BAR_COLORS.length];
        return (
          <div key={row.key}>
            <div className="flex items-baseline justify-between gap-2">
              <span className="truncate text-[12.5px] font-medium capitalize text-ink">{row.label}</span>
              <span className="shrink-0 text-[12.5px] font-semibold tabular-nums text-ink">{formatMoney(row.revenue)}</span>
            </div>
            <div className="mt-1 flex items-center gap-2">
              <div className="h-1.5 flex-1 overflow-hidden rounded-full bg-surface">
                <div className="h-full rounded-full" style={{ width: `${(row.revenue / max) * 100}%`, background: color }} />
              </div>
              <span className="w-10 shrink-0 text-right text-[11px] tabular-nums text-muted">{formatNumber(row.orders)}</span>
            </div>
          </div>
        );
      })}
    </div>
  );
}

/** Latest orders feed. */
function RecentOrders({ rows }) {
  if (!rows?.length) {
    return <p className="py-8 text-center text-[12.5px] text-muted">No orders yet.</p>;
  }
  return (
    <div className="-mx-1 divide-y divide-line/70">
      {rows.map((o) => {
        const badge = PAY_STATUS[o.payment_status] ?? PAY_STATUS.refunded;
        return (
          <Link
            key={o.id}
            href={`/fighter/orders?highlight=${o.id}`}
            className="group flex items-center gap-3 rounded-lg px-1 py-2.5 transition-colors hover:bg-surface"
          >
            <div className="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-surface text-muted">
              <ShoppingBag className="h-4 w-4" strokeWidth={2} />
            </div>
            <div className="min-w-0 flex-1">
              <div className="truncate text-[13px] font-semibold text-ink">{o.order_number}</div>
              <div className="truncate text-[12px] text-muted">{o.customer}</div>
            </div>
            <div className="shrink-0 text-right">
              <div className="text-[13px] font-bold tabular-nums text-ink">{formatMoney(o.total)}</div>
              <span className={cn('mt-0.5 inline-block rounded-full px-1.5 py-0.5 text-[10px] font-semibold capitalize ring-1', badge)}>
                {o.payment_status}
              </span>
            </div>
            <ChevronRight className="h-4 w-4 shrink-0 text-muted-2 transition-colors group-hover:text-ink" strokeWidth={2.2} />
          </Link>
        );
      })}
    </div>
  );
}

function EmptyState() {
  return (
    <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-line bg-surface px-6 py-16 text-center">
      <div className="grid h-14 w-14 place-items-center rounded-2xl bg-orange-50 text-[var(--color-brand)]">
        <Rocket className="h-7 w-7" strokeWidth={1.8} />
      </div>
      <h3 className="mt-4 text-[16px] font-semibold text-ink">No funnels yet</h3>
      <p className="mt-1 max-w-sm text-[13.5px] text-muted">
        Build your first sales funnel — design the pages, add your products, and wire up your Conversion API.
      </p>
      <CreateFunnelButton label="Create your first funnel" className="mt-5 px-4 py-2.5 text-[13.5px]" />
    </div>
  );
}

export default function Dashboard({ funnels = [], stats, analytics, funnelOptions = [], activeFunnel = null }) {
  const onFunnelChange = (uuid) => {
    router.get('/fighter', uuid ? { funnel: uuid } : {}, {
      preserveScroll: true,
      preserveState: true,
      only: ['analytics', 'activeFunnel'],
    });
  };

  const actions = (
    <Link
      href="/fighter/performance"
      className="flex items-center gap-2 rounded-xl bg-slate-100 px-3.5 py-2.5 text-[13px] font-semibold text-ink-2 transition-colors hover:bg-slate-200"
    >
      <TrendingUp className="h-4 w-4" strokeWidth={2.2} />
      Monthly report
    </Link>
  );

  return (
    <FighterLayout title="Your funnels" subtitle="Build, preview and track the funnels you own." actions={actions}>
      <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <StatTile icon={Layers} label="Funnels" value={formatNumber(stats?.funnelsTotal ?? 0)} accent="brand" />
        <StatTile icon={CheckCircle2} label="Published" value={formatNumber(stats?.funnelsPublished ?? 0)} accent="emerald" />
        <StatTile icon={ShoppingBag} label="Orders (mo)" value={formatNumber(stats?.ordersThisMonth ?? 0)} accent="sky" />
        <StatTile icon={Wallet} label="Revenue (mo)" value={formatMoney(stats?.revenueThisMonth ?? 0)} accent="amber" />
      </div>

      {analytics && (
        <div className="mt-6 space-y-4">
          <div className="flex items-center justify-between gap-3">
            <h2 className="text-[15px] font-semibold tracking-[-0.01em] text-ink">Sales overview</h2>
            {funnelOptions.length > 0 && (
              <select
                value={activeFunnel ?? ''}
                onChange={(e) => onFunnelChange(e.target.value)}
                className="max-w-[220px] rounded-xl border-0 bg-white py-2 pl-3 pr-8 text-[12.5px] font-semibold text-ink-2 ring-1 ring-line/70 transition focus:ring-2 focus:ring-[var(--color-brand)]"
              >
                <option value="">All funnels</option>
                {funnelOptions.map((f) => (
                  <option key={f.uuid} value={f.uuid}>
                    {f.name}
                  </option>
                ))}
              </select>
            )}
          </div>

          <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <TodayCard today={analytics.today} />
            <Panel
              icon={TrendingUp}
              title="Last 7 days"
              className="lg:col-span-2"
              right={
                <div className="text-right">
                  <div className="text-[15px] font-bold tabular-nums text-ink">{formatMoney(analytics.weekTotals?.revenue ?? 0)}</div>
                  <div className="text-[11px] text-muted">{formatNumber(analytics.weekTotals?.orders ?? 0)} orders</div>
                </div>
              }
            >
              <WeeklyChart data={analytics.week ?? []} />
            </Panel>
          </div>

          <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <Panel icon={Receipt} title="Recent orders" className="lg:col-span-2">
              <RecentOrders rows={analytics.recent} />
            </Panel>
            <div className="space-y-4">
              <Panel icon={CreditCard} title="By payment method">
                <Breakdown rows={analytics.paymentMethods} />
              </Panel>
              <Panel icon={CheckCircle2} title="By payment status">
                <Breakdown rows={analytics.paymentStatuses} colorByStatus />
              </Panel>
            </div>
          </div>
        </div>
      )}

      <div className="mt-7">
        {funnels.length === 0 ? (
          <EmptyState />
        ) : (
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            {funnels.map((funnel) => (
              <FunnelCard key={funnel.uuid} funnel={funnel} />
            ))}
          </div>
        )}
      </div>
    </FighterLayout>
  );
}
