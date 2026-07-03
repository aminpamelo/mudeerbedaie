import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Fragment, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  ArrowDown,
  ArrowLeft,
  ArrowUp,
  Calendar,
  Check,
  ChevronDown,
  ChevronRight,
  Flag,
  Gauge,
  Layers,
  ListChecks,
  MessageSquare,
  Pause,
  Pencil,
  Play,
  Plus,
  Search,
  Settings2,
  Star,
  Trash2,
  Undo2,
  UserCircle2,
  Users,
  X,
} from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import { Button } from '@/livehost/components/ui/button';
import { Input } from '@/livehost/components/ui/input';
import { Label } from '@/livehost/components/ui/label';
import ActivityLogModal from '@/livehost/components/mentoring/ActivityLogModal';
import MenteeBoard from '@/livehost/components/mentoring/MenteeBoard';
import MonthlyPerformanceTab from '@/livehost/components/mentoring/MonthlyPerformanceTab';

const ACTIVITY_COLOR = { green: '#10B981', amber: '#F59E0B', red: '#F43F5E', none: '#D4D4D4' };

// Overrides the shared Input component to the lighter Live Host field style
// (#EAEAEA border + soft green focus ring) used by the select/textarea fields,
// so every field on the form shares one consistent outline.
const FIELD_INPUT_CLASS =
  'h-10 rounded-lg border-[#EAEAEA] bg-white shadow-none focus-visible:border-[#EAEAEA] focus-visible:ring-2 focus-visible:ring-[#10B981]/20';

const TABS = [
  { id: 'details', label: 'Details', icon: Settings2 },
  { id: 'board', label: 'Mentee board', icon: Users },
  { id: 'stages', label: 'Stages', icon: Layers },
  { id: 'checklist', label: 'Checklist', icon: ListChecks },
  { id: 'activity', label: 'Activity', icon: MessageSquare },
  { id: 'performance', label: 'Monthly Performance', icon: Gauge },
];

const TAB_IDS = TABS.map((tab) => tab.id);

/** Read the active tab from the URL (?tab=) so it survives a reload or a shared link. */
function initialTabFromUrl() {
  if (typeof window === 'undefined') return 'details';
  const tab = new URLSearchParams(window.location.search).get('tab');
  return TAB_IDS.includes(tab) ? tab : 'details';
}

const STATUS_THEME = {
  draft: { pill: 'bg-[#F5F5F5] text-[#525252] border-[#EAEAEA]', dot: '#A3A3A3', live: false },
  active: { pill: 'bg-[#ECFDF5] text-[#059669] border-[#A7F3D0]', dot: '#10B981', live: true },
  paused: { pill: 'bg-[#FFFBEB] text-[#B45309] border-[#FDE68A]', dot: '#F59E0B', live: false },
  completed: { pill: 'bg-[#EEF2FF] text-[#4338CA] border-[#C7D2FE]', dot: '#6366F1', live: false },
};

function toDateInput(iso) {
  if (!iso) return '';
  try {
    const d = new Date(iso);
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  } catch {
    return '';
  }
}

function formatDateLabel(iso) {
  if (!iso) return null;
  try {
    return new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
  } catch {
    return null;
  }
}

