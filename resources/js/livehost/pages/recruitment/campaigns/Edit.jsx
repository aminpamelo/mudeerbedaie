import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import {
  ArrowDown,
  ArrowLeft,
  ArrowUp,
  Calendar,
  Check,
  Copy,
  ExternalLink,
  FileText,
  Globe,
  Layers,
  Pause,
  Pencil,
  Play,
  Plus,
  Settings2,
  Star,
  Target,
  Trash2,
  Undo2,
  Users,
  X,
  XCircle,
} from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import { Button } from '@/livehost/components/ui/button';
import { Input } from '@/livehost/components/ui/input';
import { Label } from '@/livehost/components/ui/label';
import FormBuilder from '@/livehost/components/form-builder/FormBuilder';

const STATUS_THEME = {
  draft:  { pill: 'bg-[#F5F5F5] text-[#525252] border-[#EAEAEA]',  dot: '#A3A3A3', live: false },
  open:   { pill: 'bg-[#ECFDF5] text-[#059669] border-[#A7F3D0]',  dot: '#10B981', live: true  },
  paused: { pill: 'bg-[#FFFBEB] text-[#B45309] border-[#FDE68A]',  dot: '#F59E0B', live: false },
  closed: { pill: 'bg-[#FFF1F2] text-[#9F1239] border-[#FECDD3]',  dot: '#F43F5E', live: false },
};

const DEFAULT_EMPTY_SCHEMA = { version: 1, pages: [] };

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

function formatDateLabel(iso) {
  if (!iso) return null;
  try {
    return new Date(iso).toLocaleString(undefined, {
      month: 'short',
      day: 'numeric',
      hour: 'numeric',
      minute: '2-digit',
    });
  } catch {
    return null;
  }
}

