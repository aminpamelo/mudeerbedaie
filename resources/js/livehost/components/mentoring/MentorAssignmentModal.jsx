import { Link, router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  ArrowRight,
  BarChart3,
  Check,
  CheckCircle2,
  Circle,
  Clock,
  ListChecks,
  Loader2,
  MessageSquare,
  Phone,
  Plus,
  Trash2,
  X,
  XCircle,
} from 'lucide-react';
import { Button } from '@/livehost/components/ui/button';

function WhatsAppIcon({ className }) {
  return (
    <svg className={className} viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
      <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.872.118.571-.085 1.758-.719 2.006-1.413.247-.694.247-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
    </svg>
  );
}

const ACTIVITY_TYPES = [
  { value: 'coaching', label: 'Coaching' },
  { value: 'meeting', label: 'Meeting' },
  { value: 'training', label: 'Training' },
  { value: 'check_in', label: 'Check-in' },
  { value: 'other', label: 'Other' },
];

function activityTypeTone(type) {
  return {
    coaching: 'bg-[#ECFDF5] text-[#047857]',
    meeting: 'bg-[#E0E7FF] text-[#4338CA]',
    training: 'bg-[#FEF3C7] text-[#B45309]',
    check_in: 'bg-[#F5F5F5] text-[#525252]',
    other: 'bg-[#F5F5F5] text-[#525252]',
  }[type] ?? 'bg-[#F5F5F5] text-[#525252]';
}

function toWhatsAppNumber(phone) {
  if (!phone) return '';
  let digits = String(phone).replace(/\D/g, '');
  if (digits.startsWith('00')) digits = digits.slice(2);
  return digits;
}

