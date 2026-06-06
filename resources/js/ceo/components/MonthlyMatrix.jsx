import { ArrowUp, ArrowDown } from 'lucide-react';
import { cn, toneColor } from '@/ceo/lib/utils';
import Sparkline from '@/ceo/components/Sparkline';

const MOM_TONE = {
  positive: 'bg-[rgba(16,185,129,0.16)] text-[var(--color-emerald-ink)]',
  negative: 'bg-[rgba(244,63,94,0.16)] text-[var(--color-rose-ink)]',
  muted: 'bg-[rgba(15,23,42,0.06)] text-muted',
};

/**
 * Industry-standard monthly performance matrix: KPI rows × Jan–Dec columns with
 * YTD total/avg, a trend sparkline, and month-over-month change. Best/worst
 * months are tinted; the polarity arrow signals whether higher or lower is
 * better. Horizontally scrollable so the 12 month columns never cramp.
 */
export default function MonthlyMatrix({ months = [], columns = {}, rows = [] }) {
  return (
    <div className="-mx-2 overflow-x-auto px-2">
      <table className="w-full border-separate border-spacing-0 text-[12px]">
        <thead>
          <tr className="font-mono text-[10px] font-medium uppercase tracking-[0.08em] text-muted-2">
            <th className="sticky left-0 z-10 bg-[rgba(255,255,255,0.85)] px-2 py-2 text-left backdrop-blur">{columns.metric}</th>
            {months.map((m) => (
              <th key={m} className="px-2 py-2 text-right font-semibold">{m}</th>
            ))}
            <th className="px-2 py-2 text-right text-ink-2">{columns.ytdTotal}</th>
            <th className="px-2 py-2 text-right text-ink-2">{columns.ytdAvg}</th>
            <th className="px-3 py-2 text-center">{columns.trend}</th>
            <th className="px-2 py-2 text-right">{columns.mom}</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => {
            const PolarityIcon = row.polarity === 'down' ? ArrowDown : ArrowUp;
            return (
              <tr key={row.key} className="border-t border-[rgba(15,23,42,0.06)]">
                <td className="sticky left-0 z-10 whitespace-nowrap bg-[rgba(255,255,255,0.85)] px-2 py-2.5 backdrop-blur">
                  <span className="flex items-center gap-1.5">
                    <PolarityIcon className="h-3 w-3 text-muted-2" strokeWidth={2.5} />
                    <span className="font-medium text-ink">{row.label}</span>
                  </span>
                </td>
                {row.display.map((cell, i) => {
                  const isBest = i === row.bestIndex;
                  const isWorst = i === row.worstIndex;
                  return (
                    <td
                      key={i}
                      className={cn(
                        'px-2 py-2.5 text-right tabular-nums',
                        cell === '' ? 'text-muted-2' : 'text-ink-2',
                        isBest && 'rounded-md bg-[rgba(16,185,129,0.14)] font-semibold text-[var(--color-emerald-ink)]',
                        isWorst && 'rounded-md bg-[rgba(244,63,94,0.12)] font-semibold text-[var(--color-rose-ink)]'
                      )}
                    >
                      {cell === '' ? '·' : cell}
                    </td>
                  );
                })}
                <td className="px-2 py-2.5 text-right font-display text-[12.5px] tabular-nums text-ink">{row.ytdTotal}</td>
                <td className="px-2 py-2.5 text-right font-display text-[12.5px] tabular-nums text-ink-2">{row.ytdAvg}</td>
                <td className="px-3 py-2.5">
                  <div className="flex justify-center">
                    <Sparkline data={row.trend} width={84} height={22} color={toneColor(row.polarity === 'down' ? 'negative' : 'info')} />
                  </div>
                </td>
                <td className="px-2 py-2.5 text-right">
                  {row.mom ? (
                    <span className={cn('inline-flex rounded-full px-1.5 py-0.5 font-mono text-[10.5px] font-semibold tabular-nums', MOM_TONE[row.mom.tone] ?? MOM_TONE.muted)}>
                      {row.mom.text}
                    </span>
                  ) : (
                    <span className="text-muted-2">—</span>
                  )}
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}
