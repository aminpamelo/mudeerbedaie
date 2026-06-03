import { toneColor } from '@/ceo/lib/utils';
import { useT } from '@/ceo/lib/i18n';

/**
 * Horizontal stacked bar showing the parts of a whole (e.g. sessions by status,
 * payment status) with a vibrant segment per category and a value legend.
 */
export default function SegmentedBar({ segments = [] }) {
  const t = useT();
  const items = segments.filter((s) => Number(s.value) >= 0);
  const total = items.reduce((sum, s) => sum + (Number(s.value) || 0), 0);

  if (total === 0) {
    return <div className="grid h-12 place-items-center rounded-xl bg-[rgba(15,23,42,0.04)] text-[12px] text-muted">{t('no_activity_period')}</div>;
  }

  return (
    <div className="flex flex-col gap-3.5">
      <div className="flex h-3 overflow-hidden rounded-full bg-[rgba(15,23,42,0.06)]">
        {items.map((seg, i) => {
          const pct = ((Number(seg.value) || 0) / total) * 100;
          if (pct === 0) return null;
          return (
            <span
              key={seg.label}
              className="bar-fill h-full first:rounded-l-full last:rounded-r-full"
              style={{ width: `${pct}%`, background: toneColor(seg.tone), animationDelay: `${i * 60}ms` }}
              title={`${seg.label}: ${seg.value}`}
            />
          );
        })}
      </div>
      <div className="flex flex-wrap gap-x-5 gap-y-2">
        {items.map((seg) => (
          <div key={seg.label} className="flex items-center gap-1.5">
            <span className="h-2.5 w-2.5 rounded-full" style={{ background: toneColor(seg.tone) }} aria-hidden="true" />
            <span className="text-[12px] text-muted">{seg.label}</span>
            <span className="font-display text-[13px] text-ink tabular-nums">{seg.value}</span>
          </div>
        ))}
      </div>
    </div>
  );
}
