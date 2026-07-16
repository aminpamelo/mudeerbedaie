import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { Layers, Loader2, Pencil, Plus, Trash2 } from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import { Button } from '@/livehost/components/ui/button';
import { Input } from '@/livehost/components/ui/input';
import { Label } from '@/livehost/components/ui/label';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/livehost/components/ui/dialog';
import CommissionTierTable from '@/livehost/components/CommissionTierTable';

// Client-only row ids so CommissionTierTable (which keys and edits by id) can
// drive a draft ladder that has not been persisted yet.
let SYNTHETIC_ID = 1;
const nextSyntheticId = () => SYNTHETIC_ID++;

function toDraftTiers(tiers) {
  return (Array.isArray(tiers) ? tiers : [])
    .slice()
    .sort((a, b) => Number(a.tier_number) - Number(b.tier_number))
    .map((t) => ({
      id: nextSyntheticId(),
      tier_number: Number(t.tier_number),
      min_gmv_myr: t.min_gmv_myr ?? 0,
      max_gmv_myr: t.max_gmv_myr === undefined ? null : t.max_gmv_myr,
      internal_percent: t.internal_percent ?? 0,
      l1_percent: t.l1_percent ?? 0,
      l2_percent: t.l2_percent ?? 0,
    }));
}

function emptyDraft() {
  return {
    id: null,
    name: '',
    description: '',
    tiers: [
      {
        id: nextSyntheticId(),
        tier_number: 1,
        min_gmv_myr: 0,
        max_gmv_myr: null,
        internal_percent: 0,
        l1_percent: 0,
        l2_percent: 0,
      },
    ],
  };
}

function TemplateCard({ template, onEdit, onDelete }) {
  return (
    <div className="rounded-[16px] border border-[#EAEAEA] bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
      <div className="flex items-start justify-between gap-4 px-5 py-4">
        <div className="min-w-0">
          <div className="flex items-center gap-2">
            <span className="grid h-8 w-8 place-items-center rounded-lg bg-[#F5F3FF] text-[#7C3AED]">
              <Layers className="h-4 w-4" strokeWidth={2.2} />
            </span>
            <h3 className="truncate text-[15px] font-semibold tracking-[-0.015em] text-[#0A0A0A]">
              {template.name}
            </h3>
            <span className="shrink-0 rounded-full border border-[#EAEAEA] bg-[#FAFAFA] px-2 py-[2px] text-[11px] font-medium text-[#525252]">
              {template.tier_count} tier{template.tier_count === 1 ? '' : 's'}
            </span>
          </div>
          {template.description ? (
            <p className="mt-1.5 text-[12.5px] text-[#737373]">{template.description}</p>
          ) : null}
        </div>
        <div className="flex shrink-0 items-center gap-1.5">
          <Button
            type="button"
            size="sm"
            variant="outline"
            onClick={() => onEdit(template)}
            className="h-8 gap-1.5"
          >
            <Pencil className="h-3.5 w-3.5" />
            Edit
          </Button>
          <button
            type="button"
            onClick={() => onDelete(template)}
            aria-label={`Delete ${template.name}`}
            className="inline-flex h-8 w-8 items-center justify-center rounded-md border border-transparent text-[#737373] transition-colors hover:border-[#FECACA] hover:bg-[#FEF2F2] hover:text-[#DC2626]"
          >
            <Trash2 className="h-3.5 w-3.5" />
          </button>
        </div>
      </div>
      <div className="px-5 pb-5">
        <CommissionTierTable
          platform={{ id: 0, name: template.name }}
          effectiveFrom={null}
          tiers={(template.tiers || []).map((t) => ({ ...t, id: t.tier_number }))}
          onEditRow={() => {}}
          onAddTier={() => {}}
          onRemoveTier={() => {}}
          readOnly
        />
      </div>
    </div>
  );
}

