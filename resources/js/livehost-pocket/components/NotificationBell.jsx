import { router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Bell, CheckCheck, MessageSquare } from 'lucide-react';

function csrf() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

async function getJson(url) {
  const res = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return res.json();
}

async function postJson(url) {
  const res = await fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() },
  });
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return res.json();
}

/**
 * Header bell for the Pocket app: unread badge, dropdown of recent host
 * notifications (video feedback, recap reminders…), mark-read on tap, and a
 * link to the full history page. Seeds its badge from the shared
 * `unreadNotificationCount` prop, then polls every 30s.
 */
export default function NotificationBell() {
  const seed = usePage().props.unreadNotificationCount ?? 0;
  const [unread, setUnread] = useState(seed);
  const [open, setOpen] = useState(false);
  const [loading, setLoading] = useState(false);
  const [items, setItems] = useState([]);
  const ref = useRef(null);

  const refreshCount = useCallback(async () => {
    try {
      const data = await getJson('/live-host/notifications/unread-count');
      setUnread(data.count);
    } catch {
      /* offline — keep last known count */
    }
  }, []);

  useEffect(() => {
    refreshCount();
    const id = setInterval(refreshCount, 30_000);
    return () => clearInterval(id);
  }, [refreshCount]);

  useEffect(() => {
    if (!open) return;
    setLoading(true);
    getJson('/live-host/notifications/feed')
      .then((d) => { setItems(d.notifications); setUnread(d.unread_count); })
      .catch(() => {})
      .finally(() => setLoading(false));
  }, [open]);

  useEffect(() => {
    const onClick = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
    document.addEventListener('mousedown', onClick);
    return () => document.removeEventListener('mousedown', onClick);
  }, []);

  const openItem = async (n) => {
    setOpen(false);
    if (!n.is_read) {
      setUnread((u) => Math.max(0, u - 1));
      postJson(`/live-host/notifications/${n.id}/read`).catch(() => {});
    }
    if (n.url) {
      router.visit(n.url);
    }
  };

  const markAll = async () => {
    setUnread(0);
    setItems((list) => list.map((n) => ({ ...n, is_read: true })));
    postJson('/live-host/notifications/read-all').catch(() => {});
  };

  return (
    <div className="relative" ref={ref}>
      <button
        type="button"
        onClick={() => setOpen((o) => !o)}
        aria-label="Notifikasi"
        className="relative grid h-[30px] w-[30px] place-items-center rounded-full text-[var(--color-pocket-ink)] transition active:scale-95"
      >
        <Bell className="h-[18px] w-[18px]" strokeWidth={2} />
        {unread > 0 && (
          <span className="absolute -right-0.5 -top-0.5 grid h-[15px] min-w-[15px] place-items-center rounded-full bg-[var(--hot,#EC4899)] px-1 text-[9px] font-bold text-white ring-2 ring-[var(--color-pocket-bg,#fff)]">
            {unread > 9 ? '9+' : unread}
          </span>
        )}
      </button>

      {open && (
        <div className="absolute right-0 top-full z-50 mt-2 w-[min(20rem,calc(100vw-2rem))] overflow-hidden rounded-2xl border border-black/[0.06] bg-white shadow-xl">
          <div className="flex items-center justify-between border-b border-black/[0.06] px-4 py-2.5">
            <h3 className="text-[13px] font-bold text-[var(--color-pocket-ink,#111)]">Notifikasi</h3>
            {unread > 0 && (
              <button type="button" onClick={markAll} className="flex items-center gap-1 text-[11px] font-medium text-[var(--color-pocket-accent,#7C3AED)]">
                <CheckCheck className="h-3.5 w-3.5" /> Tandai semua
              </button>
            )}
          </div>

          <div className="max-h-[60vh] overflow-y-auto">
            {loading ? (
              <div className="flex items-center justify-center py-10">
                <div className="h-5 w-5 animate-spin rounded-full border-2 border-[var(--color-pocket-accent,#7C3AED)] border-t-transparent" />
              </div>
            ) : items.length === 0 ? (
              <div className="px-4 py-10 text-center text-[12.5px] text-neutral-400">Tiada notifikasi lagi.</div>
            ) : (
              items.map((n) => (
                <button
                  key={n.id}
                  type="button"
                  onClick={() => openItem(n)}
                  className={`flex w-full items-start gap-2.5 px-4 py-3 text-left transition-colors hover:bg-neutral-50 ${n.is_read ? '' : 'bg-violet-50/60'}`}
                >
                  <span className="mt-0.5 grid h-7 w-7 flex-shrink-0 place-items-center rounded-full bg-violet-100 text-violet-600">
                    <MessageSquare className="h-3.5 w-3.5" />
                  </span>
                  <span className="min-w-0 flex-1">
                    <span className={`block text-[12.5px] leading-snug ${n.is_read ? 'font-medium text-neutral-700' : 'font-bold text-neutral-900'}`}>
                      {n.title}
                    </span>
                    {n.body && <span className="mt-0.5 block line-clamp-2 text-[11.5px] text-neutral-500">{n.body}</span>}
                    <span className="mt-1 block text-[10.5px] text-neutral-400">{n.created_human}</span>
                  </span>
                  {!n.is_read && <span className="mt-1.5 h-2 w-2 flex-shrink-0 rounded-full bg-[var(--color-pocket-accent,#7C3AED)]" />}
                </button>
              ))
            )}
          </div>

          <button
            type="button"
            onClick={() => { setOpen(false); router.visit('/live-host/notifications'); }}
            className="block w-full border-t border-black/[0.06] py-2.5 text-center text-[12px] font-semibold text-[var(--color-pocket-accent,#7C3AED)] hover:bg-neutral-50"
          >
            Lihat semua
          </button>
        </div>
      )}
    </div>
  );
}
