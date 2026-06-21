import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
  ArrowLeft,
  ArrowRight,
  Award,
  Ban,
  BadgeCheck,
  BarChart3,
  Check,
  CheckCircle2,
  ChevronDown,
  Circle,
  Clock,
  DollarSign,
  GraduationCap,
  ListChecks,
  Loader2,
  MessageSquare,
  Plus,
  RotateCcw,
  Sparkles,
  Trash2,
  Wand2,
  XCircle,
} from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import { Button } from '@/livehost/components/ui/button';
import ActivityLogModal from '@/livehost/components/mentoring/ActivityLogModal';

function StatusBadge({ status }) {
  const map = {
    active: { label: 'Active', tone: 'bg-[#ECFDF5] text-[#047857]' },
    graduated: { label: 'Graduated', tone: 'bg-[#E0E7FF] text-[#4338CA]' },
    dropped: { label: 'Dropped', tone: 'bg-[#FEE2E2] text-[#B91C1C]' },
  };
  const entry = map[status] ?? map.active;
  return <span className={`inline-flex items-center rounded-full px-2.5 py-1 text-[11.5px] font-medium ${entry.tone}`}>{entry.label}</span>;
}

function LevelBadge({ level }) {
  if (!level) return null;
  return (
    <span className="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold" style={{ backgroundColor: `${level.color}22`, color: level.color }}>
      {level.name}
    </span>
  );
}

function formatDate(iso) {
  if (!iso) return '—';
  try {
    return new Date(iso).toLocaleString();
  } catch {
    return iso;
  }
}

function actionLabel(action) {
  return {
    enrolled: 'Enrolled', advanced: 'Advanced', reverted: 'Reverted', leveled: 'Level set',
    graduated: 'Graduated', dropped: 'Dropped', restored: 'Restored', note: 'Note',
  }[action] ?? action;
}

function actionTone(action) {
  return {
    enrolled: 'bg-[#F5F5F5] text-[#525252]', advanced: 'bg-[#ECFDF5] text-[#047857]', reverted: 'bg-[#FEF3C7] text-[#B45309]',
    leveled: 'bg-[#EDE9FE] text-[#6D28D9]', graduated: 'bg-[#E0E7FF] text-[#4338CA]', dropped: 'bg-[#FEE2E2] text-[#B91C1C]',
    restored: 'bg-[#ECFDF5] text-[#047857]', note: 'bg-[#F5F5F5] text-[#525252]',
  }[action] ?? 'bg-[#F5F5F5] text-[#525252]';
}