export default function CommissionTemplatesIndex() {
  const { templates = [], flash } = usePage().props;

  const [open, setOpen] = useState(false);
  const [draft, setDraft] = useState(emptyDraft);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);
  const [flashMessage, setFlashMessage] = useState(null);

  useEffect(() => {
    if (flash?.success) {
      setFlashMessage(flash.success);
      const timer = setTimeout(() => setFlashMessage(null), 3000);
      return () => clearTimeout(timer);
    }
  }, [flash]);

  const isEditing = draft.id !== null;

  const openNew = () => {
    setDraft(emptyDraft());
    setError(null);
    setOpen(true);
  };

  const openEdit = (template) => {
    setDraft({
      id: template.id,
      name: template.name,
      description: template.description ?? '',
      tiers: toDraftTiers(template.tiers),
    });
    setError(null);
    setOpen(true);
  };

  const editRow = (tierId, patch) => {
    setDraft((d) => ({
      ...d,
      tiers: d.tiers.map((t) => (t.id === tierId ? { ...t, ...patch } : t)),
    }));
  };

  const addTier = () => {
    setDraft((d) => {
      const existing = [...d.tiers].sort((a, b) => Number(a.tier_number) - Number(b.tier_number));
      const top = existing[existing.length - 1];
      const nextNumber = (Number(top?.tier_number) || 0) + 1;
      const prevMax =
        top?.max_gmv_myr === null || top?.max_gmv_myr === undefined
          ? Number(top?.min_gmv_myr ?? 0)
          : Number(top.max_gmv_myr);
      const closed = existing.map((t, i) =>
        i === existing.length - 1 ? { ...t, max_gmv_myr: prevMax } : t,
      );
      return {
        ...d,
        tiers: [
          ...closed,
          {
            id: nextSyntheticId(),
            tier_number: nextNumber,
            min_gmv_myr: prevMax,
            max_gmv_myr: null,
            internal_percent: 0,
            l1_percent: 0,
            l2_percent: 0,
          },
        ],
      };
    });
  };

  const removeTier = (tierId) => {
    setDraft((d) => ({ ...d, tiers: d.tiers.filter((t) => t.id !== tierId) }));
  };

  const save = () => {
    if (!draft.name.trim()) {
      setError('Give the template a name.');
      return;
    }
    const payload = {
      name: draft.name.trim(),
      description: draft.description.trim() || null,
      tiers: draft.tiers.map((t) => ({
        tier_number: Number(t.tier_number),
        min_gmv_myr: Number(t.min_gmv_myr),
        max_gmv_myr: t.max_gmv_myr === null || t.max_gmv_myr === undefined ? null : Number(t.max_gmv_myr),
        internal_percent: Number(t.internal_percent),
        l1_percent: Number(t.l1_percent),
        l2_percent: Number(t.l2_percent),
      })),
    };

    const opts = {
      preserveScroll: true,
      onSuccess: () => setOpen(false),
      onError: (errs) => {
        const firstErr = Object.values(errs || {})[0];
        setError(Array.isArray(firstErr) ? firstErr[0] : firstErr || 'Save failed');
      },
      onFinish: () => setBusy(false),
    };

    setBusy(true);
    setError(null);
    if (isEditing) {
      router.put(`/livehost/commission-templates/${draft.id}`, payload, opts);
    } else {
      router.post('/livehost/commission-templates', payload, opts);
    }
  };

  const remove = (template) => {
    if (!window.confirm(`Delete template "${template.name}"? Hosts already using it are not affected.`)) {
      return;
    }
    router.delete(`/livehost/commission-templates/${template.id}`, { preserveScroll: true });
  };

  const newAction = (
    <Button type="button" size="sm" onClick={openNew} className="h-8 gap-1.5 bg-[#0A0A0A] text-white hover:bg-[#262626]">
      <Plus className="h-3.5 w-3.5" strokeWidth={2.5} />
      New template
    </Button>
  );

  const sorted = useMemo(
    () => [...(templates || [])].sort((a, b) => a.name.localeCompare(b.name)),
    [templates],
  );

  return (
    <>
      <Head title="Commission Templates · Live Host Desk" />
      <TopBar breadcrumb={['Live Host Desk', 'Commission Templates']} actions={newAction} />

      <div className="mx-auto max-w-[1100px] px-6 py-6">
        <div className="mb-5">
          <h1 className="text-[20px] font-semibold tracking-[-0.02em] text-[#0A0A0A]">
            Master commission templates
          </h1>
          <p className="mt-1 text-[13px] text-[#737373]">
            Reusable tier ladders. Build one here, then pick it on a host's Commission tab to apply
            the whole ladder at once. Editing a template never changes hosts already set up.
          </p>
        </div>

        {flashMessage && (
          <div className="mb-4 rounded-lg border border-[#A7F3D0] bg-[#ECFDF5] px-4 py-2.5 text-[13px] text-[#065F46]">
            {flashMessage}
          </div>
        )}

        {sorted.length === 0 ? (
          <div className="rounded-[16px] border border-dashed border-[#EAEAEA] bg-[#FAFAFA] px-6 py-14 text-center">
            <div className="mx-auto mb-3 grid h-11 w-11 place-items-center rounded-xl bg-[#F5F3FF] text-[#7C3AED]">
              <Layers className="h-5 w-5" />
            </div>
            <p className="text-[14px] font-medium text-[#0A0A0A]">No templates yet</p>
            <p className="mx-auto mt-1 max-w-[420px] text-[13px] text-[#737373]">
              Create a master ladder (e.g. “Standard 5-Tier”) so you can apply the same commission
              structure to many hosts in one click.
            </p>
            <div className="mt-4">{newAction}</div>
          </div>
        ) : (
          <div className="space-y-4">
            {sorted.map((template) => (
              <TemplateCard key={template.id} template={template} onEdit={openEdit} onDelete={remove} />
            ))}
          </div>
        )}
      </div>

      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent
          style={{ maxWidth: 'min(56rem, calc(100vw - 2rem))' }}
          className="max-h-[85vh] grid-rows-[auto_minmax(0,1fr)_auto] overflow-hidden"
        >
          <DialogHeader>
            <DialogTitle>{isEditing ? 'Edit template' : 'New commission template'}</DialogTitle>
            <DialogDescription>
              Define the tiered ladder. It can be applied to any host on any platform later.
            </DialogDescription>
          </DialogHeader>

          <div className="min-h-0 min-w-0 space-y-4 overflow-y-auto">
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-1.5">
                <Label htmlFor="tpl-name">Name</Label>
                <Input
                  id="tpl-name"
                  value={draft.name}
                  onChange={(e) => setDraft((d) => ({ ...d, name: e.target.value }))}
                  placeholder="Standard 5-Tier"
                />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="tpl-desc">Description (optional)</Label>
                <input
                  id="tpl-desc"
                  value={draft.description}
                  onChange={(e) => setDraft((d) => ({ ...d, description: e.target.value }))}
                  placeholder="Default ladder for new hosts"
                  className="flex h-9 w-full rounded-md border border-[#EAEAEA] bg-white px-3 text-[13px] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
                />
              </div>
            </div>

            <div className="min-w-0 overflow-x-auto">
              <CommissionTierTable
                platform={{ id: 0, name: draft.name || 'Template' }}
                effectiveFrom={null}
                tiers={draft.tiers}
                onEditRow={editRow}
                onAddTier={addTier}
                onRemoveTier={removeTier}
                readOnly={false}
              />
            </div>

            {error && (
              <div className="rounded-lg border border-[#FECACA] bg-[#FEF2F2] px-3 py-2 text-[12.5px] text-[#DC2626]">
                {error}
              </div>
            )}
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setOpen(false)}>
              Cancel
            </Button>
            <Button
              type="button"
              onClick={save}
              disabled={busy}
              className="gap-1.5 bg-[#0A0A0A] text-white hover:bg-[#262626]"
            >
              {busy ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : null}
              {isEditing ? 'Save changes' : 'Create template'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}

CommissionTemplatesIndex.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;
