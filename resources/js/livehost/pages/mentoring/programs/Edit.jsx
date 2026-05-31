import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
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
  Loader2,
  MessageSquare,
  Pause,
  Pencil,
  Play,
  Plus,
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

const ACTIVITY_COLOR = { green: '#10B981', amber: '#F59E0B', red: '#F43F5E', none: '#D4D4D4' };

const TABS = [
  { id: 'details', label: 'Details', icon: Settings2 },
  { id: 'stages', label: 'Stages', icon: Layers },
  { id: 'checklist', label: 'Checklist', icon: ListChecks },
  { id: 'activity', label: 'Activity', icon: MessageSquare },
  { id: 'performance', label: 'Monthly Performance', icon: Gauge },
];

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
  const { program, stages, assignableLeaders, activities, activityIndicator, mentees, performance } = usePage().props;
  const [activeTab, setActiveTab] = useState('details');
  const theme = STATUS_THEME[program.status] ?? STATUS_THEME.draft;
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
            <LifecycleActions program={program} />
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
                onClick={() => setActiveTab(tab.id)}
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

function LifecycleActions({ program }) {
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
      <Link href={`/livehost/mentoring/mentees?program=${program.id}`}>
        <Button size="sm" variant="outline" className="h-9 gap-1.5 rounded-lg">
          <Users className="h-3.5 w-3.5" strokeWidth={2} />
          Mentee board
        </Button>
      </Link>
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
            <Input name="title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
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
            <Input type="date" name="starts_at" value={form.data.starts_at} onChange={(e) => form.setData('starts_at', e.target.value)} />
          </Field>
          <Field label="Ends at" error={form.errors.ends_at}>
            <Input type="date" name="ends_at" value={form.data.ends_at} onChange={(e) => form.setData('ends_at', e.target.value)} />
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
            <Button size="sm" variant="outline" className="h-8 gap-1.5 rounded-lg" onClick={() => setAdding(true)}>
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
                        <Input value={editingDraft.name} onChange={(e) => setEditingDraft((d) => ({ ...d, name: e.target.value }))} autoFocus />
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
                  <Input value={addDraft.name} onChange={(e) => setAddDraft((d) => ({ ...d, name: e.target.value }))} placeholder="e.g. Shadow live" autoFocus />
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
        <Button size="sm" variant="outline" className="h-8 gap-1.5 rounded-lg" onClick={addItem}>
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
              <Input value={item.title} onChange={(e) => updateTitle(i, e.target.value)} placeholder="Task title" className="flex-1" />
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

function MonthlyPerformanceTab({ performance }) {
  const months = performance?.months ?? [];
  const mentees = performance?.mentees ?? [];
  const chronological = useMemo(() => [...months].reverse(), [months]);
  const [selected, setSelected] = useState(months[0]?.value ?? '');
  const selMonth = months.find((m) => m.value === selected) ?? months[0] ?? null;
  // Local copy of all scores so the trend chips reflect a save immediately.
  const [localScores, setLocalScores] = useState(() => {
    const map = {};
    mentees.forEach((m) => { map[m.id] = { ...(m.scores ?? {}) }; });
    return map;
  });
  const [edits, setEdits] = useState({});

  useEffect(() => {
    const init = {};
    mentees.forEach((m) => {
      const cell = localScores[m.id]?.[selected] ?? {};
      init[m.id] = { score: cell.score ?? '', notes: cell.notes ?? '', state: 'idle' };
    });
    setEdits(init);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [selected]);

  const setField = (id, field, val) => setEdits((p) => ({ ...p, [id]: { ...(p[id] || {}), [field]: val } }));

  const save = (id) => {
    if (!selMonth) return;
    const e = edits[id] || {};
    const scoreVal = e.score === '' || e.score === null ? null : Number(e.score);
    setEdits((p) => ({ ...p, [id]: { ...p[id], state: 'saving' } }));
    router.patch(
      `/livehost/mentoring/mentees/${id}/monthly-score`,
      { year: selMonth.year, month: selMonth.month, score: scoreVal, notes: e.notes || null },
      {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
          setEdits((p) => ({ ...p, [id]: { ...p[id], state: 'saved' } }));
          setLocalScores((p) => ({ ...p, [id]: { ...(p[id] || {}), [selected]: { score: scoreVal, notes: e.notes || null } } }));
        },
        onError: () => setEdits((p) => ({ ...p, [id]: { ...p[id], state: 'error' } })),
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

  return (
    <section className="rounded-[16px] border border-[#EAEAEA] bg-white p-6 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
      <div className="mb-5 flex items-center gap-2.5">
        <div className="grid h-8 w-8 place-items-center rounded-lg bg-[#F5F5F5] text-[#525252]">
          <Gauge className="h-4 w-4" strokeWidth={2} />
        </div>
        <div>
          <h2 className="text-[15px] font-semibold tracking-[-0.01em] text-[#0A0A0A]">Monthly performance</h2>
          <p className="mt-0.5 text-[12px] text-[#737373]">Score each mentee 1–100 for the selected month. Changes auto-save; the chips show their trend.</p>
        </div>
      </div>

      <div className="mb-4 inline-flex flex-wrap gap-1 rounded-lg bg-[#F5F5F5] p-1">
        {months.map((m) => (
          <button
            key={m.value}
            type="button"
            onClick={() => setSelected(m.value)}
            className={[
              'rounded-md px-3 py-1.5 text-[12.5px] font-medium transition-all',
              selected === m.value ? 'bg-white text-[#0A0A0A] shadow-[0_1px_2px_rgba(0,0,0,0.06)]' : 'text-[#737373] hover:text-[#404040]',
            ].join(' ')}
          >
            {m.label}
          </button>
        ))}
      </div>

      <ul className="divide-y divide-[#F0F0F0]">
        {mentees.map((m) => {
          const e = edits[m.id] || { score: '', notes: '', state: 'idle' };
          return (
            <li key={m.id} className="py-4">
              <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="min-w-0">
                  <div className="flex items-center gap-2">
                    <span className="text-[13.5px] font-semibold text-[#0A0A0A]">{m.name}</span>
                    {m.level && (
                      <span className="rounded-full px-1.5 py-0.5 text-[10px] font-semibold text-white" style={{ backgroundColor: m.level.color || '#10B981' }}>
                        {m.level.name}
                      </span>
                    )}
                    {m.status === 'graduated' && (
                      <span className="rounded-full bg-[#EEF2FF] px-1.5 py-0.5 text-[9.5px] font-medium uppercase tracking-wide text-[#4338CA]">Graduated</span>
                    )}
                  </div>
                  <div className="mt-1.5 flex items-center gap-1">
                    {chronological.map((mo) => {
                      const sc = localScores[m.id]?.[mo.value]?.score;
                      const tone = scoreTone(sc);
                      return (
                        <span
                          key={mo.value}
                          title={`${mo.label}: ${sc ?? 'no score'}`}
                          className={`inline-flex h-5 min-w-[30px] items-center justify-center rounded px-1 text-[10px] font-semibold tabular-nums ${tone.bg} ${tone.text}`}
                        >
                          {sc ?? '–'}
                        </span>
                      );
                    })}
                  </div>
                </div>
                <div className="flex items-center gap-2">
                  <div className="flex items-center gap-1">
                    <input
                      type="number"
                      min="1"
                      max="100"
                      value={e.score}
                      onChange={(ev) => setField(m.id, 'score', ev.target.value)}
                      onBlur={() => save(m.id)}
                      placeholder="–"
                      className="h-9 w-16 rounded-lg border border-[#EAEAEA] bg-white px-2 text-center text-[14px] font-semibold tabular-nums text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
                    />
                    <span className="text-[12px] text-[#A3A3A3]">/100</span>
                  </div>
                  <span className="inline-flex w-14 justify-start text-[11px]">
                    {e.state === 'saving' && <Loader2 className="h-3.5 w-3.5 animate-spin text-[#737373]" />}
                    {e.state === 'saved' && <span className="inline-flex items-center gap-1 text-[#047857]"><Check className="h-3 w-3" strokeWidth={3} /> Saved</span>}
                    {e.state === 'error' && <span className="text-[#B91C1C]">Error</span>}
                  </span>
                </div>
              </div>
              <input
                value={e.notes}
                onChange={(ev) => setField(m.id, 'notes', ev.target.value)}
                onBlur={() => save(m.id)}
                placeholder="Optional note for this month…"
                className="mt-2 w-full rounded-lg border border-[#EAEAEA] bg-white px-3 py-1.5 text-[12.5px] text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
              />
            </li>
          );
        })}
      </ul>
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
        <Button size="sm" variant="outline" className="h-8 gap-1.5 rounded-lg" onClick={() => setLogging(true)}>
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
