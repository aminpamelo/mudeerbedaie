import { cn, toneText } from '@/ceo/lib/utils';
import DeltaChip from '@/ceo/components/DeltaChip';

const ICON_TINT = {
  positive: 'var(--color-emerald)',
  warning: 'var(--color-amber)',
  negative: 'var(--color-rose)',
  info: 'var(--color-sky)',
  muted: 'var(--color-muted-2)',
};

/**
 * One frosted tile in the top pulse strip — a big operational counter with an
 * optional live dot and period-over-period delta chip.
 */
export default function PulseStat({ stat }) {
  const tint = ICON_TINT[stat.tone] ?? 'var(--color-brand)';
  return (
    <div className="relative flex flex-col gap-2 px-5 py-4">
      <span className="absolute left-0 top-4 h-7 w-1 rounded-full" style={{ background: tint }} aria-hidden="true" />
      <div className="flex items-center gap-1.5">
        {stat.live && <span className="live-dot" style={{ '--signal': tint }} />}
        <span className="label-eyebrow">{stat.label}</span>
      </div>
      <div className="flex items-baseline gap-2">
        <span className={cn('font-display text-[27px] leading-none tabular-nums', toneText(stat.tone))}>{stat.value}</span>
        <DeltaChip delta={stat.delta} />
      </div>
      {stat.hint && <span className="text-[11px] text-muted">{stat.hint}</span>}
    </div>
  );
}
