import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Layers, CheckCircle2, ShoppingBag, Wallet, ExternalLink, PencilRuler, TrendingUp, Rocket, Trash2 } from 'lucide-react';
import FighterLayout from '@/fighter/layouts/FighterLayout';
import StatTile from '@/fighter/components/StatTile';
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
      router.reload({ only: ['funnels', 'stats'] });
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

export default function Dashboard({ funnels = [], stats }) {
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
