import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Bell, CheckCheck, ChevronRight, MessageSquare } from 'lucide-react';
import PocketLayout from '@/livehost-pocket/layouts/PocketLayout';

function csrf() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function postJson(url) {
  return fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() },
  });
}

export default function Notifications() {
  const { notifications: initial, unreadCount: initialUnread } = usePage().props;
  const [items, setItems] = useState(initial ?? []);
  const [unread, setUnread] = useState(initialUnread ?? 0);

  const open = (n) => {
    if (!n.is_read) {
      setItems((list) => list.map((x) => (x.id === n.id ? { ...x, is_read: true } : x)));
      setUnread((u) => Math.max(0, u - 1));
      postJson(`/live-host/notifications/${n.id}/read`).catch(() => {});
    }
    if (n.url) router.visit(n.url);
  };

  const markAll = () => {
    setItems((list) => list.map((x) => ({ ...x, is_read: true })));
    setUnread(0);
    postJson('/live-host/notifications/read-all').catch(() => {});
  };

  return (
    <>
      <Head title="Notifikasi" />
      <div className="px-4 pb-24 pt-3">
        <div className="mb-4 flex items-center justify-between">
          <div className="flex items-center gap-2.5">
            <span className="grid h-9 w-9 place-items-center rounded-xl bg-violet-100 text-violet-600">
              <Bell className="h-4.5 w-4.5" strokeWidth={2} />
            </span>
            <div>
              <h1 className="text-[18px] font-bold tracking-[-0.01em] text-[var(--color-pocket-ink,#111)]">Notifikasi</h1>
              <p className="text-[12px] text-neutral-500">{unread > 0 ? `${unread} belum dibaca` : 'Semua dibaca'}</p>
            </div>
          </div>
          {unread > 0 && (
            <button
              type="button"
              onClick={markAll}
              className="flex items-center gap-1 rounded-full bg-violet-50 px-3 py-1.5 text-[12px] font-semibold text-violet-600"
            >
              <CheckCheck className="h-3.5 w-3.5" /> Tandai semua
            </button>
          )}
        </div>

        {items.length === 0 ? (
          <div className="rounded-2xl border border-dashed border-neutral-200 bg-white py-16 text-center text-[13px] text-neutral-400">
            Tiada notifikasi lagi.
          </div>
        ) : (
          <ul className="flex flex-col gap-2">
            {items.map((n) => (
              <li key={n.id}>
                <button
                  type="button"
                  onClick={() => open(n)}
                  className={`flex w-full items-start gap-3 rounded-2xl border px-3.5 py-3 text-left transition-colors ${
                    n.is_read ? 'border-neutral-200 bg-white' : 'border-violet-200 bg-violet-50/70'
                  }`}
                >
                  <span className="mt-0.5 grid h-8 w-8 flex-shrink-0 place-items-center rounded-full bg-violet-100 text-violet-600">
                    <MessageSquare className="h-4 w-4" />
                  </span>
                  <span className="min-w-0 flex-1">
                    <span className={`block text-[13.5px] leading-snug ${n.is_read ? 'font-semibold text-neutral-700' : 'font-bold text-neutral-900'}`}>
                      {n.title}
                    </span>
                    {n.body && <span className="mt-0.5 block text-[12px] text-neutral-500">{n.body}</span>}
                    <span className="mt-1 block text-[11px] text-neutral-400">{n.created_human}</span>
                  </span>
                  {n.url && <ChevronRight className="mt-1 h-4 w-4 flex-shrink-0 text-neutral-300" />}
                </button>
              </li>
            ))}
          </ul>
        )}
      </div>
    </>
  );
}

Notifications.layout = (page) => <PocketLayout>{page}</PocketLayout>;
