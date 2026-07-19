import { Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, ShoppingBag, Plus, Paperclip } from 'lucide-react';
import FighterLayout from '@/fighter/layouts/FighterLayout';
import { cn, formatMoney, formatDate } from '@/fighter/lib/utils';

const STATUS_STYLES = {
  pending: 'bg-amber-50 text-amber-700 ring-amber-600/20',
  confirmed: 'bg-blue-50 text-blue-700 ring-blue-600/20',
  processing: 'bg-violet-50 text-violet-700 ring-violet-600/20',
  shipped: 'bg-sky-50 text-sky-700 ring-sky-600/20',
  delivered: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
  cancelled: 'bg-rose-50 text-rose-700 ring-rose-600/20',
  refunded: 'bg-rose-50 text-rose-700 ring-rose-600/20',
  returned: 'bg-rose-50 text-rose-700 ring-rose-600/20',
};

const PAYMENT_STYLES = {
  paid: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
  pending: 'bg-amber-50 text-amber-700 ring-amber-600/20',
  failed: 'bg-rose-50 text-rose-700 ring-rose-600/20',
  refunded: 'bg-slate-100 text-slate-600 ring-slate-500/20',
};

function Pill({ value, map }) {
  const cls = map[value] ?? 'bg-slate-100 text-slate-600 ring-slate-500/20';
  return (
    <span className={cn('inline-block rounded-full px-2.5 py-0.5 text-[11.5px] font-semibold capitalize ring-1', cls)}>
      {String(value || '—').replace(/_/g, ' ')}
    </span>
  );
}

function CreateOrderButton({ variant = 'header' }) {
  const base = 'flex items-center justify-center gap-2 rounded-xl bg-[var(--color-brand)] font-semibold text-white transition-colors hover:bg-[var(--color-brand-ink)]';
  return (
    <Link href="/fighter/orders/create" className={cn(base, variant === 'header' ? 'px-3.5 py-2.5 text-[13px]' : 'mt-5 px-4 py-2.5 text-[13.5px]')}>
      <Plus className="h-4 w-4" strokeWidth={2.4} />
      Create order
    </Link>
  );
}

function goToPage(page) {
  router.get('/fighter/orders', { page }, { preserveScroll: true, preserveState: true });
}

export default function Orders({ orders }) {
  const rows = orders?.data ?? [];
  const meta = orders?.meta ?? { current_page: 1, last_page: 1, total: 0 };

  return (
    <FighterLayout
      title="Orders"
      subtitle="Orders from your funnels and manual orders. Fulfilment is handled by the team."
      actions={<CreateOrderButton />}
    >
      {rows.length === 0 ? (
        <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-line bg-surface px-6 py-16 text-center">
          <div className="grid h-14 w-14 place-items-center rounded-2xl bg-orange-50 text-[var(--color-brand)]">
            <ShoppingBag className="h-7 w-7" strokeWidth={1.8} />
          </div>
          <h3 className="mt-4 text-[16px] font-semibold text-ink">No orders yet</h3>
          <p className="mt-1 max-w-sm text-[13.5px] text-muted">
            Orders from your funnels show up here. You can also record an order manually.
          </p>
          <CreateOrderButton variant="empty" />
        </div>
      ) : (
        <>
          <div className="overflow-hidden rounded-2xl ring-1 ring-line/70">
            <div className="overflow-x-auto">
              <table className="min-w-full">
                <thead className="bg-surface">
                  <tr>
                    <th className="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-[0.04em] text-muted-2">Order</th>
                    <th className="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-[0.04em] text-muted-2">Source</th>
                    <th className="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-[0.04em] text-muted-2">Status</th>
                    <th className="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-[0.04em] text-muted-2">Payment</th>
                    <th className="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-[0.04em] text-muted-2">Tracking</th>
                    <th className="px-4 py-2.5 text-center text-[11px] font-semibold uppercase tracking-[0.04em] text-muted-2">Receipt</th>
                    <th className="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-[0.04em] text-muted-2">Total</th>
                    <th className="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-[0.04em] text-muted-2">Date</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-line/70 bg-white">
                  {rows.map((order) => (
                    <tr key={order.id} className="transition-colors hover:bg-surface/60">
                      <td className="px-4 py-3 text-[13px] font-semibold text-ink">{order.order_number}</td>
                      <td className="px-4 py-3 text-[13px] text-ink-2">{order.source_label ?? '—'}</td>
                      <td className="px-4 py-3"><Pill value={order.status} map={STATUS_STYLES} /></td>
                      <td className="px-4 py-3"><Pill value={order.payment_status} map={PAYMENT_STYLES} /></td>
                      <td className="px-4 py-3">
                        {order.tracking_id ? (
                          <div className="flex flex-col">
                            <span className="font-mono text-[12.5px] font-semibold text-ink">{order.tracking_id}</span>
                            {order.shipping_provider && (
                              <span className="text-[11px] text-muted-2">{order.shipping_provider}</span>
                            )}
                          </div>
                        ) : (
                          <span className="text-[12.5px] text-muted-2">—</span>
                        )}
                      </td>
                      <td className="px-4 py-3 text-center">
                        {order.receipt_url ? (
                          <a
                            href={order.receipt_url}
                            target="_blank"
                            rel="noopener noreferrer"
                            title="View payment receipt"
                            className="inline-grid h-7 w-7 place-items-center rounded-lg text-[var(--color-brand)] ring-1 ring-orange-600/20 transition-colors hover:bg-orange-50"
                          >
                            <Paperclip className="h-3.5 w-3.5" strokeWidth={2.2} />
                          </a>
                        ) : (
                          <span className="text-[12.5px] text-muted-2">—</span>
                        )}
                      </td>
                      <td className="px-4 py-3 text-right text-[13px] font-semibold tabular-nums text-ink">{formatMoney(order.total)}</td>
                      <td className="px-4 py-3 text-right text-[12.5px] text-muted">{formatDate(order.created_at)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>

          {meta.last_page > 1 && (
            <div className="mt-4 flex items-center justify-between">
              <div className="text-[12.5px] text-muted">
                Page {meta.current_page} of {meta.last_page} · {meta.total} orders
              </div>
              <div className="flex items-center gap-2">
                <button
                  type="button"
                  disabled={meta.current_page <= 1}
                  onClick={() => goToPage(meta.current_page - 1)}
                  className="flex items-center gap-1 rounded-lg bg-slate-100 px-3 py-2 text-[12.5px] font-semibold text-ink-2 transition-colors hover:bg-slate-200 disabled:cursor-not-allowed disabled:opacity-40"
                >
                  <ChevronLeft className="h-4 w-4" strokeWidth={2.2} /> Prev
                </button>
                <button
                  type="button"
                  disabled={meta.current_page >= meta.last_page}
                  onClick={() => goToPage(meta.current_page + 1)}
                  className="flex items-center gap-1 rounded-lg bg-slate-100 px-3 py-2 text-[12.5px] font-semibold text-ink-2 transition-colors hover:bg-slate-200 disabled:cursor-not-allowed disabled:opacity-40"
                >
                  Next <ChevronRight className="h-4 w-4" strokeWidth={2.2} />
                </button>
              </div>
            </div>
          )}
        </>
      )}
    </FighterLayout>
  );
}
