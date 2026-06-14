import { useState } from 'react';
import { router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Loader2, ArrowUpRight } from 'lucide-react';
import { cn, toneColor } from '@/ceo/lib/utils';
import { useT, useLocale } from '@/ceo/lib/i18n';
import PulseStat from './PulseStat';
import Modal from './Modal';

function currentQuery() {
  if (typeof window === 'undefined') return {};
  return Object.fromEntries(new URLSearchParams(window.location.search));
}

/** Soft tinted pill (status / priority badges) driven by a semantic tone. */
function Tag({ tone, children }) {
  const color = toneColor(tone);
  return (
    <span
      className="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold"
      style={{ color, background: `color-mix(in oklab, ${color} 15%, transparent)` }}
    >
      {children}
    </span>
  );
}

/** Thin stacked bar of a day's status flow (done / in-progress / pending, or on-time / late). */
function MiniBar({ segments }) {
  const items = segments.filter((s) => s.value > 0);
  const total = items.reduce((sum, s) => sum + s.value, 0);
  if (total === 0) return null;

  return (
    <div className="flex h-1.5 overflow-hidden rounded-full bg-[rgba(15,23,42,0.07)]">
      {items.map((s) => (
        <span key={s.key} className="h-full" style={{ width: `${(s.value / total) * 100}%`, background: toneColor(s.tone) }} />
      ))}
    </div>
  );
}

/** Workload-heat background — violet intensity scaled by how busy the day is. */
function heatStyle(cell) {
  if (!cell.inMonth || cell.heat <= 0) return undefined;
  const pct = Math.round((0.05 + cell.heat * 0.18) * 100);
  return { background: `color-mix(in oklab, var(--color-brand) ${pct}%, transparent)` };
}

/**
 * Month-grid calendar for the CEO Task Monitoring page. Plots tasks by deadline
 * or completion date (toggleable), navigated month by month via Inertia partial
 * reloads. Each day overlays four signals — priority dots, a status mini-bar, an
 * overdue/late alert flag and a workload-heat background — and opens a detail
 * panel listing every task for that day.
 */
