import { router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { cn } from '@/ceo/lib/utils';

/**
 * Period control for every CEO page. Top row: presets (Today / 7d / 30d / Month
 * / Quarter). When a calendar preset (month/quarter) is active, a second row
 * adds ◀ label ▶ to step through specific months/quarters. Navigation only swaps
 * the ?period (+ ?ref) query, staying on the current page.
 */
export default function PeriodSwitcher({ period }) {
  const options = period?.options ?? [];
  const active = period?.key ?? 'today';

  const currentPath = () => (typeof window !== 'undefined' ? window.location.pathname : '/ceo');

  const select = (key) => {
    if (key === active) return;
    router.get(currentPath(), { period: key }, { preserveScroll: true });
  };

  const step = (ref) => {
    if (!ref) return;
    router.get(currentPath(), { period: active, ref }, { preserveScroll: true });
  };

  return (
    <div className="flex flex-col items-end gap-2">
      <div className="glass inline-flex items-center gap-0.5 rounded-[12px] p-1">
        {options.map((option) => {
          const isActive = option.key === active;
          return (
            <button
              key={option.key}
              type="button"
              onClick={() => select(option.key)}
              className={cn(
                'rounded-[9px] px-3 py-1.5 text-[12.5px] font-semibold transition-all',
                isActive ? 'bg-ink text-white shadow-[0_4px_14px_-4px_rgba(15,23,42,0.5)]' : 'text-muted hover:text-ink'
              )}
            >
              {option.label}
            </button>
          );
        })}
      </div>

      {period?.stepped && (
        <div className="glass inline-flex items-center gap-1 rounded-[12px] p-1">
          <button
            type="button"
            onClick={() => step(period.prevRef)}
            disabled={!period.prevRef}
            className="grid h-7 w-7 place-items-center rounded-[9px] text-muted transition-colors hover:bg-white/60 hover:text-ink disabled:cursor-not-allowed disabled:opacity-30"
            aria-label="Previous period"
          >
            <ChevronLeft className="h-4 w-4" strokeWidth={2.2} />
          </button>
          <span className="min-w-[96px] px-1 text-center text-[12.5px] font-semibold tabular-nums text-ink">{period.label}</span>
          <button
            type="button"
            onClick={() => step(period.nextRef)}
            disabled={!period.nextRef}
            className="grid h-7 w-7 place-items-center rounded-[9px] text-muted transition-colors hover:bg-white/60 hover:text-ink disabled:cursor-not-allowed disabled:opacity-30"
            aria-label="Next period"
          >
            <ChevronRight className="h-4 w-4" strokeWidth={2.2} />
          </button>
        </div>
      )}
    </div>
  );
}
