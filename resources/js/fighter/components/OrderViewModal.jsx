import { X, Loader2, Paperclip, ExternalLink, Pencil, Package } from 'lucide-react';
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
  paid: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
  failed: 'bg-rose-50 text-rose-700 ring-rose-600/20',
};

function Pill({ value }) {
  const cls = STATUS_STYLES[value] ?? 'bg-slate-100 text-slate-600 ring-slate-500/20';
  return (
    <span className={cn('inline-block rounded-full px-2.5 py-0.5 text-[11.5px] font-semibold capitalize ring-1', cls)}>
      {String(value || '—').replace(/_/g, ' ')}
    </span>
  );
}

function Field({ label, value }) {
  if (!value) return null;
  return (
    <div>
      <div className="text-[11px] font-semibold uppercase tracking-[0.03em] text-muted-2">{label}</div>
      <div className="mt-0.5 text-[13px] text-ink">{value}</div>
    </div>
  );
}

/** Read-only detail of a single order. */
export default function OrderViewModal({ order, loading, onClose, onEdit }) {
  const methodLabel = (m) => (m === 'bank_transfer' ? 'Bank transfer' : m ? m.toUpperCase() : '—');
  const c = order?.customer ?? {};
  const addressLine = order
    ? [c.address, c.city, [c.postcode, c.state].filter(Boolean).join(' ')].filter(Boolean).join(', ')
    : '';

  return (
    <div className="fixed inset-0 z-[70] flex items-end justify-center p-4 sm:items-center" role="dialog" aria-modal="true">
      <div className="absolute inset-0 bg-black/40 backdrop-blur-sm" onClick={onClose} />
      <div className="relative z-10 flex max-h-[92dvh] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-white shadow-xl">
        <div className="flex items-center justify-between border-b border-line/70 px-5 py-4">
          <div className="min-w-0">
            <h3 className="truncate text-[15px] font-semibold text-ink">{order?.order_number ?? 'Order'}</h3>
            {order && <p className="text-[12px] text-muted">{order.source_label} · {formatDate(order.created_at)}</p>}
          </div>
          <button type="button" onClick={onClose} className="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-muted hover:bg-slate-100 hover:text-ink" aria-label="Close"><X className="h-4 w-4" strokeWidth={2.2} /></button>
        </div>

        {loading || !order ? (
          <div className="grid place-items-center py-20 text-muted-2"><Loader2 className="h-6 w-6 animate-spin" /></div>
        ) : (
          <>
            <div className="flex-1 overflow-y-auto px-5 py-4 scroll-thin">
              <div className="flex flex-wrap items-center gap-2">
                <Pill value={order.status} />
                <Pill value={order.payment_status} />
                <span className="rounded-full bg-surface px-2.5 py-0.5 text-[11.5px] font-semibold text-ink-2 ring-1 ring-line/70">{methodLabel(order.payment_method)}</span>
              </div>

              <div className="mt-4 text-[11.5px] font-semibold uppercase tracking-[0.03em] text-muted-2">Items</div>
              <div className="mt-1 divide-y divide-line/70 overflow-hidden rounded-xl bg-surface">
                {order.items.map((item) => (
                  <div key={item.id} className="flex items-center justify-between gap-3 px-3 py-2">
                    <div className="flex min-w-0 items-center gap-2">
                      <span className="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-white text-muted-2 ring-1 ring-line/70"><Package className="h-4 w-4" strokeWidth={1.8} /></span>
                      <div className="min-w-0">
                        <div className="truncate text-[13px] font-medium text-ink">{item.product_name}</div>
                        <div className="text-[11.5px] text-muted">{item.variant_name ? `${item.variant_name} · ` : ''}{item.quantity} × {formatMoney(item.unit_price)}</div>
                      </div>
                    </div>
                    <div className="shrink-0 text-[13px] font-semibold tabular-nums text-ink">{formatMoney(item.total_price)}</div>
                  </div>
                ))}
              </div>

              <div className="mt-4 grid grid-cols-2 gap-x-4 gap-y-3">
                <Field label="Customer" value={c.name} />
                <Field label="Phone" value={c.phone} />
                <Field label="Email" value={c.email} />
                <Field label="Payment ref" value={order.payment_reference} />
                {addressLine && <div className="col-span-2"><Field label="Address" value={addressLine} /></div>}
                <Field label="Tracking" value={order.tracking_id} />
                <Field label="Courier" value={order.shipping_provider} />
                {order.notes && <div className="col-span-2"><Field label="Notes" value={order.notes} /></div>}
              </div>

              {order.receipt_url && (
                <a href={order.receipt_url} target="_blank" rel="noopener noreferrer" className="mt-4 flex items-center gap-2 rounded-xl bg-surface px-3 py-2.5 text-[12.5px] font-medium text-[var(--color-brand-ink)] ring-1 ring-line/70 transition-colors hover:bg-orange-50">
                  <Paperclip className="h-4 w-4" /> View payment receipt <ExternalLink className="ml-auto h-3.5 w-3.5" />
                </a>
              )}

              <div className="mt-4 border-t border-line/70 pt-3">
                <div className="flex items-center justify-between text-[13px] text-ink-2"><span>Subtotal</span><span className="tabular-nums">{formatMoney(order.subtotal)}</span></div>
                {order.shipping_cost > 0 && <div className="mt-1 flex items-center justify-between text-[13px] text-ink-2"><span>Shipping</span><span className="tabular-nums">{formatMoney(order.shipping_cost)}</span></div>}
                <div className="mt-2 flex items-center justify-between text-[16px] font-bold text-ink"><span>Total</span><span className="tabular-nums">{formatMoney(order.total)}</span></div>
              </div>
            </div>

            <div className="flex gap-2 border-t border-line/70 px-5 py-4">
              <button type="button" onClick={onClose} className="flex-1 rounded-xl bg-slate-100 py-2.5 text-[13.5px] font-semibold text-ink-2 transition-colors hover:bg-slate-200">Close</button>
              <button type="button" onClick={() => onEdit(order)} className="flex flex-1 items-center justify-center gap-2 rounded-xl bg-[var(--color-brand)] py-2.5 text-[13.5px] font-semibold text-white transition-colors hover:bg-[var(--color-brand-ink)]">
                <Pencil className="h-4 w-4" strokeWidth={2.2} /> Edit order
              </button>
            </div>
          </>
        )}
      </div>
    </div>
  );
}
