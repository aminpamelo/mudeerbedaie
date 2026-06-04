import { useEffect, useState } from 'react';
import { Download } from 'lucide-react';
import { useT } from '@/ceo/lib/i18n';

/**
 * "Install app" affordance for the CEO PWA. Captures the browser's
 * `beforeinstallprompt` event and shows a button only while the app is
 * installable (and not already running standalone). Renders nothing otherwise,
 * so it stays invisible on unsupported browsers / once installed.
 */
export default function InstallButton() {
  const t = useT();
  const [deferred, setDeferred] = useState(null);

  useEffect(() => {
    const standalone =
      window.matchMedia?.('(display-mode: standalone)').matches || window.navigator.standalone === true;
    if (standalone) return undefined;

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

  if (!deferred) return null;

  const install = async () => {
    deferred.prompt();
    try {
      await deferred.userChoice;
    } finally {
      setDeferred(null);
    }
  };

  return (
    <button
      type="button"
      onClick={install}
      className="flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-[var(--color-brand)] to-[var(--color-violet)] px-3 py-2.5 text-[12.5px] font-semibold text-white shadow-[0_8px_20px_-8px_rgba(99,102,241,0.6)] transition-transform hover:-translate-y-0.5"
    >
      <Download className="h-[15px] w-[15px]" strokeWidth={2} />
      {t('install_app')}
    </button>
  );
}
