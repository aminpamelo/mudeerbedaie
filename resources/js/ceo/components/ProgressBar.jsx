import { toneColor } from '@/ceo/lib/utils';

/**
 * Horizontal progress / bullet bar with a vibrant gradient fill and an optional
 * target marker. Used for "X of Y" completion and load-vs-cap indicators.
 */
export default function ProgressBar({ label, value = 0, max = 1, valueLabel = null, target = null, tone = 'info' }) {
  const safeMax = Number(max) || 1;
  const pct = Math.max(0, Math.min(100, (Number(value) / safeMax) * 100));
  const color = toneColor(tone);
  const targetPct = target != null ? Math.max(0, Math.min(100, (target / safeMax) * 100)) : null;

  return (
    <div className="flex flex-col gap-1.5">
      <div className="flex items-center justify-between">
        <span className="text-[11px] font-medium text-muted">{label}</span>
        {valueLabel && <span className="text-[11px] font-semibold text-ink-2 tabular-nums">{valueLabel}</span>}
      </div>
      <div className="relative h-2 overflow-hidden rounded-full bg-[rgba(15,23,42,0.07)]">
        <div
          className="bar-fill h-full rounded-full"
          style={{
            width: `${pct}%`,
            background: `linear-gradient(90deg, color-mix(in oklab, ${color} 70%, white), ${color})`,
          }}
        />
        {targetPct != null && (
          <span className="absolute top-1/2 h-3 w-[2px] -translate-y-1/2 rounded-full bg-[rgba(15,23,42,0.5)]" style={{ left: `${targetPct}%` }} aria-hidden="true" />
        )}
      </div>
    </div>
  );
}
