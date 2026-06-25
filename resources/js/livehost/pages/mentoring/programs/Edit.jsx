import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
  ArrowDown,
  ArrowLeft,
  ArrowUp,
  Calendar,
  Check,
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

      <div className="space-y-6 p-8 pb-24">
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
        {activeTab === 'checklist' && <ChecklistTemplateEditor form={form} />}
        {activeTab === 'activity' && <ActivitiesSection program={program} activities={activities} mentees={mentees} />}
        {activeTab === 'performance' && <MonthlyPerformanceTab performance={performance} />}

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

function ChecklistTemplateEditor({ form }) {
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

      {items.length === 0 ? (
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
      )}
    </section>
  );
}

function scoreTone(score) {
  if (score === null || score === undefined || score === '') {
    return { bg: 'bg-[#F5F5F5]', text: 'text-[#A3A3A3]' };
  }
  const n = Number(score);
  if (n >= 80) return { bg: 'bg-[#ECFDF5]', text: 'text-[#047857]' };
  if (n >= 60) return { bg: 'bg-[#FEF3C7]', text: 'text-[#B45309]' };
  return { bg: 'bg-[#FEE2E2]', text: 'text-[#B91C1C]' };
}

/** Sales KPI as a percentage of the level's monthly target (capped at 100), or null. */
function salesPct(sales, target) {
  if (sales === '' || sales === null || sales === undefined) return null;
  if (!target || target <= 0) return null;
  const n = Number(sales);
  if (Number.isNaN(n)) return null;
  return Math.min(100, Math.round((n / target) * 100));
}

/**
 * Overall KPI: the mean of whichever components are present — Attitude (0–100)
 * and Sales% (actual ÷ target). Equal weighting. Returns null when neither is set.
 */
function overallKpi(attitude, sales, target) {
  const a = attitude === '' || attitude === null || attitude === undefined ? null : Math.max(0, Math.min(100, Number(attitude)));
  const s = salesPct(sales, target);
  const parts = [a, s].filter((v) => v !== null && !Number.isNaN(v));
  if (parts.length === 0) return null;
  return Math.round(parts.reduce((x, y) => x + y, 0) / parts.length);
}

/** Short column label for a month ("Jan 2026" → "Jan"). */
function monthAbbr(mo) {
  return (mo.label || '').split(' ')[0] || String(mo.month);
}

