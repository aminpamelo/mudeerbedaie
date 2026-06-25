import { Head, usePage } from '@inertiajs/react';
import { CalendarClock, CheckCircle2, Circle, Crown, GraduationCap, MessageSquare, Minus, PartyPopper, Star, TrendingDown, TrendingUp } from 'lucide-react';
import PocketLayout from '@/livehost-pocket/layouts/PocketLayout';
import { MONTH_SHORT_MS } from '@/livehost-pocket/lib/format';

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

export default function MyPath() {
  const { enrollment } = usePage().props;

  return (
    <>
      <Head title="My Performance" />
      <div className="-mx-5 min-h-full bg-[var(--app-bg)] px-4 pt-3 pb-8">
        <div className="px-1 pt-3 pb-2">
          <div className="mb-1 font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
            Mentoring
          </div>
          <h1 className="font-display text-[22px] font-medium leading-[1.08] tracking-[-0.03em] text-[var(--fg)]">
            My Performance
          </h1>
        </div>

        {!enrollment ? (
          <EmptyState />
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

            <SectionTitle>Monthly performance</SectionTitle>
            <MonthlyPerformance performance={enrollment.performance} />

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
          </>
        )}
      </div>
    </>
  );
}

MyPath.layout = (page) => <PocketLayout>{page}</PocketLayout>;
