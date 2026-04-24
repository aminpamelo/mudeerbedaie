import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import {
  ArrowDown,
  ArrowLeft,
  ArrowUp,
  Check,
  Copy,
  ExternalLink,
  Pause,
  Pencil,
  Play,
  Plus,
  Star,
  Trash2,
  X,
  XCircle,
} from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import { Button } from '@/livehost/components/ui/button';
import { Input } from '@/livehost/components/ui/input';
import { Label } from '@/livehost/components/ui/label';

const STATUS_BADGE = {
  draft: 'bg-[#F5F5F5] text-[#525252]',
  open: 'bg-[#ECFDF5] text-[#059669]',
  paused: 'bg-[#FEF3C7] text-[#B45309]',
  closed: 'bg-[#FFE4E6] text-[#9F1239]',
};

function toLocalDateTime(iso) {
  if (!iso) {
    return '';
  }
  try {
    const d = new Date(iso);
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
  } catch {
    return '';
  }
}

export default function CampaignEdit() {
  const { campaign, stages } = usePage().props;

  return (
    <>
      <Head title={`Edit ${campaign.title}`} />
      <TopBar
        breadcrumb={['Live Host Desk', 'Recruitment', 'Campaigns', campaign.title]}
        actions={
          <Link href="/livehost/recruitment/campaigns">
            <Button variant="ghost" className="gap-1.5 text-[#737373] hover:text-[#0A0A0A]">
              <ArrowLeft className="h-3.5 w-3.5" />
              Back to campaigns
            </Button>
          </Link>
        }
      />

      <div className="space-y-6 p-8">
        <div className="flex flex-wrap items-start justify-between gap-6">
          <div className="min-w-0">
            <div className="flex items-center gap-2">
              <h1 className="truncate text-3xl font-semibold leading-[1.1] tracking-[-0.03em] text-[#0A0A0A]">
                {campaign.title}
              </h1>
              <span
                className={`inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium ${STATUS_BADGE[campaign.status] ?? STATUS_BADGE.draft}`}
              >
                {campaign.status}
              </span>
            </div>
            <p className="mt-1.5 text-sm text-[#737373]">
              {campaign.applicants_count} applicant{campaign.applicants_count === 1 ? '' : 's'}
              {' · '}
              /{campaign.slug}
            </p>
          </div>
          <LifecycleActions campaign={campaign} />
        </div>

        <CampaignDetailsForm campaign={campaign} />
        <StageEditor campaign={campaign} stages={stages} />
      </div>
    </>
  );
}

CampaignEdit.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;

function LifecycleActions({ campaign }) {
  const [copied, setCopied] = useState(false);

  const copyLink = async () => {
    if (!campaign.public_url || typeof navigator === 'undefined') {
      return;
    }
    try {
      await navigator.clipboard.writeText(campaign.public_url);
      setCopied(true);
      window.setTimeout(() => setCopied(false), 1500);
    } catch {
      /* noop */
    }
  };

  const transition = (verb, confirmText) => {
    if (confirmText && !window.confirm(confirmText)) {
      return;
    }
    router.patch(
      `/livehost/recruitment/campaigns/${campaign.id}/${verb}`,
      {},
      {
        preserveScroll: true,
      }
    );
  };

  const destroy = () => {
    if (!window.confirm(`Delete "${campaign.title}"? This cannot be undone.`)) {
      return;
    }
    router.delete(`/livehost/recruitment/campaigns/${campaign.id}`);
  };

  return (
    <div className="flex flex-wrap items-center gap-2">
      {campaign.public_url && (
        <>
          <a
            href={campaign.public_url}
            target="_blank"
            rel="noreferrer"
            className="inline-flex h-9 items-center gap-1.5 rounded-lg border border-[#EAEAEA] bg-white px-3 text-[12.5px] font-medium text-[#525252] hover:bg-[#F5F5F5]"
          >
            <ExternalLink className="h-3.5 w-3.5" strokeWidth={2} />
            Public page
          </a>
          <button
            type="button"
            onClick={copyLink}
            className="inline-flex h-9 items-center gap-1.5 rounded-lg border border-[#EAEAEA] bg-white px-3 text-[12.5px] font-medium text-[#525252] hover:bg-[#F5F5F5]"
          >
            {copied ? (
              <>
                <Check className="h-3.5 w-3.5 text-[#059669]" strokeWidth={2.25} />
                Copied
              </>
            ) : (
              <>
                <Copy className="h-3.5 w-3.5" strokeWidth={2} />
                Copy link
              </>
            )}
          </button>
        </>
      )}

      {campaign.status === 'draft' && (
        <Button
          size="sm"
          className="h-9 gap-1.5 rounded-lg bg-[#059669] text-white hover:bg-[#047857]"
          onClick={() => transition('publish')}
        >
          <Play className="h-3.5 w-3.5" strokeWidth={2.25} />
          Publish
        </Button>
      )}
      {campaign.status === 'open' && (
        <Button
          size="sm"
          variant="outline"
          className="h-9 gap-1.5 rounded-lg border-[#FEF3C7] bg-[#FFFBEB] text-[#B45309] hover:bg-[#FEF3C7]"
          onClick={() => transition('pause')}
        >
          <Pause className="h-3.5 w-3.5" strokeWidth={2.25} />
          Pause
        </Button>
      )}
      {(campaign.status === 'open' || campaign.status === 'paused') && (
        <Button
          size="sm"
          variant="outline"
          className="h-9 gap-1.5 rounded-lg border-[#FFE4E6] bg-[#FFF1F2] text-[#9F1239] hover:bg-[#FFE4E6]"
          onClick={() =>
            transition('close', `Close "${campaign.title}"? Closed campaigns cannot be re-opened.`)
          }
        >
          <XCircle className="h-3.5 w-3.5" strokeWidth={2.25} />
          Close
        </Button>
      )}
      {campaign.applicants_count === 0 && (
        <Button
          size="sm"
          variant="ghost"
          className="h-9 gap-1.5 rounded-lg text-[#F43F5E] hover:bg-[#FFF1F2]"
          onClick={destroy}
        >
          <Trash2 className="h-3.5 w-3.5" strokeWidth={2} />
          Delete
        </Button>
      )}
    </div>
  );
}

