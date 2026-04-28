import { ArrowDown, ArrowUp, Minus } from 'lucide-react';

/**
 * KPI card — bold sans display number, mono eyebrow label, soft delta chip.
 * `valueSize="sm"` shrinks the number for cards that show text-heavy values.
 */
export default function KpiCard({
  label,
  value,
  delta,
  format = (v) => v,
  valueSize = 'lg',
  hint,
}) {
  const direction = delta == null ? 'flat' : delta > 0 ? 'up' : delta < 0 ? 'down' : 'flat';
  const Icon = direction === 'up' ? ArrowUp : direction === 'down' ? ArrowDown : Minus;

  return (
    <div className="kpi-card">
      <div className="label-eyebrow">{label}</div>
      <div className={valueSize === 'sm' ? 'kpi-value-sm mt-3' : 'kpi-value mt-3'}>
        {format(value)}
      </div>
      {(delta != null || hint) && (
        <div className="mt-3 flex items-center gap-2">
          {delta != null && (
            <span className="delta-chip" data-direction={direction}>
              <Icon className="size-3" strokeWidth={2.4} />
              <span>{Math.abs(delta).toFixed(1)}%</span>
            </span>
          )}
          {hint ? (
            <span className="text-[11px] text-[var(--color-muted)]">{hint}</span>
          ) : (
            delta != null && (
              <span className="text-[11px] text-[var(--color-muted)]">vs prior period</span>
            )
          )}
        </div>
      )}
    </div>
  );
}