export default function CampaignEdit() {
  const { campaign, stages } = usePage().props;
  const theme = STATUS_THEME[campaign.status] ?? STATUS_THEME.draft;
  const opensLabel = formatDateLabel(campaign.opens_at);
  const closesLabel = formatDateLabel(campaign.closes_at);
  const [activeTab, setActiveTab] = useState('campaign');

  const form = useForm({
    title: campaign.title ?? '',
    slug: campaign.slug ?? '',
    description: campaign.description ?? '',
    target_count: campaign.target_count ?? '',
    opens_at: toLocalDateTime(campaign.opens_at),
    closes_at: toLocalDateTime(campaign.closes_at),
    form_schema: campaign.form_schema ?? DEFAULT_EMPTY_SCHEMA,
  });

  const submit = (e) => {
    e?.preventDefault?.();
    form.put(`/livehost/recruitment/campaigns/${campaign.id}`, { preserveScroll: true });
  };

  const tabs = [
    { id: 'campaign', label: 'Campaign', icon: Settings2 },
    { id: 'form', label: 'Application form', icon: FileText },
  ];

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

      <div className="space-y-6 p-8 pb-24">
        {/* ───────────────────── Hero ───────────────────── */}
        <section className="relative overflow-hidden rounded-[20px] border border-[#EAEAEA] bg-white p-6 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
          {/* atmospheric corner glow */}
          <div
            className="pointer-events-none absolute -top-24 -right-24 h-64 w-64 rounded-full opacity-30"
            style={{
              background:
                campaign.status === 'open'
                  ? 'radial-gradient(circle, rgba(16,185,129,0.18), transparent 70%)'
                  : campaign.status === 'paused'
                    ? 'radial-gradient(circle, rgba(245,158,11,0.16), transparent 70%)'
                    : campaign.status === 'closed'
                      ? 'radial-gradient(circle, rgba(244,63,94,0.14), transparent 70%)'
                      : 'radial-gradient(circle, rgba(115,115,115,0.10), transparent 70%)',
            }}
          />
          <div className="relative flex flex-wrap items-start justify-between gap-6">
            <div className="min-w-0 flex-1">
              <div className="flex items-center gap-2">
                <span
                  className={`inline-flex h-[22px] items-center gap-1.5 rounded-full border px-2.5 text-[11px] font-semibold uppercase tracking-[0.06em] ${theme.pill}`}
                >
                  <span className="relative flex h-1.5 w-1.5">
                    {theme.live && (
                      <span
                        className="absolute inline-flex h-full w-full rounded-full opacity-75"
                        style={{ background: theme.dot, animation: 'pulse-dot 1.6s ease-out infinite' }}
                      />
                    )}
                    <span className="relative inline-flex h-1.5 w-1.5 rounded-full" style={{ background: theme.dot }} />
                  </span>
                  {campaign.status}
                </span>
                <span className="text-[12px] text-[#A3A3A3]">/ Campaign</span>
              </div>

              <h1 className="mt-3 truncate text-[28px] font-semibold leading-[1.1] tracking-[-0.03em] text-[#0A0A0A]">
                {campaign.title}
              </h1>

              <div className="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-[12.5px] text-[#737373]">
                <span className="inline-flex items-center gap-1.5">
                  <Users className="h-[13px] w-[13px]" strokeWidth={2} />
                  <span className="font-medium tabular-nums text-[#0A0A0A]">{campaign.applicants_count}</span>
                  applicant{campaign.applicants_count === 1 ? '' : 's'}
                </span>
                {opensLabel && (
                  <span className="inline-flex items-center gap-1.5">
                    <Calendar className="h-[13px] w-[13px]" strokeWidth={2} />
                    Opens <span className="font-medium text-[#0A0A0A]">{opensLabel}</span>
                  </span>
                )}
                {closesLabel && (
                  <span className="inline-flex items-center gap-1.5">
                    <Calendar className="h-[13px] w-[13px]" strokeWidth={2} />
                    Closes <span className="font-medium text-[#0A0A0A]">{closesLabel}</span>
                  </span>
                )}
                <span className="inline-flex items-center gap-1.5">
                  <Globe className="h-[13px] w-[13px]" strokeWidth={2} />
                  <span className="font-mono text-[11.5px] text-[#525252]">/{campaign.slug}</span>
                </span>
              </div>
            </div>
            <LifecycleActions campaign={campaign} />
          </div>
        </section>

        {/* ───────────────────── Tabs ───────────────────── */}
        <div className="flex items-center gap-6 border-b border-[#EAEAEA]">
          {tabs.map((tab) => {
            const Icon = tab.icon;
            const isActive = activeTab === tab.id;
            return (
              <button
                key={tab.id}
                type="button"
                onClick={() => setActiveTab(tab.id)}
                className={[
                  '-mb-px inline-flex items-center gap-1.5 border-b-2 px-1 pb-3 text-sm font-medium transition-colors',
                  isActive
                    ? 'border-[#0A0A0A] text-[#0A0A0A]'
                    : 'border-transparent text-[#737373] hover:text-[#0A0A0A]',
                ].join(' ')}
              >
                <Icon className="h-3.5 w-3.5" strokeWidth={2} />
                {tab.label}
              </button>
            );
          })}
        </div>

        {activeTab === 'campaign' && (
          <>
            <CampaignDetailsForm campaign={campaign} form={form} onSubmit={submit} />
            <StageEditor campaign={campaign} stages={stages} />
          </>
        )}

        {activeTab === 'form' && (
          <FormBuilderTab campaign={campaign} form={form} />
        )}

        {/* Sticky save bar — shared across tabs, visible whenever dirty */}
        {form.isDirty && (
          <div className="pointer-events-none fixed inset-x-0 bottom-4 z-40 flex justify-center px-4">
            <div className="pointer-events-auto inline-flex items-center gap-3 rounded-full border border-[#EAEAEA] bg-white/95 py-2 pl-4 pr-2 shadow-[0_8px_24px_rgba(0,0,0,0.08)] backdrop-blur">
              <span className="inline-flex h-1.5 w-1.5 rounded-full bg-[#F59E0B]" />
              <span className="text-[12.5px] font-medium text-[#525252]">Unsaved changes</span>
              <Button
                type="button"
                variant="ghost"
                size="sm"
                className="h-8 gap-1.5 rounded-full text-[#737373] hover:bg-[#F5F5F5] hover:text-[#0A0A0A]"
                onClick={() => form.reset()}
              >
                <Undo2 className="h-3.5 w-3.5" strokeWidth={2} />
                Discard
              </Button>
              <Button
                type="button"
                size="sm"
                disabled={form.processing}
                onClick={submit}
                className="h-8 gap-1.5 rounded-full bg-[#0A0A0A] px-3.5 text-white shadow-[0_1px_3px_rgba(0,0,0,0.18)] hover:bg-[#262626]"
              >
                {form.processing ? (
                  'Saving…'
                ) : (
                  <>
                    <Check className="h-3.5 w-3.5" strokeWidth={2.5} />
                    Save changes
                  </>
                )}
              </Button>
            </div>
          </div>
        )}
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
    router.patch(`/livehost/recruitment/campaigns/${campaign.id}/${verb}`, {}, { preserveScroll: true });
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
        <div className="inline-flex items-center rounded-lg border border-[#EAEAEA] bg-white shadow-[0_1px_2px_rgba(0,0,0,0.03)]">
          <a
            href={campaign.public_url}
            target="_blank"
            rel="noreferrer"
            className="inline-flex h-9 items-center gap-1.5 rounded-l-lg px-3 text-[12.5px] font-medium text-[#525252] hover:bg-[#F5F5F5] hover:text-[#0A0A0A]"
          >
            <ExternalLink className="h-3.5 w-3.5" strokeWidth={2} />
            Public page
          </a>
          <span className="h-4 w-px bg-[#EAEAEA]" />
          <button
            type="button"
            onClick={copyLink}
            className="inline-flex h-9 items-center gap-1.5 rounded-r-lg px-3 text-[12.5px] font-medium text-[#525252] hover:bg-[#F5F5F5] hover:text-[#0A0A0A]"
          >
            {copied ? (
              <>
                <Check className="h-3.5 w-3.5 text-[#059669]" strokeWidth={2.5} />
                <span className="text-[#059669]">Copied</span>
              </>
            ) : (
              <>
                <Copy className="h-3.5 w-3.5" strokeWidth={2} />
                Copy link
              </>
            )}
          </button>
        </div>
      )}

      {campaign.status === 'draft' && (
        <Button
          size="sm"
          className="h-9 gap-1.5 rounded-lg bg-[#059669] text-white shadow-[0_1px_3px_rgba(5,150,105,0.3)] hover:bg-[#047857]"
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
          className="h-9 gap-1.5 rounded-lg border-[#FDE68A] bg-[#FFFBEB] text-[#B45309] hover:bg-[#FEF3C7]"
          onClick={() => transition('pause')}
        >
          <Pause className="h-3.5 w-3.5" strokeWidth={2.25} />
          Pause
        </Button>
      )}
      {campaign.status === 'paused' && (
        <Button
          size="sm"
          className="h-9 gap-1.5 rounded-lg bg-[#059669] text-white shadow-[0_1px_3px_rgba(5,150,105,0.3)] hover:bg-[#047857]"
          onClick={() => transition('resume')}
        >
          <Play className="h-3.5 w-3.5" strokeWidth={2.25} />
          Resume
        </Button>
      )}
      {(campaign.status === 'open' || campaign.status === 'paused') && (
        <Button
          size="sm"
          variant="outline"
          className="h-9 gap-1.5 rounded-lg border-[#FECDD3] bg-[#FFF1F2] text-[#9F1239] hover:bg-[#FFE4E6]"
          onClick={() => transition('close', `Close "${campaign.title}"? Closed campaigns cannot be re-opened.`)}
        >
          <XCircle className="h-3.5 w-3.5" strokeWidth={2.25} />
          Close
        </Button>
      )}
      {campaign.applicants_count === 0 && (
        <Button
          size="sm"
          variant="ghost"
          className="h-9 gap-1.5 rounded-lg text-[#A3A3A3] hover:bg-[#FFF1F2] hover:text-[#F43F5E]"
          onClick={destroy}
        >
          <Trash2 className="h-3.5 w-3.5" strokeWidth={2} />
          Delete
        </Button>
      )}
    </div>
  );
}