export default function MenteeShow() {
  const { mentee, stages, history, kpis, suggestedLevel, levels, activities, checklist } = usePage().props;
  const [activeTab, setActiveTab] = useState('overview');

  return (
    <>
      <Head title={mentee.full_name ?? 'Mentee'} />
      <TopBar
        breadcrumb={['Live Host Desk', 'Mentoring', 'Mentees', mentee.full_name ?? '']}
        actions={
          <Link href={`/livehost/mentoring/mentees?program=${mentee.program?.id ?? ''}`}>
            <Button variant="ghost" className="gap-1.5 text-[#737373] hover:text-[#0A0A0A]">
              <ArrowLeft className="h-3.5 w-3.5" />
              Back to board
            </Button>
          </Link>
        }
      />

      <div className="space-y-6 p-8 pb-32">
        <div className="flex items-start justify-between gap-6 rounded-[16px] border border-[#EAEAEA] bg-white p-6 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
          <div className="flex items-center gap-4">
            <div className="grid h-16 w-16 place-items-center rounded-xl bg-gradient-to-br from-[#10B981] to-[#059669] text-2xl font-semibold tracking-[-0.02em] text-white">
              {(mentee.full_name ?? '?').slice(0, 1).toUpperCase()}
            </div>
            <div className="min-w-0">
              <div className="flex flex-wrap items-center gap-2">
                <span className="font-mono text-[11.5px] text-[#737373]">{mentee.mentee_number}</span>
                <StatusBadge status={mentee.status} />
                <LevelBadge level={mentee.level} />
              </div>
              <div className="mt-1 text-2xl font-semibold tracking-[-0.02em] text-[#0A0A0A]">{mentee.full_name}</div>
              <div className="mt-0.5 truncate text-sm text-[#737373]">{mentee.email}{mentee.phone ? ` · ${mentee.phone}` : ''}</div>
            </div>
          </div>
          <div className="shrink-0 text-right">
            <div className="text-[11px] uppercase tracking-wide text-[#737373]">Current stage</div>
            <div className="mt-1 text-[15px] font-semibold tracking-[-0.01em] text-[#0A0A0A]">{mentee.current_stage?.name ?? '—'}</div>
            {mentee.current_stage?.is_final && (
              <span className="mt-1 inline-flex items-center gap-1 rounded-full bg-[#ECFDF5] px-2 py-0.5 text-[10.5px] font-medium text-[#047857]">
                <BadgeCheck className="h-3 w-3" strokeWidth={2.5} /> Final stage
              </span>
            )}
            <div className="mt-2 text-[11px] text-[#737373]">Enrolled {mentee.enrolled_at_human ?? '—'}</div>
          </div>
        </div>

        <div className="flex items-center gap-6 border-b border-[#EAEAEA]">
          {[
            { id: 'overview', label: 'Overview' },
            { id: 'kpis', label: 'KPIs' },
            { id: 'level', label: 'Level' },
            { id: 'checklist', label: `Checklist · ${checklist?.length ?? 0}` },
            { id: 'coaching', label: `Coaching · ${activities?.length ?? 0}` },
            { id: 'activity', label: `Activity · ${history?.length ?? 0}` },
            { id: 'notes', label: 'Notes' },
          ].map((tab) => (
            <button key={tab.id} type="button" onClick={() => setActiveTab(tab.id)} className={['-mb-px border-b-2 px-1 pb-3 text-sm font-medium transition-colors', activeTab === tab.id ? 'border-[#0A0A0A] text-[#0A0A0A]' : 'border-transparent text-[#737373] hover:text-[#0A0A0A]'].join(' ')}>
              {tab.label}
            </button>
          ))}
        </div>

        {activeTab === 'overview' && <OverviewTab mentee={mentee} />}
        {activeTab === 'kpis' && <KpiTab kpis={kpis} />}
        {activeTab === 'level' && <LevelTab mentee={mentee} suggestedLevel={suggestedLevel} levels={levels} />}
        {activeTab === 'checklist' && <ChecklistTab mentee={mentee} checklist={checklist} />}
        {activeTab === 'coaching' && <CoachingTab mentee={mentee} activities={activities} />}
        {activeTab === 'activity' && <ActivityTab history={history} />}
        {activeTab === 'notes' && <NotesTab mentee={mentee} />}
      </div>

      <ActionBar mentee={mentee} stages={stages} />
    </>
  );
}

MenteeShow.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;

function InfoCard({ label, value }) {
  return (
    <div>
      <div className="mb-1 text-[11px] font-medium uppercase tracking-wide text-[#737373]">{label}</div>
      <div className="text-[14px] text-[#0A0A0A]">{value ?? <span className="text-[#A3A3A3]">—</span>}</div>
    </div>
  );
}

function OverviewTab({ mentee }) {
  return (
    <div className="rounded-[16px] border border-[#EAEAEA] bg-white p-6 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
      <div className="mb-4 text-[11px] font-semibold uppercase tracking-[0.08em] text-[#737373]">Mentorship</div>
      <div className="grid grid-cols-2 gap-5 md:grid-cols-3">
        <InfoCard label="Program" value={mentee.program?.title} />
        <InfoCard label="Mentor" value={mentee.mentor?.name ?? mentee.program?.leader?.name ?? 'Program leader'} />
        <InfoCard label="Current stage" value={mentee.current_stage?.name} />
        <InfoCard label="Level" value={mentee.level?.name ?? 'Not assigned'} />
        <InfoCard label="Enrolled" value={formatDate(mentee.enrolled_at)} />
        {mentee.graduated_at && <InfoCard label="Graduated" value={formatDate(mentee.graduated_at)} />}
      </div>
    </div>
  );
}