function toLocalDateTimeInput(iso) {
  if (!iso) return '';
  const d = new Date(iso);
  if (isNaN(d.getTime())) return '';
  const pad = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function fromLocalDateTimeInput(value) {
  if (!value) return null;
  const d = new Date(value);
  if (isNaN(d.getTime())) return null;
  return d.toISOString();
}

/**
 * Mentee "hub" modal opened from the kanban board. The Overview tab is the
 * original quick-edit (mentor / due / stage notes / move / drop); the other tabs
 * surface the mentee's activity log, stage checklist, stage history and KPI
 * snapshot, controllable inline. The rich data is fetched on open from
 * `mentees/{id}/detail` so the board payload stays light; every write re-fetches
 * it. `reloadOnly` scopes the board's own Inertia refresh when embedded.
 */
export default function MentorAssignmentModal({ mentee, stages, assignableMentors = [], programLeader = null, program = null, reloadOnly = null, onClose }) {
  const initial = mentee.assignment ?? {};
  const [tab, setTab] = useState('overview');
  const [mentorId, setMentorId] = useState(mentee.mentor_user_id ? String(mentee.mentor_user_id) : '');
  const [dueAt, setDueAt] = useState(toLocalDateTimeInput(initial.due_at));
  const [stageNotes, setStageNotes] = useState(initial.stage_notes ?? '');
  const [saveState, setSaveState] = useState('idle');

  const [detail, setDetail] = useState(null);
  const [loadingDetail, setLoadingDetail] = useState(true);

  const orderedStages = useMemo(
    () => (stages ?? []).slice().sort((a, b) => Number(a.position) - Number(b.position)),
    [stages],
  );
  const currentIndex = orderedStages.findIndex((s) => s.id === mentee.current_stage_id);
  const nextStage = currentIndex >= 0 ? orderedStages[currentIndex + 1] : null;

  const refreshDetail = useCallback(() => {
    fetch(`/livehost/mentoring/mentees/${mentee.id}/detail`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
      .then((r) => (r.ok ? r.json() : null))
      .then((data) => { if (data) setDetail(data); })
      .catch(() => {})
      .finally(() => setLoadingDetail(false));
  }, [mentee.id]);

  useEffect(() => { refreshDetail(); }, [refreshDetail]);

  useEffect(() => {
    const onKey = (e) => { if (e.key === 'Escape') onClose(); };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [onClose]);

  const programId = detail?.program_id ?? program?.id ?? null;

  const save = ({ thenClose = false } = {}) => {
    setSaveState('saving');
    router.patch(
      `/livehost/mentoring/mentees/${mentee.id}/current-stage`,
      { mentor_user_id: mentorId ? Number(mentorId) : null, due_at: fromLocalDateTimeInput(dueAt), stage_notes: stageNotes || null },
      {
        preserveScroll: true,
        preserveState: true,
        only: reloadOnly ?? ['mentees'],
        onSuccess: () => { setSaveState('saved'); setTimeout(() => setSaveState('idle'), 1500); if (thenClose) onClose(); },
        onError: () => setSaveState('error'),
      },
    );
  };

  const moveTo = (stageId) => {
    router.patch(`/livehost/mentoring/mentees/${mentee.id}/stage`, { to_stage_id: stageId }, { preserveScroll: true, onSuccess: () => onClose() });
  };

  const drop = () => {
    if (!window.confirm(`Drop ${mentee.full_name} from this program?`)) return;
    router.patch(`/livehost/mentoring/mentees/${mentee.id}/drop`, { notes: null }, { preserveScroll: true, onSuccess: () => onClose() });
  };

  const overdue = mentee.assignment?.is_overdue;
  const leaderHint = programLeader ? `Defaults to leader ${programLeader.name}` : 'No program leader set';

  const checklistDone = (detail?.checklist ?? []).filter((c) => c.status === 'done').length;
  const checklistTotal = (detail?.checklist ?? []).length;

  const tabs = [
    { id: 'overview', label: 'Overview' },
    { id: 'activity', label: `Activity${detail ? ` · ${detail.activities.length}` : ''}` },
    { id: 'checklist', label: `Checklist${detail ? ` · ${checklistDone}/${checklistTotal}` : ''}` },
    { id: 'history', label: 'History' },
    { id: 'performance', label: 'Performance' },
  ];

  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4" onMouseDown={(e) => { if (e.target === e.currentTarget) onClose(); }}>
      <div className="flex max-h-[88vh] w-full max-w-2xl flex-col overflow-hidden rounded-[16px] bg-white shadow-[0_20px_60px_rgba(0,0,0,0.18)]">
        {/* Header */}
        <div className="flex items-start justify-between gap-3 px-6 pt-6">
          <div className="min-w-0">
            <div className="font-mono text-[11px] text-[#737373]">{mentee.mentee_number}</div>
            <div className="mt-0.5 truncate text-[18px] font-semibold tracking-[-0.02em] text-[#0A0A0A]">{mentee.full_name}</div>
            <div className="mt-1 flex flex-wrap items-center gap-1.5">
              <span className="inline-flex items-center gap-1.5 rounded-md bg-[#F5F5F5] px-1.5 py-0.5 text-[11px] font-medium text-[#525252]">
                {orderedStages.find((s) => s.id === mentee.current_stage_id)?.name ?? 'Unassigned'}
              </span>
              {mentee.level && (
                <span className="inline-flex items-center rounded-md px-1.5 py-0.5 text-[11px] font-medium" style={{ backgroundColor: `${mentee.level.color}22`, color: mentee.level.color }}>
                  {mentee.level.name}
                </span>
              )}
              {overdue && <span className="inline-flex items-center rounded bg-[#FEE2E2] px-1 py-0.5 text-[9.5px] font-semibold uppercase tracking-wide text-[#B91C1C]">Overdue</span>}
            </div>
          </div>
          <button type="button" onClick={onClose} className="rounded-md p-1 text-[#737373] hover:bg-[#F5F5F5] hover:text-[#0A0A0A]" aria-label="Close">
            <X className="h-4 w-4" strokeWidth={2} />
          </button>
        </div>

        {/* Tabs */}
        <div className="mt-4 flex gap-1 overflow-x-auto border-b border-[#F0F0F0] px-6">
          {tabs.map((t) => (
            <button
              key={t.id}
              type="button"
              onClick={() => setTab(t.id)}
              className={['whitespace-nowrap border-b-2 px-2.5 py-2 text-[12.5px] font-medium transition-colors', tab === t.id ? 'border-[#0A0A0A] text-[#0A0A0A]' : 'border-transparent text-[#737373] hover:text-[#404040]'].join(' ')}
            >
              {t.label}
            </button>
          ))}
        </div>

        {/* Body */}
        <div className="flex-1 overflow-y-auto px-6 py-5">
          {tab === 'overview' && (
            <OverviewTab
              mentee={mentee}
              mentorId={mentorId}
              setMentorId={setMentorId}
              dueAt={dueAt}
              setDueAt={setDueAt}
              stageNotes={stageNotes}
              setStageNotes={setStageNotes}
              assignableMentors={assignableMentors}
              leaderHint={leaderHint}
              saveState={saveState}
            />
          )}
          {tab === 'activity' && <ActivityTab detail={detail} loading={loadingDetail} menteeId={mentee.id} programId={programId} onChanged={refreshDetail} />}
          {tab === 'checklist' && <ChecklistTab detail={detail} loading={loadingDetail} menteeId={mentee.id} onChanged={refreshDetail} />}
          {tab === 'history' && <HistoryTab detail={detail} loading={loadingDetail} />}
          {tab === 'performance' && <PerformanceTab detail={detail} loading={loadingDetail} />}
        </div>

        {/* Footer */}
        <div className="flex flex-wrap items-center justify-between gap-2 border-t border-[#F0F0F0] px-6 py-4">
          <Link href={`/livehost/mentoring/mentees/${mentee.id}`} className="text-[12.5px] font-medium text-[#0A0A0A] underline-offset-2 hover:underline">
            Open full profile
          </Link>
          <div className="flex items-center gap-2">
            <button type="button" onClick={drop} className="inline-flex items-center gap-1 rounded-md border border-[#FCA5A5] bg-white px-2.5 py-1.5 text-[12.5px] font-medium text-[#B91C1C] hover:bg-[#FEF2F2]">
              <XCircle className="h-3.5 w-3.5" strokeWidth={2} />
              Drop
            </button>
            <button type="button" onClick={() => save({ thenClose: true })} disabled={saveState === 'saving'} className="inline-flex items-center gap-1 rounded-md border border-[#EAEAEA] bg-white px-2.5 py-1.5 text-[12.5px] font-medium text-[#0A0A0A] hover:bg-[#F5F5F5] disabled:opacity-60">
              {saveState === 'saving' ? <Loader2 className="h-3.5 w-3.5 animate-spin" strokeWidth={2} /> : <Check className="h-3.5 w-3.5" strokeWidth={2.25} />}
              Save
            </button>
            {nextStage && (
              <button type="button" onClick={() => moveTo(nextStage.id)} className="inline-flex items-center gap-1 rounded-md bg-[#0A0A0A] px-2.5 py-1.5 text-[12.5px] font-medium text-white hover:bg-[#262626]">
                Move to {nextStage.name}
                <ArrowRight className="h-3.5 w-3.5" strokeWidth={2.25} />
              </button>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}

function OverviewTab({ mentee, mentorId, setMentorId, dueAt, setDueAt, stageNotes, setStageNotes, assignableMentors, leaderHint, saveState }) {
  return (
    <div className="space-y-4">
      {mentee.phone && (
        <div className="flex items-center justify-between gap-2 rounded-lg border border-[#EAEAEA] bg-[#FAFAFA] px-3 py-2">
          <div className="min-w-0">
            <div className="text-[10.5px] font-medium uppercase tracking-wide text-[#A3A3A3]">Phone</div>
            <div className="truncate font-mono text-[13px] text-[#0A0A0A]">{mentee.phone}</div>
          </div>
          <div className="flex shrink-0 items-center gap-1.5">
            <a href={`https://wa.me/${toWhatsAppNumber(mentee.phone)}`} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-1 rounded-md bg-[#25D366] px-2.5 py-1.5 text-[12px] font-medium text-white hover:bg-[#1FB855]">
              <WhatsAppIcon className="h-3.5 w-3.5" />
              WhatsApp
            </a>
            <a href={`tel:${String(mentee.phone).replace(/[^\d+]/g, '')}`} className="inline-flex items-center gap-1 rounded-md border border-[#EAEAEA] bg-white px-2.5 py-1.5 text-[12px] font-medium text-[#0A0A0A] hover:bg-[#F5F5F5]">
              <Phone className="h-3.5 w-3.5" strokeWidth={2} />
              Call
            </a>
          </div>
        </div>
      )}

      <div>
        <label htmlFor="mentor-assign" className="mb-1 block text-[11.5px] font-medium uppercase tracking-wide text-[#737373]">Mentor</label>
        <select id="mentor-assign" value={mentorId} onChange={(e) => setMentorId(e.target.value)} className="w-full rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-[13px] text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20">
          <option value="">— Use program leader —</option>
          {assignableMentors.map((u) => (
            <option key={u.id} value={u.id}>{u.name}{u.is_top_host_eligible ? ' ★' : ''}</option>
          ))}
        </select>
        <div className="mt-1 text-[11px] text-[#A3A3A3]">{leaderHint}</div>
      </div>

      <div>
        <label htmlFor="mentor-due" className="mb-1 block text-[11.5px] font-medium uppercase tracking-wide text-[#737373]">Due</label>
        <input id="mentor-due" type="datetime-local" value={dueAt} onChange={(e) => setDueAt(e.target.value)} className="w-full rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-[13px] text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20" />
      </div>

      <div>
        <label htmlFor="mentor-notes" className="mb-1 block text-[11.5px] font-medium uppercase tracking-wide text-[#737373]">Stage notes</label>
        <textarea id="mentor-notes" rows={3} value={stageNotes} onChange={(e) => setStageNotes(e.target.value)} placeholder="Notes scoped to this stage…" className="w-full resize-y rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-[13px] text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20" />
        <div className="mt-1 text-[11px] text-[#A3A3A3]">
          {saveState === 'saving' && <span className="inline-flex items-center gap-1"><Loader2 className="h-3 w-3 animate-spin" /> Saving…</span>}
          {saveState === 'saved' && <span className="inline-flex items-center gap-1 text-[#047857]"><Check className="h-3 w-3" strokeWidth={3} /> Saved</span>}
          {saveState === 'error' && <span className="text-[#B91C1C]">Failed to save</span>}
          {saveState === 'idle' && 'Click Save to persist mentor, due date and stage notes.'}
        </div>
      </div>
    </div>
  );
}

function TabLoading() {
  return <div className="grid place-items-center py-12 text-[#A3A3A3]"><Loader2 className="h-5 w-5 animate-spin" /></div>;
}

function nowLocalInput() {
  const d = new Date();
  const pad = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function ActivityTab({ detail, loading, menteeId, programId, onChanged }) {
  const [type, setType] = useState('coaching');
  const [title, setTitle] = useState('');
  const [notes, setNotes] = useState('');
  const [occurredAt, setOccurredAt] = useState(nowLocalInput());
  const [busy, setBusy] = useState(false);

  if (loading && !detail) return <TabLoading />;
  const activities = detail?.activities ?? [];

  const add = () => {
    if (!title.trim() || !programId) return;
    setBusy(true);
    router.post(
      `/livehost/mentoring/programs/${programId}/activities`,
      { type, title, notes: notes || null, occurred_at: fromLocalDateTimeInput(occurredAt), mentee_id: menteeId },
      {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => { setTitle(''); setNotes(''); setType('coaching'); setOccurredAt(nowLocalInput()); },
        onFinish: () => { setBusy(false); onChanged(); },
      },
    );
  };

  const remove = (a) => {
    if (!window.confirm('Remove this activity?')) return;
    router.delete(`/livehost/mentoring/activities/${a.id}`, { preserveScroll: true, preserveState: true, onFinish: onChanged });
  };

  return (
    <div className="space-y-4">
      {/* Inline log form */}
      <div className="rounded-[12px] border border-[#EAEAEA] bg-[#FAFAFA] p-3">
        <div className="mb-2 flex items-center gap-1.5 text-[12px] font-semibold text-[#0A0A0A]"><Plus className="h-3.5 w-3.5 text-[#10B981]" strokeWidth={2.5} /> Log activity</div>
        <div className="grid grid-cols-2 gap-2">
          <select value={type} onChange={(e) => setType(e.target.value)} className="h-9 rounded-lg border border-[#EAEAEA] bg-white px-2.5 text-[13px] text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20">
            {ACTIVITY_TYPES.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
          </select>
          <input type="datetime-local" value={occurredAt} onChange={(e) => setOccurredAt(e.target.value)} className="h-9 rounded-lg border border-[#EAEAEA] bg-white px-2.5 text-[13px] text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20" />
        </div>
        <input value={title} onChange={(e) => setTitle(e.target.value)} placeholder="e.g. 1:1 coaching on hook openings" className="mt-2 w-full rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-[13px] text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20" />
        <textarea value={notes} onChange={(e) => setNotes(e.target.value)} rows={2} placeholder="Notes (optional)…" className="mt-2 w-full resize-y rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-[13px] text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20" />
        <div className="mt-2 flex justify-end">
          <Button type="button" size="sm" disabled={busy || !title.trim() || !programId} onClick={add} className="gap-1.5 bg-[#0A0A0A] text-white hover:bg-[#262626] disabled:opacity-50">
            {busy ? 'Logging…' : 'Log activity'}
          </Button>
        </div>
      </div>

      {activities.length === 0 ? (
        <div className="grid place-items-center rounded-[12px] border border-dashed border-[#EAEAEA] py-10 text-center text-[13px] text-[#A3A3A3]">
          <MessageSquare className="mb-2 h-6 w-6 text-[#D4D4D4]" strokeWidth={1.5} />
          No activities logged yet.
        </div>
      ) : (
        <ul className="divide-y divide-[#F0F0F0] overflow-hidden rounded-[12px] border border-[#EAEAEA]">
          {activities.map((a) => (
            <li key={a.id} className="group flex items-start justify-between gap-4 px-4 py-3">
              <div className="min-w-0">
                <div className="flex items-center gap-2">
                  <span className={`inline-flex items-center rounded-md px-1.5 py-0.5 text-[10.5px] font-semibold uppercase tracking-wide ${activityTypeTone(a.type)}`}>{a.type.replace('_', ' ')}</span>
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
      )}
    </div>
  );
}

function ChecklistTab({ detail, loading, menteeId, onChanged }) {
  if (loading && !detail) return <TabLoading />;
  const items = detail?.checklist ?? [];
  const total = items.length;
  const done = items.filter((c) => c.status === 'done').length;
  const pct = total ? Math.round((done / total) * 100) : 0;

  const programTasks = items.filter((c) => c.source !== 'custom');
  const individualTasks = items.filter((c) => c.source === 'custom');

  const toggle = (item) => router.patch(`/livehost/mentoring/mentees/${menteeId}/checklist/${item.id}/toggle`, {}, { preserveScroll: true, preserveState: true, onFinish: onChanged });
  const remove = (item) => { if (window.confirm('Remove this task?')) router.delete(`/livehost/mentoring/mentees/${menteeId}/checklist/${item.id}`, { preserveScroll: true, preserveState: true, onFinish: onChanged }); };

  return (
    <div className="space-y-4">
      {/* Overall progress */}
      <div className="rounded-[12px] border border-[#EAEAEA] bg-white p-4">
        <div className="mb-3 flex items-center justify-between">
          <div className="flex items-center gap-2 text-[13px] font-semibold text-[#0A0A0A]"><ListChecks className="h-4 w-4 text-[#10B981]" strokeWidth={2.25} /> {done} of {total} done</div>
          <span className="text-[12px] font-medium tabular-nums text-[#737373]">{pct}%</span>
        </div>
        <div className="h-2 overflow-hidden rounded-full bg-[#F0F0F0]"><div className="h-full rounded-full bg-[#10B981] transition-all" style={{ width: `${pct}%` }} /></div>
      </div>

      {/* Program tasks (from the template) */}
      <div className="rounded-[12px] border border-[#EAEAEA] bg-white p-4">
        <div className="flex items-baseline gap-2 text-[12px] font-semibold uppercase tracking-wide text-[#737373]">
          Program tasks <span className="text-[#A3A3A3]">· {programTasks.length}</span>
        </div>
        <p className="mb-1 text-[11px] text-[#A3A3A3]">Standard tasks copied from the program checklist template.</p>
        {programTasks.length === 0 ? (
          <div className="py-4 text-center text-[12.5px] text-[#A3A3A3]">No program tasks.</div>
        ) : (
          <ul className="divide-y divide-[#F0F0F0]">
            {programTasks.map((item) => <ChecklistRow key={item.id} item={item} onToggle={toggle} onRemove={remove} />)}
          </ul>
        )}
      </div>

      {/* Individual tasks (for this mentee only) */}
      <div className="rounded-[12px] border border-[#10B981]/30 bg-[#ECFDF5]/40 p-4">
        <div className="flex items-baseline gap-2 text-[12px] font-semibold uppercase tracking-wide text-[#047857]">
          Individual tasks <span className="text-[#10B981]/70">· {individualTasks.length}</span>
        </div>
        <p className="mb-1 text-[11px] text-[#737373]">Personalised by the mentor for this mentee only.</p>
        {individualTasks.length > 0 && (
          <ul className="divide-y divide-[#10B981]/15">
            {individualTasks.map((item) => <ChecklistRow key={item.id} item={item} onToggle={toggle} onRemove={remove} />)}
          </ul>
        )}
        <AddIndividualTask menteeId={menteeId} onChanged={onChanged} hasItems={individualTasks.length > 0} />
      </div>
    </div>
  );
}

function ChecklistRow({ item, onToggle, onRemove }) {
  const isDone = item.status === 'done';
  return (
    <li className="group flex items-start gap-3 py-2.5">
      <button type="button" onClick={() => onToggle(item)} className="mt-0.5 shrink-0" aria-label={isDone ? 'Mark not done' : 'Mark done'}>
        {isDone ? <CheckCircle2 className="h-5 w-5 text-[#10B981]" strokeWidth={2} /> : <Circle className="h-5 w-5 text-[#D4D4D4] hover:text-[#10B981]" strokeWidth={2} />}
      </button>
      <div className="min-w-0 flex-1">
        <div className={['flex flex-wrap items-center gap-1.5 text-[13.5px]', isDone ? 'text-[#A3A3A3] line-through' : 'text-[#0A0A0A]'].join(' ')}>
          <span>{item.title}</span>
          {item.is_required && <span className="rounded bg-[#FEF3C7] px-1 py-0.5 text-[9.5px] font-semibold uppercase tracking-wide text-[#B45309]">Required</span>}
          {!isDone && item.due_at_human && (
            <span className={['inline-flex items-center rounded px-1 py-0.5 text-[10px] font-medium', item.is_overdue ? 'bg-[#FEE2E2] text-[#B91C1C]' : 'bg-[#F5F5F5] text-[#525252]'].join(' ')}>
              {item.is_overdue ? 'Overdue' : 'Due'} {item.due_at_human}
            </span>
          )}
        </div>
        {item.description && <div className="mt-0.5 text-[11.5px] text-[#737373]">{item.description}</div>}
        {isDone && item.completed_at_human && <div className="mt-0.5 text-[11px] text-[#A3A3A3]">Done {item.completed_at_human}</div>}
      </div>
      <button type="button" onClick={() => onRemove(item)} className="shrink-0 rounded-md p-1.5 text-[#A3A3A3] opacity-0 transition-opacity hover:bg-[#FFF1F2] hover:text-[#F43F5E] group-hover:opacity-100" title="Remove">
        <Trash2 className="h-3.5 w-3.5" strokeWidth={2} />
      </button>
    </li>
  );
}

function AddIndividualTask({ menteeId, onChanged, hasItems }) {
  const [title, setTitle] = useState('');
  const [dueAt, setDueAt] = useState('');
  const [note, setNote] = useState('');
  const [required, setRequired] = useState(false);
  const [busy, setBusy] = useState(false);

  const add = () => {
    if (!title.trim()) return;
    setBusy(true);
    router.post(
      `/livehost/mentoring/mentees/${menteeId}/checklist`,
      { title, description: note || null, is_required: required, due_at: dueAt || null },
      {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => { setTitle(''); setDueAt(''); setNote(''); setRequired(false); },
        onFinish: () => { setBusy(false); onChanged(); },
      },
    );
  };

  return (
    <div className={['rounded-lg border border-[#10B981]/25 bg-white p-3', hasItems ? 'mt-3' : 'mt-2'].join(' ')}>
      <div className="mb-2 text-[11px] font-semibold uppercase tracking-wide text-[#047857]">Add individual task</div>
      <input value={title} onChange={(e) => setTitle(e.target.value)} onKeyDown={(e) => { if (e.key === 'Enter') add(); }} placeholder="Task title…" className="w-full rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-[13px] text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20" />
      <div className="mt-2 grid grid-cols-2 gap-2">
        <div>
          <label className="mb-0.5 block text-[10.5px] font-medium uppercase tracking-wide text-[#A3A3A3]">Due (optional)</label>
          <input type="date" value={dueAt} onChange={(e) => setDueAt(e.target.value)} className="w-full rounded-lg border border-[#EAEAEA] bg-white px-2.5 py-1.5 text-[12.5px] text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20" />
        </div>
        <label className="flex cursor-pointer items-center gap-2 self-end pb-1.5 text-[12.5px] text-[#0A0A0A]">
          <input type="checkbox" checked={required} onChange={(e) => setRequired(e.target.checked)} className="h-4 w-4 accent-[#10B981]" />
          Required
        </label>
      </div>
      <input value={note} onChange={(e) => setNote(e.target.value)} placeholder="Note (optional)…" className="mt-2 w-full rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-[12.5px] text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20" />
      <div className="mt-2 flex justify-end">
        <Button type="button" size="sm" disabled={busy || !title.trim()} onClick={add} className="gap-1.5 bg-[#0A0A0A] text-white hover:bg-[#262626] disabled:opacity-50">
          <Plus className="h-3.5 w-3.5" strokeWidth={2.25} /> Add task
        </Button>
      </div>
    </div>
  );
}

function HistoryTab({ detail, loading }) {
  if (loading && !detail) return <TabLoading />;
  const history = detail?.history ?? [];

  if (history.length === 0) {
    return <div className="grid place-items-center rounded-[12px] border border-dashed border-[#EAEAEA] py-10 text-center text-[13px] text-[#A3A3A3]"><Clock className="mb-2 h-6 w-6 text-[#D4D4D4]" strokeWidth={1.5} /> No history yet.</div>;
  }

  return (
    <ol className="relative space-y-0 border-l border-[#EAEAEA] pl-4">
      {history.map((h) => (
        <li key={h.id} className="relative pb-4 last:pb-0">
          <span className="absolute -left-[21px] top-1 h-2.5 w-2.5 rounded-full border-2 border-white bg-[#10B981]" />
          <div className="text-[13px] text-[#0A0A0A]">
            {h.from_stage && h.to_stage ? (
              <span><span className="text-[#737373]">{h.from_stage.name}</span> → <span className="font-semibold">{h.to_stage.name}</span></span>
            ) : h.to_stage ? (
              <span className="font-semibold">{h.to_stage.name}</span>
            ) : (
              <span className="font-semibold capitalize">{(h.action ?? 'updated').replace('_', ' ')}</span>
            )}
          </div>
          {h.notes && <div className="mt-0.5 text-[12px] text-[#525252]">{h.notes}</div>}
          <div className="mt-0.5 text-[11px] text-[#A3A3A3]">{h.created_at_human}{h.changed_by ? ` · ${h.changed_by.name}` : ''}</div>
        </li>
      ))}
    </ol>
  );
}

function KpiTile({ label, value, sub }) {
  return (
    <div className="rounded-[12px] border border-[#EAEAEA] bg-white p-3">
      <div className="text-[10.5px] font-medium uppercase tracking-wide text-[#A3A3A3]">{label}</div>
      <div className="mt-0.5 text-[18px] font-semibold tabular-nums tracking-[-0.02em] text-[#0A0A0A]">{value}</div>
      {sub && <div className="text-[11px] text-[#737373]">{sub}</div>}
    </div>
  );
}

function PerformanceTab({ detail, loading }) {
  if (loading && !detail) return <TabLoading />;
  const k = detail?.kpis ?? null;
  const scores = detail?.monthlyScores ?? [];
  if (!k) return <div className="py-10 text-center text-[13px] text-[#A3A3A3]">No performance data.</div>;

  return (
    <div className="space-y-5">
      <div className="space-y-3">
        <div className="flex items-center gap-2 text-[12px] text-[#737373]"><BarChart3 className="h-3.5 w-3.5 text-[#A3A3A3]" strokeWidth={2} /> Live activity · last 30 days ({k.from} → {k.to})</div>
        <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
          <KpiTile label="Sessions" value={k.sessions} sub={`${k.ended} ended`} />
          <KpiTile label="Attendance" value={`${k.attendancePct}%`} sub={`${k.noShows} no-show`} />
          <KpiTile label="Live hours" value={k.hours} />
          <KpiTile label="GMV" value={`RM ${Number(k.gmv).toLocaleString()}`} />
          <KpiTile label="GMV / hour" value={`RM ${Number(k.avgGmvPerHour).toLocaleString()}`} />
        </div>
      </div>

      <div className="space-y-2">
        <div className="text-[11px] font-semibold uppercase tracking-wide text-[#737373]">Monthly scores</div>
        {scores.length === 0 ? (
          <div className="rounded-[12px] border border-dashed border-[#EAEAEA] py-6 text-center text-[12.5px] text-[#A3A3A3]">No monthly scores recorded yet.</div>
        ) : (
          <ul className="divide-y divide-[#F0F0F0] overflow-hidden rounded-[12px] border border-[#EAEAEA]">
            {scores.map((s) => (
              <li key={s.id} className="flex items-center justify-between gap-3 px-3.5 py-2.5">
                <div className="min-w-0">
                  <div className="text-[13px] font-semibold text-[#0A0A0A]">{s.period}</div>
                  {s.notes && <div className="truncate text-[11.5px] text-[#737373]">{s.notes}</div>}
                </div>
                <div className="flex shrink-0 items-center gap-4 text-right">
                  <div>
                    <div className="text-[10px] uppercase tracking-wide text-[#A3A3A3]">Attitude</div>
                    <div className="text-[13px] font-semibold tabular-nums text-[#0A0A0A]">{s.attitude_score != null ? `${s.attitude_score}/100` : '—'}</div>
                  </div>
                  <div>
                    <div className="text-[10px] uppercase tracking-wide text-[#A3A3A3]">Sales</div>
                    <div className="text-[13px] font-semibold tabular-nums text-[#0A0A0A]">{s.sales_quantity ?? '—'}</div>
                  </div>
                </div>
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  );
}