function CampaignDetailsForm({ campaign }) {
  const form = useForm({
    title: campaign.title ?? '',
    slug: campaign.slug ?? '',
    description: campaign.description ?? '',
    target_count: campaign.target_count ?? '',
    opens_at: toLocalDateTime(campaign.opens_at),
    closes_at: toLocalDateTime(campaign.closes_at),
  });

  const submit = (e) => {
    e.preventDefault();
    form.put(`/livehost/recruitment/campaigns/${campaign.id}`, {
      preserveScroll: true,
    });
  };

  return (
    <section className="rounded-[16px] border border-[#EAEAEA] bg-white p-6 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
      <div className="mb-4">
        <h2 className="text-[15px] font-semibold tracking-[-0.01em] text-[#0A0A0A]">
          Campaign details
        </h2>
        <p className="mt-0.5 text-xs text-[#737373]">
          Changing the slug updates the public URL.
        </p>
      </div>

      <form onSubmit={submit} className="space-y-5">
        <Field label="Campaign title" error={form.errors.title}>
          <Input
            name="title"
            value={form.data.title}
            onChange={(e) => form.setData('title', e.target.value)}
          />
        </Field>
        <Field label="URL slug" error={form.errors.slug}>
          <Input
            name="slug"
            value={form.data.slug}
            onChange={(e) => form.setData('slug', e.target.value)}
          />
        </Field>
        <Field label="Description" error={form.errors.description}>
          <textarea
            name="description"
            value={form.data.description}
            onChange={(e) => form.setData('description', e.target.value)}
            rows={5}
            className="w-full rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
          />
        </Field>

        <div className="grid grid-cols-3 gap-4">
          <Field label="Target hires" error={form.errors.target_count}>
            <Input
              type="number"
              min="1"
              name="target_count"
              value={form.data.target_count ?? ''}
              onChange={(e) => form.setData('target_count', e.target.value)}
            />
          </Field>
          <Field label="Opens at" error={form.errors.opens_at}>
            <Input
              type="datetime-local"
              name="opens_at"
              value={form.data.opens_at}
              onChange={(e) => form.setData('opens_at', e.target.value)}
            />
          </Field>
          <Field label="Closes at" error={form.errors.closes_at}>
            <Input
              type="datetime-local"
              name="closes_at"
              value={form.data.closes_at}
              onChange={(e) => form.setData('closes_at', e.target.value)}
            />
          </Field>
        </div>

        <div className="flex items-center justify-end gap-2 border-t border-[#F0F0F0] pt-4">
          <Button type="submit" disabled={form.processing}>
            {form.processing ? 'Saving…' : 'Save changes'}
          </Button>
        </div>
      </form>
    </section>
  );
}