function CampaignDetailsForm({ campaign, form, onSubmit }) {
  // Derive base host for live URL preview
  const baseUrl = useMemo(() => {
    if (campaign.public_url) {
      try {
        const u = new URL(campaign.public_url);
        return `${u.origin}${u.pathname.replace(/\/[^/]+$/, '')}/`;
      } catch {
        return '';
      }
    }
    if (typeof window !== 'undefined') {
      return `${window.location.origin}/recruitment/`;
    }
    return '/recruitment/';
  }, [campaign.public_url]);

  const descriptionLength = (form.data.description ?? '').length;

  return (
    <form onSubmit={onSubmit}>
      <section className="rounded-[16px] border border-[#EAEAEA] bg-white p-6 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
        {/* Section header */}
        <div className="mb-5 flex items-start justify-between gap-3">
          <div className="flex items-center gap-2.5">
            <div className="grid h-8 w-8 place-items-center rounded-lg bg-[#F5F5F5] text-[#525252]">
              <Pencil className="h-4 w-4" strokeWidth={2} />
            </div>
            <div>
              <h2 className="text-[15px] font-semibold tracking-[-0.01em] text-[#0A0A0A]">
                Campaign details
              </h2>
              <p className="mt-0.5 text-[12px] text-[#737373]">
                Public-facing copy and the URL applicants visit.
              </p>
            </div>
          </div>
        </div>

        {/* Top: Title + Slug side by side */}
        <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
          <Field label="Campaign title" error={form.errors.title}>
            <Input
              name="title"
              value={form.data.title}
              onChange={(e) => form.setData('title', e.target.value)}
              placeholder="e.g. Live Host Recruitment - Aug 2026"
            />
          </Field>
          <Field label="URL slug" error={form.errors.slug}>
            <div className="relative">
              <Input
                name="slug"
                value={form.data.slug}
                onChange={(e) => form.setData('slug', e.target.value)}
                className="font-mono text-[13px] tracking-tight"
              />
            </div>
            <div className="mt-1.5 flex items-center gap-1.5 truncate rounded-md bg-[#F9FAFB] px-2.5 py-1.5 font-mono text-[11.5px] text-[#737373]">
              <Globe className="h-[12px] w-[12px] shrink-0 text-[#A3A3A3]" strokeWidth={2} />
              <span className="truncate">
                {baseUrl}
                <span className="text-[#0A0A0A]">{form.data.slug || 'your-slug'}</span>
              </span>
            </div>
          </Field>
        </div>

        {/* Description */}
        <div className="mt-5">
          <Field label="Description" error={form.errors.description}>
            <textarea
              name="description"
              value={form.data.description}
              onChange={(e) => form.setData('description', e.target.value)}
              rows={4}
              placeholder="What is this campaign about? Who are you looking for?"
              className="w-full rounded-lg border border-[#EAEAEA] bg-white px-3 py-2.5 text-[13.5px] leading-relaxed text-[#0A0A0A] placeholder:text-[#A3A3A3] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
            />
            <div className="mt-1 flex justify-end font-mono text-[10.5px] text-[#A3A3A3]">
              {descriptionLength} chars
            </div>
          </Field>
        </div>

        {/* Schedule & target — visually grouped */}
        <div className="mt-5 rounded-[12px] border border-[#F0F0F0] bg-[#FAFAFA] p-4">
          <div className="mb-3 flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-[0.07em] text-[#737373]">
            <Target className="h-3 w-3" strokeWidth={2.25} />
            Schedule & target
          </div>
          <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
            <Field label="Target hires" error={form.errors.target_count} hint="Optional">
              <Input
                type="number"
                min="1"
                name="target_count"
                placeholder="e.g. 5"
                value={form.data.target_count ?? ''}
                onChange={(e) => form.setData('target_count', e.target.value)}
                className="bg-white tabular-nums"
              />
            </Field>
            <Field label="Opens at" error={form.errors.opens_at} hint="Local time">
              <Input
                type="datetime-local"
                name="opens_at"
                value={form.data.opens_at}
                onChange={(e) => form.setData('opens_at', e.target.value)}
                className="bg-white"
              />
            </Field>
            <Field label="Closes at" error={form.errors.closes_at} hint="Local time">
              <Input
                type="datetime-local"
                name="closes_at"
                value={form.data.closes_at}
                onChange={(e) => form.setData('closes_at', e.target.value)}
                className="bg-white"
              />
            </Field>
          </div>
        </div>
      </section>
    </form>
  );
}

