import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { ChevronLeft, ChevronRight, ShoppingBag, Plus, Paperclip, ExternalLink, Eye, Pencil, Trash2, RotateCcw, Loader2, Trash, AlertTriangle } from 'lucide-react';
import FighterLayout from '@/fighter/layouts/FighterLayout';
import { cn, formatMoney, formatDate, fighterJson, fighterSend } from '@/fighter/lib/utils';
import OrderViewModal from '@/fighter/components/OrderViewModal';
import OrderEditModal from '@/fighter/components/OrderEditModal';

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

function IconButton({ title, onClick, busy, tone = 'default', children }) {
  const tones = {
    default: 'text-muted hover:bg-surface hover:text-ink',
    brand: 'text-[var(--color-brand)] hover:bg-orange-50',
    danger: 'text-muted hover:bg-rose-50 hover:text-rose-600',
    emerald: 'text-emerald-600 hover:bg-emerald-50',
  };
  return (
    <button type="button" title={title} onClick={onClick} disabled={busy} className={cn('grid h-8 w-8 place-items-center rounded-lg transition-colors disabled:opacity-40', tones[tone])}>
      {busy ? <Loader2 className="h-4 w-4 animate-spin" /> : children}
    </button>
  );
}

function DeleteConfirm({ order, busy, onCancel, onConfirm }) {
  return (
    <div className="fixed inset-0 z-[75] flex items-center justify-center p-4" role="dialog" aria-modal="true">
      <div className="absolute inset-0 bg-black/40 backdrop-blur-sm" onClick={busy ? undefined : onCancel} />
      <div className="relative z-10 w-full max-w-sm rounded-2xl bg-white p-5 shadow-xl">
        <div className="flex items-start gap-3">
          <div className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-rose-50 text-rose-600"><AlertTriangle className="h-5 w-5" strokeWidth={2} /></div>
          <div>
            <h3 className="text-[15px] font-semibold text-ink">Move order to bin?</h3>
            <p className="mt-1 text-[13px] text-muted">{order.order_number} will be hidden from your list and the fulfilment team. You can restore it from the bin anytime.</p>
          </div>
        </div>
        <div className="mt-5 flex gap-2">
          <button type="button" onClick={onCancel} disabled={busy} className="flex-1 rounded-xl bg-slate-100 py-2.5 text-[13.5px] font-semibold text-ink-2 transition-colors hover:bg-slate-200 disabled:opacity-40">Cancel</button>
          <button type="button" onClick={onConfirm} disabled={busy} className="flex flex-1 items-center justify-center gap-2 rounded-xl bg-rose-600 py-2.5 text-[13.5px] font-semibold text-white transition-colors hover:bg-rose-700 disabled:opacity-50">
            {busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <Trash2 className="h-4 w-4" strokeWidth={2.2} />}
            Move to bin
          </button>
        </div>
      </div>
    </div>
  );
}

