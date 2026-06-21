import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { ShoppingBag, Search, X, CheckCircle2, AlertTriangle, RotateCcw } from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import { Button } from '@/livehost/components/ui/button';

const STATUS_STYLES = {
  pending: 'bg-[#F5F5F5] text-[#737373] border-[#E5E5E5]',
  confirmed: 'bg-[#DBEAFE] text-[#1E40AF] border-[#BFDBFE]',
  processing: 'bg-[#FEF3C7] text-[#92400E] border-[#FDE68A]',
  paid: 'bg-[#DCFCE7] text-[#166534] border-[#BBF7D0]',
  shipped: 'bg-[#E0F2FE] text-[#075985] border-[#BAE6FD]',
  delivered: 'bg-[#DCFCE7] text-[#166534] border-[#BBF7D0]',
  cancelled: 'bg-[#FEE2E2] text-[#991B1B] border-[#FECACA]',
  refunded: 'bg-[#FEE2E2] text-[#991B1B] border-[#FECACA]',
  returned: 'bg-[#FEE2E2] text-[#991B1B] border-[#FECACA]',
};

function StatusBadge({ status }) {
  const cls = STATUS_STYLES[status] ?? STATUS_STYLES.pending;
  return (
    <span
      className={`inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-medium capitalize ${cls}`}
    >
      {status ?? '—'}
    </span>
  );
}

function formatMoney(amount, currency) {
  if (amount === null || amount === undefined) {
    return '—';
  }
  const n = Number(amount);
  if (Number.isNaN(n)) {
    return '—';
  }
  return `${currency || 'MYR'} ${n.toFixed(2)}`;
}