export default function ProgramEdit() {
  const { program, stages, assignableLeaders, activities, activityIndicator, mentees, performance, board } = usePage().props;
  const [activeTab, setActiveTab] = useState(initialTabFromUrl);
  const theme = STATUS_THEME[program.status] ?? STATUS_THEME.draft;

  // Persist the active tab in the URL (?tab=stages) without a server round-trip, so a
  // reload or a shared link reopens the same tab instead of snapping back to Details.
  const changeTab = (id) => {
    setActiveTab(id);
    if (typeof window === 'undefined') return;
    const url = new URL(window.location.href);
    if (id === 'details') {
      url.searchParams.delete('tab');
    } else {
      url.searchParams.set('tab', id);
    }
    window.history.replaceState(window.history.state, '', url);
  };
  const startsLabel = formatDateLabel(program.starts_at);
  const endsLabel = formatDateLabel(program.ends_at);

  const form = useForm({
    title: program.title ?? '',
    slug: program.slug ?? '',
    description: program.description ?? '',
    leader_user_id: program.leader_user_id ?? '',
    starts_at: toDateInput(program.starts_at),
    ends_at: toDateInput(program.ends_at),
    checklist_template: program.checklist_template ?? [],
  });

  const submit = (e) => {
    e?.preventDefault?.();
    form.transform((data) => ({
      ...data,
      leader_user_id: data.leader_user_id || null,
      starts_at: data.starts_at || null,
      ends_at: data.ends_at || null,
    }));
    form.put(`/livehost/mentoring/programs/${program.id}`, { preserveScroll: true });
  };

  return (
    <>
      <Head title={`Edit ${program.title}`} />
      <TopBar
        breadcrumb={['Live Host Desk', 'Mentoring', 'Programs', program.title]}
        actions={
          <Link href="/livehost/mentoring/programs">
            <Button variant="ghost" className="gap-1.5 text-[#737373] hover:text-[#0A0A0A]">
              <ArrowLeft className="h-3.5 w-3.5" />
              Back to programs
            </Button>
          </Link>
        }
      />

      <div className="space-y-6 p-4 sm:p-6 lg:p-8 pb-24">
        {/* Hero */}
        <section className="relative overflow-hidden rounded-[20px] border border-[#EAEAEA] bg-white p-6 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
          <div className="relative flex flex-wrap items-start justify-between gap-6">
            <div className="min-w-0 flex-1">
              <div className="flex items-center gap-2">
                <span className={`inline-flex h-[22px] items-center gap-1.5 rounded-full border px-2.5 text-[11px] font-semibold uppercase tracking-[0.06em] ${theme.pill}`}>
                  <span className="relative flex h-1.5 w-1.5">
                    {theme.live && (
                      <span className="absolute inline-flex h-full w-full rounded-full opacity-75" style={{ background: theme.dot, animation: 'pulse-dot 1.6s ease-out infinite' }} />
                    )}
                    <span className="relative inline-flex h-1.5 w-1.5 rounded-full" style={{ background: theme.dot }} />
                  </span>
                  {program.status}
                </span>
                <span className="text-[12px] text-[#A3A3A3]">/ Program</span>
              </div>

              <h1 className="mt-3 truncate text-[28px] font-semibold leading-[1.1] tracking-[-0.03em] text-[#0A0A0A]">
                {program.title}
              </h1>

              <div className="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-[12.5px] text-[#737373]">
                <span className="inline-flex items-center gap-1.5">
                  <Users className="h-[13px] w-[13px]" strokeWidth={2} />
                  <span className="font-medium tabular-nums text-[#0A0A0A]">{program.active_mentees_count}</span>
                  active mentee{program.active_mentees_count === 1 ? '' : 's'}
                </span>
                <span className="inline-flex items-center gap-1.5">
                  <UserCircle2 className="h-[13px] w-[13px]" strokeWidth={2} />
                  Leader{' '}
                  <span className="font-medium text-[#0A0A0A]">{program.leader?.name ?? 'Unassigned'}</span>
                </span>
                {activityIndicator && (
                  <span className="inline-flex items-center gap-1.5" title={`${activityIndicator.count30} activities in 30 days`}>
                    <span className="inline-block h-2 w-2 rounded-full" style={{ backgroundColor: ACTIVITY_COLOR[activityIndicator.level] ?? '#D4D4D4' }} />
                    Mentor activity <span className="font-medium text-[#0A0A0A]">{activityIndicator.label}</span>
                  </span>
                )}
                {startsLabel && (
                  <span className="inline-flex items-center gap-1.5">
                    <Calendar className="h-[13px] w-[13px]" strokeWidth={2} />
                    {startsLabel}{endsLabel ? ` → ${endsLabel}` : ''}
                  </span>
                )}
              </div>
            </div>
            <LifecycleActions program={program} onOpenBoard={() => changeTab('board')} />
          </div>
        </section>

        {/* Tabs */}
        <div className="flex flex-wrap items-center gap-x-6 gap-y-1 border-b border-[#EAEAEA]">
          {TABS.map((tab) => {
            const Icon = tab.icon;
            const isActive = activeTab === tab.id;
            return (
              <button
                key={tab.id}
                type="button"
                onClick={() => changeTab(tab.id)}
                className={[
                  '-mb-px inline-flex items-center gap-1.5 border-b-2 px-1 pb-3 text-sm font-medium transition-colors',
                  isActive ? 'border-[#0A0A0A] text-[#0A0A0A]' : 'border-transparent text-[#737373] hover:text-[#0A0A0A]',
                ].join(' ')}
              >
                <Icon className="h-3.5 w-3.5" strokeWidth={2} />
                {tab.label}
              </button>
            );
          })}
        </div>

        {activeTab === 'details' && <ProgramDetailsForm form={form} onSubmit={submit} assignableLeaders={assignableLeaders} />}
        {activeTab === 'board' && (
          <MenteeBoard
            program={program}
            stages={board?.stages ?? []}
            mentees={board?.mentees ?? []}
            counts={board?.counts}
            assignableMentors={board?.assignableMentors ?? []}
            enrollableHosts={board?.enrollableHosts ?? []}
            reloadOnly={['board']}
          />
        )}
        {activeTab === 'stages' && <StageEditor program={program} stages={stages} />}
        {activeTab === 'checklist' && <ChecklistOverview program={program} form={form} />}
        {activeTab === 'activity' && <ActivitiesSection program={program} activities={activities} mentees={mentees} />}
        {activeTab === 'performance' && <MonthlyPerformanceTab performance={performance} program={program} board={board} />}

        {form.isDirty && (
          <div className="pointer-events-none fixed inset-x-0 bottom-4 z-40 flex justify-center px-4">
            <div className="pointer-events-auto inline-flex items-center gap-3 rounded-full border border-[#EAEAEA] bg-white/95 py-2 pl-4 pr-2 shadow-[0_8px_24px_rgba(0,0,0,0.08)] backdrop-blur">
              <span className="inline-flex h-1.5 w-1.5 rounded-full bg-[#F59E0B]" />
              <span className="text-[12.5px] font-medium text-[#525252]">Unsaved changes</span>
              <Button type="button" variant="ghost" size="sm" className="h-8 gap-1.5 rounded-full text-[#737373] hover:bg-[#F5F5F5] hover:text-[#0A0A0A]" onClick={() => form.reset()}>
                <Undo2 className="h-3.5 w-3.5" strokeWidth={2} />
                Discard
              </Button>
              <Button type="button" size="sm" disabled={form.processing} onClick={submit} className="h-8 gap-1.5 rounded-full bg-[#0A0A0A] px-3.5 text-white shadow-[0_1px_3px_rgba(0,0,0,0.18)] hover:bg-[#262626]">
                {form.processing ? 'Saving…' : (<><Check className="h-3.5 w-3.5" strokeWidth={2.5} /> Save changes</>)}
              </Button>
            </div>
          </div>
        )}
      </div>
    </>
  );
}

ProgramEdit.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;

function LifecycleActions({ program, onOpenBoard }) {
  const transition = (verb, confirmText) => {
    if (confirmText && !window.confirm(confirmText)) {
      return;
    }
    router.patch(`/livehost/mentoring/programs/${program.id}/${verb}`, {}, { preserveScroll: true });
  };

  const destroy = () => {
    if (!window.confirm(`Delete "${program.title}"? This cannot be undone.`)) {
      return;
    }
    router.delete(`/livehost/mentoring/programs/${program.id}`);
  };

  return (
    <div className="flex flex-wrap items-center gap-2">
      <Button type="button" size="sm" variant="outline" className="h-9 gap-1.5 rounded-lg shadow-none focus-visible:border-[#EAEAEA] focus-visible:ring-2 focus-visible:ring-[#10B981]/20" onClick={onOpenBoard}>
        <Users className="h-3.5 w-3.5" strokeWidth={2} />
        Mentee board
      </Button>
      {program.status === 'draft' && (
        <Button size="sm" className="h-9 gap-1.5 rounded-lg bg-[#059669] text-white shadow-[0_1px_3px_rgba(5,150,105,0.3)] hover:bg-[#047857]" onClick={() => transition('activate')}>
          <Play className="h-3.5 w-3.5" strokeWidth={2.25} />
          Activate
        </Button>
      )}
      {program.status === 'active' && (
        <Button size="sm" variant="outline" className="h-9 gap-1.5 rounded-lg border-[#FDE68A] bg-[#FFFBEB] text-[#B45309] hover:bg-[#FEF3C7]" onClick={() => transition('pause')}>
          <Pause className="h-3.5 w-3.5" strokeWidth={2.25} />
          Pause
        </Button>
      )}
      {program.status === 'paused' && (
        <Button size="sm" className="h-9 gap-1.5 rounded-lg bg-[#059669] text-white shadow-[0_1px_3px_rgba(5,150,105,0.3)] hover:bg-[#047857]" onClick={() => transition('activate')}>
          <Play className="h-3.5 w-3.5" strokeWidth={2.25} />
          Resume
        </Button>
      )}
      {(program.status === 'active' || program.status === 'paused') && (
        <Button size="sm" variant="outline" className="h-9 gap-1.5 rounded-lg border-[#C7D2FE] bg-[#EEF2FF] text-[#4338CA] hover:bg-[#E0E7FF]" onClick={() => transition('complete', `Mark "${program.title}" as completed?`)}>
          <Flag className="h-3.5 w-3.5" strokeWidth={2.25} />
          Complete
        </Button>
      )}
      {program.mentees_count === 0 && (
        <Button size="sm" variant="ghost" className="h-9 gap-1.5 rounded-lg text-[#A3A3A3] hover:bg-[#FFF1F2] hover:text-[#F43F5E]" onClick={destroy}>
          <Trash2 className="h-3.5 w-3.5" strokeWidth={2} />
          Delete
        </Button>
      )}
    </div>
  );
}

