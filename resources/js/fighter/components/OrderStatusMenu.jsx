import { useCallback, useEffect, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { ChevronDown, Check, Loader2, Lock } from 'lucide-react';
import { cn } from '@/fighter/lib/utils';
import { FIGHTER_STATUSES, STATUS_STYLES, statusLabel } from '@/fighter/lib/orderStatus';

const MENU_WIDTH = 268;

/**
 * The status badge on an order row, upgraded into a one-click status picker.
 *
 * Orders the fulfilment team has taken over (shipped, delivered, refunded, …)
 * render as a plain, locked badge — the fighter can see where the order is but
 * can't overwrite the team.
 *
 * The panel is portalled to <body> because the orders table scrolls inside two
 * `overflow` wrappers that would otherwise clip it.
 */
export default function OrderStatusMenu({ order, busy, onSelect }) {
  const [open, setOpen] = useState(false);
  const [pos, setPos] = useState(null);
  const triggerRef = useRef(null);
  const menuRef = useRef(null);

  const place = useCallback(() => {
    const rect = triggerRef.current?.getBoundingClientRect();
    if (!rect) return;
    const height = menuRef.current?.offsetHeight ?? 300;
    const below = window.innerHeight - rect.bottom;
    setPos({
      left: Math.max(8, Math.min(rect.left, window.innerWidth - MENU_WIDTH - 8)),
      top: below < height + 16 ? Math.max(8, rect.top - height - 6) : rect.bottom + 6,
    });
  }, []);

  useLayoutEffect(() => {
    if (open) place();
  }, [open, place]);

  useEffect(() => {
    if (!open) return undefined;
    const onPointerDown = (e) => {
      if (!triggerRef.current?.contains(e.target) && !menuRef.current?.contains(e.target)) setOpen(false);
    };
    const onKey = (e) => e.key === 'Escape' && setOpen(false);
    document.addEventListener('mousedown', onPointerDown);
    document.addEventListener('keydown', onKey);
    window.addEventListener('resize', place);
    window.addEventListener('scroll', place, true);
    return () => {
      document.removeEventListener('mousedown', onPointerDown);
      document.removeEventListener('keydown', onKey);
      window.removeEventListener('resize', place);
      window.removeEventListener('scroll', place, true);
    };
  }, [open, place]);

  const cls = STATUS_STYLES[order.status] ?? 'bg-slate-100 text-slate-600 ring-slate-500/20';

  if (!order.status_editable) {
    return (
      <span
        title="The fulfilment team is handling this order now, so they manage its status."
        className={cn('inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[11.5px] font-semibold capitalize ring-1', cls)}
      >
        {statusLabel(order.status)}
        <Lock className="h-2.5 w-2.5 opacity-60" strokeWidth={2.6} />
      </span>
    );
  }

  return (
    <>
      <button
        ref={triggerRef}
        type="button"
        onClick={() => setOpen((v) => !v)}
        disabled={busy}
        title="Change order status"
        aria-haspopup="listbox"
        aria-expanded={open}
        className={cn(
          'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[11.5px] font-semibold capitalize ring-1 transition-opacity hover:opacity-80 disabled:opacity-50',
          cls
        )}
      >
        {statusLabel(order.status)}
        {busy ? <Loader2 className="h-3 w-3 animate-spin" /> : <ChevronDown className="h-3 w-3" strokeWidth={2.6} />}
      </button>

      {open && createPortal(
        <div
          ref={menuRef}
          role="listbox"
          style={{ left: pos?.left ?? -9999, top: pos?.top ?? -9999, width: MENU_WIDTH }}
          className="fixed z-[85] overflow-hidden rounded-xl border border-line bg-white shadow-lg"
        >
          <p className="border-b border-line/70 bg-surface px-3 py-2 text-[10.5px] font-semibold uppercase tracking-[0.04em] text-muted-2">
            {order.order_number}
          </p>
          {FIGHTER_STATUSES.map(({ key, label, Icon, hint }) => {
            const current = key === order.status;
            return (
              <button
                key={key}
                type="button"
                role="option"
                aria-selected={current}
                onClick={() => {
                  setOpen(false);
                  if (!current) onSelect(key);
                }}
                className={cn(
                  'flex w-full items-start gap-2.5 px-3 py-2.5 text-left transition-colors',
                  current ? 'bg-orange-50/60' : 'hover:bg-surface'
                )}
              >
                <Icon className="mt-0.5 h-4 w-4 shrink-0 text-muted" strokeWidth={2} />
                <span className="min-w-0 flex-1">
                  <span className="flex items-center gap-1.5 text-[13px] font-semibold text-ink">
                    {label}
                    {current && <Check className="h-3.5 w-3.5 text-[var(--color-brand)]" strokeWidth={2.8} />}
                  </span>
                  <span className="mt-0.5 block text-[11.5px] leading-snug text-muted">{hint}</span>
                </span>
              </button>
            );
          })}
        </div>,
        document.body
      )}
    </>
  );
}