function formatDateTime(iso) {
  if (!iso) {
    return '—';
  }
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) {
    return iso;
  }
  return date.toLocaleString(undefined, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function formatSessionRange(startIso, endIso) {
  if (!startIso) {
    return '—';
  }
  const start = new Date(startIso);
  if (Number.isNaN(start.getTime())) {
    return startIso;
  }
  const startFmt = formatDateTime(startIso);
  if (!endIso) {
    return `${startFmt} → ongoing`;
  }
  const end = new Date(endIso);
  if (Number.isNaN(end.getTime())) {
    return startFmt;
  }
  const sameDay = start.toDateString() === end.toDateString();
  const endFmt = sameDay
    ? end.toLocaleString(undefined, { hour: '2-digit', minute: '2-digit' })
    : formatDateTime(endIso);
  return `${startFmt} → ${endFmt}`;
}

function StatCard({ label, value, icon: Icon, tint = 'default', accent = false }) {
  const tintClasses = {
    emerald: 'bg-[#ECFDF5] text-[#059669]',
    amber: 'bg-[#FFFBEB] text-[#B45309]',
    rose: 'bg-[#FFF1F2] text-[#BE123C]',
    ink: 'bg-[#F5F5F5] text-[#0A0A0A]',
    default: 'bg-[#F5F5F5] text-[#737373]',
  };

  return (
    <div className="rounded-[16px] border border-[#EAEAEA] bg-white p-5 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
      <div className="flex items-start justify-between gap-3">
        <div>
          <div className="text-[11.5px] font-medium uppercase tracking-wide text-[#737373]">
            {label}
          </div>
          <div
            className={`mt-2 text-2xl font-semibold tracking-[-0.02em] tabular-nums ${
              accent ? 'text-[#B45309]' : 'text-[#0A0A0A]'
            }`}
          >
            {value}
          </div>
        </div>
        {Icon && (
          <div
            className={`grid h-9 w-9 place-items-center rounded-lg ${tintClasses[tint] ?? tintClasses.default}`}
          >
            <Icon className="h-4 w-4" strokeWidth={2} />
          </div>
        )}
      </div>
    </div>
  );
}

export default function PlatformOrdersIndex() {
  const { orders, summary, shops, filters } = usePage().props;

  const [search, setSearch] = useState(filters?.search ?? '');
  const [shop, setShop] = useState(filters?.shop ?? '');
  const [unmatched, setUnmatched] = useState(
    filters?.unmatched_only === '1' || filters?.unmatched_only === 1 || filters?.unmatched_only === true
  );

  const buildParams = (overrides = {}) => {
    const next = {
      search: overrides.search ?? search,
      shop: overrides.shop ?? shop,
      unmatched_only: (overrides.unmatched ?? unmatched) ? '1' : '',
    };
    Object.keys(next).forEach((k) => {
      if (next[k] === '' || next[k] === null || next[k] === undefined) {
        delete next[k];
      }
    });
    return next;
  };

  const applyFilters = (overrides = {}) => {
    router.get('/livehost/orders', buildParams(overrides), {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    });
  };

  const reset = () => {
    setSearch('');
    setShop('');
    setUnmatched(false);
    router.get(
      '/livehost/orders',
      {},
      {
        preserveState: true,
        preserveScroll: true,
        replace: true,
      }
    );
  };

  const hasFilters = Boolean(search || shop || unmatched);

  return (
    <>
      <Head title="Platform Orders" />
      <TopBar breadcrumb={['Live Host Desk', 'Platform Orders']} />

      <div className="space-y-6 p-8">
        <div className="flex flex-wrap items-end justify-between gap-8">
          <div>
            <h1 className="flex items-center gap-2.5 text-3xl font-semibold leading-[1.1] tracking-[-0.03em] text-[#0A0A0A]">
              <ShoppingBag className="h-7 w-7 text-[#404040]" strokeWidth={2} />
              Platform Orders
            </h1>
            <p className="mt-1.5 text-sm text-[#737373]">
              TikTok Shop orders synced via the platform integration. Used as the source for refund
              reconciliation and live host commission.
            </p>
          </div>
        </div>

        {/* Summary cards */}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <StatCard
            label="Total orders"
            value={summary?.total ?? 0}
            icon={ShoppingBag}
            tint="ink"
          />
          <StatCard
            label="Matched to session"
            value={summary?.matched ?? 0}
            icon={CheckCircle2}
            tint="emerald"
          />
          <StatCard
            label="Unmatched"
            value={summary?.unmatched ?? 0}
            icon={AlertTriangle}
            tint="amber"
            accent={(summary?.unmatched ?? 0) > 0}
          />
          <StatCard
            label="Refunded / cancelled"
            value={summary?.refunded ?? 0}
            icon={RotateCcw}
            tint="rose"
          />
        </div>

        {/* Filter bar */}
        <div className="flex flex-wrap items-center gap-3 rounded-[16px] border border-[#EAEAEA] bg-white p-4 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
          <div className="relative max-w-md flex-1 min-w-[220px]">
            <Search
              className="pointer-events-none absolute left-3 top-1/2 h-[14px] w-[14px] -translate-y-1/2 text-[#737373]"
              strokeWidth={2}
            />
            <input
              type="text"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              onKeyDown={(event) => {
                if (event.key === 'Enter') {
                  event.preventDefault();
                  applyFilters();
                }
              }}
              placeholder="Search order #, platform ID, email…"
              className="h-9 w-full rounded-lg border border-[#EAEAEA] bg-[#FAFAFA] pl-9 pr-3 text-sm text-[#0A0A0A] placeholder:text-[#A3A3A3] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
            />
          </div>
          <select
            value={shop}
            onChange={(event) => {
              const next = event.target.value;
              setShop(next);
              applyFilters({ shop: next });
            }}
            className="h-9 rounded-lg border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
          >
            <option value="">All shops</option>
            {(shops ?? []).map((s) => (
              <option key={s.id} value={s.id}>
                {s.name}
              </option>
            ))}
          </select>
          <label className="inline-flex h-9 items-center gap-2 rounded-lg border border-[#EAEAEA] bg-white px-3 text-[12.5px] text-[#0A0A0A]">
            <input
              type="checkbox"
              checked={unmatched}
              onChange={(event) => {
                const next = event.target.checked;
                setUnmatched(next);
                applyFilters({ unmatched: next });
              }}
              className="h-3.5 w-3.5 rounded border-[#D4D4D4] text-[#10B981] focus:ring-[#10B981]/20"
            />
            Unmatched only
          </label>
          <Button
            variant="outline"
            size="sm"
            onClick={() => applyFilters()}
            className="border-[#EAEAEA] bg-white text-[#0A0A0A] hover:bg-[#F5F5F5] shadow-none focus-visible:border-[#EAEAEA] focus-visible:ring-2 focus-visible:ring-[#10B981]/20"
          >
            Apply
          </Button>
          {hasFilters && (
            <button
              type="button"
              onClick={reset}
              className="inline-flex items-center gap-1 text-sm font-medium text-[#059669] hover:text-[#047857]"
            >
              <X className="h-3.5 w-3.5" strokeWidth={2} />
              Reset
            </button>
          )}
        </div>

        {/* Table */}
        <div className="overflow-hidden rounded-[16px] border border-[#EAEAEA] bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
          {orders.data.length === 0 ? (
            <div className="py-16 text-center">
              <div className="text-sm text-[#737373]">No orders match these filters.</div>
              {hasFilters && (
                <button
                  type="button"
                  onClick={reset}
                  className="mt-2 text-sm font-medium text-[#059669] hover:text-[#047857]"
                >
                  Clear filters
                </button>
              )}
            </div>
          ) : (
            <table className="w-full text-sm">
              <thead>
                <tr className="bg-[#F5F5F5] text-[11.5px] font-medium uppercase tracking-wide text-[#737373]">
                  <th className="px-5 py-3 text-left">Order</th>
                  <th className="px-5 py-3 text-left">Shop</th>
                  <th className="px-5 py-3 text-right">Total</th>
                  <th className="px-5 py-3 text-left">Status</th>
                  <th className="px-5 py-3 text-left">Matched session</th>
                  <th className="px-5 py-3 text-left">Paid at</th>
                </tr>
              </thead>
              <tbody>
                {orders.data.map((order) => (
                  <tr
                    key={order.id}
                    className="border-t border-[#F0F0F0] transition-colors hover:bg-[#FAFAFA]"
                  >
                    <td className="px-5 py-3.5">
                      <a
                        href={`/admin/product-orders/${order.id}`}
                        target="_blank"
                        rel="noreferrer"
                        className="font-mono text-[12.5px] font-semibold tracking-[-0.01em] text-[#0A0A0A] hover:text-[#10B981]"
                      >
                        {order.order_number ?? '—'}
                      </a>
                      {order.platform_order_id && (
                        <div className="mt-0.5 truncate max-w-[220px] text-[11.5px] text-[#A3A3A3]">
                          {order.platform_order_id}
                        </div>
                      )}
                    </td>
                    <td className="px-5 py-3.5 text-[12.5px] text-[#0A0A0A]">
                      {order.platform_account?.name ?? '—'}
                    </td>
                    <td className="px-5 py-3.5 text-right tabular-nums text-[13px] font-semibold text-[#0A0A0A]">
                      {formatMoney(order.total_amount, order.currency)}
                    </td>
                    <td className="px-5 py-3.5">
                      <StatusBadge status={order.status} />
                    </td>
                    <td className="px-5 py-3.5">
                      {order.matched_live_session ? (
                        <Link
                          href={`/livehost/sessions/${order.matched_live_session.id}`}
                          className="text-[12.5px] font-medium text-[#10B981] hover:underline"
                        >
                          {formatSessionRange(
                            order.matched_live_session.actual_start_at,
                            order.matched_live_session.actual_end_at
                          )}
                          {order.matched_live_session.live_host?.name && (
                            <span className="ml-1 text-[#737373]">
                              · {order.matched_live_session.live_host.name}
                            </span>
                          )}
                        </Link>
                      ) : (
                        <span className="text-[12.5px] text-[#A3A3A3]">—</span>
                      )}
                    </td>
                    <td className="px-5 py-3.5 text-[12.5px] tabular-nums text-[#404040]">
                      {formatDateTime(order.paid_time)}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>

        {/* Pagination */}
        {orders.last_page > 1 && (
          <div className="flex items-center justify-between">
            <div className="text-xs text-[#737373]">
              Showing {orders.from ?? 0}–{orders.to ?? 0} of {orders.total}
            </div>
            <div className="flex gap-1">
              {orders.links.map((link, index) => (
                <button
                  key={`${link.label}-${index}`}
                  type="button"
                  disabled={!link.url}
                  onClick={() => {
                    if (link.url) {
                      router.visit(link.url, {
                        preserveScroll: true,
                        preserveState: true,
                      });
                    }
                  }}
                  dangerouslySetInnerHTML={{ __html: link.label }}
                  className={[
                    'min-w-8 h-8 rounded-md px-2 text-xs font-medium',
                    link.active
                      ? 'bg-[#0A0A0A] text-white'
                      : 'text-[#737373] hover:bg-[#F5F5F5]',
                    !link.url ? 'cursor-default opacity-40' : '',
                  ].join(' ')}
                />
              ))}
            </div>
          </div>
        )}
      </div>
    </>
  );
}

PlatformOrdersIndex.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;
