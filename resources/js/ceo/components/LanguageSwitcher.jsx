import { router } from '@inertiajs/react';
import { cn } from '@/ceo/lib/utils';
import { useLocale } from '@/ceo/lib/i18n';

/**
 * Compact BM | EN toggle for the CEO dashboard language. Posts to /ceo/locale
 * (session-scoped, default Malay) and reloads the current page in the chosen
 * language via Inertia's redirect-back.
 */
export default function LanguageSwitcher() {
  const { locale, locales } = useLocale();

  const select = (key) => {
    if (key === locale) return;
    router.post('/ceo/locale', { locale: key }, { preserveScroll: true });
  };

  return (
    <div className="flex items-center gap-0.5 rounded-[10px] bg-white/50 p-0.5">
      {locales.map((opt) => {
        const active = opt.key === locale;
        return (
          <button
            key={opt.key}
            type="button"
            onClick={() => select(opt.key)}
            className={cn(
              'flex-1 rounded-lg px-2.5 py-1.5 text-[11.5px] font-semibold transition-all',
              active ? 'bg-ink text-white shadow-[0_4px_12px_-4px_rgba(15,23,42,0.5)]' : 'text-muted hover:text-ink'
            )}
          >
            {opt.label}
          </button>
        );
      })}
    </div>
  );
}
