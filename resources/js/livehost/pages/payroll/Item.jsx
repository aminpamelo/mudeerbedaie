import { Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { ArrowLeft, ExternalLink, Loader2, Percent, ShoppingBag, Wallet } from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import PayrollBreakdownBody from '@/livehost/components/payroll/PayrollBreakdown';

const ORDER_STATUS_STYLE = {
  completed: 'bg-[#DCFCE7] text-[#166534]',
  delivered: 'bg-[#DCFCE7] text-[#166534]',
  shipped: 'bg-[#DBEAFE] text-[#1E40AF]',
  processing: 'bg-[#FEF3C7] text-[#92400E]',
  confirmed: 'bg-[#F5F5F5] text-[#525252]',
  cancelled: 'bg-[#FEE2E2] text-[#991B1B]',
  refunded: 'bg-[#FEE2E2] text-[#991B1B]',
  returned: 'bg-[#FEE2E2] text-[#991B1B]',
};
const orderStatusCls = (s) => ORDER_STATUS_STYLE[s] ?? 'bg-[#F5F5F5] text-[#525252]';

function shortDate(iso) {
  if (!iso) {
    return '—';
  }
  const d = new Date(iso);
  return Number.isNaN(d.getTime()) ? '—' : d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
}

const STATUS_STYLES = {
  draft: 'bg-[#F5F5F5] text-[#737373] border-[#E5E5E5]',
  locked: 'bg-[#FEF3C7] text-[#92400E] border-[#FDE68A]',
  paid: 'bg-[#DCFCE7] text-[#166534] border-[#BBF7D0]',
};
const STATUS_LABELS = { draft: 'Draft', locked: 'Locked', paid: 'Paid' };

function rm(value) {
  const n = Number(value ?? 0);
  if (!Number.isFinite(n)) {
    return '—';
  }
  return `RM ${n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function formatPeriod(startIso, endIso) {
  if (!startIso || !endIso) {
    return '—';
  }
  const start = new Date(`${startIso}T00:00:00`);
  const end = new Date(`${endIso}T00:00:00`);
  if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) {
    return `${startIso} – ${endIso}`;
  }
  const sM = start.toLocaleString(undefined, { month: 'short' });
  const eM = end.toLocaleString(undefined, { month: 'short' });
  const year = end.getFullYear();
  return start.getFullYear() === end.getFullYear() && sM === eM
    ? `${sM} ${start.getDate()} – ${end.getDate()}, ${year}`
    : `${sM} ${start.getDate()} – ${eM} ${end.getDate()}, ${year}`;
}

function initialsOf(name) {
  return (name ?? '?')
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((p) => p[0]?.toUpperCase() ?? '')
    .join('') || '?';
}

function Tile({ label, value, strong = false, accent }) {
  const tone = accent === 'primary' ? 'text-[#059669]' : accent === 'warn' ? 'text-[#B45309]' : 'text-[#0A0A0A]';
  return (
    <div className={`rounded-[14px] border border-[#EAEAEA] bg-white p-4 ${strong ? 'ring-1 ring-[#A7F3D0]' : ''}`}>
      <div className="text-[10.5px] font-medium uppercase tracking-[0.04em] text-[#737373]">{label}</div>
      <div className={`mt-1.5 text-[18px] font-semibold tabular-nums tracking-[-0.02em] ${tone}`}>{value}</div>
    </div>
  );
}

export default function PayrollItem() {
  const { run, item, orders, rateContext } = usePage().props;
  const statusCls = STATUS_STYLES[run.status] ?? STATUS_STYLES.draft;

  return (
    <>
      <Head title={`${item.host_name ?? 'Host'} · Payroll Run #${run.id}`} />
      <TopBar breadcrumb={['Live Host Desk', 'Payroll', `Run #${run.id}`, item.host_name ?? 'Host']} />

      <div className="space-y-6 p-4 sm:p-6 lg:p-8">
        <Link href={`/livehost/payroll/${run.id}`} className="inline-flex items-center gap-1.5 text-[13px] font-medium text-[#737373] hover:text-[#0A0A0A]">
          <ArrowLeft className="h-4 w-4" strokeWidth={2} /> Back to Run #{run.id}
        </Link>

        {/* Host header */}
        <div className="flex flex-wrap items-start justify-between gap-4 rounded-[16px] border border-[#EAEAEA] bg-white p-5 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
          <div className="flex items-center gap-3.5">
            <span className="grid h-12 w-12 place-items-center rounded-xl bg-gradient-to-br from-[#10B981] to-[#059669] text-sm font-semibold text-white">
              {initialsOf(item.host_name)}
            </span>
            <div>
              <h1 className="text-xl font-semibold tracking-[-0.02em] text-[#0A0A0A] sm:text-2xl">{item.host_name ?? 'Unknown host'}</h1>
              <div className="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-[12.5px] text-[#737373]">
                {item.host_email && <span>{item.host_email}</span>}
                <span className="text-[#D4D4D4]">·</span>
                <span>Run #{run.id} · {formatPeriod(run.period_start, run.period_end)}</span>
                <span className={`inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-medium ${statusCls}`}>
                  {STATUS_LABELS[run.status] ?? run.status}
                </span>
              </div>
            </div>
          </div>
          <div className="text-right">
            <div className="flex items-center justify-end gap-1.5 text-[11px] font-medium uppercase tracking-[0.04em] text-[#737373]">
              <Wallet className="h-3.5 w-3.5" strokeWidth={2} /> Net payout
            </div>
            <div className="mt-0.5 text-2xl font-semibold tabular-nums tracking-[-0.02em] text-[#059669]">{rm(item.net_payout_myr)}</div>
          </div>
        </div>

        {/* Payout components */}
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
          <Tile label="Base salary" value={rm(item.base_salary_myr)} />
          <Tile label={`Per-live (${item.sessions_count} sessions)`} value={rm(item.total_per_live_myr)} />
          <Tile label="Net GMV" value={rm(item.net_gmv_myr)} />
          <Tile label="GMV commission" value={rm(item.gmv_commission_myr)} accent="primary" />
          <Tile label="Override L1" value={rm(item.override_l1_myr)} />
          <Tile label="Override L2" value={rm(item.override_l2_myr)} />
          <Tile label="Gross total" value={rm(item.gross_total_myr)} />
          <Tile label="Deductions" value={rm(item.deductions_myr)} accent="warn" />
        </div>

        {/* Commission rate editor (draft runs) */}
        <RateEditor run={run} item={item} rateContext={rateContext} />

        {/* Session detail + overrides (shared with the inline expand) */}
        <div className="rounded-[16px] border border-[#EAEAEA] bg-white p-5 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
          <PayrollBreakdownBody item={item} />
        </div>

        {/* Orders from this host's lives */}
        <OrdersSection orders={orders} />
      </div>
    </>
  );
}

function RateEditor({ run, item, rateContext }) {
  const platforms = rateContext?.platforms ?? [];
  if (!rateContext?.editable || platforms.length === 0) {
    return null;
  }
  return (
    <div className="rounded-[16px] border border-[#EAEAEA] bg-white p-5 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
      <div className="mb-3 flex items-center gap-2.5">
        <span className="grid h-9 w-9 place-items-center rounded-xl bg-[#EEF2FF] text-[#4338CA]"><Percent className="h-4 w-4" strokeWidth={2.25} /></span>
        <div>
          <h2 className="text-[15px] font-semibold tracking-[-0.015em] text-[#0A0A0A]">GMV commission rate</h2>
          <p className="mt-0.5 text-[12px] text-[#737373]">Applies from {rateContext.period_start} (this run's start) and recomputes the run. Draft only.</p>
        </div>
      </div>
      <div className="flex flex-col divide-y divide-[#F0F0F0]">
        {platforms.map((p) => <RateRow key={p.platform_id} run={run} item={item} platform={p} />)}
      </div>
    </div>
  );
}

function RateRow({ run, item, platform }) {
  const [rate, setRate] = useState(platform.current_rate != null ? String(platform.current_rate) : '');
  const [busy, setBusy] = useState(false);

  const save = () => {
    if (rate === '' || busy) {
      return;
    }
    setBusy(true);
    router.post(
      `/livehost/payroll/${run.id}/items/${item.id}/rate`,
      { platform_id: platform.platform_id, commission_rate_percent: Number(rate) },
      { preserveScroll: true, onFinish: () => setBusy(false) },
    );
  };

  return (
    <div className="flex flex-wrap items-center gap-3 py-2.5">
      <div className="min-w-[140px] flex-1">
        <div className="text-[13px] font-medium text-[#0A0A0A]">{platform.platform_name}</div>
        <div className={`text-[11px] ${platform.current_rate == null ? 'text-[#B45309]' : 'text-[#737373]'}`}>
          {platform.current_rate == null ? 'No rate set — commission is RM 0' : `Current: ${platform.current_rate}%`}
        </div>
      </div>
      <div className="flex items-center gap-1.5">
        <input
          type="number" min="0" max="100" step="0.01" value={rate}
          onChange={(e) => setRate(e.target.value)}
          placeholder="0.00"
          className="h-9 w-24 rounded-lg border border-[#EAEAEA] bg-white px-2.5 text-right text-[13px] tabular-nums text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
        />
        <span className="text-[13px] text-[#737373]">%</span>
      </div>
      <button
        type="button"
        onClick={save}
        disabled={busy || rate === ''}
        className="inline-flex h-9 items-center gap-1.5 rounded-lg bg-[#10B981] px-3 text-[12.5px] font-semibold text-white transition-colors hover:bg-[#059669] disabled:opacity-40"
      >
        {busy ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : null} Save &amp; recompute
      </button>
    </div>
  );
}

function OrdersSection({ orders }) {
  const summary = orders?.summary ?? { total: 0, total_amount: 0, refunded_count: 0, refunded_amount: 0, by_status: [], shown: 0 };
  const list = orders?.list ?? [];
  const [status, setStatus] = useState('all');
  const [session, setSession] = useState('all');

  const sessionIds = useMemo(
    () => [...new Set(list.map((o) => o.session_id).filter(Boolean))].sort((a, b) => a - b),
    [list],
  );

  const visible = useMemo(
    () => list.filter((o) => (status === 'all' || o.status === status) && (session === 'all' || String(o.session_id) === session)),
    [list, status, session],
  );

  return (
    <div className="rounded-[16px] border border-[#EAEAEA] bg-white p-5 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-2.5">
          <span className="grid h-9 w-9 place-items-center rounded-xl bg-[#F5F5F5] text-[#525252]"><ShoppingBag className="h-4 w-4" strokeWidth={2} /></span>
          <div>
            <h2 className="text-[15px] font-semibold tracking-[-0.015em] text-[#0A0A0A]">Orders from lives</h2>
            <p className="mt-0.5 text-[12px] text-[#737373]">TikTok Shop orders matched to this host's sessions this run</p>
          </div>
        </div>
      </div>

      {/* Summary tiles */}
      <div className="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <Tile label="Total orders" value={summary.total.toLocaleString()} />
        <Tile label="Total value" value={rm(summary.total_amount)} />
        <Tile label="Refunded / cancelled" value={summary.refunded_count.toLocaleString()} accent="warn" />
        <Tile label="Refunded value" value={rm(summary.refunded_amount)} accent="warn" />
      </div>

      {summary.total === 0 ? (
        <div className="rounded-[12px] border border-dashed border-[#E5E5E5] px-4 py-8 text-center text-[12.5px] text-[#A3A3A3]">
          No orders matched to this host's sessions.
        </div>
      ) : (
        <>
          {/* Filters */}
          <div className="mb-3 flex flex-wrap items-center gap-2">
            <div className="flex flex-wrap gap-1">
              <FilterChip label={`All (${summary.total})`} active={status === 'all'} onClick={() => setStatus('all')} />
              {summary.by_status.map((s) => (
                <FilterChip key={s.status} label={`${s.status} (${s.count})`} active={status === s.status} onClick={() => setStatus(s.status)} tone={orderStatusCls(s.status)} />
              ))}
            </div>
            {sessionIds.length > 1 && (
              <select value={session} onChange={(e) => setSession(e.target.value)} className="ml-auto h-8 rounded-lg border border-[#EAEAEA] bg-white px-2 text-[12px] text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20">
                <option value="all">All sessions</option>
                {sessionIds.map((sid) => <option key={sid} value={String(sid)}>Session #{sid}</option>)}
              </select>
            )}
          </div>

          <div className="overflow-hidden rounded-[12px] border border-[#EAEAEA]">
            <table className="w-full text-[12px]">
              <thead>
                <tr className="bg-[#F5F5F5] text-[10.5px] font-medium text-[#737373]">
                  <th className="px-3 py-2 text-left">Order</th>
                  <th className="px-3 py-2 text-left">Shop</th>
                  <th className="px-3 py-2 text-right">Session</th>
                  <th className="px-3 py-2 text-right">Total</th>
                  <th className="px-3 py-2 text-left">Status</th>
                  <th className="px-3 py-2 text-right">Paid</th>
                </tr>
              </thead>
              <tbody>
                {visible.map((o) => (
                  <tr key={o.id} className="border-t border-[#F0F0F0]">
                    <td className="px-3 py-1.5 font-mono text-[11px] text-[#0A0A0A]">{o.ref ?? `#${o.id}`}</td>
                    <td className="px-3 py-1.5 text-[#525252]">{o.shop ?? '—'}</td>
                    <td className="px-3 py-1.5 text-right">
                      <Link href={`/livehost/orders?session=${o.session_id}`} onClick={(e) => e.stopPropagation()} className="inline-flex items-center gap-1 font-mono text-[11px] text-[#4338CA] hover:underline">
                        #{o.session_id}<ExternalLink className="h-2.5 w-2.5" strokeWidth={2} />
                      </Link>
                    </td>
                    <td className="px-3 py-1.5 text-right tabular-nums">{rm(o.total)}</td>
                    <td className="px-3 py-1.5">
                      <span className={`inline-flex items-center rounded-full px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide ${orderStatusCls(o.status)}`}>{o.status}</span>
                    </td>
                    <td className="px-3 py-1.5 text-right tabular-nums text-[#737373]">{shortDate(o.paid_at)}</td>
                  </tr>
                ))}
                {visible.length === 0 && (
                  <tr><td colSpan={6} className="px-3 py-6 text-center text-[12px] text-[#A3A3A3]">No orders match this filter.</td></tr>
                )}
              </tbody>
            </table>
          </div>
          {summary.shown < summary.total && (
            <p className="mt-2 text-[11px] text-[#A3A3A3]">Showing the latest {summary.shown} of {summary.total} orders. Use the per-session filter or open Platform Orders for the full list.</p>
          )}
        </>
      )}
    </div>
  );
}

function FilterChip({ label, active, onClick, tone }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={`rounded-full px-2.5 py-1 text-[11.5px] font-medium capitalize transition-colors ${
        active ? 'bg-[#0A0A0A] text-white' : `${tone ?? 'bg-[#F5F5F5] text-[#525252]'} hover:opacity-80`
      }`}
    >
      {label}
    </button>
  );
}

PayrollItem.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;
