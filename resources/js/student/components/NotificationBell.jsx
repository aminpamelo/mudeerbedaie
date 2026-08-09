import { usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Bell, BookOpen, CheckCheck, Inbox } from 'lucide-react';
import { cn } from '@/student/lib/utils';

function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

export default function NotificationBell() {
  const { props } = usePage();
  const [count, setCount] = useState(props.unreadNotificationCount ?? 0);
  const [open, setOpen] = useState(false);
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(false);
  const ref = useRef(null);

  // Poll unread count every 30s
  useEffect(() => {
    let active = true;
    const poll = () => {
      fetch('/my/notifications/unread-count', { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        .then((r) => (r.ok ? r.json() : null))
        .then((data) => active && data && setCount(data.count ?? 0))
        .catch(() => {});
    };
    const id = setInterval(poll, 30000);
    return () => { active = false; clearInterval(id); };
  }, []);

  // Close on outside click
  useEffect(() => {
    if (!open) return undefined;
    const onClick = (e) => ref.current && !ref.current.contains(e.target) && setOpen(false);
    document.addEventListener('mousedown', onClick);
    return () => document.removeEventListener('mousedown', onClick);
  }, [open]);

  const loadFeed = () => {
    setLoading(true);
    fetch('/my/notifications/feed', { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
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

  const markRead = async (n) => {
    if (!n.is_read) {
      await fetch(`/my/notifications/${n.id}/read`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
        credentials: 'same-origin',
      }).catch(() => {});
      setCount((c) => Math.max(0, c - 1));
      setItems((prev) => prev.map((item) => item.id === n.id ? { ...item, is_read: true } : item));
    }
    if (n.url) {
      setOpen(false);
      window.location.href = n.url;
    }
  };

  const markAllRead = async () => {
    await fetch('/my/notifications/read-all', {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
      credentials: 'same-origin',
    }).catch(() => {});
    setCount(0);
    setItems((prev) => prev.map((item) => ({ ...item, is_read: true })));
  };

  return (
    <div className="relative" ref={ref}>
      {/* Bell button */}
      <button
        type="button"
        onClick={toggle}
        className="relative grid h-9 w-9 place-items-center rounded-xl text-white/70 transition-all hover:bg-white/15 hover:text-white"
        aria-label={`Notifications${count ? ` (${count} unread)` : ''}`}
      >
        <Bell className="h-[18px] w-[18px]" strokeWidth={2} />
        {count > 0 && (
          <span className="absolute -right-0.5 -top-0.5 grid h-[18px] min-w-[18px] animate-pulse place-items-center rounded-full bg-[var(--color-accent)] px-1 text-[10px] font-bold leading-none text-white ring-2 ring-violet-900/60">
            {count > 99 ? '99+' : count}
          </span>
        )}
      </button>

      {/* Dropdown */}
      {open && (
        <div className="absolute right-0 z-50 mt-2 w-[340px] overflow-hidden rounded-2xl bg-white shadow-[0_24px_60px_-16px_rgba(124,58,237,0.35)] ring-1 ring-violet-100">
          {/* Header */}
          <div className="flex items-center justify-between border-b border-violet-50 bg-gradient-to-r from-violet-50 to-rose-50 px-4 py-3">
            <div className="flex items-center gap-2">
              <Bell className="h-4 w-4 text-violet-500" strokeWidth={2} />
              <span className="text-[14px] font-bold text-violet-900">Notifications</span>
              {count > 0 && (
                <span className="grid h-5 min-w-5 place-items-center rounded-full bg-[var(--color-accent)] px-1.5 text-[10px] font-bold text-white">
                  {count}
                </span>
              )}
            </div>
            {count > 0 && (
              <button
                type="button"
                onClick={markAllRead}
                className="flex items-center gap-1 text-[11px] font-semibold text-violet-500 transition-colors hover:text-violet-700"
              >
                <CheckCheck className="h-3.5 w-3.5" strokeWidth={2} />
                Mark all read
              </button>
            )}
          </div>

          {/* Feed */}
          <div className="max-h-[380px] overflow-y-auto scroll-thin">
            {loading ? (
              <div className="flex flex-col items-center gap-2 px-4 py-10">
                <div className="h-5 w-5 animate-spin rounded-full border-2 border-violet-200 border-t-violet-500" />
                <span className="text-[12px] text-muted">Loading...</span>
              </div>
            ) : items.length === 0 ? (
              <div className="flex flex-col items-center gap-3 px-4 py-12">
                <div className="grid h-14 w-14 place-items-center rounded-2xl bg-violet-50">
                  <Inbox className="h-7 w-7 text-violet-300" strokeWidth={1.5} />
                </div>
                <div className="text-center">
                  <p className="text-[13px] font-semibold text-ink">All caught up!</p>
                  <p className="mt-0.5 text-[12px] text-muted">No notifications yet</p>
                </div>
              </div>
            ) : (
              items.map((n) => (
                <button
                  key={n.id}
                  type="button"
                  onClick={() => markRead(n)}
                  className={cn(
                    'flex w-full items-start gap-3 px-4 py-3 text-left transition-colors hover:bg-violet-50/60',
                    !n.is_read && 'bg-violet-50/40'
                  )}
                >
                  <div className={cn(
                    'mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-xl',
                    n.is_read ? 'bg-gray-100 text-muted-2' : 'bg-gradient-to-br from-violet-500 to-rose-500 text-white'
                  )}>
                    <BookOpen className="h-4 w-4" strokeWidth={2} />
                  </div>
                  <div className="min-w-0 flex-1">
                    <div className="truncate text-[13px] font-semibold text-ink">{n.title}</div>
                    <div className="mt-0.5 text-[12px] leading-relaxed text-muted line-clamp-2">{n.body}</div>
                    <div className="mt-1 text-[10.5px] text-muted-2">{n.created_human}</div>
                  </div>
                  {!n.is_read && <span className="mt-2 h-2 w-2 shrink-0 rounded-full bg-[var(--color-accent)]" />}
                </button>
              ))
            )}
          </div>
        </div>
      )}
    </div>
  );
}
