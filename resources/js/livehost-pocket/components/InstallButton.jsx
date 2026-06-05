import { useEffect, useState } from 'react';
import { Download, X } from 'lucide-react';

/**
 * "Pasang aplikasi" affordance for the Pocket PWA. Captures the browser's
 * `beforeinstallprompt` event and shows a slim banner only while the app is
 * installable (and not already running standalone). Renders nothing otherwise,
 * so it stays invisible on unsupported browsers / once installed. Dismissal is
 * remembered for the session so it doesn't nag on every navigation.
 *
 * Mirrors the CEO InstallButton, restyled to the violet pocket tokens.
 */
export default function InstallButton() {
  const [deferred, setDeferred] = useState(null);
  const [dismissed, setDismissed] = useState(
    () => typeof sessionStorage !== 'undefined' && sessionStorage.getItem('pocket-install-dismissed') === '1'
  );

  useEffect(() => {
    const standalone =
      window.matchMedia?.('(display-mode: standalone)').matches || window.navigator.standalone === true;
    if (standalone) {
      return undefined;
    }

    const onPrompt = (event) => {
      event.preventDefault();
      setDeferred(event);
    };
    const onInstalled = () => setDeferred(null);

    window.addEventListener('beforeinstallprompt', onPrompt);
    window.addEventListener('appinstalled', onInstalled);

    return () => {
      window.removeEventListener('beforeinstallprompt', onPrompt);
      window.removeEventListener('appinstalled', onInstalled);
    };
  }, []);

  if (!deferred || dismissed) {
    return null;
  }

  const install = async () => {
    deferred.prompt();
    try {
      await deferred.userChoice;
    } finally {
      setDeferred(null);
    }
  };

  const dismiss = () => {
    setDismissed(true);
    try {
      sessionStorage.setItem('pocket-install-dismissed', '1');
    } catch {
      // ignore — best-effort
    }
  };

  return (
    <div className="mx-5 mt-2 flex items-center gap-3 rounded-[var(--radius-pocket-card)] border border-[var(--color-pocket-border)] bg-white px-4 py-3 shadow-[var(--shadow-pocket-card)]">
      <div className="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-[var(--color-pocket-accent-soft)] text-[var(--color-pocket-accent-ink)]">
        <Download className="h-[18px] w-[18px]" strokeWidth={2} />
      </div>
      <div className="min-w-0 flex-1">
        <p className="text-[13px] font-semibold text-[var(--color-pocket-ink)]">Pasang aplikasi</p>
        <p className="truncate text-[12px] text-[var(--color-pocket-muted)]">
          Akses pantas dari skrin utama telefon anda.
        </p>
      </div>
      <button
        type="button"
        onClick={install}
        className="flex-shrink-0 rounded-full bg-[var(--color-pocket-accent)] px-3.5 py-2 text-[12.5px] font-semibold text-white transition active:scale-95"
      >
        Pasang
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
