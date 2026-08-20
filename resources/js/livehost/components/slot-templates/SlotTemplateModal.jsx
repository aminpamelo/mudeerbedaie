import { useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { LayoutTemplate, Loader2, X } from 'lucide-react';
import { Button } from '@/livehost/components/ui/button';
import WeeklySlotGrid, { withKeys } from '@/livehost/components/session-slots/WeeklySlotGrid';

const inputClass =
  'h-9 rounded-lg border border-[#EAEAEA] bg-white px-2.5 text-[13px] text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20';

/**
 * Create / edit a reusable slot template (name + description + weekly grid of
 * time windows). Posts through Inertia so the list refreshes with flash. Slots
 * come in the same snake_case shape the server stores.
 */
export default function SlotTemplateModal({ template, onClose }) {
  const isEdit = Boolean(template?.id);

  const form = useForm({
    name: template?.name ?? '',
    description: template?.description ?? '',
    slots: withKeys(template?.slots ?? []),
  });

  useEffect(() => {
    const onKey = (e) => { if (e.key === 'Escape') onClose(); };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [onClose]);

  const setSlots = (next) => form.setData('slots', next);

  const canSave =
    form.data.name.trim().length > 0 &&
    form.data.slots.length > 0 &&
    form.data.slots.every((s) => s.start_time && s.end_time && s.end_time !== s.start_time) &&
    !form.processing;

  const submit = () => {
    form.transform((data) => ({
      name: data.name,
      description: data.description || null,
      slots: data.slots.map(({ day_of_week, start_time, end_time }) => ({ day_of_week, start_time, end_time })),
    }));
    const opts = { preserveScroll: true, onSuccess: onClose };
    if (isEdit) {
      form.put(`/livehost/slot-templates/${template.id}`, opts);
    } else {
      form.post('/livehost/slot-templates', opts);
    }
  };

  // Surface any per-slot validation error (e.g. slots.2.end_time) as one hint.
  const slotError = form.errors.slots
    || Object.entries(form.errors).find(([k]) => k.startsWith('slots.'))?.[1];

  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4" onMouseDown={(e) => { if (e.target === e.currentTarget) onClose(); }}>
      <div className="flex max-h-[88vh] w-full max-w-xl flex-col overflow-hidden rounded-[16px] bg-white shadow-[0_20px_60px_rgba(0,0,0,0.18)]">
        <div className="flex items-start justify-between gap-3 border-b border-[#F0F0F0] px-5 py-4">
          <div className="flex items-center gap-2.5">
            <span className="grid h-9 w-9 place-items-center rounded-xl bg-[#F5F3FF] text-[#6D28D9]"><LayoutTemplate className="h-4 w-4" strokeWidth={2.25} /></span>
            <div>
              <h3 className="text-[15px] font-semibold text-[#0A0A0A]">{isEdit ? 'Edit template' : 'New slot template'}</h3>
              <p className="mt-0.5 text-[12px] text-[#737373]">A reusable weekly grid you can apply to any account's override</p>
            </div>
          </div>
          <button type="button" onClick={onClose} className="rounded-md p-1 text-[#737373] hover:bg-[#F5F5F5] hover:text-[#0A0A0A]"><X className="h-4 w-4" strokeWidth={2} /></button>
        </div>

        <div className="min-h-0 flex-1 overflow-y-auto p-5">
          <div className="flex flex-col gap-3">
            <label className="text-[11px] font-semibold uppercase tracking-wide text-[#737373]">
              Name
              <input
                type="text"
                value={form.data.name}
                onChange={(e) => form.setData('name', e.target.value)}
                placeholder="e.g. Standard 8-slot"
                maxLength={120}
                className={`${inputClass} mt-1 w-full font-normal normal-case tracking-normal`}
              />
            </label>
            {form.errors.name && <p className="-mt-1 text-[11px] text-[#B91C1C]">{form.errors.name}</p>}

            <label className="text-[11px] font-semibold uppercase tracking-wide text-[#737373]">
              Description <span className="font-normal normal-case text-[#A3A3A3]">· optional</span>
              <input
                type="text"
                value={form.data.description}
                onChange={(e) => form.setData('description', e.target.value)}
                placeholder="What this template is for"
                maxLength={255}
                className={`${inputClass} mt-1 w-full font-normal normal-case tracking-normal`}
              />
            </label>

            <div className="rounded-[12px] border border-[#EAEAEA] p-3">
              <div className="mb-2 text-[11px] font-semibold uppercase tracking-wide text-[#737373]">Slots · per day</div>
              <WeeklySlotGrid slots={form.data.slots} onChange={setSlots} />
              {slotError && <p className="mt-1.5 text-[11px] text-[#B91C1C]">{typeof slotError === 'string' ? slotError : 'Add at least one valid slot (start and end must differ).'}</p>}
            </div>

            <div className="mt-1 flex items-center justify-between">
              <Button type="button" variant="ghost" onClick={onClose} className="h-9 text-[#737373]">Cancel</Button>
              <Button type="button" onClick={submit} disabled={!canSave} className="h-9 gap-1.5 bg-[#10B981] text-white hover:bg-[#059669] disabled:opacity-40">
                {form.processing ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : null} {isEdit ? 'Save changes' : 'Create template'}
              </Button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
