import { router } from '@inertiajs/react';
import { cn } from '@/ceo/lib/utils';

/**
 * Segmented control for the global time window. Posts the chosen period to the
 * same route via Inertia, preserving scroll.
 */
export default function PeriodSwitcher({ period }) {
  const options = period?.options ?? [];
  const active = period?.key ?? 'today';

  const select = (key) => {
    if (key === active) return;
    router.get('/ceo', { period: key }, { preserveScroll: true });
  };

  return (
    <div className="glass inline-flex items-center gap-0.5 rounded-[12px] p-1">
      {options.map((option) => {
        const isActive = option.key === active;
        return (
          <button
            key={option.key}
            type="button"
            onClick={() => select(option.key)}
            className={cn(
              'rounded-[9px] px-3.5 py-1.5 text-[12.5px] font-semibold transition-all',
              isActive ? 'bg-ink text-white shadow-[0_4px_14px_-4px_rgba(15,23,42,0.5)]' : 'text-muted hover:text-ink'
            )}
          >
            {option.label}
          </button>
        );
      })}
    </div>
  );
}