function ProgramDetailsForm({ form, onSubmit, assignableLeaders }) {
  const descriptionLength = (form.data.description ?? '').length;

  return (
    <form onSubmit={onSubmit}>
      <section className="rounded-[16px] border border-[#EAEAEA] bg-white p-6 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
        <div className="mb-5 flex items-center gap-2.5">
          <div className="grid h-8 w-8 place-items-center rounded-lg bg-[#F5F5F5] text-[#525252]">
            <Pencil className="h-4 w-4" strokeWidth={2} />
          </div>
          <div>
            <h2 className="text-[15px] font-semibold tracking-[-0.01em] text-[#0A0A0A]">Program details</h2>
            <p className="mt-0.5 text-[12px] text-[#737373]">Title, leader, and the cohort window.</p>
          </div>
        </div>

        <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
          <Field label="Program title" error={form.errors.title}>
            <Input name="title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} className={FIELD_INPUT_CLASS} />
          </Field>
          <Field label="Program leader (top host)" error={form.errors.leader_user_id}>
            <select
              value={form.data.leader_user_id ?? ''}
              onChange={(e) => form.setData('leader_user_id', e.target.value)}
              className="h-10 w-full appearance-none rounded-lg border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
            >
              <option value="">— No leader yet —</option>
              {(assignableLeaders ?? []).map((u) => (
                <option key={u.id} value={u.id}>
                  {u.name}{u.is_top_host_eligible ? ' ★' : ''}
                </option>
              ))}
            </select>
          </Field>
        </div>

        <div className="mt-5">
          <Field label="Description" error={form.errors.description}>
            <textarea
              name="description"
              value={form.data.description}
              onChange={(e) => form.setData('description', e.target.value)}
              rows={4}
              className="w-full rounded-lg border border-[#EAEAEA] bg-white px-3 py-2.5 text-[13.5px] leading-relaxed text-[#0A0A0A] placeholder:text-[#A3A3A3] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
            />
            <div className="mt-1 flex justify-end font-mono text-[10.5px] text-[#A3A3A3]">{descriptionLength} chars</div>
          </Field>
        </div>

        <div className="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
          <Field label="Starts at" error={form.errors.starts_at}>
            <Input type="date" name="starts_at" value={form.data.starts_at} onChange={(e) => form.setData('starts_at', e.target.value)} className={FIELD_INPUT_CLASS} />
          </Field>
          <Field label="Ends at" error={form.errors.ends_at}>
            <Input type="date" name="ends_at" value={form.data.ends_at} onChange={(e) => form.setData('ends_at', e.target.value)} className={FIELD_INPUT_CLASS} />
          </Field>
        </div>
      </section>
    </form>
  );
}

