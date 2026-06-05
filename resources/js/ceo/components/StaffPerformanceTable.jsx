import { cn, initialsFrom, toneColor } from '@/ceo/lib/utils';

function onTimeTone(rate) {
  if (rate === null || rate === undefined) return 'muted';
  if (rate >= 85) return 'positive';
  if (rate >= 60) return 'warning';
  return 'negative';
}

/**
 * Staff task-performance leaderboard for the CEO task-monitoring page. Laggards
 * (most overdue / open) surface at the top. Overdue counts are highlighted, and
 * each row shows the on-time completion rate as a number + a mini bar. Column
 * headers come pre-translated from the backend payload.
 */
export default function StaffPerformanceTable({ columns = [], rows = [], emptyText = '' }) {
  if (rows.length === 0) {
    return <div className="grid h-16 place-items-center rounded-xl bg-[rgba(15,23,42,0.04)] text-[12px] text-muted">{emptyText}</div>;
  }

  const label = (key) => columns.find((c) => c.key === key)?.label ?? '';

  return (
    <div className="flex flex-col">
      <div className="grid grid-cols-[1fr_3rem_3rem_3rem_120px] items-center gap-4 px-1 pb-2 font-mono text-[10px] font-medium uppercase tracking-[0.1em] text-muted-2">
        <span>{label('name')}</span>
        <span className="text-right">{label('open')}</span>
        <span className="text-right">{label('overdue')}</span>
        <span className="text-right">{label('completed')}</span>
        <span className="text-right">{label('onTime')}</span>
      </div>
      <div className="flex flex-col divide-y divide-[rgba(15,23,42,0.06)]">
        {rows.map((row, i) => {
          const tone = onTimeTone(row.onTime);
          const hasRate = row.onTime !== null && row.onTime !== undefined;
          return (
            <div key={i} className="grid grid-cols-[1fr_3rem_3rem_3rem_120px] items-center gap-4 py-2.5">
              <div className="flex min-w-0 items-center gap-2.5">
                <span className="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-gradient-to-br from-[var(--color-brand)] to-[var(--color-violet)] text-[10px] font-semibold text-white">
                  {initialsFrom(row.name)}
                </span>
                <span className="truncate text-[13px] font-medium text-ink">{row.name}</span>
              </div>
              <span className="text-right font-display text-[14px] tabular-nums text-ink-2">{row.open}</span>
              <span className={cn('text-right font-display text-[14px] tabular-nums', row.overdue > 0 ? 'text-[var(--color-rose-ink)]' : 'text-muted-2')}>
                {row.overdue}
              </span>
              <span className="text-right font-display text-[14px] tabular-nums text-ink-2">{row.completed}</span>
              <div className="flex items-center justify-end gap-2">
                <div className="h-1.5 w-14 overflow-hidden rounded-full bg-[rgba(15,23,42,0.07)]">
                  {hasRate && <div className="h-full rounded-full" style={{ width: `${row.onTime}%`, background: toneColor(tone) }} />}
                </div>
                <span className="w-9 text-right font-display text-[13px] tabular-nums" style={{ color: hasRate ? toneColor(tone) : 'var(--color-muted-2)' }}>
                  {hasRate ? `${row.onTime}%` : '—'}
                </span>
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}