function StageEditor({ campaign, stages: initialStages }) {
  const [stages, setStages] = useState(initialStages ?? []);
  const [editingId, setEditingId] = useState(null);
  const [editingDraft, setEditingDraft] = useState({ name: '', description: '', is_final: false });
  const [adding, setAdding] = useState(false);
  const [addDraft, setAddDraft] = useState({ name: '', description: '', is_final: false });

  useEffect(() => {
    setStages(initialStages ?? []);
  }, [initialStages]);

  const sorted = useMemo(
    () => [...stages].sort((a, b) => a.position - b.position),
    [stages]
  );

  const beginEdit = (stage) => {
    setEditingId(stage.id);
    setEditingDraft({
      name: stage.name ?? '',
      description: stage.description ?? '',
      is_final: !!stage.is_final,
    });
  };

  const cancelEdit = () => {
    setEditingId(null);
    setEditingDraft({ name: '', description: '', is_final: false });
  };

  const saveEdit = (stage) => {
    router.put(
      `/livehost/recruitment/campaigns/${campaign.id}/stages/${stage.id}`,
      editingDraft,
      {
        preserveScroll: true,
        onSuccess: () => cancelEdit(),
      }
    );
  };

  const destroy = (stage) => {
    if (stage.applicants_count > 0) {
      window.alert('This stage still has applicants. Move them to another stage first.');
      return;
    }
    if (!window.confirm(`Delete the "${stage.name}" stage?`)) {
      return;
    }
    router.delete(`/livehost/recruitment/campaigns/${campaign.id}/stages/${stage.id}`, {
      preserveScroll: true,
    });
  };

  const reorder = (stage, direction) => {
    const index = sorted.findIndex((s) => s.id === stage.id);
    if (index < 0) {
      return;
    }
    const target = direction === 'up' ? index - 1 : index + 1;
    if (target < 0 || target >= sorted.length) {
      return;
    }
    const next = [...sorted];
    [next[index], next[target]] = [next[target], next[index]];
    const ids = next.map((s) => s.id);
    // Optimistic update
    setStages(next.map((s, i) => ({ ...s, position: i + 1 })));
    router.put(
      `/livehost/recruitment/campaigns/${campaign.id}/stages/reorder`,
      { stage_ids: ids },
      { preserveScroll: true }
    );
  };

  const addStage = () => {
    router.post(`/livehost/recruitment/campaigns/${campaign.id}/stages`, addDraft, {
      preserveScroll: true,
      onSuccess: () => {
        setAdding(false);
        setAddDraft({ name: '', description: '', is_final: false });
      },
    });
  };

  return (
    <section className="rounded-[16px] border border-[#EAEAEA] bg-white p-6 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
      <div className="mb-4 flex items-center justify-between">
        <div>
          <h2 className="text-[15px] font-semibold tracking-[-0.01em] text-[#0A0A0A]">
            Stages
          </h2>
          <p className="mt-0.5 text-xs text-[#737373]">
            Exactly one stage must be marked as final. Applicants at the final stage can be hired.
          </p>
        </div>
        {!adding && (
          <Button
            size="sm"
            variant="outline"
            className="h-8 gap-1.5 rounded-lg"
            onClick={() => setAdding(true)}
          >
            <Plus className="h-3.5 w-3.5" strokeWidth={2.25} />
            Add stage
          </Button>
        )}
      </div>

      <ul className="divide-y divide-[#F0F0F0] rounded-lg border border-[#EAEAEA]">
        {sorted.map((stage, index) => {
          const isEditing = editingId === stage.id;
          return (
            <li key={stage.id} className="p-3">
              {isEditing ? (
                <div className="space-y-3">
                  <div className="grid grid-cols-3 gap-3">
                    <div className="col-span-2">
                      <Label className="text-[12px] font-medium text-[#0A0A0A]">Name</Label>
                      <Input
                        value={editingDraft.name}
                        onChange={(e) =>
                          setEditingDraft((d) => ({ ...d, name: e.target.value }))
                        }
                      />
                    </div>
                    <label className="mt-6 inline-flex items-center gap-2 text-[12.5px] text-[#0A0A0A]">
                      <input
                        type="checkbox"
                        checked={editingDraft.is_final}
                        onChange={(e) =>
                          setEditingDraft((d) => ({ ...d, is_final: e.target.checked }))
                        }
                      />
                      Final stage
                    </label>
                  </div>
                  <div>
                    <Label className="text-[12px] font-medium text-[#0A0A0A]">Description</Label>
                    <textarea
                      value={editingDraft.description}
                      onChange={(e) =>
                        setEditingDraft((d) => ({ ...d, description: e.target.value }))
                      }
                      rows={2}
                      className="w-full rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
                    />
                  </div>
                  <div className="flex items-center justify-end gap-2">
                    <Button type="button" variant="ghost" size="sm" onClick={cancelEdit}>
                      <X className="mr-1 h-3.5 w-3.5" strokeWidth={2.25} />
                      Cancel
                    </Button>
                    <Button type="button" size="sm" onClick={() => saveEdit(stage)}>
                      <Check className="mr-1 h-3.5 w-3.5" strokeWidth={2.25} />
                      Save stage
                    </Button>
                  </div>
                </div>
              ) : (
                <div className="flex items-center gap-3">
                  <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-[#F5F5F5] text-[12px] font-semibold text-[#525252]">
                    {stage.position}
                  </div>
                  <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2">
                      <span className="truncate text-[13.5px] font-medium text-[#0A0A0A]">
                        {stage.name}
                      </span>
                      {stage.is_final && (
                        <span className="inline-flex items-center gap-1 rounded-full bg-[#ECFDF5] px-2 py-0.5 text-[11px] font-medium text-[#059669]">
                          <Star className="h-3 w-3" strokeWidth={2.25} />
                          Final
                        </span>
                      )}
                    </div>
                    {stage.description && (
                      <div className="mt-0.5 truncate text-[11.5px] text-[#737373]">
                        {stage.description}
                      </div>
                    )}
                    <div className="mt-0.5 text-[11px] text-[#737373]">
                      {stage.applicants_count} applicant{stage.applicants_count === 1 ? '' : 's'}
                    </div>
                  </div>
                  <div className="inline-flex items-center gap-1">
                    <button
                      type="button"
                      onClick={() => reorder(stage, 'up')}
                      disabled={index === 0}
                      className="inline-flex h-8 w-8 items-center justify-center rounded-md text-[#737373] hover:bg-[#F0F0F0] hover:text-[#0A0A0A] disabled:opacity-40"
                      title="Move up"
                    >
                      <ArrowUp className="h-[14px] w-[14px]" strokeWidth={2} />
                    </button>
                    <button
                      type="button"
                      onClick={() => reorder(stage, 'down')}
                      disabled={index === sorted.length - 1}
                      className="inline-flex h-8 w-8 items-center justify-center rounded-md text-[#737373] hover:bg-[#F0F0F0] hover:text-[#0A0A0A] disabled:opacity-40"
                      title="Move down"
                    >
                      <ArrowDown className="h-[14px] w-[14px]" strokeWidth={2} />
                    </button>
                    <button
                      type="button"
                      onClick={() => beginEdit(stage)}
                      className="inline-flex h-8 w-8 items-center justify-center rounded-md text-[#737373] hover:bg-[#F0F0F0] hover:text-[#0A0A0A]"
                      title="Edit"
                    >
                      <Pencil className="h-[14px] w-[14px]" strokeWidth={2} />
                    </button>
                    <button
                      type="button"
                      onClick={() => destroy(stage)}
                      className="inline-flex h-8 w-8 items-center justify-center rounded-md text-[#737373] hover:bg-[#FFF1F2] hover:text-[#F43F5E]"
                      title="Delete"
                    >
                      <Trash2 className="h-[14px] w-[14px]" strokeWidth={2} />
                    </button>
                  </div>
                </div>
              )}
            </li>
          );
        })}

        {adding && (
          <li className="space-y-3 bg-[#FAFAFA] p-3">
            <div className="grid grid-cols-3 gap-3">
              <div className="col-span-2">
                <Label className="text-[12px] font-medium text-[#0A0A0A]">Name</Label>
                <Input
                  value={addDraft.name}
                  onChange={(e) => setAddDraft((d) => ({ ...d, name: e.target.value }))}
                  placeholder="Final interview"
                />
              </div>
              <label className="mt-6 inline-flex items-center gap-2 text-[12.5px] text-[#0A0A0A]">
                <input
                  type="checkbox"
                  checked={addDraft.is_final}
                  onChange={(e) =>
                    setAddDraft((d) => ({ ...d, is_final: e.target.checked }))
                  }
                />
                Final stage
              </label>
            </div>
            <div>
              <Label className="text-[12px] font-medium text-[#0A0A0A]">Description</Label>
              <textarea
                value={addDraft.description}
                onChange={(e) => setAddDraft((d) => ({ ...d, description: e.target.value }))}
                rows={2}
                className="w-full rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
              />
            </div>
            <div className="flex items-center justify-end gap-2">
              <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={() => {
                  setAdding(false);
                  setAddDraft({ name: '', description: '', is_final: false });
                }}
              >
                Cancel
              </Button>
              <Button type="button" size="sm" onClick={addStage} disabled={!addDraft.name.trim()}>
                Add stage
              </Button>
            </div>
          </li>
        )}
      </ul>
    </section>
  );
}

function Field({ label, error, children }) {
  return (
    <div className="space-y-1.5">
      <Label className="text-[13px] font-medium text-[#0A0A0A]">{label}</Label>
      {children}
      {error && <p className="mt-1 text-xs text-[#F43F5E]">{error}</p>}
    </div>
  );
}