function StageEditor({ program, stages: initialStages }) {
  const [stages, setStages] = useState(initialStages ?? []);
  const [editingId, setEditingId] = useState(null);
  const [editingDraft, setEditingDraft] = useState({ name: '', description: '', is_final: false });
  const [adding, setAdding] = useState(false);
  const [addDraft, setAddDraft] = useState({ name: '', description: '', is_final: false });

  useEffect(() => {
    setStages(initialStages ?? []);
  }, [initialStages]);

  const sorted = useMemo(() => [...stages].sort((a, b) => a.position - b.position), [stages]);
  const totalMentees = useMemo(() => sorted.reduce((sum, s) => sum + (s.mentees_count ?? 0), 0), [sorted]);

  const beginEdit = (stage) => {
    setEditingId(stage.id);
    setEditingDraft({ name: stage.name ?? '', description: stage.description ?? '', is_final: !!stage.is_final });
  };
  const cancelEdit = () => {
    setEditingId(null);
    setEditingDraft({ name: '', description: '', is_final: false });
  };
  const saveEdit = (stage) => {
    router.put(`/livehost/mentoring/programs/${program.id}/stages/${stage.id}`, editingDraft, {
      preserveScroll: true,
      onSuccess: () => cancelEdit(),
    });
  };
  const destroy = (stage) => {
    if (stage.mentees_count > 0) {
      window.alert('This stage still has mentees. Move them to another stage first.');
      return;
    }
    if (!window.confirm(`Delete the "${stage.name}" stage?`)) {
      return;
    }
    router.delete(`/livehost/mentoring/programs/${program.id}/stages/${stage.id}`, { preserveScroll: true });
  };
  const reorder = (stage, direction) => {
    const index = sorted.findIndex((s) => s.id === stage.id);
    if (index < 0) return;
    const target = direction === 'up' ? index - 1 : index + 1;
    if (target < 0 || target >= sorted.length) return;
    const next = [...sorted];
    [next[index], next[target]] = [next[target], next[index]];
    const ids = next.map((s) => s.id);
    setStages(next.map((s, i) => ({ ...s, position: i + 1 })));
    router.put(`/livehost/mentoring/programs/${program.id}/stages/reorder`, { stage_ids: ids }, { preserveScroll: true });
  };
  const addStage = () => {
    router.post(`/livehost/mentoring/programs/${program.id}/stages`, addDraft, {
      preserveScroll: true,
      onSuccess: () => {
        setAdding(false);
        setAddDraft({ name: '', description: '', is_final: false });
      },
    });
  };

  return (
    <section className="rounded-[16px] border border-[#EAEAEA] bg-white p-6 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
      <div className="mb-5 flex items-start justify-between gap-3">
        <div className="flex items-center gap-2.5">
          <div className="grid h-8 w-8 place-items-center rounded-lg bg-[#F5F5F5] text-[#525252]">
            <Layers className="h-4 w-4" strokeWidth={2} />
          </div>
          <div>
            <h2 className="text-[15px] font-semibold tracking-[-0.01em] text-[#0A0A0A]">Mentoring stages</h2>
            <p className="mt-0.5 text-[12px] text-[#737373]">
              Mentees move through these stages.{' '}
              <span className="text-[#525252]">One stage must be marked as final (graduation).</span>
            </p>
          </div>
        </div>
        <div className="flex items-center gap-3">
          <span className="hidden items-center gap-1.5 rounded-full border border-[#F0F0F0] bg-[#FAFAFA] px-2.5 py-1 text-[11px] font-medium text-[#525252] sm:inline-flex">
            <span className="font-mono text-[#0A0A0A]">{sorted.length}</span> stage{sorted.length === 1 ? '' : 's'}
            <span className="text-[#D4D4D4]">·</span>
            <span className="font-mono text-[#0A0A0A]">{totalMentees}</span> mentee{totalMentees === 1 ? '' : 's'}
          </span>
          {!adding && (
            <Button size="sm" variant="outline" className="h-8 gap-1.5 rounded-lg shadow-none focus-visible:border-[#EAEAEA] focus-visible:ring-2 focus-visible:ring-[#10B981]/20" onClick={() => setAdding(true)}>
              <Plus className="h-3.5 w-3.5" strokeWidth={2.25} />
              Add stage
            </Button>
          )}
        </div>
      </div>

      <ol className="relative space-y-2">
        {sorted.length > 1 && (
          <div className="pointer-events-none absolute left-[15px] top-3 bottom-3 w-px bg-gradient-to-b from-[#E5E5E5] via-[#E5E5E5] to-transparent" aria-hidden="true" />
        )}
        {sorted.map((stage, index) => {
          const isEditing = editingId === stage.id;
          return (
            <li key={stage.id} className="relative">
              <div className={`group rounded-[12px] border transition-all ${isEditing ? 'border-[#0A0A0A]/15 bg-[#FAFAFA] shadow-[0_4px_12px_rgba(0,0,0,0.04)]' : 'border-[#EAEAEA] bg-white hover:border-[#D4D4D4] hover:shadow-[0_1px_3px_rgba(0,0,0,0.05)]'}`}>
                {isEditing ? (
                  <div className="space-y-3 p-4">
                    <div className="grid grid-cols-3 gap-3">
                      <div className="col-span-2">
                        <Label className="text-[12px] font-medium text-[#0A0A0A]">Name</Label>
                        <Input value={editingDraft.name} onChange={(e) => setEditingDraft((d) => ({ ...d, name: e.target.value }))} autoFocus className={FIELD_INPUT_CLASS} />
                      </div>
                      <div className="flex items-end">
                        <label className="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-[12.5px] text-[#0A0A0A] hover:bg-[#F9FAFB]">
                          <input type="checkbox" checked={editingDraft.is_final} onChange={(e) => setEditingDraft((d) => ({ ...d, is_final: e.target.checked }))} className="h-3.5 w-3.5 rounded border-[#D4D4D4] accent-[#059669]" />
                          <span className="inline-flex items-center gap-1"><Star className="h-3 w-3" strokeWidth={2.25} /> Final</span>
                        </label>
                      </div>
                    </div>
                    <div>
                      <Label className="text-[12px] font-medium text-[#0A0A0A]">Description <span className="text-[#A3A3A3]">(optional)</span></Label>
                      <textarea value={editingDraft.description} onChange={(e) => setEditingDraft((d) => ({ ...d, description: e.target.value }))} rows={2} className="w-full rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-[13px] text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20" />
                    </div>
                    <div className="flex items-center justify-end gap-2">
                      <Button type="button" variant="ghost" size="sm" onClick={cancelEdit}>
                        <X className="mr-1 h-3.5 w-3.5" strokeWidth={2.25} /> Cancel
                      </Button>
                      <Button type="button" size="sm" onClick={() => saveEdit(stage)} className="bg-[#0A0A0A] text-white hover:bg-[#262626]">
                        <Check className="mr-1 h-3.5 w-3.5" strokeWidth={2.5} /> Save stage
                      </Button>
                    </div>
                  </div>
                ) : (
                  <div className="flex items-center gap-3 p-3 pr-2">
                    <div className="relative grid h-8 w-8 shrink-0 place-items-center rounded-full text-[12.5px] font-semibold tabular-nums text-white shadow-[0_1px_2px_rgba(0,0,0,0.06)]" style={{ background: stage.is_final ? 'linear-gradient(135deg, #10B981, #059669)' : 'linear-gradient(135deg, #525252, #262626)' }}>
                      {stage.is_final ? <Star className="h-3.5 w-3.5" strokeWidth={2.5} fill="white" /> : stage.position}
                    </div>
                    <div className="min-w-0 flex-1">
                      <div className="flex items-center gap-2">
                        <span className="truncate text-[13.5px] font-semibold text-[#0A0A0A]">{stage.name}</span>
                        {stage.is_final && (
                          <span className="inline-flex items-center gap-1 rounded-full border border-[#A7F3D0] bg-[#ECFDF5] px-2 py-0.5 text-[10.5px] font-semibold uppercase tracking-[0.04em] text-[#059669]">Final</span>
                        )}
                        <span className="inline-flex items-center gap-1 rounded-full bg-[#F5F5F5] px-2 py-0.5 text-[10.5px] font-medium tabular-nums text-[#525252]" title="Mentees in this stage">
                          <Users className="h-2.5 w-2.5" strokeWidth={2.5} />
                          {stage.mentees_count}
                        </span>
                      </div>
                      {stage.description && <div className="mt-0.5 truncate text-[12px] leading-relaxed text-[#737373]">{stage.description}</div>}
                    </div>
                    <div className="inline-flex items-center gap-0.5 opacity-60 transition-opacity group-hover:opacity-100">
                      <button type="button" onClick={() => reorder(stage, 'up')} disabled={index === 0} className="inline-flex h-7 w-7 items-center justify-center rounded-md text-[#737373] hover:bg-[#F0F0F0] hover:text-[#0A0A0A] disabled:opacity-30 disabled:hover:bg-transparent" title="Move up">
                        <ArrowUp className="h-[13px] w-[13px]" strokeWidth={2} />
                      </button>
                      <button type="button" onClick={() => reorder(stage, 'down')} disabled={index === sorted.length - 1} className="inline-flex h-7 w-7 items-center justify-center rounded-md text-[#737373] hover:bg-[#F0F0F0] hover:text-[#0A0A0A] disabled:opacity-30 disabled:hover:bg-transparent" title="Move down">
                        <ArrowDown className="h-[13px] w-[13px]" strokeWidth={2} />
                      </button>
                      <span className="mx-0.5 h-4 w-px bg-[#EAEAEA]" />
                      <button type="button" onClick={() => beginEdit(stage)} className="inline-flex h-7 w-7 items-center justify-center rounded-md text-[#737373] hover:bg-[#F0F0F0] hover:text-[#0A0A0A]" title="Edit stage">
                        <Pencil className="h-[13px] w-[13px]" strokeWidth={2} />
                      </button>
                      <button type="button" onClick={() => destroy(stage)} className="inline-flex h-7 w-7 items-center justify-center rounded-md text-[#A3A3A3] hover:bg-[#FFF1F2] hover:text-[#F43F5E]" title="Delete stage">
                        <Trash2 className="h-[13px] w-[13px]" strokeWidth={2} />
                      </button>
                    </div>
                  </div>
                )}
              </div>
            </li>
          );
        })}

        {adding && (
          <li className="relative">
            <div className="rounded-[12px] border border-dashed border-[#0A0A0A]/20 bg-[#FAFAFA] p-4">
              <div className="mb-3 flex items-center gap-2">
                <div className="grid h-8 w-8 place-items-center rounded-full bg-white text-[#525252] shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
                  <Plus className="h-3.5 w-3.5" strokeWidth={2.25} />
                </div>
                <span className="text-[12.5px] font-semibold text-[#0A0A0A]">New stage</span>
              </div>
              <div className="grid grid-cols-3 gap-3">
                <div className="col-span-2">
                  <Label className="text-[12px] font-medium text-[#0A0A0A]">Name</Label>
                  <Input value={addDraft.name} onChange={(e) => setAddDraft((d) => ({ ...d, name: e.target.value }))} placeholder="e.g. Shadow live" autoFocus className={FIELD_INPUT_CLASS} />
                </div>
                <div className="flex items-end">
                  <label className="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-[12.5px] text-[#0A0A0A] hover:bg-[#F9FAFB]">
                    <input type="checkbox" checked={addDraft.is_final} onChange={(e) => setAddDraft((d) => ({ ...d, is_final: e.target.checked }))} className="h-3.5 w-3.5 rounded border-[#D4D4D4] accent-[#059669]" />
                    <span className="inline-flex items-center gap-1"><Star className="h-3 w-3" strokeWidth={2.25} /> Final</span>
                  </label>
                </div>
              </div>
              <div className="mt-3">
                <Label className="text-[12px] font-medium text-[#0A0A0A]">Description <span className="text-[#A3A3A3]">(optional)</span></Label>
                <textarea value={addDraft.description} onChange={(e) => setAddDraft((d) => ({ ...d, description: e.target.value }))} rows={2} className="w-full rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-[13px] text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20" />
              </div>
              <div className="mt-3 flex items-center justify-end gap-2">
                <Button type="button" variant="ghost" size="sm" onClick={() => { setAdding(false); setAddDraft({ name: '', description: '', is_final: false }); }}>
                  Cancel
                </Button>
                <Button type="button" size="sm" onClick={addStage} disabled={!addDraft.name.trim()} className="bg-[#0A0A0A] text-white hover:bg-[#262626] disabled:opacity-50">
                  <Plus className="mr-1 h-3.5 w-3.5" strokeWidth={2.5} /> Add stage
                </Button>
              </div>
            </div>
          </li>
        )}
      </ol>
    </section>
  );
}

