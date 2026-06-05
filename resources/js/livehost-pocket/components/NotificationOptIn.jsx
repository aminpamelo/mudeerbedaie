import { useEffect, useState } from 'react';
import { Bell, X } from 'lucide-react';
import {
  pushSupported,
  notificationPermission,
  subscribeToPush,
} from '@/livehost-pocket/lib/push';

const DISMISS_KEY = 'pocket-notif-dismissed';

/**
 * Dismissible banner that nudges the host to enable push notifications for
 * session reminders, schedule changes, and replacement updates.
 *
 * Behaviour by permission state:
 *   - granted : silently (re)subscribe on mount so a reinstalled / cleared
 *               subscription is restored; render nothing.
 *   - default : show the opt-in banner (unless dismissed this session).
 *   - denied  : render nothing — the browser blocks re-prompting anyway.
 *
 * Styling follows the pocket card tokens, matching InstallButton.
 */
export default function NotificationOptIn() {
  const [permission, setPermission] = useState(() => notificationPermission());
  const [busy, setBusy] = useState(false);
  const [dismissed, setDismissed] = useState(
    () => typeof localStorage !== 'undefined' && localStorage.getItem(DISMISS_KEY) === '1'
  );

  useEffect(() => {
    if (!pushSupported()) {
      return;
    }
    if (notificationPermission() === 'granted') {
      // Already granted — make sure the server has a live subscription.
      subscribeToPush().catch((error) => {
        console.error('[pocket] failed to restore push subscription', error);
      });
    }
  }, []);

  if (!pushSupported() || permission !== 'default' || dismissed) {
    return null;
  }

  const enable = async () => {
    setBusy(true);
    try {
      const result = await Notification.requestPermission();
      setPermission(result);
      if (result === 'granted') {
        await subscribeToPush();
      }
    } catch {
      // Leave the banner so the host can retry.
    } finally {
      setBusy(false);
    }
  };

  const dismiss = () => {
    setDismissed(true);
    try {
      localStorage.setItem(DISMISS_KEY, '1');
    } catch {
      // ignore — best-effort
    }
  };

  return (
    <div className="mx-5 mt-2 flex items-center gap-3 rounded-[var(--radius-pocket-card)] border border-[var(--color-pocket-border)] bg-white px-4 py-3 shadow-[var(--shadow-pocket-card)]">
      <div className="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-[var(--color-pocket-accent-soft)] text-[var(--color-pocket-accent-ink)]">
        <Bell className="h-[18px] w-[18px]" strokeWidth={2} />
      </div>
      <div className="min-w-0 flex-1">
        <p className="text-[13px] font-semibold text-[var(--color-pocket-ink)]">Hidupkan notifikasi</p>
        <p className="truncate text-[12px] text-[var(--color-pocket-muted)]">
          Peringatan sesi, perubahan jadual &amp; ganti slot.
        </p>
      </div>
      <button
        type="button"
        onClick={enable}
        disabled={busy}
        className="flex-shrink-0 rounded-full bg-[var(--color-pocket-accent)] px-3.5 py-2 text-[12.5px] font-semibold text-white transition active:scale-95 disabled:opacity-60"
      >
        {busy ? 'Sebentar…' : 'Hidupkan'}
      </button>
      <button
        type="button"
        onClick={dismiss}
        aria-label="Tutup"
        className="flex-shrink-0 text-[var(--color-pocket-muted)] transition hover:text-[var(--color-pocket-ink)]"
      >
        <X className="h-4 w-4" strokeWidth={2} />
      </button>
    </div>
  );
}