export default function Orders({ orders, view = 'active', trashCount = 0 }) {
  const rows = orders?.data ?? [];
  const meta = orders?.meta ?? { current_page: 1, last_page: 1, total: 0 };
  const isTrash = view === 'trash';

  const [modal, setModal] = useState(null); // 'view' | 'edit'
  const [detail, setDetail] = useState(null);
  const [detailLoading, setDetailLoading] = useState(false);
  const [confirmRow, setConfirmRow] = useState(null);
  const [busyId, setBusyId] = useState(null);

  const switchView = (v) => router.get('/fighter/orders', v === 'trash' ? { view: 'trash' } : {}, { preserveScroll: true });
  const goToPage = (page) => router.get('/fighter/orders', { ...(isTrash ? { view: 'trash' } : {}), page }, { preserveScroll: true, preserveState: true });
  const reload = () => router.reload({ preserveScroll: true });

  const openDetail = async (row, mode) => {
    setModal(mode);
    setDetail(null);
    setDetailLoading(true);
    try {
      const { data } = await fighterJson(`/fighter/orders/${row.id}`);
      setDetail(data);
    } catch {
      setModal(null);
    } finally {
      setDetailLoading(false);
    }
  };

  const closeModal = () => { setModal(null); setDetail(null); };

  const doDelete = async () => {
    const row = confirmRow;
    setBusyId(row.id);
    try {
      await fighterSend(`/fighter/orders/${row.id}`, { method: 'DELETE' });
      setConfirmRow(null);
      reload();
    } catch {
      /* keep the dialog open on error */
    } finally {
      setBusyId(null);
    }
  };

  const doRestore = async (row) => {
    setBusyId(row.id);
    try {
      await fighterSend(`/fighter/orders/${row.id}/restore`, { method: 'POST' });
      reload();
    } finally {
      setBusyId(null);
    }
  };

  const emptyState = (
    <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-line bg-surface px-6 py-16 text-center">
      <div className="grid h-14 w-14 place-items-center rounded-2xl bg-orange-50 text-[var(--color-brand)]">
        {isTrash ? <Trash className="h-7 w-7" strokeWidth={1.8} /> : <ShoppingBag className="h-7 w-7" strokeWidth={1.8} />}
      </div>
      <h3 className="mt-4 text-[16px] font-semibold text-ink">{isTrash ? 'Bin is empty' : 'No orders yet'}</h3>
      <p className="mt-1 max-w-sm text-[13.5px] text-muted">
        {isTrash ? 'Orders you move to the bin show up here so you can restore them.' : 'Orders from your funnels show up here. You can also record an order manually.'}
      </p>
      {!isTrash && <CreateOrderButton variant="empty" />}
    </div>
  );

  return (
    <FighterLayout
      title="Orders"
      subtitle="Orders from your funnels and manual orders. Fulfilment is handled by the team."
      actions={<CreateOrderButton />}
    >
      {/* View toggle: active orders vs bin */}
      <div className="mb-4 inline-flex items-center gap-1 rounded-xl bg-surface p-1">
        <button type="button" onClick={() => switchView('active')} className={cn('rounded-lg px-3.5 py-1.5 text-[12.5px] font-semibold transition-colors', !isTrash ? 'bg-white text-ink shadow-sm' : 'text-muted hover:text-ink')}>
          Orders
        </button>
        <button type="button" onClick={() => switchView('trash')} className={cn('flex items-center gap-1.5 rounded-lg px-3.5 py-1.5 text-[12.5px] font-semibold transition-colors', isTrash ? 'bg-white text-ink shadow-sm' : 'text-muted hover:text-ink')}>
          <Trash className="h-3.5 w-3.5" strokeWidth={2.2} /> Bin
          {trashCount > 0 && <span className="rounded-full bg-rose-100 px-1.5 py-0.5 text-[10.5px] font-bold text-rose-700">{trashCount}</span>}
        </button>
      </div>

      {rows.length === 0 ? emptyState : (
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
                    {!isTrash && <th className="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-[0.04em] text-muted-2">Tracking</th>}
                    <th className="px-4 py-2.5 text-center text-[11px] font-semibold uppercase tracking-[0.04em] text-muted-2">Receipt</th>
                    <th className="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-[0.04em] text-muted-2">Total</th>
                    <th className="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-[0.04em] text-muted-2">{isTrash ? 'Deleted' : 'Date'}</th>
                    <th className="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-[0.04em] text-muted-2">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-line/70 bg-white">
                  {rows.map((order) => (
                    <tr key={order.id} className="transition-colors hover:bg-surface/60">
                      <td className="px-4 py-3 text-[13px] font-semibold text-ink">{order.order_number}</td>
                      <td className="px-4 py-3 text-[13px] text-ink-2">{order.source_label ?? '—'}</td>
                      <td className="px-4 py-3"><Pill value={order.status} map={STATUS_STYLES} /></td>
                      <td className="px-4 py-3"><Pill value={order.payment_status} map={PAYMENT_STYLES} /></td>
                      {!isTrash && (
                        <td className="px-4 py-3">
                          {order.tracking_id ? (
                            <div className="flex flex-col">
                              {order.tracking_url ? (
                                <a href={order.tracking_url} target="_blank" rel="noopener noreferrer" title="Track this parcel" className="inline-flex items-center gap-1 font-mono text-[12.5px] font-semibold text-[var(--color-brand)] hover:underline">
                                  {order.tracking_id}
                                  <ExternalLink className="h-3 w-3" strokeWidth={2.2} />
                                </a>
                              ) : (
                                <span className="font-mono text-[12.5px] font-semibold text-ink">{order.tracking_id}</span>
                              )}
                              {order.shipping_provider && <span className="text-[11px] text-muted-2">{order.shipping_provider}</span>}
                            </div>
                          ) : (
                            <span className="text-[12.5px] text-muted-2">—</span>
                          )}
                        </td>
                      )}
                      <td className="px-4 py-3 text-center">
                        {order.receipt_url ? (
                          <a href={order.receipt_url} target="_blank" rel="noopener noreferrer" title="View payment receipt" className="inline-grid h-7 w-7 place-items-center rounded-lg text-[var(--color-brand)] ring-1 ring-orange-600/20 transition-colors hover:bg-orange-50">
                            <Paperclip className="h-3.5 w-3.5" strokeWidth={2.2} />
                          </a>
                        ) : (
                          <span className="text-[12.5px] text-muted-2">—</span>
                        )}
                      </td>
                      <td className="px-4 py-3 text-right text-[13px] font-semibold tabular-nums text-ink">{formatMoney(order.total)}</td>
                      <td className="px-4 py-3 text-right text-[12.5px] text-muted">{formatDate(isTrash ? order.deleted_at : order.created_at)}</td>
                      <td className="px-4 py-3">
                        <div className="flex items-center justify-end gap-0.5">
                          {isTrash ? (
                            <IconButton title="Restore order" tone="emerald" busy={busyId === order.id} onClick={() => doRestore(order)}>
                              <RotateCcw className="h-4 w-4" strokeWidth={2.2} />
                            </IconButton>
                          ) : (
                            <>
                              <IconButton title="View order" tone="brand" onClick={() => openDetail(order, 'view')}>
                                <Eye className="h-4 w-4" strokeWidth={2.2} />
                              </IconButton>
                              <IconButton title="Edit order" onClick={() => openDetail(order, 'edit')}>
                                <Pencil className="h-4 w-4" strokeWidth={2.2} />
                              </IconButton>
                              <IconButton title="Move to bin" tone="danger" onClick={() => setConfirmRow(order)}>
                                <Trash2 className="h-4 w-4" strokeWidth={2.2} />
                              </IconButton>
                            </>
                          )}
                        </div>
                      </td>
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
                <button type="button" disabled={meta.current_page <= 1} onClick={() => goToPage(meta.current_page - 1)} className="flex items-center gap-1 rounded-lg bg-slate-100 px-3 py-2 text-[12.5px] font-semibold text-ink-2 transition-colors hover:bg-slate-200 disabled:cursor-not-allowed disabled:opacity-40">
                  <ChevronLeft className="h-4 w-4" strokeWidth={2.2} /> Prev
                </button>
                <button type="button" disabled={meta.current_page >= meta.last_page} onClick={() => goToPage(meta.current_page + 1)} className="flex items-center gap-1 rounded-lg bg-slate-100 px-3 py-2 text-[12.5px] font-semibold text-ink-2 transition-colors hover:bg-slate-200 disabled:cursor-not-allowed disabled:opacity-40">
                  Next <ChevronRight className="h-4 w-4" strokeWidth={2.2} />
                </button>
              </div>
            </div>
          )}
        </>
      )}

      {modal === 'view' && (
        <OrderViewModal
          order={detail}
          loading={detailLoading}
          onClose={closeModal}
          onEdit={() => setModal('edit')}
        />
      )}

      {modal === 'edit' && detailLoading && !detail && (
        <div className="fixed inset-0 z-[70] grid place-items-center bg-black/40 backdrop-blur-sm">
          <Loader2 className="h-7 w-7 animate-spin text-white" />
        </div>
      )}

      {modal === 'edit' && detail && (
        <OrderEditModal
          order={detail}
          onClose={closeModal}
          onSaved={() => { closeModal(); reload(); }}
        />
      )}

      {confirmRow && (
        <DeleteConfirm
          order={confirmRow}
          busy={busyId === confirmRow.id}
          onCancel={() => setConfirmRow(null)}
          onConfirm={doDelete}
        />
      )}
    </FighterLayout>
  );
}
