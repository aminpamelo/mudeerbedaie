import { cn, toneText, toneColor } from '@/ceo/lib/utils';

/**
 * Standalone KPI tile for the department detail header — a label, a big tonal
 * value, an optional hint, and a colored accent stripe.
 */
export default function KpiTile({ kpi }) {
  const tint = toneColor(kpi.tone === 'muted' ? null : kpi.tone);
  return (
    <div className="glass relative flex flex-col gap-1.5 overflow-hidden rounded-2xl px-4 py-3.5">
      <span className="absolute left-0 top-3.5 h-7 w-1 rounded-full" style={{ background: tint }} aria-hidden="true" />
      <span className="label-eyebrow">{kpi.label}</span>
      <span className={cn('font-display text-[24px] leading-none tabular-nums', toneText(kpi.tone))}>{kpi.value}</span>
      {kpi.hint && <span className="text-[11px] text-muted">{kpi.hint}</span>}
    </div>
  );
}
