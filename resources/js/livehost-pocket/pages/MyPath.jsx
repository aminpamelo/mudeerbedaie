import { Head, usePage } from '@inertiajs/react';
import { CheckCircle2, Circle, Crown, GraduationCap, MessageSquare } from 'lucide-react';
import PocketLayout from '@/livehost-pocket/layouts/PocketLayout';

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

function StageStepper({ stages }) {
  return (
    <div className="overflow-hidden rounded-[16px] border border-[var(--hair)] bg-[var(--app-bg-2)] p-[16px]">
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

function ChecklistCard({ checklist }) {
  return (
    <div className="rounded-[16px] border border-[var(--hair)] bg-[var(--app-bg-2)] p-[16px]">
      <div className="mb-2 flex items-center justify-between">
        <div className="text-[13px] font-semibold text-[var(--fg)]">{checklist.done} of {checklist.total} done</div>
        <div className="font-mono text-[11px] font-bold text-[var(--fg-2)]">{checklist.pct}%</div>
      </div>
      <div className="h-2 overflow-hidden rounded-full bg-[var(--app-bg)]">
        <div className="h-full rounded-full bg-[var(--accent)] transition-all" style={{ width: `${checklist.pct}%` }} />
      </div>
      <ul className="mt-3 space-y-2">
        {checklist.items.length === 0 && <li className="py-2 text-center text-[12.5px] text-[var(--fg-3)]">No tasks yet.</li>}
        {checklist.items.map((item, i) => {
          const isDone = item.status === 'done';
          return (
            <li key={i} className="flex items-center gap-2.5">
              {isDone ? <CheckCircle2 className="h-[18px] w-[18px] shrink-0 text-[var(--accent)]" strokeWidth={2} /> : <Circle className="h-[18px] w-[18px] shrink-0 text-[var(--fg-3)]" strokeWidth={2} />}
              <span className={`text-[13px] ${isDone ? 'text-[var(--fg-3)] line-through' : 'text-[var(--fg)]'}`}>
                {item.title}
                {item.is_required && !isDone && <span className="ml-1.5 font-mono text-[9px] font-bold uppercase tracking-wide text-[var(--hot)]">Required</span>}
              </span>
            </li>
          );
        })}
      </ul>
    </div>
  );
}

export default function MyPath() {
  const { enrollment } = usePage().props;

  return (
    <>
      <Head title="My Path" />
      <div className="-mx-5 min-h-full bg-[var(--app-bg)] px-4 pt-3 pb-8">
        <div className="px-1 pt-3 pb-2">
          <div className="mb-1 font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
            Mentoring
          </div>
          <h1 className="font-display text-[22px] font-medium leading-[1.08] tracking-[-0.03em] text-[var(--fg)]">
            My Path
          </h1>
        </div>

        {!enrollment ? (
          <EmptyState />
        ) : (
          <>
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

            <SectionTitle>Path to top host</SectionTitle>
            <LevelLadder ladder={enrollment.ladder} />

            <SectionTitle>Journey</SectionTitle>
            <StageStepper stages={enrollment.stages} />

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
