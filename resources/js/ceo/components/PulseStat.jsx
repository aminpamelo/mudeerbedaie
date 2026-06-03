import { cn, toneText } from '@/ceo/lib/utils';

function DeltaChip({ delta }) {
  if (!delta) return null;
  const map = {
    up: 'bg-[var(--color-emerald-soft)] text-[var(--color-emerald-ink)]',
    down: 'bg-[var(--color-rose-soft)] text-[var(--color-rose-ink)]',
    flat: 'bg-surface-2 text-muted',
  };
  return (
    <span className={cn('inline-flex items-center rounded-full px-1.5 py-px font-mono text-[10.5px] font-medium tabular-nums', map[delta.direction] ?? map.flat)}>
      {delta.text}
    </span>
  );
}

/**
 * One big number in the top pulse strip. Point-in-time operational counters,
 * optionally with a period-over-period delta chip.
 */
export default function PulseStat({ stat }) {
  return (
    <div className="flex flex-col gap-1.5 px-5 py-4">
      <div className="flex items-center gap-1.5">
        {stat.live && <span className="pulse-dot h-[6px] w-[6px]" aria-hidden="true" />}
        <span className="label-eyebrow">{stat.label}</span>
      </div>
      <div className="flex items-baseline gap-2">
        <span className={cn('font-display text-[28px] leading-none tabular-nums', toneText(stat.tone))}>{stat.value}</span>
        <DeltaChip delta={stat.delta} />
      </div>
      {stat.hint && <span className="text-[11px] text-muted">{stat.hint}</span>}
    </div>
  );
}