function ActivityTab({ history }) {
  if (!history || history.length === 0) {
    return (
      <div className="grid place-items-center rounded-[16px] border border-dashed border-[#EAEAEA] bg-white px-8 py-16 text-center">
        <div className="text-sm text-[#737373]">No activity yet.</div>
      </div>
    );
  }
  return (
    <div className="rounded-[16px] border border-[#EAEAEA] bg-white p-6 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
      <ol className="relative space-y-5 pl-6 before:absolute before:left-[7px] before:top-1 before:bottom-1 before:w-px before:bg-[#EAEAEA]">
        {history.map((event, index) => (
          <li key={event.id} className="relative">
            <span className={['absolute -left-6 top-1 h-3.5 w-3.5 rounded-full border-2 bg-white', index === 0 ? 'border-[#10B981]' : 'border-[#EAEAEA]'].join(' ')} />
            <div className="flex items-baseline justify-between gap-4">
              <div className="min-w-0">
                <div className="flex items-center gap-2">
                  <span className={`inline-flex items-center rounded-md px-1.5 py-0.5 text-[10.5px] font-semibold uppercase tracking-wide ${actionTone(event.action)}`}>{actionLabel(event.action)}</span>
                  <span className="text-[13.5px] font-medium text-[#0A0A0A]">
                    {event.from_stage?.name ?? '—'}
                    <span className="mx-1.5 text-[#A3A3A3]">→</span>
                    {event.to_stage?.name ?? '—'}
                  </span>
                </div>
                {event.notes && <div className="mt-1 whitespace-pre-wrap text-[13px] text-[#525252]">{event.notes}</div>}
                <div className="mt-1 text-[11px] text-[#A3A3A3]">{event.created_at_human}{event.changed_by?.name ? ` · ${event.changed_by.name}` : ''}</div>
              </div>
            </div>
          </li>
        ))}
      </ol>
    </div>
  );
}

function NotesTab({ mentee }) {
  const [notes, setNotes] = useState(mentee.notes ?? '');
  const [saveState, setSaveState] = useState('idle');
  const timerRef = useRef(null);
  const lastSavedRef = useRef(mentee.notes ?? '');

  useEffect(() => {
    setNotes(mentee.notes ?? '');
    lastSavedRef.current = mentee.notes ?? '';
  }, [mentee.id, mentee.notes]);

  const save = (value) => {
    setSaveState('saving');
    router.patch(`/livehost/mentoring/mentees/${mentee.id}/notes`, { notes: value }, {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        lastSavedRef.current = value;
        setSaveState('saved');
        window.setTimeout(() => setSaveState('idle'), 1500);
      },
      onError: () => setSaveState('error'),
    });
  };

  const handleChange = (e) => {
    const value = e.target.value;
    setNotes(value);
    if (timerRef.current) window.clearTimeout(timerRef.current);
    timerRef.current = window.setTimeout(() => {
      if (value !== lastSavedRef.current) save(value);
    }, 500);
  };

  useEffect(() => () => { if (timerRef.current) window.clearTimeout(timerRef.current); }, []);

  return (
    <div className="rounded-[16px] border border-[#EAEAEA] bg-white p-6 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
      <div className="mb-2 flex items-center justify-between">
        <div className="text-[11px] font-semibold uppercase tracking-[0.08em] text-[#737373]">Mentor notes</div>
        <div className="text-[11px] text-[#A3A3A3]">
          {saveState === 'saving' && <span className="inline-flex items-center gap-1"><Loader2 className="h-3 w-3 animate-spin" /> Saving…</span>}
          {saveState === 'saved' && <span className="inline-flex items-center gap-1 text-[#047857]"><Check className="h-3 w-3" strokeWidth={3} /> Saved</span>}
          {saveState === 'error' && <span className="text-[#B91C1C]">Failed to save</span>}
          {saveState === 'idle' && 'Auto-saves 500 ms after you stop typing.'}
        </div>
      </div>
      <textarea value={notes} onChange={handleChange} rows={10} placeholder="Private notes about this mentee…" className="w-full resize-y rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-[13.5px] leading-relaxed text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20" />
    </div>
  );
}

function KpiStat({ icon: Icon, label, value, sub }) {
  return (
    <div className="rounded-[14px] border border-[#EAEAEA] bg-white p-5 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
      <div className="flex items-center gap-2 text-[11px] font-medium uppercase tracking-[0.06em] text-[#737373]">
        <Icon className="h-3.5 w-3.5 text-[#A3A3A3]" strokeWidth={2} />
        {label}
      </div>
      <div className="mt-2 text-[26px] font-semibold leading-none tracking-[-0.02em] text-[#0A0A0A] tabular-nums">{value}</div>
      {sub && <div className="mt-1.5 text-[12px] text-[#737373]">{sub}</div>}
    </div>
  );
}

