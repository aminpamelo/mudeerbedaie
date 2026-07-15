import { router } from '@inertiajs/react';
import { Bell, CheckCheck, ShoppingBag } from 'lucide-react';
import FighterLayout from '@/fighter/layouts/FighterLayout';
import { cn, csrfToken } from '@/fighter/lib/utils';

async function markAllRead() {
  await fetch('/fighter/notifications/read-all', {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
    credentials: 'same-origin',
  });
  router.reload({ only: ['notifications', 'unreadNotificationCount', 'unreadCount'] });
}

async function openNotification(n) {
  if (!n.is_read) {
    await fetch(`/fighter/notifications/${n.id}/read`, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
      credentials: 'same-origin',
    });
  }
  if (n.url) router.visit(n.url);
}

export default function Notifications({ notifications = [], unreadCount = 0 }) {
  const actions = unreadCount > 0 && (
    <button
      type="button"
      onClick={markAllRead}
      className="flex items-center gap-2 rounded-xl bg-slate-100 px-3.5 py-2.5 text-[13px] font-semibold text-ink-2 transition-colors hover:bg-slate-200"
    >
      <CheckCheck className="h-4 w-4" strokeWidth={2.2} />
      Mark all read
    </button>
  );

  return (
    <FighterLayout title="Notifications" subtitle="New orders from your funnels, newest first." actions={actions}>
      {notifications.length === 0 ? (
        <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-line bg-surface px-6 py-16 text-center">
          <div className="grid h-14 w-14 place-items-center rounded-2xl bg-orange-50 text-[var(--color-brand)]">
            <Bell className="h-7 w-7" strokeWidth={1.8} />
          </div>
          <h3 className="mt-4 text-[16px] font-semibold text-ink">You're all caught up</h3>
          <p className="mt-1 max-w-sm text-[13.5px] text-muted">New-order alerts will show up here.</p>
        </div>
      ) : (
        <div className="flex flex-col gap-2">
          {notifications.map((n) => (
            <button
              key={n.id}
              type="button"
              onClick={() => openNotification(n)}
              className={cn(
                'flex items-start gap-3 rounded-2xl px-4 py-3.5 text-left ring-1 transition-colors',
                n.is_read ? 'bg-white ring-line/70 hover:bg-surface/60' : 'bg-orange-50/60 ring-orange-600/15 hover:bg-orange-50'
              )}
            >
              <div className={cn('mt-0.5 grid h-9 w-9 shrink-0 place-items-center rounded-xl', n.is_read ? 'bg-slate-100 text-muted' : 'bg-[var(--color-brand)] text-white')}>
                <ShoppingBag className="h-[17px] w-[17px]" strokeWidth={2} />
              </div>
              <div className="min-w-0 flex-1">
                <div className="flex items-center gap-2">
                  <span className="truncate text-[13.5px] font-semibold text-ink">{n.title}</span>
                  {!n.is_read && <span className="h-1.5 w-1.5 shrink-0 rounded-full bg-[var(--color-brand)]" />}
                </div>
                <p className="mt-0.5 truncate text-[12.5px] text-muted">{n.body}</p>
              </div>
              <span className="shrink-0 text-[11.5px] text-muted-2">{n.created_human}</span>
            </button>
          ))}
        </div>
      )}
    </FighterLayout>
  );
}
