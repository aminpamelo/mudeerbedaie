import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { CalendarClock, CheckCircle2, ChevronLeft, ChevronRight, Circle, Crown, GraduationCap, Loader2, MessageSquare, Minus, PartyPopper, Star, TrendingDown, TrendingUp, Trophy, Video } from 'lucide-react';
import PocketLayout from '@/livehost-pocket/layouts/PocketLayout';
import { MONTH_SHORT_MS } from '@/livehost-pocket/lib/format';
import { initialsFrom } from '@/livehost-pocket/lib/utils';

/** Colour tone for a 0-100 KPI score — mirrors the PIC desk's score bands. */
function kpiTone(score) {
  if (score === null || score === undefined) {
    return { text: 'var(--fg-3)', bar: 'var(--hair-2)', soft: 'var(--app-bg)' };
  }
  if (score >= 80) {
    return { text: '#047857', bar: '#10B981', soft: '#ECFDF5' };
  }
  if (score >= 60) {
    return { text: '#B45309', bar: '#F59E0B', soft: '#FEF7E6' };
  }
  return { text: '#B91C1C', bar: '#E11D48', soft: '#FEECEF' };
}

/** "Jun" or "Jun 2026" from 1-based month + year. */
function monthLabel(year, month, withYear = false) {
  const m = MONTH_SHORT_MS[(Number(month) || 1) - 1] ?? '';
  return withYear ? `${m} ${year}` : m;
}