function KpiTab({ kpis }) {
  if (!kpis) {
    return (
      <div className="grid place-items-center rounded-[16px] border border-dashed border-[#EAEAEA] bg-white px-8 py-16 text-center">
        <div className="text-sm text-[#737373]">No KPI data available.</div>
      </div>
    );
  }
  const money = (v) => `RM ${Number(v ?? 0).toLocaleString(undefined, { maximumFractionDigits: 0 })}`;
  return (
    <div className="space-y-3">
      <div className="flex items-center gap-2 text-[12px] text-[#737373]">
        <BarChart3 className="h-3.5 w-3.5 text-[#A3A3A3]" strokeWidth={2} />
        Performance over the last 30 days ({kpis.from} → {kpis.to}). These signals drive the suggested level.
      </div>
      <div className="grid grid-cols-2 gap-3 md:grid-cols-3">
        <KpiStat icon={BarChart3} label="Sessions" value={kpis.sessions} sub={`${kpis.ended} completed · ${kpis.noShows} no-show`} />
        <KpiStat icon={Clock} label="Hours live" value={kpis.hours} sub="From completed sessions" />
        <KpiStat icon={DollarSign} label="Net GMV" value={money(kpis.gmv)} sub={`${money(kpis.avgGmvPerHour)} / hour`} />
        <KpiStat icon={BadgeCheck} label="Attendance" value={`${kpis.attendancePct}%`} sub="Completed ÷ scheduled" />
        <KpiStat icon={Award} label="Avg GMV / hour" value={money(kpis.avgGmvPerHour)} />
        <KpiStat icon={BarChart3} label="Completed" value={kpis.ended} sub={`of ${kpis.sessions} scheduled`} />
      </div>
    </div>
  );
}

function LevelTab({ mentee, suggestedLevel, levels }) {
  const [selected, setSelected] = useState(mentee.level?.id ? String(mentee.level.id) : '');
  const [busy, setBusy] = useState(false);

  const patchLevel = (levelId, source) => {
    setBusy(true);
    router.patch(`/livehost/mentoring/mentees/${mentee.id}/level`, { level_id: levelId, source }, {
      preserveScroll: true,
      onFinish: () => setBusy(false),
    });
  };

  const isSuggestionCurrent = suggestedLevel && mentee.level?.id === suggestedLevel.id;

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        {/* Current level */}
        <div className="rounded-[16px] border border-[#EAEAEA] bg-white p-6 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
          <div className="text-[11px] font-semibold uppercase tracking-[0.08em] text-[#737373]">Current level</div>
          {mentee.level ? (
            <div className="mt-3 flex items-center gap-3">
              <span className="inline-block h-9 w-9 rounded-full ring-1 ring-black/5" style={{ backgroundColor: mentee.level.color || '#A3A3A3' }} />
              <div>
                <div className="text-[20px] font-semibold tracking-[-0.02em] text-[#0A0A0A]">{mentee.level.name}</div>
                <div className="text-[12px] text-[#737373]">Manually set by the mentor or auto-applied</div>
              </div>
            </div>
          ) : (
            <div className="mt-3 text-[14px] text-[#A3A3A3]">No level assigned yet.</div>
          )}
        </div>

        {/* Suggestion */}
        <div className="rounded-[16px] border border-[#A7F3D0] bg-[#ECFDF5] p-6">
          <div className="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-[#047857]">
            <Sparkles className="h-3.5 w-3.5" strokeWidth={2.25} /> Suggested from KPIs
          </div>
          {suggestedLevel ? (
            <>
              <div className="mt-3 flex items-center gap-3">
                <span className="inline-block h-8 w-8 rounded-full ring-1 ring-black/5" style={{ backgroundColor: suggestedLevel.color || '#10B981' }} />
                <div className="text-[18px] font-semibold tracking-[-0.02em] text-[#065F46]">{suggestedLevel.name}</div>
              </div>
              <button
                type="button"
                disabled={busy || isSuggestionCurrent}
                onClick={() => patchLevel(suggestedLevel.id, 'auto')}
                className="mt-4 inline-flex items-center gap-1.5 rounded-lg bg-[#059669] px-3.5 py-2 text-[13px] font-medium text-white hover:bg-[#047857] disabled:opacity-50"
              >
                <Wand2 className="h-3.5 w-3.5" strokeWidth={2.25} />
                {isSuggestionCurrent ? 'Already applied' : 'Apply suggestion'}
              </button>
            </>
          ) : (
            <div className="mt-3 text-[13px] text-[#047857]">No level matches yet — add thresholds or levels.</div>
          )}
        </div>
      </div>

      {/* Manual override */}
      <div className="rounded-[16px] border border-[#EAEAEA] bg-white p-6 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
        <div className="text-[11px] font-semibold uppercase tracking-[0.08em] text-[#737373]">Set level manually</div>
        <p className="mt-1 text-[12.5px] text-[#737373]">The mentor's judgement always wins over the auto-suggestion.</p>
        <div className="mt-3 flex flex-wrap items-center gap-2">
          <select value={selected} onChange={(e) => setSelected(e.target.value)} className="h-10 min-w-[200px] rounded-lg border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20">
            <option value="">— No level —</option>
            {(levels ?? []).map((l) => (
              <option key={l.id} value={l.id}>{l.name}{l.is_top ? ' ★' : ''}</option>
            ))}
          </select>
          <Button type="button" disabled={busy} onClick={() => patchLevel(selected ? Number(selected) : null, 'manual')} className="bg-[#0A0A0A] text-white hover:bg-[#262626]">
            Save level
          </Button>
        </div>
      </div>
    </div>
  );
}