/**
 * The Checklist tab: a monitoring matrix of every active mentee × the program's
 * template tasks (done / pending / not-on-their-list), with each mentee's
 * individual-task progress and an overall percentage. The template itself is
 * edited from a modal behind the "Edit template" button.
 */
function ChecklistOverview({ program, form }) {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [editing, setEditing] = useState(false);
  const [expanded, setExpanded] = useState(() => new Set());
  const [drafts, setDrafts] = useState({});
  const [editingTask, setEditingTask] = useState(null);
  const [editDraft, setEditDraft] = useState({ title: '', due_at: '', is_required: false, description: '' });

  const fetchMatrix = useCallback(() => {
    fetch(`/livehost/mentoring/programs/${program.id}/checklist-overview`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
      .then((r) => (r.ok ? r.json() : null))
      .then((d) => { if (d) setData(d); })
      .catch(() => {})
      .finally(() => setLoading(false));
  }, [program.id]);

  useEffect(() => { fetchMatrix(); }, [fetchMatrix]);

  const saveTemplate = () => {
    form.transform((d) => ({ ...d, leader_user_id: d.leader_user_id || null, starts_at: d.starts_at || null, ends_at: d.ends_at || null }));
    form.put(`/livehost/mentoring/programs/${program.id}`, {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => { setEditing(false); fetchMatrix(); },
    });
  };

  const toggleExpand = (id) => {
    setExpanded((prev) => {
      const next = new Set(prev);
      next.has(id) ? next.delete(id) : next.add(id);
      return next;
    });
  };

  const inertiaOpts = { preserveScroll: true, preserveState: true, onFinish: fetchMatrix };

  const toggleItem = (menteeId, itemId) => {
    if (!itemId) { return; }
    router.patch(`/livehost/mentoring/mentees/${menteeId}/checklist/${itemId}/toggle`, {}, inertiaOpts);
  };

  const deleteCustom = (menteeId, itemId) => {
    router.delete(`/livehost/mentoring/mentees/${menteeId}/checklist/${itemId}`, inertiaOpts);
  };

  const startEdit = (t) => {
    setEditingTask(t.id);
    setEditDraft({
      title: t.title ?? '',
      due_at: t.due_at ?? '',
      is_required: !!t.is_required,
      description: t.description ?? '',
    });
  };

  const cancelEdit = () => { setEditingTask(null); };

  const saveEdit = (menteeId, itemId) => {
    if (!editDraft.title.trim()) { return; }
    router.patch(`/livehost/mentoring/mentees/${menteeId}/checklist/${itemId}`, {
      title: editDraft.title.trim(),
      description: editDraft.description.trim() || null,
      due_at: editDraft.due_at || null,
      is_required: editDraft.is_required,
    }, {
      ...inertiaOpts,
      onSuccess: () => setEditingTask(null),
    });
  };

  const draftFor = (id) => drafts[id] ?? { title: '', due_at: '', is_required: false, description: '' };
  const setDraft = (id, patch) => setDrafts((prev) => ({ ...prev, [id]: { ...draftFor(id), ...patch } }));

  const addCustom = (menteeId) => {
    const draft = draftFor(menteeId);
    if (!draft.title.trim()) { return; }
    router.post(`/livehost/mentoring/mentees/${menteeId}/checklist`, {
      title: draft.title.trim(),
      description: draft.description.trim() || null,
      due_at: draft.due_at || null,
      is_required: draft.is_required,
    }, {
      ...inertiaOpts,
      onSuccess: () => setDrafts((prev) => ({ ...prev, [menteeId]: { title: '', due_at: '', is_required: false, description: '' } })),
    });
  };

  const columns = data?.columns ?? [];
  const mentees = data?.mentees ?? [];
  const colSpan = columns.length + 3;

  return (
    <section className="overflow-hidden rounded-[16px] border border-[#EAEAEA] bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
      <div className="flex flex-wrap items-start justify-between gap-3 border-b border-[#F0F0F0] p-5">
        <div className="flex items-center gap-2.5">
          <div className="grid h-8 w-8 place-items-center rounded-lg bg-[#F5F5F5] text-[#525252]"><ListChecks className="h-4 w-4" strokeWidth={2} /></div>
          <div>
            <h2 className="text-[15px] font-semibold tracking-[-0.01em] text-[#0A0A0A]">Checklist monitoring</h2>
            <p className="mt-0.5 text-[12px] text-[#737373]">Every active mentee and the tasks they've completed.</p>
          </div>
        </div>
        <Button size="sm" variant="outline" className="h-8 gap-1.5 rounded-lg shadow-none focus-visible:border-[#EAEAEA] focus-visible:ring-2 focus-visible:ring-[#10B981]/20" onClick={() => setEditing(true)}>
          <Pencil className="h-3.5 w-3.5" strokeWidth={2} /> Edit template
        </Button>
      </div>

      {loading && !data ? (
        <div className="grid place-items-center py-16"><div className="h-5 w-5 animate-spin rounded-full border-2 border-[#E5E5E5] border-t-[#10B981]" /></div>
      ) : mentees.length === 0 ? (
        <div className="py-16 text-center text-[13px] text-[#A3A3A3]">No active mentees to monitor yet.</div>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full border-collapse text-left">
            <thead>
              <tr className="border-b border-[#F0F0F0]">
                <th className="sticky left-0 z-10 bg-white px-4 py-3 text-[11px] font-semibold uppercase tracking-wide text-[#737373]">Mentee</th>
                {columns.map((title) => (
                  <th key={title} className="px-2 py-3 text-center text-[11px] font-medium text-[#737373]" title={title}>
                    <div className="mx-auto max-w-[72px] truncate">{title}</div>
                  </th>
                ))}
                <th className="px-3 py-3 text-center text-[11px] font-semibold uppercase tracking-wide text-[#047857]" title="Individual (custom) tasks for this mentee">Individual</th>
                <th className="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-wide text-[#737373]">Progress</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[#F5F5F5]">
              {mentees.map((m) => {
                const isOpen = expanded.has(m.id);
                const draft = draftFor(m.id);
                return (
                <Fragment key={m.id}>
                <tr className={`group ${isOpen ? 'bg-[#FAFAFA]' : 'hover:bg-[#FAFAFA]'}`}>
                  <td className={`sticky left-0 z-10 px-4 py-3 ${isOpen ? 'bg-[#FAFAFA]' : 'bg-white group-hover:bg-[#FAFAFA]'}`}>
                    <div className="flex items-center gap-2">
                      <button
                        type="button"
                        onClick={() => toggleExpand(m.id)}
                        className="grid h-6 w-6 shrink-0 place-items-center rounded-md text-[#A3A3A3] hover:bg-[#EFEFEF] hover:text-[#525252]"
                        aria-label={isOpen ? 'Collapse individual checklist' : 'Expand individual checklist'}
                      >
                        {isOpen ? <ChevronDown className="h-4 w-4" strokeWidth={2.25} /> : <ChevronRight className="h-4 w-4" strokeWidth={2.25} />}
                      </button>
                      <Link href={`/livehost/mentoring/mentees/${m.id}`} className="block min-w-0">
                        <div className="truncate text-[13px] font-semibold text-[#0A0A0A] hover:underline">{m.name}</div>
                        <div className="text-[11px] text-[#A3A3A3]">{m.stage ?? '—'}</div>
                      </Link>
                    </div>
                  </td>
                  {columns.map((title) => {
                    const cell = m.cells?.[title];
                    const status = cell?.status ?? 'na';
                    if (!cell) {
                      return <td key={title} className="px-2 py-3 text-center"><span className="text-[#E5E5E5]">–</span></td>;
                    }
                    return (
                      <td key={title} className="px-2 py-3 text-center">
                        <button
                          type="button"
                          onClick={() => toggleItem(m.id, cell.id)}
                          title={status === 'done' ? 'Mark as not done' : 'Mark as done'}
                          className={`mx-auto grid h-6 w-6 place-items-center rounded-md transition-colors ${status === 'done' ? 'bg-[#ECFDF5] text-[#10B981] hover:bg-[#D1FAE5]' : 'text-[#D4D4D4] hover:bg-[#F0F0F0] hover:text-[#10B981]'}`}
                        >
                          {status === 'done'
                            ? <Check className="h-4 w-4" strokeWidth={2.5} />
                            : <span className="block h-2.5 w-2.5 rounded-full border border-current" />}
                        </button>
                      </td>
                    );
                  })}
                  <td className="px-3 py-3 text-center">
                    {m.custom_total > 0 ? (
                      <button type="button" onClick={() => toggleExpand(m.id)} className="inline-flex items-center rounded-full bg-[#ECFDF5] px-2 py-0.5 text-[11px] font-semibold tabular-nums text-[#047857] hover:bg-[#D1FAE5]">{m.custom_done}/{m.custom_total}</button>
                    ) : (
                      <span className="text-[11px] text-[#D4D4D4]">—</span>
                    )}
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex items-center justify-end gap-2">
                      <div className="h-1.5 w-16 overflow-hidden rounded-full bg-[#F0F0F0]"><div className="h-full rounded-full bg-[#10B981]" style={{ width: `${m.pct}%` }} /></div>
                      <span className="w-9 text-right text-[12px] font-medium tabular-nums text-[#525252]">{m.pct}%</span>
                    </div>
                  </td>
                </tr>
                {isOpen && (
                  <tr className="bg-[#FBFBFB]">
                    <td colSpan={colSpan} className="px-4 pb-5 pt-1">
                      <div className="ml-8 rounded-[12px] border border-[#EDEDED] bg-white p-4">
                        <div className="mb-3 flex items-center gap-1.5">
                          <Star className="h-3.5 w-3.5 text-[#047857]" strokeWidth={2} />
                          <span className="text-[12px] font-semibold text-[#0A0A0A]">Individual checklist</span>
                          <span className="text-[11px] text-[#A3A3A3]">· tasks unique to {m.name?.split(' ')[0] ?? 'this mentee'}</span>
                        </div>

                        {(m.custom_tasks?.length ?? 0) > 0 ? (
                          <ul className="mb-3 space-y-1.5">
                            {m.custom_tasks.map((t) => (
                              editingTask === t.id ? (
                              <li key={t.id} className="rounded-lg border border-[#10B981]/40 bg-[#F0FDF9] p-3">
                                <div className="flex flex-col gap-2 sm:flex-row sm:items-end">
                                  <div className="flex-1">
                                    <Label className="mb-1 block text-[11px] text-[#737373]">Task</Label>
                                    <Input
                                      value={editDraft.title}
                                      onChange={(e) => setEditDraft((d) => ({ ...d, title: e.target.value }))}
                                      onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); saveEdit(m.id, t.id); } }}
                                      autoFocus
                                      className="h-9"
                                    />
                                  </div>
                                  <div className="sm:w-40">
                                    <Label className="mb-1 block text-[11px] text-[#737373]">Due date</Label>
                                    <Input type="date" value={editDraft.due_at} onChange={(e) => setEditDraft((d) => ({ ...d, due_at: e.target.value }))} className="h-9" />
                                  </div>
                                  <label className="flex h-9 items-center gap-1.5 text-[12px] text-[#525252]">
                                    <input type="checkbox" checked={editDraft.is_required} onChange={(e) => setEditDraft((d) => ({ ...d, is_required: e.target.checked }))} className="h-3.5 w-3.5 rounded border-[#D4D4D4] text-[#10B981] focus:ring-[#10B981]/30" />
                                    Required
                                  </label>
                                </div>
                                <Input
                                  value={editDraft.description}
                                  onChange={(e) => setEditDraft((d) => ({ ...d, description: e.target.value }))}
                                  placeholder="Note (optional)"
                                  className="mt-2 h-9"
                                />
                                <div className="mt-2 flex justify-end gap-2">
                                  <Button type="button" size="sm" variant="ghost" onClick={cancelEdit} className="h-8">Cancel</Button>
                                  <Button type="button" size="sm" onClick={() => saveEdit(m.id, t.id)} disabled={!editDraft.title.trim()} className="h-8 gap-1.5 bg-[#0A0A0A] text-white hover:bg-[#262626] disabled:opacity-40">
                                    <Check className="h-3.5 w-3.5" strokeWidth={2.5} /> Save
                                  </Button>
                                </div>
                              </li>
                              ) : (
                              <li key={t.id} className="group/task flex items-start gap-2.5 rounded-lg border border-[#F0F0F0] px-3 py-2 hover:border-[#E5E5E5]">
                                <button
                                  type="button"
                                  onClick={() => toggleItem(m.id, t.id)}
                                  className={`mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-md border transition-colors ${t.status === 'done' ? 'border-[#10B981] bg-[#10B981] text-white' : 'border-[#D4D4D4] text-transparent hover:border-[#10B981]'}`}
                                  aria-label={t.status === 'done' ? 'Mark as not done' : 'Mark as done'}
                                >
                                  <Check className="h-3.5 w-3.5" strokeWidth={3} />
                                </button>
                                <div className="min-w-0 flex-1">
                                  <div className="flex flex-wrap items-center gap-1.5">
                                    <span className={`text-[12.5px] font-medium ${t.status === 'done' ? 'text-[#A3A3A3] line-through' : 'text-[#0A0A0A]'}`}>{t.title}</span>
                                    {t.is_required && <span className="rounded bg-[#FEF2F2] px-1.5 py-0.5 text-[10px] font-semibold text-[#DC2626]">Required</span>}
                                    {t.due_at_human && <span className={`inline-flex items-center gap-1 text-[10.5px] ${t.is_overdue ? 'font-semibold text-[#DC2626]' : 'text-[#A3A3A3]'}`}><Calendar className="h-3 w-3" strokeWidth={2} />{t.due_at_human}</span>}
                                  </div>
                                  {t.description && <p className="mt-0.5 text-[11.5px] text-[#737373]">{t.description}</p>}
                                </div>
                                <div className="flex shrink-0 items-center gap-0.5">
                                  <button type="button" onClick={() => startEdit(t)} className="mt-0.5 rounded-md p-1 text-[#C4C4C4] hover:bg-[#F0F0F0] hover:text-[#525252]" aria-label="Edit task">
                                    <Pencil className="h-3.5 w-3.5" strokeWidth={2} />
                                  </button>
                                  <button type="button" onClick={() => deleteCustom(m.id, t.id)} className="mt-0.5 rounded-md p-1 text-[#C4C4C4] hover:bg-[#FEF2F2] hover:text-[#DC2626]" aria-label="Delete task">
                                    <Trash2 className="h-3.5 w-3.5" strokeWidth={2} />
                                  </button>
                                </div>
                              </li>
                              )
                            ))}
                          </ul>
                        ) : (
                          <p className="mb-3 text-[12px] text-[#A3A3A3]">No individual tasks yet. Add one below.</p>
                        )}

                        <div className="rounded-lg border border-dashed border-[#E0E0E0] bg-[#FAFAFA] p-3">
                          <div className="flex flex-col gap-2 sm:flex-row sm:items-end">
                            <div className="flex-1">
                              <Label className="mb-1 block text-[11px] text-[#737373]">Task</Label>
                              <Input
                                value={draft.title}
                                onChange={(e) => setDraft(m.id, { title: e.target.value })}
                                onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); addCustom(m.id); } }}
                                placeholder="e.g. Practice TikTok hooks"
                                className="h-9"
                              />
                            </div>
                            <div className="sm:w-40">
                              <Label className="mb-1 block text-[11px] text-[#737373]">Due date</Label>
                              <Input type="date" value={draft.due_at} onChange={(e) => setDraft(m.id, { due_at: e.target.value })} className="h-9" />
                            </div>
                            <label className="flex h-9 items-center gap-1.5 text-[12px] text-[#525252]">
                              <input type="checkbox" checked={draft.is_required} onChange={(e) => setDraft(m.id, { is_required: e.target.checked })} className="h-3.5 w-3.5 rounded border-[#D4D4D4] text-[#10B981] focus:ring-[#10B981]/30" />
                              Required
                            </label>
                            <Button type="button" size="sm" onClick={() => addCustom(m.id)} disabled={!draft.title.trim()} className="h-9 gap-1.5 bg-[#0A0A0A] text-white hover:bg-[#262626] disabled:opacity-40">
                              <Plus className="h-3.5 w-3.5" strokeWidth={2.5} /> Add
                            </Button>
                          </div>
                          <Input
                            value={draft.description}
                            onChange={(e) => setDraft(m.id, { description: e.target.value })}
                            placeholder="Note (optional)"
                            className="mt-2 h-9"
                          />
                        </div>
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
      )}

      {editing && (
        <div className="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4" onMouseDown={(e) => { if (e.target === e.currentTarget && !form.processing) setEditing(false); }}>
          <div className="flex max-h-[88vh] w-full max-w-2xl flex-col overflow-hidden rounded-[16px] bg-white shadow-[0_20px_60px_rgba(0,0,0,0.18)]">
            <div className="flex items-center justify-between border-b border-[#F0F0F0] px-5 py-4">
              <div className="text-[15px] font-semibold tracking-[-0.015em] text-[#0A0A0A]">Edit checklist template</div>
              <button type="button" onClick={() => setEditing(false)} className="rounded-md p-1 text-[#737373] hover:bg-[#F5F5F5] hover:text-[#0A0A0A]" aria-label="Close"><X className="h-4 w-4" strokeWidth={2} /></button>
            </div>
            <div className="flex-1 overflow-y-auto p-5"><ChecklistTemplateEditor form={form} embedded /></div>
            <div className="flex items-center justify-between gap-2 border-t border-[#F0F0F0] px-5 py-4">
              <p className="text-[11.5px] text-[#A3A3A3]">Edits apply to mentees enrolled from now on.</p>
              <div className="flex gap-2">
                <Button type="button" variant="ghost" disabled={form.processing} onClick={() => setEditing(false)}>Cancel</Button>
                <Button type="button" disabled={form.processing} onClick={saveTemplate} className="bg-[#0A0A0A] text-white hover:bg-[#262626] disabled:opacity-50">{form.processing ? 'Saving…' : 'Save template'}</Button>
              </div>
            </div>
          </div>
        </div>
      )}
    </section>
  );
}