export default function TaskCalendar({ calendar, onOpenList }) {
  const t = useT();
  const { locale } = useLocale();
  const { basis, month, weekdays, weeks, summary, legend } = calendar;
  const [day, setDay] = useState(null);
  const [busy, setBusy] = useState(false);

  function navigate(overrides) {
    const params = { ...currentQuery(), tab: 'calendar', ...overrides };
    Object.keys(params).forEach((k) => {
      if (params[k] === '' || params[k] == null) delete params[k];
    });
    router.get('/ceo/tasks', params, {
      only: ['calendar'],
      preserveState: true,
      preserveScroll: true,
      replace: true,
      onStart: () => setBusy(true),
      onFinish: () => setBusy(false),
    });
  }

  function changeBasis(next) {
    if (next === basis) return;
    navigate({ basis: next === 'deadline' ? undefined : next });
  }

  const fmtDate = (iso) => {
    if (!iso) return '';
    try {
      return new Date(`${iso}T00:00:00`).toLocaleDateString(locale === 'ms' ? 'ms-MY' : 'en-GB', { day: 'numeric', month: 'short' });
    } catch {
      return iso;
    }
  };

  const hasTasks = weeks.some((week) => week.some((c) => c.total > 0));

  const bases = [
    { value: 'deadline', label: t('cal_basis_deadline') },
    { value: 'completed', label: t('cal_basis_completed') },
  ];

  return (
    <div className="flex flex-col gap-5">
      {/* Header: month + busiest caption, basis toggle, month nav */}
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-2.5">
          <h3 className="font-display text-[18px] text-ink">{month.label}</h3>
          {busy && <Loader2 className="h-4 w-4 animate-spin text-muted-2" />}
          {summary.busiest && (
            <span className="hidden items-center gap-1 text-[11.5px] text-muted sm:inline-flex">
              · {t('cal_busiest')}: <span className="font-semibold text-ink-2">{summary.busiest.label}</span>
              <span className="rounded-full bg-[rgba(99,102,241,0.12)] px-1.5 py-0.5 text-[10px] font-bold text-[var(--color-brand-ink)] tabular-nums">
                {summary.busiest.count}
              </span>
            </span>
          )}
        </div>

        <div className="flex flex-wrap items-center gap-2">
          <div className="glass inline-flex items-center gap-0.5 rounded-[12px] p-1">
            {bases.map((b) => (
              <button
                key={b.value}
                type="button"
                onClick={() => changeBasis(b.value)}
                className={cn(
                  'rounded-[9px] px-3 py-1.5 text-[12px] font-semibold transition-all',
                  basis === b.value ? 'bg-ink text-white' : 'text-muted hover:text-ink'
                )}
              >
                {b.label}
              </button>
            ))}
          </div>

          <div className="glass inline-flex items-center gap-0.5 rounded-[12px] p-1">
            <button
              type="button"
              onClick={() => navigate({ month: month.prev })}
              aria-label={t('cal_prev_month')}
              className="grid h-7 w-7 place-items-center rounded-[9px] text-muted transition-colors hover:bg-white/70 hover:text-ink"
            >
              <ChevronLeft className="h-4 w-4" />
            </button>
            <button
              type="button"
              onClick={() => navigate({ month: undefined })}
              disabled={month.isCurrent}
              className="rounded-[9px] px-2.5 py-1.5 text-[12px] font-semibold text-muted transition-colors hover:bg-white/70 hover:text-ink disabled:opacity-40"
            >
              {t('cal_today')}
            </button>
            <button
              type="button"
              onClick={() => navigate({ month: month.next })}
              aria-label={t('cal_next_month')}
              className="grid h-7 w-7 place-items-center rounded-[9px] text-muted transition-colors hover:bg-white/70 hover:text-ink"
            >
              <ChevronRight className="h-4 w-4" />
            </button>
          </div>
        </div>
      </div>

      {/* Month summary tiles */}
      <section className="grid grid-cols-2 gap-3 sm:grid-cols-4">
        {summary.stats.map((stat) => (
          <div key={stat.label} className="glass rounded-2xl">
            <PulseStat stat={stat} />
          </div>
        ))}
      </section>

      {/* Calendar grid */}
      <div className={cn('glass-card rounded-[20px] p-2 transition-opacity sm:p-4', busy && 'opacity-60')}>
        <div role="grid" aria-label={t('tasks_tab_calendar')}>
          <div role="row" className="mb-2 grid grid-cols-7 gap-1 sm:gap-2">
            {weekdays.map((wd) => (
              <div key={wd} role="columnheader" className="px-1 pb-1 text-center font-mono text-[10px] font-medium uppercase tracking-[0.08em] text-muted-2">
                {wd}
              </div>
            ))}
          </div>

          <div className="flex flex-col gap-1 sm:gap-2">
            {weeks.map((week, wi) => (
              <div key={wi} role="row" className="grid grid-cols-7 gap-1 sm:gap-2">
                {week.map((cell) => {
                  const interactive = cell.total > 0;
                  return (
                    <div
                      key={cell.date}
                      role="gridcell"
                      tabIndex={interactive ? 0 : undefined}
                      onClick={interactive ? () => setDay(cell) : undefined}
                      onKeyDown={
                        interactive
                          ? (e) => {
                              if (e.key === 'Enter' || e.key === ' ') {
                                e.preventDefault();
                                setDay(cell);
                              }
                            }
                          : undefined
                      }
                      aria-label={interactive ? `${cell.dateLabel} · ${cell.total}` : cell.dateLabel}
                      style={heatStyle(cell)}
                      className={cn(
                        'flex min-h-[78px] flex-col gap-1 rounded-xl border p-1.5 text-left transition-all sm:min-h-[106px]',
                        cell.inMonth ? 'border-[rgba(15,23,42,0.06)]' : 'border-transparent opacity-45',
                        cell.isWeekend && cell.inMonth && cell.heat <= 0 && 'bg-[rgba(15,23,42,0.02)]',
                        interactive &&
                          'cursor-pointer hover:-translate-y-0.5 hover:border-[var(--color-brand)] hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-brand)]',
                        cell.alert > 0 && 'ring-1 ring-[rgba(244,63,94,0.4)]'
                      )}
                    >
                      {/* Day number + alert / count */}
                    <div className="flex items-center justify-between gap-1">
                      <span
                        className={cn(
                          'grid h-6 min-w-[24px] place-items-center rounded-full px-1 text-[12px] font-semibold tabular-nums',
                          cell.isToday ? 'bg-[var(--color-brand)] text-white shadow-sm' : cell.inMonth ? 'text-ink' : 'text-muted-2'
                        )}
                      >
                        {cell.day}
                      </span>
                      <div className="flex items-center gap-1">
                        {cell.alert > 0 && (
                          <span className="inline-flex items-center rounded-full bg-[rgba(244,63,94,0.14)] px-1.5 py-0.5 text-[10px] font-bold text-[var(--color-rose-ink)] tabular-nums">
                            {cell.alert}
                          </span>
                        )}
                        {cell.total > 0 && <span className="text-[11px] font-semibold text-muted tabular-nums">{cell.total}</span>}
                      </div>
                    </div>

                    {cell.total > 0 && (
                      <>
                        {/* Priority dots */}
                        <div className="flex flex-wrap items-center gap-[3px]">
                          {cell.tasks.slice(0, 6).map((tk) => (
                            <span key={tk.id} className="h-1.5 w-1.5 rounded-full" style={{ background: toneColor(tk.priorityTone) }} />
                          ))}
                          {cell.total > 6 && <span className="text-[9px] font-semibold text-muted-2">+{cell.total - 6}</span>}
                        </div>

                        {/* Status flow mini-bar */}
                        <MiniBar segments={cell.segments} />

                        {/* Task chips (desktop) */}
                        <div className="mt-auto hidden flex-col gap-0.5 sm:flex">
                          {cell.tasks.slice(0, 2).map((tk) => (
                            <span
                              key={tk.id}
                              className="flex items-center gap-1 rounded-md bg-white/55 px-1.5 py-0.5 text-[10.5px] font-medium text-ink-2"
                            >
                              <span className="h-1.5 w-1.5 shrink-0 rounded-full" style={{ background: toneColor(tk.priorityTone) }} />
                              <span className="truncate">{tk.title}</span>
                            </span>
                          ))}
                          {cell.total > 2 && (
                            <span className="px-1.5 text-[10px] font-semibold text-muted-2">{t('cal_more', { count: cell.total - 2 })}</span>
                          )}
                        </div>
                      </>
                    )}
                    </div>
                  );
                })}
              </div>
            ))}
          </div>
        </div>

        {!hasTasks && (
          <div className="mt-3 grid h-12 place-items-center rounded-xl bg-[rgba(15,23,42,0.04)] text-[12px] text-muted">{t('cal_empty_month')}</div>
        )}

        {/* Legend */}
        <div className="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-[rgba(15,23,42,0.06)] pt-3 text-[11px] text-muted">
          <span className="label-eyebrow">{t('cal_priority')}</span>
          {legend.priority.map((l) => (
            <span key={l.label} className="inline-flex items-center gap-1.5">
              <span className="h-2 w-2 rounded-full" style={{ background: toneColor(l.tone) }} />
              {l.label}
            </span>
          ))}
          <span className="mx-1 hidden h-3.5 w-px bg-[rgba(15,23,42,0.12)] sm:block" />
          {legend.flow.map((l) => (
            <span key={l.label} className="inline-flex items-center gap-1.5">
              <span className="h-2 w-3 rounded-sm" style={{ background: toneColor(l.tone) }} />
              {l.label}
            </span>
          ))}
        </div>
      </div>

      {/* Day detail panel */}
      {day && (
        <Modal title={day.dateLabel} size="md" onClose={() => setDay(null)} closeLabel={t('close')}>
          <div className="flex flex-col gap-2">
            {day.tasks.map((tk) => {
              const dateLine = tk.completedAt
                ? t('cal_done_on', { date: fmtDate(tk.completedAt) })
                : tk.deadline
                  ? t('cal_due_on', { date: fmtDate(tk.deadline) })
                  : null;
              return (
                <div key={tk.id} className="rounded-xl border border-[rgba(15,23,42,0.07)] bg-white/60 p-3">
                  <div className="flex items-start justify-between gap-3">
                    <div className="flex min-w-0 items-start gap-2">
                      <span className="mt-1 h-2 w-2 shrink-0 rounded-full" style={{ background: toneColor(tk.priorityTone) }} title={tk.priorityLabel} />
                      <div className="min-w-0">
                        <p className="text-[13px] font-semibold text-ink">{tk.title}</p>
                        <div className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] text-muted">
                          {tk.category && (
                            <span
                              className="inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 font-medium"
                              style={{ color: tk.category.color, background: `${tk.category.color}14` }}
                            >
                              <span className="h-1.5 w-1.5 rounded-full" style={{ background: tk.category.color }} />
                              {tk.category.name}
                            </span>
                          )}
                          <span>{tk.assignees.length > 0 ? tk.assignees.join(', ') : t('cal_unassigned')}</span>
                        </div>
                      </div>
                    </div>
                    <div className="flex shrink-0 flex-col items-end gap-1">
                      <Tag tone={tk.statusTone}>{tk.statusLabel}</Tag>
                      {dateLine && (
                        <span className={cn('text-[10.5px]', tk.overdue || tk.late ? 'font-semibold text-[var(--color-rose-ink)]' : 'text-muted')}>
                          {dateLine}
                        </span>
                      )}
                    </div>
                  </div>
                  {(tk.overdue || tk.late) && (
                    <div className="mt-2">
                      <span className="inline-flex items-center rounded-full bg-[rgba(244,63,94,0.12)] px-2 py-0.5 text-[10.5px] font-semibold text-[var(--color-rose-ink)]">
                        {tk.late ? t('cal_late') : t('tasks_overdue_tag')}
                      </span>
                    </div>
                  )}
                </div>
              );
            })}
          </div>

          {onOpenList && (
            <div className="mt-4 flex justify-end">
              <button
                type="button"
                onClick={() => {
                  setDay(null);
                  onOpenList();
                }}
                className="inline-flex items-center gap-1.5 text-[12px] font-semibold text-[var(--color-brand-ink)] transition-colors hover:text-ink"
              >
                {t('cal_view_list')}
                <ArrowUpRight className="h-3.5 w-3.5" strokeWidth={2.2} />
              </button>
            </div>
          )}
        </Modal>
      )}
    </div>
  );
}
