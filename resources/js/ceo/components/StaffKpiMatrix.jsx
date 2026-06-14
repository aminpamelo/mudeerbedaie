import { Fragment, useState } from 'react';
import { ChevronRight } from 'lucide-react';
import { cn, toneColor } from '@/ceo/lib/utils';
import Sparkline from '@/ceo/components/Sparkline';
import { useT } from '@/ceo/lib/i18n';

const MOM_TONE = {
  positive: 'bg-[rgba(16,185,129,0.16)] text-[var(--color-emerald-ink)]',
  negative: 'bg-[rgba(244,63,94,0.16)] text-[var(--color-rose-ink)]',
  muted: 'bg-[rgba(15,23,42,0.06)] text-muted',
};

/**
 * Inline task list shown when a staff row is expanded — their tasks with status,
 * deadline and overdue flag (the "their task and status" drill-down).
 */
function TaskList({ tasks, emptyKey = 'kpi_no_tasks' }) {
  const t = useT();

  if (!tasks || tasks.length === 0) {
    return (
      <div className="grid h-16 place-items-center rounded-xl bg-[rgba(15,23,42,0.03)] text-[11.5px] text-muted">
        {t(emptyKey)}
      </div>
    );
  }

  return (
    <div className="flex flex-col divide-y divide-[rgba(15,23,42,0.06)]">
      {tasks.map((task) => (
        <div key={task.id} className="flex flex-wrap items-center justify-between gap-2 py-2">
          <div className="flex min-w-0 items-start gap-2">
            <span className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full" style={{ background: toneColor(task.tone) }} aria-hidden="true" />
            <div className="min-w-0">
              <div className="truncate text-[12.5px] font-medium text-ink">{task.title}</div>
              <div className="mt-0.5 flex flex-wrap items-center gap-1.5 text-[10.5px] text-muted">
                {task.category && (
                  <span className="inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 font-medium" style={{ color: task.category.color, background: `${task.category.color}14` }}>
                    <span className="h-1.5 w-1.5 rounded-full" style={{ background: task.category.color }} />
                    {task.category.name}
                  </span>
                )}
                {task.source && <span className="truncate">{task.source}</span>}
                {task.overdue && <span className="rounded-full bg-[rgba(244,63,94,0.12)] px-1.5 py-0.5 font-semibold text-[var(--color-rose-ink)]">{t('tasks_overdue_tag')}</span>}
              </div>
            </div>
          </div>
          <div className="flex shrink-0 items-center gap-2.5">
            {task.deadline && <span className="font-mono text-[10.5px] tabular-nums text-muted-2">{task.deadline}</span>}
            <span
              className="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10.5px] font-semibold"
              style={{ color: toneColor(task.tone), background: `color-mix(in oklab, ${toneColor(task.tone)} 14%, white)` }}
            >
              {task.statusLabel}
            </span>
          </div>
        </div>
      ))}
    </div>
  );
}

/**
 * Staff KPI matrix: one row per employee × Jan–Dec columns. Each cell shows
 * tasks completed that month and their on-time rate ("3 · 100%"); best/worst
 * months are tinted by completed count. YTD completed + overall on-time and a
 * trend sparkline close each row. Clicking a row reveals that staff's tasks.
 * Horizontally scrollable so the 12 month columns never cramp.
 */
export default function StaffKpiMatrix({ months = [], columns = {}, rows = [], headingKey = 'kpi_tasks_heading', emptyKey = 'kpi_no_tasks' }) {
  const t = useT();
  const [openKey, setOpenKey] = useState(null);
  const colCount = 1 + months.length + 3;

  const toggle = (key) => setOpenKey((prev) => (prev === key ? null : key));

  return (
    <div className="-mx-2 overflow-x-auto px-2">
      <table className="w-full border-separate border-spacing-0 text-[12px]">
        <thead>
          <tr className="font-mono text-[10px] font-medium uppercase tracking-[0.08em] text-muted-2">
            <th className="sticky left-0 z-10 bg-[rgba(255,255,255,0.85)] px-2 py-2 text-left backdrop-blur">{columns.staff}</th>
            {months.map((m) => (
              <th key={m} className="px-2 py-2 text-right font-semibold">{m}</th>
            ))}
            <th className="px-2 py-2 text-right text-ink-2">{columns.total}</th>
            <th className="px-2 py-2 text-right text-ink-2">{columns.onTime}</th>
            <th className="px-3 py-2 text-center">{columns.trend}</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => {
            const isOpen = openKey === row.key;
            const chartColor = toneColor('info');

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
                          'whitespace-nowrap px-2 py-2.5 text-right tabular-nums',
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
                  <td className="px-2 py-2.5 text-right font-display text-[12.5px] tabular-nums text-ink-2">{row.ytdOnTime}</td>
                  <td className="px-3 py-2.5">
                    <div className="flex items-center justify-center gap-2">
                      <Sparkline data={row.trend} width={84} height={22} color={chartColor} />
                      {row.mom && (
                        <span className={cn('inline-flex rounded-full px-1.5 py-0.5 font-mono text-[10px] font-semibold tabular-nums', MOM_TONE[row.mom.tone] ?? MOM_TONE.muted)}>
                          {row.mom.text}
                        </span>
                      )}
                    </div>
                  </td>
                </tr>

                {isOpen && (
                  <tr className="border-t border-[rgba(15,23,42,0.06)] bg-[rgba(15,23,42,0.02)]">
                    <td colSpan={colCount} className="px-3 pb-4 pt-3">
                      <div className="flex flex-col gap-2">
                        <span className="font-mono text-[10px] font-medium uppercase tracking-[0.08em] text-muted-2">
                          {t(headingKey)} · {row.label}
                        </span>
                        <TaskList tasks={row.tasks} emptyKey={emptyKey} />
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