function ChecklistTemplateEditor({ form, embedded = false }) {
  const items = form.data.checklist_template ?? [];
  const setItems = (next) => form.setData('checklist_template', next);
  const addItem = () => setItems([...items, { title: '', is_required: true }]);
  const updateTitle = (i, v) => setItems(items.map((it, idx) => (idx === i ? { ...it, title: v } : it)));
  const toggleReq = (i) => setItems(items.map((it, idx) => (idx === i ? { ...it, is_required: !it.is_required } : it)));
  const remove = (i) => setItems(items.filter((_, idx) => idx !== i));
  const move = (i, dir) => {
    const target = dir === 'up' ? i - 1 : i + 1;
    if (target < 0 || target >= items.length) return;
    const next = [...items];
    [next[i], next[target]] = [next[target], next[i]];
    setItems(next);
  };

  const list = items.length === 0 ? (
    <div className="rounded-[12px] border border-dashed border-[#E5E5E5] bg-[#FAFAFA] py-10 text-center text-[12.5px] text-[#A3A3A3]">
      No template tasks. Mentees will start with an empty checklist.
    </div>
  ) : (
    <ul className="space-y-2">
      {items.map((item, i) => (
        <li key={i} className="flex items-center gap-2 rounded-[10px] border border-[#EAEAEA] bg-white p-2">
          <div className="flex flex-col">
            <button type="button" onClick={() => move(i, 'up')} disabled={i === 0} className="inline-flex h-5 w-5 items-center justify-center rounded text-[#737373] hover:bg-[#F0F0F0] disabled:opacity-30">
              <ArrowUp className="h-3 w-3" strokeWidth={2} />
            </button>
            <button type="button" onClick={() => move(i, 'down')} disabled={i === items.length - 1} className="inline-flex h-5 w-5 items-center justify-center rounded text-[#737373] hover:bg-[#F0F0F0] disabled:opacity-30">
              <ArrowDown className="h-3 w-3" strokeWidth={2} />
            </button>
          </div>
          <Input value={item.title} onChange={(e) => updateTitle(i, e.target.value)} placeholder="Task title" className={`flex-1 ${FIELD_INPUT_CLASS}`} />
          <label className="inline-flex shrink-0 cursor-pointer items-center gap-1.5 rounded-lg border border-[#EAEAEA] bg-white px-2.5 py-2 text-[11.5px] text-[#0A0A0A] hover:bg-[#F9FAFB]">
            <input type="checkbox" checked={!!item.is_required} onChange={() => toggleReq(i)} className="h-3.5 w-3.5 rounded border-[#D4D4D4] accent-[#059669]" />
            Required
          </label>
          <button type="button" onClick={() => remove(i)} className="shrink-0 rounded-md p-1.5 text-[#A3A3A3] hover:bg-[#FFF1F2] hover:text-[#F43F5E]" title="Remove">
            <Trash2 className="h-3.5 w-3.5" strokeWidth={2} />
          </button>
        </li>
      ))}
    </ul>
  );

  if (embedded) {
    return (
      <div>
        <div className="mb-3 flex items-center justify-between gap-3">
          <p className="text-[12px] text-[#737373]">Tasks copied onto each mentee when they're enrolled.</p>
          <Button size="sm" variant="outline" className="h-8 shrink-0 gap-1.5 rounded-lg shadow-none focus-visible:border-[#EAEAEA] focus-visible:ring-2 focus-visible:ring-[#10B981]/20" onClick={addItem}>
            <Plus className="h-3.5 w-3.5" strokeWidth={2.25} /> Add task
          </Button>
        </div>
        {list}
      </div>
    );
  }

  return (
    <section className="rounded-[16px] border border-[#EAEAEA] bg-white p-6 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
      <div className="mb-5 flex items-start justify-between gap-3">
        <div className="flex items-center gap-2.5">
          <div className="grid h-8 w-8 place-items-center rounded-lg bg-[#F5F5F5] text-[#525252]">
            <ListChecks className="h-4 w-4" strokeWidth={2} />
          </div>
          <div>
            <h2 className="text-[15px] font-semibold tracking-[-0.01em] text-[#0A0A0A]">Checklist template</h2>
            <p className="mt-0.5 text-[12px] text-[#737373]">
              Tasks copied onto each mentee when they're enrolled.{' '}
              <span className="text-[#525252]">Edits apply to mentees enrolled from now on.</span>
            </p>
          </div>
        </div>
        <Button size="sm" variant="outline" className="h-8 gap-1.5 rounded-lg shadow-none focus-visible:border-[#EAEAEA] focus-visible:ring-2 focus-visible:ring-[#10B981]/20" onClick={addItem}>
          <Plus className="h-3.5 w-3.5" strokeWidth={2.25} /> Add task
        </Button>
      </div>
      {list}
    </section>
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

function ActivitiesSection({ program, activities, mentees }) {
  const [logging, setLogging] = useState(false);

  const remove = (a) => {
    if (!window.confirm('Remove this activity?')) return;
    router.delete(`/livehost/mentoring/activities/${a.id}`, { preserveScroll: true });
  };

  return (
    <section className="rounded-[16px] border border-[#EAEAEA] bg-white p-6 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
      <div className="mb-5 flex items-start justify-between gap-3">
        <div className="flex items-center gap-2.5">
          <div className="grid h-8 w-8 place-items-center rounded-lg bg-[#F5F5F5] text-[#525252]">
            <MessageSquare className="h-4 w-4" strokeWidth={2} />
          </div>
          <div>
            <h2 className="text-[15px] font-semibold tracking-[-0.01em] text-[#0A0A0A]">Activity log</h2>
            <p className="mt-0.5 text-[12px] text-[#737373]">Coaching, meetings, and training the leader runs. Drives the activity indicator.</p>
          </div>
        </div>
        <Button size="sm" variant="outline" className="h-8 gap-1.5 rounded-lg shadow-none focus-visible:border-[#EAEAEA] focus-visible:ring-2 focus-visible:ring-[#10B981]/20" onClick={() => setLogging(true)}>
          <Plus className="h-3.5 w-3.5" strokeWidth={2.25} />
          Log activity
        </Button>
      </div>

      {!activities || activities.length === 0 ? (
        <div className="rounded-[12px] border border-dashed border-[#E5E5E5] bg-[#FAFAFA] py-10 text-center text-[12.5px] text-[#A3A3A3]">
          Nothing logged yet. Record the first coaching session or meeting.
        </div>
      ) : (
        <ul className="divide-y divide-[#F0F0F0]">
          {activities.map((a) => (
            <li key={a.id} className="group flex items-start justify-between gap-4 py-3">
              <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-2">
                  <span className={`inline-flex items-center rounded-md px-1.5 py-0.5 text-[10.5px] font-semibold uppercase tracking-wide ${activityTypeTone(a.type)}`}>
                    {a.type.replace('_', ' ')}
                  </span>
                  <span className="text-[13.5px] font-semibold text-[#0A0A0A]">{a.title}</span>
                  {a.mentee && (
                    <span className="inline-flex items-center rounded-full bg-[#F5F5F5] px-2 py-0.5 text-[10.5px] font-medium text-[#525252]">
                      {a.mentee.name}
                    </span>
                  )}
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

      {logging && (
        <ActivityLogModal programId={program.id} mentees={mentees ?? []} onClose={() => setLogging(false)} />
      )}
    </section>
  );
}

function Field({ label, error, children }) {
  return (
    <div className="space-y-1.5">
      <Label className="text-[12.5px] font-medium text-[#0A0A0A]">{label}</Label>
      {children}
      {error && <p className="mt-1 text-[11.5px] text-[#F43F5E]">{error}</p>}
    </div>
  );
}