function FormBuilderTab({ campaign, form }) {
  const isPublished = campaign.status === 'open';
  const previewUrl = campaign.preview_url ?? null;
  const schemaErrors = form.errors.form_schema;

  return (
    <section className="space-y-4">
      {/* Header row: description + preview link */}
      <div className="flex flex-wrap items-start justify-between gap-3 rounded-[16px] border border-[#EAEAEA] bg-white p-5 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
        <div className="flex items-center gap-2.5">
          <div className="grid h-8 w-8 place-items-center rounded-lg bg-[#F5F5F5] text-[#525252]">
            <FileText className="h-4 w-4" strokeWidth={2} />
          </div>
          <div>
            <h2 className="text-[15px] font-semibold tracking-[-0.01em] text-[#0A0A0A]">
              Application form
            </h2>
            <p className="mt-0.5 text-[12px] text-[#737373]">
              Drag pages and fields to design the form applicants fill in.
            </p>
          </div>
        </div>

        <div className="flex flex-col items-end gap-1">
          {isPublished && previewUrl ? (
            <a
              href={previewUrl}
              target="_blank"
              rel="noreferrer"
              className="inline-flex h-9 items-center gap-1.5 rounded-lg border border-[#EAEAEA] bg-white px-3 text-[12.5px] font-medium text-[#525252] shadow-[0_1px_2px_rgba(0,0,0,0.03)] hover:bg-[#F5F5F5] hover:text-[#0A0A0A]"
            >
              <ExternalLink className="h-3.5 w-3.5" strokeWidth={2} />
              Preview
            </a>
          ) : (
            <>
              <span className="inline-flex h-9 cursor-not-allowed items-center gap-1.5 rounded-lg border border-[#EAEAEA] bg-[#FAFAFA] px-3 text-[12.5px] font-medium text-[#A3A3A3]">
                <ExternalLink className="h-3.5 w-3.5" strokeWidth={2} />
                Preview
              </span>
              <span className="text-[11px] text-[#A3A3A3]">
                Publish the campaign first to enable the public form.
              </span>
            </>
          )}
        </div>
      </div>

      {/* Server-side schema errors (from backend validator) */}
      {schemaErrors && (
        <div className="rounded-[12px] border border-[#FECACA] bg-[#FEF2F2] p-3">
          <p className="text-[12.5px] font-medium text-[#991B1B]">
            The form couldn&rsquo;t be saved. Fix these issues:
          </p>
          <ul className="mt-1.5 space-y-1 pl-5 text-[11.5px] text-[#991B1B]">
            {(Array.isArray(schemaErrors) ? schemaErrors : [schemaErrors]).map((err, i) => (
              <li key={i} className="list-disc">{err}</li>
            ))}
          </ul>
        </div>
      )}

      <FormBuilder
        schema={form.data.form_schema}
        onChange={(newSchema) => form.setData('form_schema', newSchema)}
      />
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

  const sorted = useMemo(() => [...stages].sort((a, b) => a.position - b.position), [stages]);

  const totalApplicants = useMemo(
    () => sorted.reduce((sum, s) => sum + (s.applicants_count ?? 0), 0),
    [sorted]
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
    router.put(`/livehost/recruitment/campaigns/${campaign.id}/stages/${stage.id}`, editingDraft, {
      preserveScroll: true,
      onSuccess: () => cancelEdit(),
    });
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
      {/* Section header */}
      <div className="mb-5 flex items-start justify-between gap-3">
        <div className="flex items-center gap-2.5">
          <div className="grid h-8 w-8 place-items-center rounded-lg bg-[#F5F5F5] text-[#525252]">
            <Layers className="h-4 w-4" strokeWidth={2} />
          </div>
          <div>
            <h2 className="text-[15px] font-semibold tracking-[-0.01em] text-[#0A0A0A]">
              Pipeline stages
            </h2>
            <p className="mt-0.5 text-[12px] text-[#737373]">
              Applicants move through these stages.{' '}
              <span className="text-[#525252]">One stage must be marked as final.</span>
            </p>
          </div>
        </div>

        <div className="flex items-center gap-3">
          <span className="hidden items-center gap-1.5 rounded-full border border-[#F0F0F0] bg-[#FAFAFA] px-2.5 py-1 text-[11px] font-medium text-[#525252] sm:inline-flex">
            <span className="font-mono text-[#0A0A0A]">{sorted.length}</span> stage
            {sorted.length === 1 ? '' : 's'}
            <span className="text-[#D4D4D4]">·</span>
            <span className="font-mono text-[#0A0A0A]">{totalApplicants}</span> total applicant
            {totalApplicants === 1 ? '' : 's'}
          </span>
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
      </div>

      {/* Stages flow */}
      {sorted.length === 0 && !adding ? (
        <div className="flex flex-col items-center justify-center rounded-[12px] border border-dashed border-[#E5E5E5] bg-[#FAFAFA] py-12 text-center">
          <div className="mb-3 grid h-10 w-10 place-items-center rounded-full bg-white text-[#737373] shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
            <Layers className="h-4 w-4" strokeWidth={2} />
          </div>
          <h3 className="text-[13.5px] font-semibold text-[#0A0A0A]">No stages yet</h3>
          <p className="mt-1 max-w-xs text-[12px] text-[#737373]">
            Add your first stage to start moving applicants through your hiring pipeline.
          </p>
          <Button
            size="sm"
            className="mt-4 h-8 gap-1.5 rounded-lg bg-[#0A0A0A] text-white hover:bg-[#262626]"
            onClick={() => setAdding(true)}
          >
            <Plus className="h-3.5 w-3.5" strokeWidth={2.25} />
            Add first stage
          </Button>
        </div>
      ) : (
        <ol className="relative space-y-2">
          {/* connector line */}
          {sorted.length > 1 && (
            <div
              className="pointer-events-none absolute left-[15px] top-3 bottom-3 w-px bg-gradient-to-b from-[#E5E5E5] via-[#E5E5E5] to-transparent"
              aria-hidden="true"
            />
          )}

          {sorted.map((stage, index) => {
            const isEditing = editingId === stage.id;

            return (
              <li key={stage.id} className="relative">
                <div
                  className={`group rounded-[12px] border transition-all ${
                    isEditing
                      ? 'border-[#0A0A0A]/15 bg-[#FAFAFA] shadow-[0_4px_12px_rgba(0,0,0,0.04)]'
                      : 'border-[#EAEAEA] bg-white hover:border-[#D4D4D4] hover:shadow-[0_1px_3px_rgba(0,0,0,0.05)]'
                  } ${stage.is_final ? 'ring-1 ring-[#A7F3D0]/0' : ''}`}
                >
                  {isEditing ? (
                    <div className="space-y-3 p-4">
                      <div className="grid grid-cols-3 gap-3">
                        <div className="col-span-2">
                          <Label className="text-[12px] font-medium text-[#0A0A0A]">Name</Label>
                          <Input
                            value={editingDraft.name}
                            onChange={(e) =>
                              setEditingDraft((d) => ({ ...d, name: e.target.value }))
                            }
                            placeholder="e.g. Phone screen"
                            autoFocus
                          />
                        </div>
                        <div className="flex items-end">
                          <label className="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-[12.5px] text-[#0A0A0A] hover:bg-[#F9FAFB]">
                            <input
                              type="checkbox"
                              checked={editingDraft.is_final}
                              onChange={(e) =>
                                setEditingDraft((d) => ({ ...d, is_final: e.target.checked }))
                              }
                              className="h-3.5 w-3.5 rounded border-[#D4D4D4] accent-[#059669]"
                            />
                            <span className="inline-flex items-center gap-1">
                              <Star className="h-3 w-3" strokeWidth={2.25} />
                              Final stage
                            </span>
                          </label>
                        </div>
                      </div>
                      <div>
                        <Label className="text-[12px] font-medium text-[#0A0A0A]">
                          Description <span className="text-[#A3A3A3]">(optional)</span>
                        </Label>
                        <textarea
                          value={editingDraft.description}
                          onChange={(e) =>
                            setEditingDraft((d) => ({ ...d, description: e.target.value }))
                          }
                          rows={2}
                          placeholder="What happens at this stage?"
                          className="w-full rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-[13px] text-[#0A0A0A] placeholder:text-[#A3A3A3] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
                        />
                      </div>
                      <div className="flex items-center justify-end gap-2">
                        <Button type="button" variant="ghost" size="sm" onClick={cancelEdit}>
                          <X className="mr-1 h-3.5 w-3.5" strokeWidth={2.25} />
                          Cancel
                        </Button>
                        <Button
                          type="button"
                          size="sm"
                          onClick={() => saveEdit(stage)}
                          className="bg-[#0A0A0A] text-white hover:bg-[#262626]"
                        >
                          <Check className="mr-1 h-3.5 w-3.5" strokeWidth={2.5} />
                          Save stage
                        </Button>
                      </div>
                    </div>
                  ) : (
                    <div className="flex items-center gap-3 p-3 pr-2">
                      {/* Stage node — sits on the connector line */}
                      <div
                        className="relative grid h-8 w-8 shrink-0 place-items-center rounded-full text-[12.5px] font-semibold tabular-nums text-white shadow-[0_1px_2px_rgba(0,0,0,0.06)]"
                        style={{
                          background: stage.is_final
                            ? 'linear-gradient(135deg, #10B981, #059669)'
                            : 'linear-gradient(135deg, #525252, #262626)',
                        }}
                      >
                        {stage.is_final ? (
                          <Star className="h-3.5 w-3.5" strokeWidth={2.5} fill="white" />
                        ) : (
                          stage.position
                        )}
                      </div>

                      <div className="min-w-0 flex-1">
                        <div className="flex items-center gap-2">
                          <span className="truncate text-[13.5px] font-semibold text-[#0A0A0A]">
                            {stage.name}
                          </span>
                          {stage.is_final && (
                            <span className="inline-flex items-center gap-1 rounded-full border border-[#A7F3D0] bg-[#ECFDF5] px-2 py-0.5 text-[10.5px] font-semibold uppercase tracking-[0.04em] text-[#059669]">
                              Final
                            </span>
                          )}
                          <span
                            className="inline-flex items-center gap-1 rounded-full bg-[#F5F5F5] px-2 py-0.5 text-[10.5px] font-medium tabular-nums text-[#525252]"
                            title="Applicants in this stage"
                          >
                            <Users className="h-2.5 w-2.5" strokeWidth={2.5} />
                            {stage.applicants_count}
                          </span>
                        </div>
                        {stage.description && (
                          <div className="mt-0.5 truncate text-[12px] leading-relaxed text-[#737373]">
                            {stage.description}
                          </div>
                        )}
                      </div>

                      {/* Actions — visible on hover, always visible on touch */}
                      <div className="inline-flex items-center gap-0.5 opacity-60 transition-opacity group-hover:opacity-100">
                        <button
                          type="button"
                          onClick={() => reorder(stage, 'up')}
                          disabled={index === 0}
                          className="inline-flex h-7 w-7 items-center justify-center rounded-md text-[#737373] hover:bg-[#F0F0F0] hover:text-[#0A0A0A] disabled:opacity-30 disabled:hover:bg-transparent"
                          title="Move up"
                        >
                          <ArrowUp className="h-[13px] w-[13px]" strokeWidth={2} />
                        </button>
                        <button
                          type="button"
                          onClick={() => reorder(stage, 'down')}
                          disabled={index === sorted.length - 1}
                          className="inline-flex h-7 w-7 items-center justify-center rounded-md text-[#737373] hover:bg-[#F0F0F0] hover:text-[#0A0A0A] disabled:opacity-30 disabled:hover:bg-transparent"
                          title="Move down"
                        >
                          <ArrowDown className="h-[13px] w-[13px]" strokeWidth={2} />
                        </button>
                        <span className="mx-0.5 h-4 w-px bg-[#EAEAEA]" />
                        <button
                          type="button"
                          onClick={() => beginEdit(stage)}
                          className="inline-flex h-7 w-7 items-center justify-center rounded-md text-[#737373] hover:bg-[#F0F0F0] hover:text-[#0A0A0A]"
                          title="Edit stage"
                        >
                          <Pencil className="h-[13px] w-[13px]" strokeWidth={2} />
                        </button>
                        <button
                          type="button"
                          onClick={() => destroy(stage)}
                          className="inline-flex h-7 w-7 items-center justify-center rounded-md text-[#A3A3A3] hover:bg-[#FFF1F2] hover:text-[#F43F5E]"
                          title="Delete stage"
                        >
                          <Trash2 className="h-[13px] w-[13px]" strokeWidth={2} />
                        </button>
                      </div>
                    </div>
                  )}
                </div>
              </li>
            );
          })}

          {/* Add-stage drawer */}
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
                    <Input
                      value={addDraft.name}
                      onChange={(e) => setAddDraft((d) => ({ ...d, name: e.target.value }))}
                      placeholder="e.g. Final interview"
                      autoFocus
                    />
                  </div>
                  <div className="flex items-end">
                    <label className="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-[12.5px] text-[#0A0A0A] hover:bg-[#F9FAFB]">
                      <input
                        type="checkbox"
                        checked={addDraft.is_final}
                        onChange={(e) =>
                          setAddDraft((d) => ({ ...d, is_final: e.target.checked }))
                        }
                        className="h-3.5 w-3.5 rounded border-[#D4D4D4] accent-[#059669]"
                      />
                      <span className="inline-flex items-center gap-1">
                        <Star className="h-3 w-3" strokeWidth={2.25} />
                        Final stage
                      </span>
                    </label>
                  </div>
                </div>
                <div className="mt-3">
                  <Label className="text-[12px] font-medium text-[#0A0A0A]">
                    Description <span className="text-[#A3A3A3]">(optional)</span>
                  </Label>
                  <textarea
                    value={addDraft.description}
                    onChange={(e) =>
                      setAddDraft((d) => ({ ...d, description: e.target.value }))
                    }
                    rows={2}
                    placeholder="What happens at this stage?"
                    className="w-full rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-[13px] text-[#0A0A0A] placeholder:text-[#A3A3A3] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
                  />
                </div>
                <div className="mt-3 flex items-center justify-end gap-2">
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
                  <Button
                    type="button"
                    size="sm"
                    onClick={addStage}
                    disabled={!addDraft.name.trim()}
                    className="bg-[#0A0A0A] text-white hover:bg-[#262626] disabled:opacity-50"
                  >
                    <Plus className="mr-1 h-3.5 w-3.5" strokeWidth={2.5} />
                    Add stage
                  </Button>
                </div>
              </div>
            </li>
          )}
        </ol>
      )}
    </section>
  );
}

function Field({ label, error, hint, children }) {
  return (
    <div className="space-y-1.5">
      <div className="flex items-center justify-between">
        <Label className="text-[12.5px] font-medium text-[#0A0A0A]">{label}</Label>
        {hint && !error && <span className="text-[10.5px] text-[#A3A3A3]">{hint}</span>}
      </div>
      {children}
      {error && <p className="mt-1 text-[11.5px] text-[#F43F5E]">{error}</p>}
    </div>
  );
}