function ChecklistTab({ mentee, checklist }) {
  const [newTitle, setNewTitle] = useState('');
  const [busy, setBusy] = useState(false);

  const items = checklist ?? [];
  const total = items.length;
  const done = items.filter((c) => c.status === 'done').length;
  const pct = total ? Math.round((done / total) * 100) : 0;

  const toggle = (item) => {
    router.patch(`/livehost/mentoring/mentees/${mentee.id}/checklist/${item.id}/toggle`, {}, { preserveScroll: true, preserveState: false });
  };
  const remove = (item) => {
    if (!window.confirm('Remove this task?')) return;
    router.delete(`/livehost/mentoring/mentees/${mentee.id}/checklist/${item.id}`, { preserveScroll: true });
  };
  const add = () => {
    if (!newTitle.trim()) return;
    setBusy(true);
    router.post(`/livehost/mentoring/mentees/${mentee.id}/checklist`, { title: newTitle, is_required: false }, {
      preserveScroll: true,
      onSuccess: () => setNewTitle(''),
      onFinish: () => setBusy(false),
    });
  };

  return (
    <div className="space-y-4">
      <div className="rounded-[16px] border border-[#EAEAEA] bg-white p-6 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
        <div className="mb-3 flex items-center justify-between">
          <div className="flex items-center gap-2 text-[13px] font-semibold text-[#0A0A0A]">
            <ListChecks className="h-4 w-4 text-[#10B981]" strokeWidth={2.25} />
            {done} of {total} done
          </div>
          <span className="text-[12px] font-medium tabular-nums text-[#737373]">{pct}%</span>
        </div>
        <div className="h-2 overflow-hidden rounded-full bg-[#F0F0F0]">
          <div className="h-full rounded-full bg-[#10B981] transition-all" style={{ width: `${pct}%` }} />
        </div>

        <ul className="mt-4 divide-y divide-[#F0F0F0]">
          {items.length === 0 && <li className="py-6 text-center text-[13px] text-[#A3A3A3]">No tasks yet.</li>}
          {items.map((item) => {
            const isDone = item.status === 'done';
            return (
              <li key={item.id} className="group flex items-center gap-3 py-2.5">
                <button type="button" onClick={() => toggle(item)} className="shrink-0" aria-label={isDone ? 'Mark not done' : 'Mark done'}>
                  {isDone ? <CheckCircle2 className="h-5 w-5 text-[#10B981]" strokeWidth={2} /> : <Circle className="h-5 w-5 text-[#D4D4D4] hover:text-[#10B981]" strokeWidth={2} />}
                </button>
                <div className="min-w-0 flex-1">
                  <div className={['text-[13.5px]', isDone ? 'text-[#A3A3A3] line-through' : 'text-[#0A0A0A]'].join(' ')}>
                    {item.title}
                    {item.is_required && <span className="ml-1.5 text-[10px] font-semibold uppercase tracking-wide text-[#F59E0B]">Required</span>}
                  </div>
                  {isDone && item.completed_at_human && <div className="text-[11px] text-[#A3A3A3]">Done {item.completed_at_human}</div>}
                </div>
                <button type="button" onClick={() => remove(item)} className="shrink-0 rounded-md p-1.5 text-[#A3A3A3] opacity-0 transition-opacity hover:bg-[#FFF1F2] hover:text-[#F43F5E] group-hover:opacity-100" title="Remove">
                  <Trash2 className="h-3.5 w-3.5" strokeWidth={2} />
                </button>
              </li>
            );
          })}
        </ul>

        <div className="mt-4 flex items-center gap-2 border-t border-[#F0F0F0] pt-4">
          <input
            value={newTitle}
            onChange={(e) => setNewTitle(e.target.value)}
            onKeyDown={(e) => { if (e.key === 'Enter') add(); }}
            placeholder="Add a task…"
            className="flex-1 rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-[13px] text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
          />
          <Button type="button" disabled={busy || !newTitle.trim()} onClick={add} className="gap-1.5 bg-[#0A0A0A] text-white hover:bg-[#262626] disabled:opacity-50">
            <Plus className="h-3.5 w-3.5" strokeWidth={2.25} /> Add
          </Button>
        </div>
      </div>
    </div>
  );
}

