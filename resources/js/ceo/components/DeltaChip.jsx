import { cn } from '@/ceo/lib/utils';

const MAP = {
  up: 'bg-[rgba(16,185,129,0.16)] text-[var(--color-emerald-ink)]',
  down: 'bg-[rgba(244,63,94,0.16)] text-[var(--color-rose-ink)]',
  flat: 'bg-[rgba(15,23,42,0.06)] text-muted',
};

/**
 * Period-over-period delta pill. `delta` is `{ direction: up|down|flat, text }`
 * where `direction` encodes goodness (up = green, down = red), not raw sign, so
 * a metric where "less is better" can still render a green improvement.
 */
export default function DeltaChip({ delta, className }) {
  if (!delta) {
    return null;
  }

  return (
    <span
      className={cn(
        'inline-flex items-center rounded-full px-1.5 py-px font-mono text-[10.5px] font-semibold tabular-nums',
        MAP[delta.direction] ?? MAP.flat,
        className
      )}
    >
      {delta.text}
    </span>
  );
}
