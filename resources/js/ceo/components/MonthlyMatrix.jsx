import { Fragment, useState } from 'react';
import { ArrowUp, ArrowDown, ChevronRight } from 'lucide-react';
import { cn, toneColor } from '@/ceo/lib/utils';
import Sparkline from '@/ceo/components/Sparkline';
import AreaChart from '@/ceo/components/AreaChart';
import { useT } from '@/ceo/lib/i18n';

const MOM_TONE = {
  positive: 'bg-[rgba(16,185,129,0.16)] text-[var(--color-emerald-ink)]',
  negative: 'bg-[rgba(244,63,94,0.16)] text-[var(--color-rose-ink)]',
  muted: 'bg-[rgba(15,23,42,0.06)] text-muted',
};

/**
 * Resolve a row's drill-down breakdown. Department detail rows carry a true
 * per-day `daily` series; the yearly MonthlyReport rows don't, so fall back to
 * the rendered bucket columns (dropping blank future buckets).
 */
function breakdownFor(row, months) {
  if (Array.isArray(row.daily) && row.daily.length > 0) {
    return { items: row.daily, daily: true };
  }
  const items = (months ?? [])
    .map((label, i) => ({ label, display: row.display?.[i] ?? '', value: row.trend?.[i] ?? 0 }))
    .filter((it) => it.display !== '');
  return { items, daily: false };
}

/**
 * Industry-standard monthly performance matrix: KPI rows × Jan–Dec columns with
 * YTD total/avg, a trend sparkline, and month-over-month change. Best/worst
 * months are tinted; the polarity arrow signals whether higher or lower is
 * better. Horizontally scrollable so the 12 month columns never cramp.
 *
 * Clicking a row slides open an inline drill-down: a larger trend chart plus a
 * day-by-day (or bucket-by-bucket) breakdown for that metric.
 */
export default function MonthlyMatrix({ months = [], columns = {}, rows = [] }) {
  const t = useT();
  const [openKey, setOpenKey] = useState(null);
  const colCount = 1 + months.length + 4;

  const toggle = (key) => setOpenKey((prev) => (prev === key ? null : key));

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
            const isOpen = openKey === row.key;
            const chartColor = toneColor(row.polarity === 'down' ? 'negative' : 'info');
            const breakdown = breakdownFor(row, months);

            return (
              <Fragment key={row.key}>
                <tr
                  onClick={() => toggle(row.key)}
                  onKeyDown={(e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                      e.preventDefault();
                      toggle(row.key);
                    }
                  }}
                  tabIndex={0}
                  role="button"
                  aria-expanded={isOpen}
                  className={cn(
                    'cursor-pointer border-t border-[rgba(15,23,42,0.06)] transition-colors hover:bg-[rgba(15,23,42,0.025)] focus:outline-none focus-visible:bg-[rgba(15,23,42,0.04)]',
                    isOpen && 'bg-[rgba(15,23,42,0.03)]'
                  )}
                >
                  <td className={cn('sticky left-0 z-10 whitespace-nowrap px-2 py-2.5 backdrop-blur', isOpen ? 'bg-[rgba(247,248,250,0.92)]' : 'bg-[rgba(255,255,255,0.85)]')}>
                    <span className="flex items-center gap-1.5">
                      <ChevronRight className={cn('h-3 w-3 text-muted-2 transition-transform', isOpen && 'rotate-90 text-ink-2')} strokeWidth={2.5} />
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
                      <Sparkline data={row.trend} width={84} height={22} color={chartColor} />
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

                {isOpen && (
                  <tr className="border-t border-[rgba(15,23,42,0.06)] bg-[rgba(15,23,42,0.02)]">
                    <td colSpan={colCount} className="px-3 pb-5 pt-3">
                      <div className="flex flex-col gap-3">
                        <span className="font-mono text-[10px] font-medium uppercase tracking-[0.08em] text-muted-2">
                          {breakdown.daily ? t('daily_breakdown') : t('breakdown')} · {row.label}
                        </span>
                        {breakdown.items.length > 0 ? (
                          <>
                            <AreaChart data={breakdown.items.map((it) => it.value)} color={chartColor} height={96} />
                            <div className="flex flex-wrap gap-1.5">
                              {breakdown.items.map((it, i) => (
                                <span
                                  key={i}
                                  className="inline-flex items-baseline gap-1 rounded-md bg-[rgba(255,255,255,0.7)] px-2 py-1 ring-1 ring-[rgba(15,23,42,0.06)]"
                                >
                                  <span className="font-mono text-[10px] text-muted-2">{it.label}</span>
                                  <span className="text-[11.5px] font-medium tabular-nums text-ink-2">{it.display}</span>
                                </span>
                              ))}
                            </div>
                          </>
                        ) : (
                          <div className="grid h-16 place-items-center rounded-xl bg-[rgba(15,23,42,0.03)] text-[11.5px] text-muted">
                            {t('no_data_period')}
                          </div>
                        )}
                      </div>
                    </td>
                  </tr>
                )}
              </Fragment>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}