function activityTypeTone(type) {
  return {
    coaching: 'bg-[#ECFDF5] text-[#047857]',
    meeting: 'bg-[#E0E7FF] text-[#4338CA]',
    training: 'bg-[#FEF3C7] text-[#B45309]',
    check_in: 'bg-[#F5F5F5] text-[#525252]',
    other: 'bg-[#F5F5F5] text-[#525252]',
  }[type] ?? 'bg-[#F5F5F5] text-[#525252]';
}

function CoachingTab({ mentee, activities }) {
  const [logging, setLogging] = useState(false);

  const remove = (a) => {
    if (!window.confirm('Remove this activity?')) return;
    router.delete(`/livehost/mentoring/activities/${a.id}`, { preserveScroll: true });
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2 text-[12px] text-[#737373]">
          <MessageSquare className="h-3.5 w-3.5 text-[#A3A3A3]" strokeWidth={2} />
          Coaching, meetings, and training logged for this mentee.
        </div>
        <Button size="sm" onClick={() => setLogging(true)} className="gap-1.5 bg-[#0A0A0A] text-white hover:bg-[#262626]">
          <Plus className="h-3.5 w-3.5" strokeWidth={2.25} />
          Log activity
        </Button>
      </div>

      {!activities || activities.length === 0 ? (
        <div className="grid place-items-center rounded-[16px] border border-dashed border-[#EAEAEA] bg-white px-8 py-16 text-center">
          <div className="text-sm text-[#737373]">No activities logged yet.</div>
        </div>
      ) : (
        <div className="overflow-hidden rounded-[16px] border border-[#EAEAEA] bg-white">
          <ul className="divide-y divide-[#F0F0F0]">
            {activities.map((a) => (
              <li key={a.id} className="group flex items-start justify-between gap-4 px-5 py-3.5">
                <div className="min-w-0">
                  <div className="flex items-center gap-2">
                    <span className={`inline-flex items-center rounded-md px-1.5 py-0.5 text-[10.5px] font-semibold uppercase tracking-wide ${activityTypeTone(a.type)}`}>
                      {a.type.replace('_', ' ')}
                    </span>
                    <span className="text-[13.5px] font-semibold text-[#0A0A0A]">{a.title}</span>
                  </div>
                  {a.notes && <div className="mt-1 whitespace-pre-wrap text-[13px] text-[#525252]">{a.notes}</div>}
                  <div className="mt-1 text-[11px] text-[#A3A3A3]">{a.occurred_at_human}{a.created_by ? ` · ${a.created_by}` : ''}</div>
                </div>
                <button type="button" onClick={() => remove(a)} className="shrink-0 rounded-md p-1.5 text-[#A3A3A3] opacity-0 transition-opacity hover:bg-[#FFF1F2] hover:text-[#F43F5E] group-hover:opacity-100" title="Remove">
                  <Trash2 className="h-3.5 w-3.5" strokeWidth={2} />
                </button>
              </li>
            ))}
          </ul>
        </div>
      )}

      {logging && (
        <ActivityLogModal programId={mentee.program.id} presetMenteeId={mentee.id} onClose={() => setLogging(false)} />
      )}
    </div>
  );
}

function ActionBar({ mentee, stages }) {
  const canManage = Boolean(usePage().props?.auth?.permissions?.canManageMentoring);
  const [moveMenuOpen, setMoveMenuOpen] = useState(false);
  const [dropOpen, setDropOpen] = useState(false);
  const [dropNotes, setDropNotes] = useState('');
  const [busy, setBusy] = useState(false);
  const moveMenuRef = useRef(null);

  useEffect(() => {
    const onClick = (e) => {
      if (moveMenuRef.current && !moveMenuRef.current.contains(e.target)) setMoveMenuOpen(false);
    };
    document.addEventListener('mousedown', onClick);
    return () => document.removeEventListener('mousedown', onClick);
  }, []);

  const orderedStages = useMemo(() => (stages ?? []).slice().sort((a, b) => Number(a.position) - Number(b.position)), [stages]);
  const currentIndex = orderedStages.findIndex((s) => s.id === mentee.current_stage_id);
  const nextStage = currentIndex >= 0 ? orderedStages[currentIndex + 1] : null;
  const isFinalStage = Boolean(mentee.current_stage?.is_final);
  const isActive = mentee.status === 'active';
  const isDropped = mentee.status === 'dropped';

  const moveTo = (stageId) => {
    if (!stageId || busy) return;
    setBusy(true);
    router.patch(`/livehost/mentoring/mentees/${mentee.id}/stage`, { to_stage_id: stageId }, {
      preserveScroll: true,
      onFinish: () => { setBusy(false); setMoveMenuOpen(false); },
    });
  };

  const drop = () => {
    setBusy(true);
    router.patch(`/livehost/mentoring/mentees/${mentee.id}/drop`, { notes: dropNotes || null }, {
      preserveScroll: true,
      onFinish: () => { setBusy(false); setDropOpen(false); setDropNotes(''); },
    });
  };

  const restore = () => {
    setBusy(true);
    router.patch(`/livehost/mentoring/mentees/${mentee.id}/restore`, {}, { preserveScroll: true, onFinish: () => setBusy(false) });
  };

  const graduate = () => {
    if (!window.confirm(`Graduate ${mentee.full_name}? They'll be marked eligible to become a top host.`)) return;
    setBusy(true);
    router.post(`/livehost/mentoring/mentees/${mentee.id}/graduate`, {}, { preserveScroll: true, onFinish: () => setBusy(false) });
  };

  if (mentee.status === 'graduated') {
    return (
      <div className="fixed bottom-0 left-0 right-0 z-40 border-t border-[#C7D2FE] bg-[#EEF2FF]/95 backdrop-blur">
        <div className="flex items-center gap-3 px-8 py-4">
          <div className="grid h-9 w-9 place-items-center rounded-full bg-[#4338CA] text-white"><GraduationCap className="h-5 w-5" strokeWidth={2.25} /></div>
          <div>
            <div className="text-[14px] font-semibold tracking-[-0.01em] text-[#3730A3]">Graduated — now eligible to lead a program</div>
            <div className="text-[12px] text-[#4338CA]">{mentee.full_name} completed the program. Assign them as a leader on a new program when ready.</div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <>
      <div className="fixed bottom-0 left-0 right-0 z-40 border-t border-[#EAEAEA] bg-white/95 backdrop-blur">
        <div className="flex items-center justify-between gap-3 px-8 py-3">
          <div className="text-[12px] text-[#737373]">
            {isActive ? (
              <>On <span className="font-semibold text-[#0A0A0A]">{mentee.current_stage?.name ?? '—'}</span>{nextStage && (<>{' · next is '}<span className="font-semibold text-[#0A0A0A]">{nextStage.name}</span></>)}</>
            ) : (
              <>Mentee is <span className="font-semibold text-[#0A0A0A]">{mentee.status}</span>.</>
            )}
          </div>

          <div className="flex items-center gap-2">
            {isDropped && (
              <Button type="button" disabled={busy || mentee.current_stage_id === null} onClick={restore} className="gap-1.5 bg-[#10B981] text-white hover:bg-[#059669] disabled:opacity-50">
                <RotateCcw className="h-3.5 w-3.5" strokeWidth={2.25} />
                Restore to {mentee.current_stage?.name ?? 'stage'}
              </Button>
            )}
            <Button type="button" disabled={!isActive || !nextStage || busy} onClick={() => nextStage && moveTo(nextStage.id)} className="gap-1.5 bg-[#0A0A0A] text-white hover:bg-[#262626] disabled:opacity-50">
              Move to next stage
              <ArrowRight className="h-3.5 w-3.5" strokeWidth={2.25} />
            </Button>

            <div className="relative" ref={moveMenuRef}>
              <Button type="button" variant="outline" disabled={!isActive || busy} onClick={() => setMoveMenuOpen((v) => !v)} className="gap-1.5 shadow-none focus-visible:border-[#EAEAEA] focus-visible:ring-2 focus-visible:ring-[#10B981]/20">
                Move to…
                <ChevronDown className="h-3.5 w-3.5" />
              </Button>
              {moveMenuOpen && (
                <div className="absolute bottom-full right-0 mb-1.5 w-[220px] overflow-hidden rounded-lg border border-[#EAEAEA] bg-white shadow-[0_10px_30px_rgba(0,0,0,0.1)]">
                  <div className="max-h-[280px] overflow-y-auto p-1">
                    {orderedStages.map((stage) => {
                      const isCurrent = stage.id === mentee.current_stage_id;
                      return (
                        <button key={stage.id} type="button" onClick={() => moveTo(stage.id)} disabled={isCurrent} className={['flex w-full items-center justify-between rounded-md px-2.5 py-2 text-left text-[13px]', isCurrent ? 'cursor-default bg-[#F5F5F5] text-[#737373]' : 'text-[#0A0A0A] hover:bg-[#F5F5F5]'].join(' ')}>
                          <span>{stage.name}</span>
                          {isCurrent && <Check className="h-3.5 w-3.5 text-[#10B981]" strokeWidth={3} />}
                          {stage.is_final && !isCurrent && <span className="rounded-full bg-[#ECFDF5] px-1.5 py-0.5 text-[9.5px] font-medium uppercase tracking-wide text-[#047857]">Final</span>}
                        </button>
                      );
                    })}
                  </div>
                </div>
              )}
            </div>

            <Button type="button" variant="outline" disabled={!isActive || busy} onClick={() => setDropOpen(true)} className="gap-1.5 border-[#F43F5E] text-[#F43F5E] hover:bg-[#FFF1F2]">
              <XCircle className="h-3.5 w-3.5" />
              Drop
            </Button>

            {canManage && (
              <Button type="button" disabled={!isActive || !isFinalStage || busy} onClick={graduate} className="gap-1.5 bg-[#4338CA] text-white hover:bg-[#3730A3] disabled:bg-[#4338CA]/40 disabled:text-white/80" title={isFinalStage ? 'Graduate this mentee' : 'Move the mentee to the final stage before graduating'}>
                <GraduationCap className="h-3.5 w-3.5" />
                Graduate
              </Button>
            )}
          </div>
        </div>
      </div>

      {dropOpen && (
        <div className="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4">
          <div className="w-full max-w-md rounded-[16px] bg-white p-6 shadow-lg">
            <div className="mb-1 flex items-center gap-2 text-[18px] font-semibold tracking-[-0.015em] text-[#0A0A0A]">
              <Ban className="h-4 w-4 text-[#F43F5E]" strokeWidth={2.25} />
              Drop {mentee.full_name}?
            </div>
            <p className="mb-4 text-[13px] text-[#737373]">They'll leave the active board. You can restore them later. Optionally leave a reason.</p>
            <textarea value={dropNotes} onChange={(e) => setDropNotes(e.target.value)} rows={4} placeholder="Reason (optional)" className="mb-4 w-full resize-none rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-[13.5px] text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#F43F5E]/25" />
            <div className="flex justify-end gap-2">
              <Button type="button" variant="ghost" disabled={busy} onClick={() => { setDropOpen(false); setDropNotes(''); }}>Cancel</Button>
              <Button type="button" disabled={busy} onClick={drop} className="bg-[#F43F5E] text-white hover:bg-[#E11D48]">{busy ? 'Dropping…' : 'Drop mentee'}</Button>
            </div>
          </div>
        </div>
      )}
    </>
  );
}
