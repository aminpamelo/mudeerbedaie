import { router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Bell, ShoppingBag } from 'lucide-react';
import { cn, csrfToken } from '@/fighter/lib/utils';

/**
 * Notification bell with a polling dropdown feed of new-order alerts. The
 * badge seeds from HandleFighterInertiaRequests' shared prop, then refreshes
 * the unread count every 30s. Opening the dropdown fetches the recent feed.
 */
export default function NotificationBell({ dark = true }) {
  const { props } = usePage();
  const [count, setCount] = useState(props.unreadNotificationCount ?? 0);
  const [open, setOpen] = useState(false);
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(false);
  const ref = useRef(null);

  // Keep the badge fresh regardless of the dropdown being open.
  useEffect(() => {
    let active = true;
    const poll = () => {
      fetch('/fighter/notifications/unread-count', { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        .then((r) => (r.ok ? r.json() : null))
        .then((data) => active && data && setCount(data.count ?? 0))
        .catch(() => {});
    };
    const id = setInterval(poll, 30000);
    return () => {
      active = false;
      clearInterval(id);
    };
  }, []);

  // Close on outside click.
  useEffect(() => {
    if (!open) return undefined;
    const onClick = (e) => ref.current && !ref.current.contains(e.target) && setOpen(false);
    document.addEventListener('mousedown', onClick);
    return () => document.removeEventListener('mousedown', onClick);
  }, [open]);

  const loadFeed = () => {
    setLoading(true);
    fetch('/fighter/notifications/feed', { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
      .then((r) => (r.ok ? r.json() : null))
      .then((data) => {
        if (data) {
          setItems(data.notifications ?? []);
          setCount(data.unread_count ?? 0);
        }
      })
      .catch(() => {})
      .finally(() => setLoading(false));
  };

  const toggle = () => {
    const next = !open;
    setOpen(next);
    if (next) loadFeed();
  };

  const openItem = async (n) => {
    if (!n.is_read) {
      await fetch(`/fighter/notifications/${n.id}/read`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
        credentials: 'same-origin',
      }).catch(() => {});
      setCount((c) => Math.max(0, c - 1));
    }
    setOpen(false);
    if (n.url) router.visit(n.url);
  };

  return (
    <div className="relative" ref={ref}>
      <button
        type="button"
        onClick={toggle}
        className={cn(
          'relative grid h-9 w-9 place-items-center rounded-lg transition-colors',
          dark ? 'text-white/70 hover:bg-white/10 hover:text-white' : 'text-muted hover:bg-slate-100 hover:text-ink'
        )}
        aria-label={`Notifications${count ? ` (${count} unread)` : ''}`}
      >
        <Bell className="h-[18px] w-[18px]" strokeWidth={2} />
        {count > 0 && (
          <span className="absolute -right-0.5 -top-0.5 grid h-4 min-w-4 place-items-center rounded-full bg-[var(--color-rose)] px-1 text-[10px] font-bold leading-none text-white">
            {count > 99 ? '99+' : count}
          </span>
        )}
      </button>

      {open && (
        <div className="absolute right-0 z-50 mt-2 w-[320px] overflow-hidden rounded-2xl bg-white shadow-[0_24px_60px_-20px_rgba(0,0,0,0.45)] ring-1 ring-line">
          <div className="flex items-center justify-between border-b border-line px-4 py-2.5">
            <span className="text-[13px] font-semibold text-ink">Notifications</span>
            <button
              type="button"
              onClick={() => {
                setOpen(false);
                router.visit('/fighter/notifications');
              }}
              className="text-[12px] font-semibold text-[var(--color-brand-ink)] hover:underline"
            >
              See all
            </button>
          </div>
          <div className="max-h-[360px] overflow-y-auto scroll-thin">
            {loading ? (
              <div className="px-4 py-6 text-center text-[12.5px] text-muted">Loading…</div>
            ) : items.length === 0 ? (
              <div className="px-4 py-8 text-center text-[12.5px] text-muted">No notifications yet</div>
            ) : (
              items.map((n) => (
                <button
                  key={n.id}
                  type="button"
                  onClick={() => openItem(n)}
                  className={cn('flex w-full items-start gap-2.5 px-4 py-3 text-left transition-colors hover:bg-surface', !n.is_read && 'bg-orange-50/50')}
                >
                  <div className={cn('mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-lg', n.is_read ? 'bg-slate-100 text-muted' : 'bg-[var(--color-brand)] text-white')}>
                    <ShoppingBag className="h-3.5 w-3.5" strokeWidth={2} />
                  </div>
                  <div className="min-w-0 flex-1">
                    <div className="truncate text-[12.5px] font-semibold text-ink">{n.title}</div>
                    <div className="truncate text-[11.5px] text-muted">{n.body}</div>
                    <div className="mt-0.5 text-[10.5px] text-muted-2">{n.created_human}</div>
                  </div>
                  {!n.is_read && <span className="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-[var(--color-brand)]" />}
                </button>
              ))
            )}
          </div>
        </div>
      )}
    </div>
  );
}
