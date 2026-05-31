import { Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import {
  ArrowLeft,
  CheckCircle2,
  ChevronRight,
  Circle,
  GraduationCap,
  Plus,
  Sparkles,
} from 'lucide-react';
import PocketLayout from '@/livehost-pocket/layouts/PocketLayout';

const ACTIVITY_TYPES = ['coaching', 'meeting', 'training', 'check_in', 'other'];

function Section({ title, children, action }) {
  return (
    <div className="mt-5">
      <div className="mb-2 flex items-center justify-between px-1">
        <div className="font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">{title}</div>
        {action}
      </div>
      {children}
    </div>
  );
}

function money(v) {
  return `RM ${Number(v ?? 0).toLocaleString(undefined, { maximumFractionDigits: 0 })}`;
}

export default function MenteeCoach() {
  const { mentee, stages, kpis, suggestedLevel, levels, checklist, activities } = usePage().props;
  const [busy, setBusy] = useState(false);
  const [levelChoice, setLevelChoice] = useState(mentee.level?.id ? String(mentee.level.id) : '');
  const [logOpen, setLogOpen] = useState(false);
  const [actType, setActType] = useState('coaching');
  const [actTitle, setActTitle] = useState('');

  const orderedStages = useMemo(() => [...(stages ?? [])].sort((a, b) => a.position - b.position), [stages]);
  const isActive = mentee.status === 'active';
  const isFinal = Boolean(mentee.current_stage?.is_final);
  const checklistDone = (checklist ?? []).filter((c) => c.status === 'done').length;
  const checklistPct = (checklist ?? []).length ? Math.round((checklistDone / checklist.length) * 100) : 0;

  const post = (url, data = {}, opts = {}) => {
    setBusy(true);
    router.post(url, data, { preserveScroll: true, onFinish: () => setBusy(false), ...opts });
  };
  const patch = (url, data = {}, opts = {}) => {
    setBusy(true);
    router.patch(url, data, { preserveScroll: true, onFinish: () => setBusy(false), ...opts });
  };

  const moveTo = (stageId) => patch(`/live-host/mentees/${mentee.id}/stage`, { to_stage_id: stageId });
  const toggle = (item) => patch(`/live-host/mentees/${mentee.id}/checklist/${item.id}/toggle`);
  const applyLevel = (levelId, source) => patch(`/live-host/mentees/${mentee.id}/level`, { level_id: levelId, source });
  const graduate = () => {
    if (!window.confirm(`Graduate ${mentee.name}? They'll be marked eligible to become a top host.`)) return;
    post(`/live-host/mentees/${mentee.id}/graduate`);
  };
  const logActivity = () => {
    if (!actTitle.trim()) return;
    post(`/live-host/mentees/${mentee.id}/activities`, { type: actType, title: actTitle, occurred_at: new Date().toISOString() }, {
      onSuccess: () => { setActTitle(''); setLogOpen(false); },
    });
  };

  return (
    <>
      <Head title={mentee.name ?? 'Mentee'} />
      <div className="-mx-5 min-h-full bg-[var(--app-bg)] px-4 pt-3 pb-8">
        <Link href="/live-host/mentees" className="inline-flex items-center gap-1 px-1 pt-2 text-[12px] font-medium text-[var(--fg-2)]">
          <ArrowLeft className="h-3.5 w-3.5" strokeWidth={2} /> My Mentees
        </Link>

        {/* Hero */}
        <div className="mt-2 rounded-[16px] border border-[var(--hair)] bg-[var(--app-bg-2)] p-[16px]">
          <div className="flex items-center gap-3">
            <span className="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-gradient-to-br from-[var(--accent)] to-[var(--hot)] font-display text-[17px] font-bold text-white">
              {(mentee.name ?? '?').slice(0, 1).toUpperCase()}
            </span>
            <div className="min-w-0 flex-1">
              <div className="truncate font-display text-[17px] font-medium tracking-[-0.02em] text-[var(--fg)]">{mentee.name}</div>
              <div className="mt-0.5 flex items-center gap-2 text-[11.5px] text-[var(--fg-2)]">
                <span>{mentee.current_stage?.name ?? '—'}</span>
                {mentee.level && (
                  <span className="rounded-full px-1.5 py-0.5 text-[10px] font-semibold text-white" style={{ backgroundColor: mentee.level.color || '#10B981' }}>
                    {mentee.level.name}
                  </span>
                )}
              </div>
            </div>
          </div>
        </div>

        {/* KPIs */}
        <Section title="Last 30 days">
          <div className="grid grid-cols-3 gap-2">
            <KpiTile label="Sessions" value={kpis?.sessions ?? 0} />
            <KpiTile label="Hours" value={kpis?.hours ?? 0} />
            <KpiTile label="Net GMV" value={money(kpis?.gmv)} />
            <KpiTile label="Attendance" value={`${kpis?.attendancePct ?? 0}%`} />
            <KpiTile label="No-shows" value={kpis?.noShows ?? 0} />
            <KpiTile label="GMV/hr" value={money(kpis?.avgGmvPerHour)} />
          </div>
        </Section>

        {/* Stage / journey */}
        {isActive && (
          <Section title="Stage">
            <div className="rounded-[16px] border border-[var(--hair)] bg-[var(--app-bg-2)] p-[14px]">
              <div className="flex flex-wrap gap-1.5">
                {orderedStages.map((s) => {
                  const current = s.id === mentee.current_stage_id;
                  return (
                    <button
                      key={s.id}
                      type="button"
                      disabled={busy || current}
                      onClick={() => moveTo(s.id)}
                      className={[
                        'rounded-full px-2.5 py-1 text-[11.5px] font-medium transition',
                        current
                          ? 'bg-[var(--accent)] text-white'
                          : 'bg-[var(--app-bg)] text-[var(--fg-2)] ring-1 ring-[var(--hair)] active:scale-95',
                      ].join(' ')}
                    >
                      {s.name}
                    </button>
                  );
                })}
              </div>
              {isFinal && (
                <button
                  type="button"
                  disabled={busy}
                  onClick={graduate}
                  className="mt-3 flex w-full items-center justify-center gap-1.5 rounded-[12px] bg-[var(--accent)] px-4 py-[11px] font-sans text-[13px] font-bold text-[var(--accent-ink)] transition active:scale-[0.98] disabled:opacity-60"
                >
                  <GraduationCap className="h-4 w-4" strokeWidth={2.25} /> Graduate {mentee.name?.split(' ')[0]}
                </button>
              )}
            </div>
          </Section>
        )}

        {/* Level */}
        <Section title="Performance level">
          <div className="rounded-[16px] border border-[var(--hair)] bg-[var(--app-bg-2)] p-[14px]">
            {suggestedLevel && (
              <div className="mb-3 flex items-center justify-between gap-2 rounded-[10px] bg-[var(--app-bg)] px-3 py-2">
                <div className="flex items-center gap-1.5 text-[12px] text-[var(--fg-2)]">
                  <Sparkles className="h-3.5 w-3.5 text-[var(--accent)]" strokeWidth={2.25} />
                  Suggested: <span className="font-semibold text-[var(--fg)]">{suggestedLevel.name}</span>
                </div>
                <button
                  type="button"
                  disabled={busy || mentee.level?.id === suggestedLevel.id}
                  onClick={() => applyLevel(suggestedLevel.id, 'auto')}
                  className="shrink-0 rounded-full bg-[var(--accent)] px-2.5 py-1 text-[11px] font-bold text-[var(--accent-ink)] disabled:opacity-50"
                >
                  {mentee.level?.id === suggestedLevel.id ? 'Applied' : 'Apply'}
                </button>
              </div>
            )}
            <div className="flex items-center gap-2">
              <select
                value={levelChoice}
                onChange={(e) => setLevelChoice(e.target.value)}
                className="h-10 flex-1 rounded-[10px] border border-[var(--hair)] bg-[var(--app-bg)] px-3 text-[13px] text-[var(--fg)]"
              >
                <option value="">— No level —</option>
                {(levels ?? []).map((l) => (
                  <option key={l.id} value={l.id}>{l.name}{l.is_top ? ' ★' : ''}</option>
                ))}
              </select>
              <button
                type="button"
                disabled={busy}
                onClick={() => applyLevel(levelChoice ? Number(levelChoice) : null, 'manual')}
                className="rounded-[10px] bg-[var(--fg)] px-3.5 py-2 text-[13px] font-bold text-[var(--app-bg)] disabled:opacity-60"
              >
                Save
              </button>
            </div>
          </div>
        </Section>

        {/* Checklist */}
        <Section title={`Tasks · ${checklistDone}/${(checklist ?? []).length}`}>
          <div className="rounded-[16px] border border-[var(--hair)] bg-[var(--app-bg-2)] p-[14px]">
            <div className="mb-3 h-1.5 overflow-hidden rounded-full bg-[var(--app-bg)]">
              <div className="h-full rounded-full bg-[var(--accent)]" style={{ width: `${checklistPct}%` }} />
            </div>
            <ul className="space-y-2.5">
              {(checklist ?? []).length === 0 && <li className="text-[12.5px] text-[var(--fg-3)]">No tasks.</li>}
              {(checklist ?? []).map((item) => {
                const done = item.status === 'done';
                return (
                  <li key={item.id}>
                    <button type="button" disabled={busy} onClick={() => toggle(item)} className="flex w-full items-center gap-2.5 text-left">
                      {done ? <CheckCircle2 className="h-[19px] w-[19px] shrink-0 text-[var(--accent)]" strokeWidth={2} /> : <Circle className="h-[19px] w-[19px] shrink-0 text-[var(--fg-3)]" strokeWidth={2} />}
                      <span className={`text-[13px] ${done ? 'text-[var(--fg-3)] line-through' : 'text-[var(--fg)]'}`}>
                        {item.title}
                        {item.is_required && !done && <span className="ml-1.5 font-mono text-[9px] font-bold uppercase text-[var(--hot)]">Req</span>}
                      </span>
                    </button>
                  </li>
                );
              })}
            </ul>
          </div>
        </Section>

        {/* Coaching log */}
        <Section
          title="Coaching"
          action={
            <button type="button" onClick={() => setLogOpen((v) => !v)} className="inline-flex items-center gap-1 rounded-full bg-[var(--app-bg-2)] px-2.5 py-1 text-[11px] font-bold text-[var(--fg)] ring-1 ring-[var(--hair)]">
              <Plus className="h-3 w-3" strokeWidth={2.5} /> Log
            </button>
          }
        >
          {logOpen && (
            <div className="mb-2 space-y-2 rounded-[16px] border border-[var(--hair)] bg-[var(--app-bg-2)] p-[14px]">
              <div className="flex gap-2">
                <select value={actType} onChange={(e) => setActType(e.target.value)} className="h-9 rounded-[10px] border border-[var(--hair)] bg-[var(--app-bg)] px-2 text-[12px] text-[var(--fg)]">
                  {ACTIVITY_TYPES.map((t) => <option key={t} value={t}>{t.replace('_', ' ')}</option>)}
                </select>
                <input
                  value={actTitle}
                  onChange={(e) => setActTitle(e.target.value)}
                  onKeyDown={(e) => { if (e.key === 'Enter') logActivity(); }}
                  placeholder="What did you cover?"
                  className="h-9 flex-1 rounded-[10px] border border-[var(--hair)] bg-[var(--app-bg)] px-3 text-[12px] text-[var(--fg)]"
                />
              </div>
              <button type="button" disabled={busy || !actTitle.trim()} onClick={logActivity} className="w-full rounded-[10px] bg-[var(--accent)] py-2 text-[12.5px] font-bold text-[var(--accent-ink)] disabled:opacity-50">
                Save activity
              </button>
            </div>
          )}
          <div className="overflow-hidden rounded-[16px] border border-[var(--hair)] bg-[var(--app-bg-2)]">
            {(activities ?? []).length === 0 ? (
              <div className="px-[14px] py-5 text-center text-[12.5px] text-[var(--fg-3)]">No coaching logged yet.</div>
            ) : (
              <ul className="divide-y divide-[var(--hair)]">
                {activities.map((a) => (
                  <li key={a.id} className="flex items-start gap-2.5 px-[14px] py-[11px]">
                    <ChevronRight className="mt-0.5 h-3.5 w-3.5 shrink-0 text-[var(--fg-3)]" strokeWidth={2} />
                    <div className="min-w-0">
                      <div className="text-[13px] text-[var(--fg)]">{a.title}</div>
                      <div className="mt-0.5 font-mono text-[10px] uppercase tracking-wide text-[var(--fg-3)]">{a.type.replace('_', ' ')} · {a.occurred_at_human}</div>
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </div>
        </Section>
      </div>
    </>
  );
}

function KpiTile({ label, value }) {
  return (
    <div className="rounded-[12px] border border-[var(--hair)] bg-[var(--app-bg-2)] px-3 py-2.5">
      <div className="font-mono text-[9px] font-bold uppercase tracking-[0.1em] text-[var(--fg-3)]">{label}</div>
      <div className="mt-1 font-display text-[16px] font-medium tabular-nums text-[var(--fg)]">{value}</div>
    </div>
  );
}

MenteeCoach.layout = (page) => <PocketLayout>{page}</PocketLayout>;