/** Ringgit value with 2 decimals, e.g. "RM 2,909.00". */
function rm(n) {
  return `RM ${Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function SectionTitle({ children }) {
  return (
    <div className="mb-2 mt-5 px-1 font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
      {children}
    </div>
  );
}

function EmptyState() {
  return (
    <div className="mt-10 flex flex-col items-center px-6 text-center">
      <div className="grid h-16 w-16 place-items-center rounded-full bg-[var(--app-bg-2)] ring-1 ring-[var(--hair)]">
        <GraduationCap className="h-7 w-7 text-[var(--fg-3)]" strokeWidth={1.8} />
      </div>
      <div className="mt-4 font-display text-[18px] font-medium tracking-[-0.02em] text-[var(--fg)]">
        Not in a mentoring program yet
      </div>
      <p className="mt-1.5 text-[13px] leading-relaxed text-[var(--fg-2)]">
        When your team enrols you in a mentoring program, your stage, level, and tasks on the path to
        becoming a top host will show up here.
      </p>
    </div>
  );
}

function StageStepper({ stages, progress }) {
  return (
    <div className="overflow-hidden rounded-[16px] border border-[var(--hair)] bg-[var(--app-bg-2)] p-[16px]">
      {progress && progress.total > 0 && (
        <div className="mb-3">
          <div className="mb-1.5 flex items-center justify-between">
            <span className="text-[12px] font-semibold text-[var(--fg)]">Stage {progress.current_position} of {progress.total}</span>
            <span className="font-mono text-[11px] font-bold text-[var(--fg-2)]">{progress.pct}%</span>
          </div>
          <div className="h-2 overflow-hidden rounded-full bg-[var(--app-bg)]">
            <div className="h-full rounded-full bg-[var(--accent)] transition-all" style={{ width: `${progress.pct}%` }} />
          </div>
        </div>
      )}
      <ol className="space-y-0">
        {stages.map((s, i) => {
          const isLast = i === stages.length - 1;
          const dot =
            s.state === 'done'
              ? 'bg-[var(--accent)] text-white'
              : s.state === 'current'
                ? 'bg-white text-[var(--accent)] ring-2 ring-[var(--accent)]'
                : 'bg-[var(--app-bg)] text-[var(--fg-3)] ring-1 ring-[var(--hair)]';
          return (
            <li key={s.position} className="relative flex gap-3 pb-4 last:pb-0">
              {!isLast && <span className="absolute left-[13px] top-7 h-[calc(100%-12px)] w-px bg-[var(--hair)]" aria-hidden="true" />}
              <span className={`relative z-10 grid h-[27px] w-[27px] shrink-0 place-items-center rounded-full text-[11px] font-bold ${dot}`}>
                {s.state === 'done' ? <CheckCircle2 className="h-4 w-4" strokeWidth={2.5} /> : s.is_final ? <Crown className="h-3.5 w-3.5" strokeWidth={2.25} /> : s.position}
              </span>
              <div className="pt-1">
                <div className={`text-[13.5px] ${s.state === 'current' ? 'font-semibold text-[var(--fg)]' : s.state === 'done' ? 'text-[var(--fg-2)]' : 'text-[var(--fg-3)]'}`}>
                  {s.name}
                </div>
                {s.state === 'current' && <div className="text-[11px] font-medium text-[var(--accent)]">You are here</div>}
              </div>
            </li>
          );
        })}
      </ol>
    </div>
  );
}

function LevelLadder({ ladder }) {
  return (
    <div className="flex flex-wrap gap-2 rounded-[16px] border border-[var(--hair)] bg-[var(--app-bg-2)] p-[16px]">
      {ladder.map((l) => {
        const base = 'inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11.5px] font-semibold';
        if (l.state === 'current') {
          return (
            <span key={l.name} className={base} style={{ backgroundColor: l.color || '#10B981', color: '#fff' }}>
              {l.is_top && <Crown className="h-3 w-3" strokeWidth={2.5} />} {l.name}
            </span>
          );
        }
        if (l.state === 'achieved') {
          return (
            <span key={l.name} className={`${base} text-[var(--fg)]`} style={{ backgroundColor: `${l.color}26` }}>
              <CheckCircle2 className="h-3 w-3" strokeWidth={2.5} /> {l.name}
            </span>
          );
        }
        return (
          <span key={l.name} className={`${base} bg-[var(--app-bg)] text-[var(--fg-3)] ring-1 ring-[var(--hair)]`}>
            {l.is_top && <Crown className="h-3 w-3" strokeWidth={2.25} />} {l.name}
          </span>
        );
      })}
    </div>
  );
}

function TaskRow({ item }) {
  const isDone = item.status === 'done';
  return (
    <li className="flex items-start gap-2.5">
      {isDone ? <CheckCircle2 className="mt-px h-[18px] w-[18px] shrink-0 text-[var(--accent)]" strokeWidth={2} /> : <Circle className="mt-px h-[18px] w-[18px] shrink-0 text-[var(--fg-3)]" strokeWidth={2} />}
      <div className="min-w-0 flex-1">
        <div className="flex flex-wrap items-center gap-x-1.5 gap-y-0.5">
          <span className={`text-[13px] ${isDone ? 'text-[var(--fg-3)] line-through' : 'text-[var(--fg)]'}`}>{item.title}</span>
          {item.is_required && !isDone && <span className="font-mono text-[9px] font-bold uppercase tracking-wide text-[var(--hot)]">Required</span>}
          {item.due_at_human && (
            <span className={`inline-flex items-center gap-1 text-[10.5px] ${item.is_overdue ? 'font-bold text-[var(--hot)]' : 'text-[var(--fg-3)]'}`}>
              <CalendarClock className="h-3 w-3" strokeWidth={2} />{item.is_overdue ? `Overdue · ${item.due_at_human}` : item.due_at_human}
            </span>
          )}
        </div>
        {item.description && <p className="mt-0.5 text-[11.5px] leading-snug text-[var(--fg-2)]">{item.description}</p>}
      </div>
    </li>
  );
}

function ChecklistCard({ checklist }) {
  const program = checklist.program ?? [];
  const individual = checklist.individual ?? [];

  return (
    <div className="rounded-[16px] border border-[var(--hair)] bg-[var(--app-bg-2)] p-[16px]">
      <div className="mb-2 flex items-center justify-between">
        <div className="text-[13px] font-semibold text-[var(--fg)]">{checklist.done} of {checklist.total} done</div>
        <div className="font-mono text-[11px] font-bold text-[var(--fg-2)]">{checklist.pct}%</div>
      </div>
      <div className="h-2 overflow-hidden rounded-full bg-[var(--app-bg)]">
        <div className="h-full rounded-full bg-[var(--accent)] transition-all" style={{ width: `${checklist.pct}%` }} />
      </div>

      {checklist.total === 0 && <div className="py-4 text-center text-[12.5px] text-[var(--fg-3)]">No tasks yet.</div>}

      {program.length > 0 && (
        <>
          <div className="mb-2 mt-4 font-mono text-[9.5px] font-bold uppercase tracking-[0.12em] text-[var(--fg-3)]">Program tasks</div>
          <ul className="space-y-2.5">
            {program.map((item, i) => <TaskRow key={i} item={item} />)}
          </ul>
        </>
      )}

      {individual.length > 0 && (
        <>
          <div className="mb-2 mt-4 flex items-center gap-1.5 border-t border-[var(--hair)] pt-3 font-mono text-[9.5px] font-bold uppercase tracking-[0.12em] text-[#047857]">
            <Star className="h-3 w-3" strokeWidth={2.5} />
            Just for you · {checklist.individual_done}/{checklist.individual_total}
          </div>
          <ul className="space-y-2.5">
            {individual.map((item, i) => <TaskRow key={i} item={item} />)}
          </ul>
        </>
      )}
    </div>
  );
}

function StatusBanner({ status }) {
  if (status === 'graduated') {
    return (
      <div className="mt-2 flex items-center gap-2.5 rounded-[14px] border border-[#10B98140] bg-[#ECFDF5] px-3.5 py-3">
        <PartyPopper className="h-[18px] w-[18px] shrink-0 text-[#047857]" strokeWidth={2} />
        <div className="text-[13px] font-semibold text-[#047857]">Congratulations — you&rsquo;ve graduated!</div>
      </div>
    );
  }
  if (status === 'dropped') {
    return (
      <div className="mt-2 flex items-center gap-2.5 rounded-[14px] border border-[var(--hair-2)] bg-[var(--app-bg-2)] px-3.5 py-3">
        <Circle className="h-[18px] w-[18px] shrink-0 text-[var(--fg-3)]" strokeWidth={2} />
        <div className="text-[13px] font-medium text-[var(--fg-2)]">Your participation in this program has ended.</div>
      </div>
    );
  }
  return null;
}

function DeltaPill({ delta }) {
  if (delta === null || delta === undefined) {
    return null;
  }
  if (delta > 0) {
    return (
      <span className="inline-flex items-center gap-1 rounded-full bg-[#ECFDF5] px-2 py-[3px] text-[11px] font-bold text-[#047857]">
        <TrendingUp className="h-3 w-3" strokeWidth={2.5} /> +{delta}
      </span>
    );
  }
  if (delta < 0) {
    return (
      <span className="inline-flex items-center gap-1 rounded-full bg-[#FEECEF] px-2 py-[3px] text-[11px] font-bold text-[#B91C1C]">
        <TrendingDown className="h-3 w-3" strokeWidth={2.5} /> {delta}
      </span>
    );
  }
  return (
    <span className="inline-flex items-center gap-1 rounded-full bg-[var(--app-bg)] px-2 py-[3px] text-[11px] font-bold text-[var(--fg-3)]">
      <Minus className="h-3 w-3" strokeWidth={2.5} /> 0
    </span>
  );
}

function ScoreTrend({ trend }) {
  const withData = trend.filter((t) => t.overall !== null);
  if (withData.length === 0) {
    return null;
  }
  return (
    <div className="mt-4 border-t border-[var(--hair)] pt-3">
      <div className="mb-2 font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">Last {trend.length} month{trend.length === 1 ? '' : 's'}</div>
      <div className="flex items-end justify-between gap-1.5" style={{ height: '76px' }}>
        {trend.map((t) => {
          const tone = kpiTone(t.overall);
          const h = t.overall === null ? 4 : Math.max(6, Math.round((t.overall / 100) * 56));
          return (
            <div key={t.period} className="flex flex-1 flex-col items-center gap-1">
              <div className="text-[9.5px] font-bold tabular-nums text-[var(--fg-2)]" style={{ minHeight: '12px' }}>
                {t.overall === null ? '' : t.overall}
              </div>
              <div
                className="w-full max-w-[26px] rounded-[5px] transition-all"
                style={{ height: `${h}px`, backgroundColor: tone.bar, opacity: t.overall === null ? 0.4 : 1 }}
              />
              <div className="font-mono text-[9px] uppercase tracking-wide text-[var(--fg-3)]">{monthLabel(t.year, t.month)}</div>
            </div>
          );
        })}
      </div>
    </div>
  );
}

function MonthlyPerformance({ performance }) {
  if (!performance || !performance.has_scores) {
    return (
      <div className="rounded-[16px] border border-dashed border-[var(--hair-2)] bg-[var(--app-bg-2)] px-3 py-6 text-center">
        <div className="text-[13px] font-medium text-[var(--fg-2)]">No monthly score yet</div>
        <p className="mt-1 text-[12px] leading-relaxed text-[var(--fg-3)]">
          Your mentor records your attitude &amp; sales score each month. It will show up here.
        </p>
      </div>
    );
  }

  const { latest, trend, delta_overall: delta, sales_target: target } = performance;
  const tone = kpiTone(latest.overall);

  return (
    <div className="rounded-[16px] border border-[var(--hair)] bg-[var(--app-bg-2)] p-[16px]">
      {/* Overall score */}
      <div className="flex items-start justify-between gap-3">
        <div>
          <div className="font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">Overall score</div>
          <div className="mt-1 flex items-end gap-2">
            <span className="font-display text-[34px] font-medium leading-none tracking-[-0.04em] tabular-nums" style={{ color: tone.text }}>
              {latest.overall === null ? '—' : latest.overall}
            </span>
            {latest.overall !== null && <span className="pb-1 text-[14px] font-semibold text-[var(--fg-3)]">%</span>}
            <span className="pb-1"><DeltaPill delta={delta} /></span>
          </div>
        </div>
        <span className="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold" style={{ backgroundColor: tone.soft, color: tone.text }}>
          {monthLabel(latest.year, latest.month, true)}
        </span>
      </div>

      {/* Attitude + Sales breakdown */}
      <div className="mt-4 grid grid-cols-2 gap-2.5">
        <div className="rounded-[12px] bg-[var(--app-bg)] px-3 py-2.5">
          <div className="font-mono text-[9.5px] font-bold uppercase tracking-[0.12em] text-[var(--fg-3)]">Attitude</div>
          <div className="mt-1 font-display text-[18px] font-medium tabular-nums text-[var(--fg)]">
            {latest.attitude === null ? '—' : <>{latest.attitude}<span className="text-[12px] text-[var(--fg-3)]">/100</span></>}
          </div>
        </div>
        <div className="rounded-[12px] bg-[var(--app-bg)] px-3 py-2.5">
          <div className="font-mono text-[9.5px] font-bold uppercase tracking-[0.12em] text-[var(--fg-3)]">Sales</div>
          <div className="mt-1 font-display text-[18px] font-medium tabular-nums text-[var(--fg)]">
            {latest.sales === null ? '—' : rm(latest.sales)}
            {latest.sales !== null && target ? <span className="text-[12px] text-[var(--fg-3)]"> / {rm(target)}</span> : null}
          </div>
          {latest.sales_pct !== null && (
            <div className="mt-1.5 h-1.5 overflow-hidden rounded-full bg-[var(--hair)]">
              <div className="h-full rounded-full" style={{ width: `${latest.sales_pct}%`, backgroundColor: kpiTone(latest.sales_pct).bar }} />
            </div>
          )}
        </div>
      </div>

      <ScoreTrend trend={trend} />
    </div>
  );
}

/** Compact Ringgit for dense chips — whole RM stays short, sen only when present. */
function rmCompact(n) {
  const num = Number(n);
  if (Number.isNaN(num)) return 'RM 0';
  const hasSen = Math.round(num) !== num;
  return `RM ${num.toLocaleString(undefined, { minimumFractionDigits: hasSen ? 2 : 0, maximumFractionDigits: 2 })}`;
}

const CATEGORY_LABELS = { lateness: 'Lateness', absence: 'Absence', rule_violation: 'Rule violation', misconduct: 'Misconduct', other: 'Other' };

function DailyStrip({ daily }) {
  if (!daily || !daily.days || daily.days.length === 0) {
    return (
      <div className="rounded-[16px] border border-dashed border-[var(--hair-2)] bg-[var(--app-bg-2)] px-3 py-6 text-center">
        <div className="text-[13px] font-medium text-[var(--fg-2)]">No sales logged this month yet</div>
        <p className="mt-1 text-[12px] leading-relaxed text-[var(--fg-3)]">Your daily sales show up here as you go live.</p>
      </div>
    );
  }
  return (
    <div className="rounded-[16px] border border-[var(--hair)] bg-[var(--app-bg-2)] p-[16px]">
      <div className="mb-3 flex items-end justify-between">
        <div>
          <div className="font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">{daily.month_label} · total</div>
          <div className="font-display text-[22px] font-medium tracking-[-0.03em] tabular-nums text-[var(--fg)]">{rm(daily.total)}</div>
        </div>
        <div className="flex items-center gap-2 font-mono text-[9.5px] uppercase tracking-wide text-[var(--fg-3)]">
          <span className="flex items-center gap-1"><span className="h-1.5 w-1.5 rounded-full bg-[var(--accent)]" /> comment</span>
          <span className="flex items-center gap-1"><span className="h-1.5 w-1.5 rounded-full bg-[#EF4444]" /> conduct</span>
        </div>
      </div>
      <div className="flex gap-1.5 overflow-x-auto pb-1">
        {daily.days.map((d) => (
          <div key={d.date} className={`flex w-[46px] shrink-0 flex-col items-center gap-0.5 rounded-[10px] border px-1 py-1.5 ${d.sales > 0 ? 'border-[var(--hair)] bg-[var(--app-bg-2)]' : 'border-[var(--hair)] bg-[var(--app-bg)]'}`}>
            <span className={`text-[10px] font-semibold ${d.sessions > 0 ? 'text-[var(--fg)]' : 'text-[var(--fg-3)]'}`}>{d.day}</span>
            <span className="text-[9.5px] font-bold tabular-nums text-[var(--fg)]">{d.sales > 0 ? rmCompact(d.sales).replace('RM ', '') : '·'}</span>
            <span className="flex h-1.5 items-center gap-0.5">
              {d.has_comment && <span className="h-1.5 w-1.5 rounded-full bg-[var(--accent)]" />}
              {d.has_disciplinary && <span className="h-1.5 w-1.5 rounded-full bg-[#EF4444]" />}
            </span>
          </div>
        ))}
      </div>
    </div>
  );
}

/**
 * The day-by-day strip with a month browser. `months` is newest-first; switching
 * month fetches that month's strip from the JSON endpoint (the rest of the page
 * stays put). The previous month stays visible, dimmed, while the next loads.
 */
function DailyStripSection({ initialDaily, months }) {
  const [daily, setDaily] = useState(initialDaily);
  const [loading, setLoading] = useState(false);

  const list = months.length > 0 ? months : (daily ? [{ year: daily.year, month: daily.month, label: daily.month_label }] : []);
  const idx = list.findIndex((m) => m.year === daily?.year && m.month === daily?.month);
  const canOlder = idx >= 0 && idx < list.length - 1; // older months sit later in a newest-first list
  const canNewer = idx > 0;

  const go = (targetIdx) => {
    const m = list[targetIdx];
    if (!m || loading) return;
    setLoading(true);
    fetch(`/live-host/my-path/daily?year=${m.year}&month=${m.month}`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    })
      .then((r) => r.json())
      .then((data) => setDaily(data))
      .catch(() => {})
      .finally(() => setLoading(false));
  };

  const arrow = 'grid h-9 w-9 shrink-0 place-items-center rounded-[11px] border border-[var(--hair-2)] bg-[var(--app-bg-2)] text-[var(--fg-2)] transition active:scale-95 disabled:opacity-30';

  return (
    <>
      <div className="mb-2 flex items-center gap-2">
        <button type="button" onClick={() => go(idx + 1)} disabled={!canOlder || loading} aria-label="Previous month" className={arrow}>
          <ChevronLeft className="h-4 w-4" strokeWidth={2.2} />
        </button>
        <div className="relative flex-1">
          <select
            value={`${daily?.year}-${daily?.month}`}
            onChange={(e) => { const [y, m] = e.target.value.split('-').map(Number); go(list.findIndex((x) => x.year === y && x.month === m)); }}
            className="h-9 w-full appearance-none rounded-[11px] border border-[var(--hair-2)] bg-[var(--app-bg-2)] px-3 text-center text-[13px] font-semibold text-[var(--fg)] focus:border-[var(--accent)] focus:outline-none"
          >
            {list.map((m) => <option key={`${m.year}-${m.month}`} value={`${m.year}-${m.month}`}>{m.label}</option>)}
          </select>
          {loading && <Loader2 className="pointer-events-none absolute right-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 animate-spin text-[var(--fg-3)]" />}
        </div>
        <button type="button" onClick={() => go(idx - 1)} disabled={!canNewer || loading} aria-label="Next month" className={arrow}>
          <ChevronRight className="h-4 w-4" strokeWidth={2.2} />
        </button>
      </div>
      <div className={loading ? 'opacity-60 transition-opacity' : 'transition-opacity'}>
        <DailyStrip daily={daily} />
      </div>
    </>
  );
}

function CommentsFeed({ comments }) {
  return (
    <div className="overflow-hidden rounded-[16px] border border-[var(--hair)] bg-[var(--app-bg-2)]">
      <ul className="divide-y divide-[var(--hair)]">
        {comments.map((c, i) => (
          <li key={i} className="px-[14px] py-[11px]">
            <div className="flex items-center justify-between">
              <span className="font-mono text-[10px] font-bold uppercase tracking-wide text-[var(--fg-3)]">{c.date_human}</span>
              {c.by && <span className="text-[10px] text-[var(--fg-3)]">{c.by}</span>}
            </div>
            <p className="mt-1 whitespace-pre-wrap text-[13px] leading-snug text-[var(--fg)]">{c.comment}</p>
          </li>
        ))}
      </ul>
    </div>
  );
}

function ConductList({ conduct }) {
  return (
    <div className="overflow-hidden rounded-[16px] border border-[#F0C8C8] bg-[#FEF7F7]">
      <ul className="divide-y divide-[#F5DADA]">
        {conduct.map((r, i) => (
          <li key={i} className="px-[14px] py-[11px]">
            <div className="flex flex-wrap items-center gap-2">
              <span className={`inline-flex items-center rounded-md px-1.5 py-0.5 text-[9.5px] font-bold uppercase tracking-wide ${r.severity === 'major' ? 'bg-[#FEE2E2] text-[#B91C1C]' : 'bg-[#FEF3C7] text-[#B45309]'}`}>{r.severity}</span>
              <span className="text-[12.5px] font-semibold text-[var(--fg)]">{CATEGORY_LABELS[r.category] ?? r.category}</span>
              <span className="text-[10.5px] text-[var(--fg-3)]">{r.incident_date_human}</span>
            </div>
            <p className="mt-1 whitespace-pre-wrap text-[12.5px] leading-snug text-[var(--fg-2)]">{r.description}</p>
          </li>
        ))}
      </ul>
    </div>
  );
}

/** Segmented switch between the host's own performance and the cohort board. */
function SubTabs({ tab, onChange }) {
  const tabs = [
    { key: 'performance', label: 'My Performance' },
    { key: 'leaderboard', label: 'Leaderboard' },
  ];
  return (
    <div className="mt-3 flex gap-1 rounded-[14px] border border-[var(--hair)] bg-[var(--app-bg-3)] p-1">
      {tabs.map((t) => {
        const active = tab === t.key;
        return (
          <button
            key={t.key}
            type="button"
            onClick={() => onChange(t.key)}
            className={`flex-1 rounded-[10px] px-3 py-2 text-[12.5px] font-semibold transition ${active ? 'bg-[var(--app-bg-2)] text-[var(--accent)] shadow-sm' : 'text-[var(--fg-2)] hover:text-[var(--fg)]'}`}
          >
            {t.label}
          </button>
        );
      })}
    </div>
  );
}

/** Top-3 get a medal tone; everyone else gets a neutral numbered chip. */
const RANK_TONES = [
  { bg: '#FEF3C7', text: '#92400E', ring: '#F59E0B' }, // gold
  { bg: '#F1F5F9', text: '#475569', ring: '#94A3B8' }, // silver
  { bg: '#FBE7DA', text: '#9A3412', ring: '#EA9A5B' }, // bronze
];

function RankBadge({ rank }) {
  if (rank <= 3) {
    const t = RANK_TONES[rank - 1];
    return (
      <span
        className="grid h-8 w-8 shrink-0 place-items-center rounded-full text-[13px] font-bold tabular-nums"
        style={{ backgroundColor: t.bg, color: t.text, boxShadow: `inset 0 0 0 1.5px ${t.ring}` }}
      >
        {rank}
      </span>
    );
  }
  return (
    <span className="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-[var(--app-bg)] text-[13px] font-bold tabular-nums text-[var(--fg-3)] ring-1 ring-[var(--hair)]">
      {rank}
    </span>
  );
}

function LeaderboardRow({ row }) {
  return (
    <li
      className="flex items-center gap-3 px-[14px] py-[11px]"
      style={row.is_me ? { backgroundColor: 'var(--accent-soft)' } : undefined}
    >
      <RankBadge rank={row.rank} />
      <div className="relative h-8 w-8 shrink-0 overflow-hidden rounded-full bg-gradient-to-br from-[var(--accent)] to-[var(--hot)]">
        {row.avatar_url ? (
          <img src={row.avatar_url} alt={row.name} className="h-full w-full object-cover" />
        ) : (
          <span className="grid h-full w-full place-items-center text-[10px] font-bold text-white">{initialsFrom(row.name)}</span>
        )}
      </div>
      <div className="min-w-0 flex-1">
        <div className="flex items-center gap-1.5">
          <span className={`truncate text-[13px] text-[var(--fg)] ${row.is_me ? 'font-bold' : 'font-medium'}`}>{row.name}</span>
          {row.is_me && (
            <span className="shrink-0 rounded-full bg-[var(--accent)] px-1.5 py-[1px] text-[9px] font-bold uppercase tracking-wide text-white">You</span>
          )}
        </div>
        {row.level && (
          <span
            className="mt-0.5 inline-block rounded-full px-1.5 py-[1px] text-[9.5px] font-semibold"
            style={{ backgroundColor: `${row.level.color || '#7C3AED'}20`, color: row.level.color || 'var(--fg-2)' }}
          >
            {row.level.name}
          </span>
        )}
      </div>
      <span className="shrink-0 font-display text-[13.5px] font-semibold tabular-nums text-[var(--fg)]">{rm(row.sales)}</span>
    </li>
  );
}

function Leaderboard({ leaderboard }) {
  const [periodKey, setPeriodKey] = useState('this_month');

  if (!leaderboard) {
    return (
      <div className="mt-4 rounded-[16px] border border-dashed border-[var(--hair-2)] bg-[var(--app-bg-2)] px-3 py-6 text-center">
        <div className="text-[13px] font-medium text-[var(--fg-2)]">No leaderboard yet</div>
        <p className="mt-1 text-[12px] leading-relaxed text-[var(--fg-3)]">Join a mentoring program to see how you rank against your cohort.</p>
      </div>
    );
  }

  const period = leaderboard.periods[periodKey];
  const rows = period.rows;
  const me = rows.find((r) => r.is_me) ?? null;
  const soloCohort = leaderboard.member_count <= 1;

  return (
    <div className="mt-3">
      {/* Period toggle */}
      <div className="flex gap-1 rounded-[12px] border border-[var(--hair)] bg-[var(--app-bg-3)] p-1">
        {[
          { key: 'this_month', label: 'This month' },
          { key: 'all_time', label: 'All time' },
        ].map((p) => {
          const active = periodKey === p.key;
          return (
            <button
              key={p.key}
              type="button"
              onClick={() => setPeriodKey(p.key)}
              className={`flex-1 rounded-[9px] px-3 py-1.5 text-[11.5px] font-semibold transition ${active ? 'bg-[var(--app-bg-2)] text-[var(--accent)] shadow-sm' : 'text-[var(--fg-2)] hover:text-[var(--fg)]'}`}
            >
              {p.label}
            </button>
          );
        })}
      </div>

      {/* Your rank summary */}
      <div className="mt-3 flex items-center gap-3 rounded-[16px] border border-[var(--hair)] bg-[var(--app-bg-2)] p-[16px]">
        <span className="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-gradient-to-br from-[var(--accent)] to-[var(--hot)] text-white">
          <Trophy className="h-5 w-5" strokeWidth={2} />
        </span>
        <div className="min-w-0 flex-1">
          <div className="font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
            {leaderboard.program_title}
          </div>
          {soloCohort ? (
            <div className="mt-0.5 text-[13px] font-medium text-[var(--fg-2)]">You&rsquo;re the only host in your cohort right now.</div>
          ) : me ? (
            <div className="mt-0.5 text-[14px] text-[var(--fg)]">
              You&rsquo;re <span className="font-display font-semibold text-[var(--accent)]">#{me.rank}</span> of {leaderboard.member_count} · {period.label}
            </div>
          ) : (
            <div className="mt-0.5 text-[13px] font-medium text-[var(--fg-2)]">Your ranking will show once you log sales.</div>
          )}
        </div>
        {me && !soloCohort && (
          <span className="shrink-0 font-display text-[16px] font-semibold tabular-nums text-[var(--fg)]">{rm(me.sales)}</span>
        )}
      </div>

      {/* Ranked list */}
      <div className="mt-3 overflow-hidden rounded-[16px] border border-[var(--hair)] bg-[var(--app-bg-2)]">
        <ul className="divide-y divide-[var(--hair)]">
          {rows.map((row) => (
            <LeaderboardRow key={row.mentee_id} row={row} />
          ))}
        </ul>
      </div>

      <p className="mt-2.5 px-1 text-[10.5px] leading-relaxed text-[var(--fg-3)]">
        Ranked by sales generated — auto live-session GMV plus any PIC adjustments. Only your program cohort is shown.
      </p>
    </div>
  );
}

export default function MyPath() {
  const { enrollment, leaderboard } = usePage().props;
  const [tab, setTab] = useState('performance');

  return (
    <>
      <Head title="My Performance" />
      <div className="-mx-5 min-h-full bg-[var(--app-bg)] px-4 pt-3 pb-8">
        <div className="px-1 pt-3 pb-2">
          <div className="mb-1 font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
            Mentoring
          </div>
          <h1 className="font-display text-[22px] font-medium leading-[1.08] tracking-[-0.03em] text-[var(--fg)]">
            {tab === 'leaderboard' ? 'Leaderboard' : 'My Performance'}
          </h1>
        </div>

        {!enrollment ? (
          <EmptyState />
        ) : (
          <>
            <SubTabs tab={tab} onChange={setTab} />

            {tab === 'leaderboard' ? (
              <Leaderboard leaderboard={leaderboard} />
            ) : (
              <>
            {enrollment.status !== 'active' && <StatusBanner status={enrollment.status} />}

            {/* Hero */}
            <div className="mt-2 rounded-[16px] border border-[var(--hair)] bg-[var(--app-bg-2)] p-[16px]">
              <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                  <div className="font-mono text-[10px] text-[var(--fg-3)]">{enrollment.mentee_number}</div>
                  <div className="mt-0.5 truncate font-display text-[17px] font-medium tracking-[-0.02em] text-[var(--fg)]">
                    {enrollment.program.title}
                  </div>
                  <div className="mt-1 text-[12px] text-[var(--fg-2)]">
                    Mentor: <span className="font-medium text-[var(--fg)]">{enrollment.mentor?.name ?? 'Your team'}</span>
                  </div>
                </div>
                {enrollment.level && (
                  <span className="shrink-0 rounded-full px-2.5 py-1 text-[11.5px] font-semibold text-white" style={{ backgroundColor: enrollment.level.color || '#10B981' }}>
                    {enrollment.level.name}
                  </span>
                )}
              </div>
              <div className="mt-3 flex items-center gap-2 rounded-[10px] bg-[var(--app-bg)] px-3 py-2">
                <GraduationCap className="h-4 w-4 text-[var(--accent)]" strokeWidth={2} />
                <span className="text-[12.5px] text-[var(--fg-2)]">
                  Current stage: <span className="font-semibold text-[var(--fg)]">{enrollment.current_stage?.name ?? '—'}</span>
                </span>
              </div>
            </div>

            <Link
              href="/live-host/videos"
              className="mt-3 flex items-center gap-3 rounded-[16px] border border-[var(--hair)] bg-[var(--app-bg-2)] px-[14px] py-3 transition active:scale-[0.99]"
            >
              <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[var(--accent-soft)] text-[var(--accent)]">
                <Video className="h-5 w-5" strokeWidth={2.2} />
              </span>
              <div className="min-w-0 flex-1">
                <div className="text-[13px] font-semibold text-[var(--fg)]">Daily video log</div>
                <div className="text-[11.5px] text-[var(--fg-2)]">Record the videos you make each day.</div>
              </div>
              <ChevronRight className="h-4 w-4 shrink-0 text-[var(--fg-3)]" strokeWidth={2} />
            </Link>

            <SectionTitle>Monthly performance</SectionTitle>
            <MonthlyPerformance performance={enrollment.performance} />

            <SectionTitle>Day by day</SectionTitle>
            <DailyStripSection initialDaily={enrollment.daily} months={enrollment.available_months ?? []} />

            <SectionTitle>Path to top host</SectionTitle>
            <LevelLadder ladder={enrollment.ladder} />

            <SectionTitle>Journey</SectionTitle>
            <StageStepper stages={enrollment.stages} progress={enrollment.stage_progress} />

            <SectionTitle>My tasks</SectionTitle>
            <ChecklistCard checklist={enrollment.checklist} />

            {enrollment.activities.length > 0 && (
              <>
                <SectionTitle>Recent coaching</SectionTitle>
                <div className="overflow-hidden rounded-[16px] border border-[var(--hair)] bg-[var(--app-bg-2)]">
                  <ul className="divide-y divide-[var(--hair)]">
                    {enrollment.activities.map((a, i) => (
                      <li key={i} className="flex items-start gap-2.5 px-[14px] py-[11px]">
                        <MessageSquare className="mt-0.5 h-4 w-4 shrink-0 text-[var(--fg-3)]" strokeWidth={2} />
                        <div className="min-w-0">
                          <div className="text-[13px] text-[var(--fg)]">{a.title}</div>
                          <div className="mt-0.5 font-mono text-[10px] uppercase tracking-wide text-[var(--fg-3)]">
                            {a.type.replace('_', ' ')} · {a.occurred_at_human}
                          </div>
                        </div>
                      </li>
                    ))}
                  </ul>
                </div>
              </>
            )}

            {enrollment.comments?.length > 0 && (
              <>
                <SectionTitle>Feedback from your PIC</SectionTitle>
                <CommentsFeed comments={enrollment.comments} />
              </>
            )}

            {enrollment.conduct?.length > 0 && (
              <>
                <SectionTitle>Conduct records</SectionTitle>
                <ConductList conduct={enrollment.conduct} />
              </>
            )}
              </>
            )}
          </>
        )}
      </div>
    </>
  );
}

MyPath.layout = (page) => <PocketLayout>{page}</PocketLayout>;