/** Full Ringgit value with 2 decimals, e.g. "RM 2,909.00" (editor + tooltips). */
function formatRM(n) {
  const num = Number(n);
  if (Number.isNaN(num)) return '–';
  return `RM ${num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

/** Compact Ringgit for the dense matrix cell: whole RM stays short, sen shown only when present. */
function formatRMCompact(n) {
  const num = Number(n);
  if (Number.isNaN(num)) return '–';
  const hasSen = Math.round(num) !== num;
  return `RM ${num.toLocaleString(undefined, { minimumFractionDigits: hasSen ? 2 : 0, maximumFractionDigits: 2 })}`;
}

function MonthlyPerformanceTab({ performance }) {
  const months = performance?.months ?? [];
  const mentees = performance?.mentees ?? [];
  const [query, setQuery] = useState('');
  const visibleMentees = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return mentees;
    return mentees.filter((m) => (m.name ?? '').toLowerCase().includes(q));
  }, [mentees, query]);

  // Local copy of every host's scores so a cell repaints the moment it's edited.
  const [localScores, setLocalScores] = useState(() => {
    const map = {};
    mentees.forEach((m) => { map[m.id] = { ...(m.scores ?? {}) }; });
    return map;
  });
  const [saveState, setSaveState] = useState({}); // `${menteeId}:${monthValue}` -> 'saving' | 'saved' | 'error'
  const [editing, setEditing] = useState(null); // { menteeId, month, rect }

  const currentMonthValue = useMemo(() => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
  }, []);

  const saveCell = (menteeId, mo, draft) => {
    const attitudeVal = draft.attitude === '' || draft.attitude === null || draft.attitude === undefined ? null : Number(draft.attitude);
    const salesVal = draft.sales === '' || draft.sales === null || draft.sales === undefined ? null : Number(draft.sales);
    const notesVal = draft.notes ? draft.notes : null;
    const key = `${menteeId}:${mo.value}`;
    // Optimistic: paint the new value straight away, then persist.
    setLocalScores((p) => ({ ...p, [menteeId]: { ...(p[menteeId] || {}), [mo.value]: { attitude: attitudeVal, sales: salesVal, notes: notesVal } } }));
    setSaveState((s) => ({ ...s, [key]: 'saving' }));
    router.patch(
      `/livehost/mentoring/mentees/${menteeId}/monthly-score`,
      { year: mo.year, month: mo.month, attitude_score: attitudeVal, sales_quantity: salesVal, notes: notesVal },
      {
        preserveScroll: true,
        preserveState: true,
        only: ['performance'],
        onSuccess: () => setSaveState((s) => ({ ...s, [key]: 'saved' })),
        onError: () => setSaveState((s) => ({ ...s, [key]: 'error' })),
      },
    );
  };

  if (mentees.length === 0) {
    return (
      <section className="rounded-[16px] border border-[#EAEAEA] bg-white p-6 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
        <div className="py-10 text-center text-[13px] text-[#737373]">No mentees to evaluate yet. Enrol mentees first.</div>
      </section>
    );
  }

  const editMentee = editing ? mentees.find((m) => m.id === editing.menteeId) : null;

  return (
    <section className="rounded-[16px] border border-[#EAEAEA] bg-white p-6 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
      <div className="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div className="flex items-center gap-2.5">
          <div className="grid h-8 w-8 place-items-center rounded-lg bg-[#F5F5F5] text-[#525252]">
            <Gauge className="h-4 w-4" strokeWidth={2} />
          </div>
          <div>
            <h2 className="text-[15px] font-semibold tracking-[-0.01em] text-[#0A0A0A]">Monthly performance</h2>
            <p className="mt-0.5 text-[12px] text-[#737373]">Each cell shows the host's <span className="font-semibold text-[#0A0A0A]">Sales (RM)</span> (top) and <span className="font-medium text-[#525252]">Attitude</span> below; the colour is their Overall KPI. Click a cell to edit. Auto-saves.</p>
          </div>
        </div>
        <div className="relative w-full max-w-[260px] sm:w-[240px]">
          <Search className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-[#A3A3A3]" strokeWidth={2} />
          <input
            type="search"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder="Search host name…"
            className="h-9 w-full rounded-lg border border-[#EAEAEA] bg-white pl-9 pr-3 text-[13px] text-[#0A0A0A] placeholder:text-[#A3A3A3] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
          />
        </div>
      </div>

      <div className="mb-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-[#737373]">
        <span className="font-medium text-[#525252]">Overall colour:</span>
        <span className="inline-flex items-center gap-1.5"><span className="h-2.5 w-2.5 rounded-sm bg-[#ECFDF5] ring-1 ring-[#A7F3D0]" /> 80–100</span>
        <span className="inline-flex items-center gap-1.5"><span className="h-2.5 w-2.5 rounded-sm bg-[#FEF3C7] ring-1 ring-[#FDE68A]" /> 60–79</span>
        <span className="inline-flex items-center gap-1.5"><span className="h-2.5 w-2.5 rounded-sm bg-[#FEE2E2] ring-1 ring-[#FECACA]" /> below 60</span>
        <span className="inline-flex items-center gap-1.5"><span className="h-2.5 w-2.5 rounded-sm bg-[#F5F5F5] ring-1 ring-[#EAEAEA]" /> no score</span>
      </div>

      {visibleMentees.length === 0 ? (
        <div className="rounded-[12px] border border-dashed border-[#E5E5E5] bg-[#FAFAFA] py-10 text-center text-[12.5px] text-[#A3A3A3]">
          No host matches “{query.trim()}”.
        </div>
      ) : (
        <div className="-mx-2 overflow-x-auto px-2">
          <table className="w-full border-separate border-spacing-0 text-sm">
            <thead>
              <tr>
                <th className="sticky left-0 z-20 border-b border-r border-[#EAEAEA] bg-white px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-[0.05em] text-[#737373]">
                  Host
                </th>
                {months.map((mo) => (
                  <th
                    key={mo.value}
                    className={`border-b border-[#EAEAEA] px-1 py-2 text-center text-[11px] font-semibold uppercase tracking-[0.04em] ${mo.value === currentMonthValue ? 'text-[#0A0A0A]' : 'text-[#A3A3A3]'}`}
                  >
                    {monthAbbr(mo)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {visibleMentees.map((m) => {
                const target = m.sales_target ?? null;
                return (
                  <tr key={m.id} className="group">
                    <td className="sticky left-0 z-10 border-b border-r border-[#F0F0F0] bg-white px-2 py-2 group-hover:bg-[#FAFAFA]">
                      <div className="flex items-center gap-2">
                        <span className="whitespace-nowrap text-[13px] font-semibold text-[#0A0A0A]">{m.name}</span>
                        {m.level && (
                          <span className="shrink-0 rounded-full px-1.5 py-0.5 text-[9.5px] font-semibold text-white" style={{ backgroundColor: m.level.color || '#10B981' }}>
                            {m.level.name}
                          </span>
                        )}
                        {m.status === 'graduated' && (
                          <span className="shrink-0 rounded-full bg-[#EEF2FF] px-1.5 py-0.5 text-[9px] font-medium uppercase tracking-wide text-[#4338CA]">Grad</span>
                        )}
                      </div>
                    </td>
                    {months.map((mo) => {
                      const cell = localScores[m.id]?.[mo.value];
                      const sales = cell && cell.sales !== null && cell.sales !== undefined ? cell.sales : null;
                      const attitude = cell && cell.attitude !== null && cell.attitude !== undefined ? cell.attitude : null;
                      const ov = cell ? overallKpi(cell.attitude, cell.sales, target) : null;
                      const tone = scoreTone(ov);
                      const hasData = sales !== null || attitude !== null;
                      const key = `${m.id}:${mo.value}`;
                      const st = saveState[key];
                      const isOpen = editing && editing.menteeId === m.id && editing.month.value === mo.value;
                      return (
                        <td key={mo.value} className="border-b border-[#F0F0F0] p-1 text-center">
                          <button
                            type="button"
                            onClick={(e) => setEditing({ menteeId: m.id, month: mo, rect: e.currentTarget.getBoundingClientRect() })}
                            title={`${m.name} · ${mo.label} · Sales ${sales !== null ? formatRM(sales) : '—'}${target ? ` / ${formatRM(target)}` : ''} · Attitude ${attitude ?? '—'} · Overall ${ov != null ? `${ov}%` : '—'}`}
                            className={[
                              'flex h-10 w-full min-w-[54px] flex-col items-center justify-center rounded-md leading-none transition-all',
                              tone.bg,
                              tone.text,
                              'hover:ring-2 hover:ring-[#10B981]/40',
                              st === 'saving' ? 'animate-pulse' : '',
                              st === 'error' ? 'ring-2 ring-[#F43F5E]' : '',
                              isOpen ? 'ring-2 ring-[#0A0A0A]' : '',
                            ].join(' ')}
                          >
                            {hasData ? (
                              <>
                                <span className="text-[12.5px] font-bold tabular-nums">{sales !== null ? formatRMCompact(sales) : '–'}</span>
                                <span className="mt-[3px] text-[9px] font-semibold uppercase tracking-[0.04em] tabular-nums opacity-70">A {attitude !== null ? attitude : '–'}</span>
                              </>
                            ) : (
                              <span className="text-[12px] font-bold">–</span>
                            )}
                          </button>
                        </td>
                      );
                    })}
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}

      {editing && editMentee && (
        <CellEditor
          key={`${editing.menteeId}:${editing.month.value}`}
          mentee={editMentee}
          month={editing.month}
          rect={editing.rect}
          target={editMentee.sales_target ?? null}
          initial={localScores[editing.menteeId]?.[editing.month.value] ?? {}}
          onSave={(draft) => saveCell(editing.menteeId, editing.month, draft)}
          onClose={() => setEditing(null)}
        />
      )}
    </section>
  );
}

/**
 * Floating editor for one matrix cell. Records Attitude + Sales + an optional note
 * for the host/month and commits once — on Done, Esc, outside-click, or switching
 * cells (unmount) — but only when something actually changed.
 */
function CellEditor({ mentee, month, rect, target, initial, onSave, onClose }) {
  const [draft, setDraft] = useState({
    attitude: initial.attitude ?? '',
    sales: initial.sales ?? '',
    notes: initial.notes ?? '',
  });
  const popRef = useRef(null);
  const draftRef = useRef(draft);
  draftRef.current = draft;
  const dirtyRef = useRef(false);
  const savedRef = useRef(false);

  const commit = () => {
    if (savedRef.current) return;
    savedRef.current = true;
    if (dirtyRef.current) onSave(draftRef.current);
  };

  // Commit any pending edit when the popover unmounts (covers Done, outside-click,
  // Esc, and clicking straight onto another cell).
  useEffect(() => () => commit(), []);

  useEffect(() => {
    const onKey = (e) => {
      if (e.key === 'Escape') onClose();
      if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') onClose();
    };
    const onDown = (e) => {
      if (popRef.current && !popRef.current.contains(e.target)) onClose();
    };
    document.addEventListener('keydown', onKey);
    document.addEventListener('mousedown', onDown);
    return () => {
      document.removeEventListener('keydown', onKey);
      document.removeEventListener('mousedown', onDown);
    };
  }, [onClose]);

  const change = (field, val) => {
    dirtyRef.current = true;
    setDraft((d) => ({ ...d, [field]: val }));
  };

  const overall = overallKpi(draft.attitude, draft.sales, target);
  const overallTone = scoreTone(overall);

  // Anchor under the clicked cell, clamped to the viewport (flips above if low).
  const W = 248;
  const vw = typeof window !== 'undefined' ? window.innerWidth : 1280;
  const vh = typeof window !== 'undefined' ? window.innerHeight : 800;
  const left = Math.max(12, Math.min(rect.left, vw - W - 12));
  const estH = 240;
  const top = rect.bottom + 6 + estH > vh - 12 ? Math.max(12, rect.top - estH - 6) : rect.bottom + 6;

  return (
    <div
      ref={popRef}
      style={{ position: 'fixed', left, top, width: W, zIndex: 60 }}
      className="rounded-[14px] border border-[#EAEAEA] bg-white p-4 shadow-[0_16px_48px_rgba(0,0,0,0.16)]"
    >
      <div className="mb-3 flex items-start justify-between gap-2">
        <div className="min-w-0">
          <div className="flex items-center gap-1.5">
            <span className="truncate text-[13px] font-semibold text-[#0A0A0A]">{mentee.name}</span>
            {mentee.level && (
              <span className="shrink-0 rounded-full px-1.5 py-0.5 text-[9px] font-semibold text-white" style={{ backgroundColor: mentee.level.color || '#10B981' }}>
                {mentee.level.name}
              </span>
            )}
          </div>
          <div className="text-[11px] text-[#A3A3A3]">{month.label}</div>
        </div>
        <div className="flex flex-col items-center leading-none">
          <span className="mb-1 text-[8.5px] font-semibold uppercase tracking-[0.06em] text-[#A3A3A3]">Overall</span>
          <span className={`inline-flex h-7 min-w-[48px] items-center justify-center rounded-md px-1.5 text-[12.5px] font-bold tabular-nums ${overallTone.bg} ${overallTone.text}`}>
            {overall != null ? `${overall}%` : '–'}
          </span>
        </div>
      </div>

      <div className="space-y-2.5">
        <label className="flex items-center justify-between gap-2">
          <span className="text-[11.5px] font-medium text-[#525252]">Attitude</span>
          <span className="flex items-center gap-1">
            <input
              type="number"
              min="1"
              max="100"
              autoFocus
              value={draft.attitude}
              onChange={(e) => change('attitude', e.target.value)}
              placeholder="–"
              className="h-9 w-20 rounded-lg border border-[#EAEAEA] bg-white px-2 text-center text-[14px] font-semibold tabular-nums text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
            />
            <span className="w-12 text-[11px] text-[#A3A3A3]">/ 100</span>
          </span>
        </label>
        <label className="flex items-center justify-between gap-2">
          <span className="text-[11.5px] font-medium text-[#525252]">Sales (RM)</span>
          <span className="flex items-center gap-1">
            <input
              type="number"
              min="0"
              step="0.01"
              value={draft.sales}
              onChange={(e) => change('sales', e.target.value)}
              placeholder="–"
              className="h-9 w-24 rounded-lg border border-[#EAEAEA] bg-white px-2 text-center text-[14px] font-semibold tabular-nums text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
            />
            <span className="w-16 whitespace-nowrap text-[11px] text-[#A3A3A3]">{target ? `/ ${formatRM(target)}` : 'RM'}</span>
          </span>
        </label>
        <input
          value={draft.notes}
          onChange={(e) => change('notes', e.target.value)}
          placeholder="Optional note…"
          className="w-full rounded-lg border border-[#EAEAEA] bg-white px-3 py-1.5 text-[12px] text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
        />
      </div>

      <div className="mt-3 flex items-center justify-between">
        <span className="text-[10.5px] text-[#A3A3A3]">{target ? `Sales target ${formatRM(target)}/mo` : 'No sales target on this level'}</span>
        <Button type="button" size="sm" onClick={onClose} className="h-8 gap-1.5 rounded-lg bg-[#0A0A0A] px-3 text-white hover:bg-[#262626]">
          <Check className="h-3.5 w-3.5" strokeWidth={2.5} /> Done
        </Button>
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
