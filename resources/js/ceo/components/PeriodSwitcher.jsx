import { router } from '@inertiajs/react';
import { cn } from '@/ceo/lib/utils';

/**
 * Segmented control for the global time window. Posts the chosen period back to
 * the same route via Inertia, preserving scroll and replacing history so the
 * back button doesn't fill with period toggles.
 */
export default function PeriodSwitcher({ period }) {
  const options = period?.options ?? [];
  const active = period?.key ?? 'today';

  const select = (key) => {
    if (key === active) return;
    router.get('/ceo', { period: key }, { preserveScroll: true });
  };

  return (
    <div className="inline-flex items-center gap-0.5 rounded-[10px] border border-border bg-surface p-0.5">
      {options.map((option) => {
        const isActive = option.key === active;
        return (
          <button
            key={option.key}
            type="button"
            onClick={() => select(option.key)}
            className={cn(
              'rounded-[8px] px-3 py-1.5 text-[12.5px] font-medium transition-colors',
              isActive ? 'bg-ink text-white' : 'text-muted hover:bg-surface-2 hover:text-ink'
            )}
          >
            {option.label}
          </button>
        );
      })}
    </div>
  );
}
