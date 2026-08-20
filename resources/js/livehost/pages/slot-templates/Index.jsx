import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { CalendarClock, LayoutTemplate, Pencil, Plus, Trash2 } from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import { Button } from '@/livehost/components/ui/button';
import SlotTemplateModal from '@/livehost/components/slot-templates/SlotTemplateModal';

const DAYS = [
  { v: 0, label: 'Sun' }, { v: 1, label: 'Mon' }, { v: 2, label: 'Tue' }, { v: 3, label: 'Wed' },
  { v: 4, label: 'Thu' }, { v: 5, label: 'Fri' }, { v: 6, label: 'Sat' },
];

export default function SlotTemplatesIndex() {
  const { templates = [], flash } = usePage().props;
  const [editing, setEditing] = useState(undefined); // undefined = closed, null = new, object = edit

  const remove = (template) => {
    if (!window.confirm(`Delete the template "${template.name}"?`)) {
      return;
    }
    router.delete(`/livehost/slot-templates/${template.id}`, { preserveScroll: true });
  };

  const newAction = (
    <Button size="sm" onClick={() => setEditing(null)} className="h-9 gap-1.5 rounded-lg bg-ink text-white hover:bg-[#262626]">
      <Plus className="h-[13px] w-[13px]" strokeWidth={2.5} />
      New template
    </Button>
  );

  return (
    <>
      <Head title="Slot Templates" />
      <TopBar breadcrumb={['Live Host Desk', 'Slot Templates']} actions={newAction} />

      <div className="space-y-6 p-4 sm:p-6 lg:p-8">
        <div className="flex flex-wrap items-end justify-between gap-4">
          <div>
            <h1 className="text-2xl sm:text-3xl font-semibold leading-[1.1] tracking-[-0.03em] text-[#0A0A0A]">
              Slot Templates
            </h1>
            <p className="mt-1.5 text-sm text-[#737373]">
              Reusable weekly grids of time windows — set once, then apply to any account&rsquo;s slot override in one pick.
            </p>
          </div>
        </div>

        {flash?.error && (
          <div className="rounded-[12px] border border-[#FECACA] bg-[#FEF2F2] px-4 py-3 text-sm text-[#991B1B]">{flash.error}</div>
        )}
        {flash?.success && (
          <div className="rounded-[12px] border border-[#A7F3D0] bg-[#ECFDF5] px-4 py-3 text-sm text-[#065F46]">{flash.success}</div>
        )}

        {templates.length === 0 ? (
          <div className="grid place-items-center rounded-[16px] border border-dashed border-[#E5E5E5] bg-[#FAFAFA] px-6 py-16 text-center">
            <span className="grid h-12 w-12 place-items-center rounded-2xl bg-[#F5F3FF] text-[#6D28D9]">
              <LayoutTemplate className="h-6 w-6" strokeWidth={1.75} />
            </span>
            <h3 className="mt-4 text-[15px] font-semibold text-[#0A0A0A]">No templates yet</h3>
            <p className="mt-1 max-w-sm text-[13px] text-[#737373]">
              Create a template with your usual weekly time windows, then apply it to a slot override without clicking each slot by hand.
            </p>
            <Button size="sm" onClick={() => setEditing(null)} className="mt-5 h-9 gap-1.5 rounded-lg bg-ink text-white hover:bg-[#262626]">
              <Plus className="h-[13px] w-[13px]" strokeWidth={2.5} /> New template
            </Button>
          </div>
        ) : (
          <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
            {templates.map((t) => (
              <div key={t.id} className="flex flex-col rounded-[16px] border border-[#EAEAEA] bg-white p-4 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
                <div className="flex items-start justify-between gap-3">
                  <div className="min-w-0">
                    <div className="flex items-center gap-2">
                      <h3 className="truncate text-[15px] font-semibold text-[#0A0A0A]">{t.name}</h3>
                      {!t.isActive && (
                        <span className="rounded-full bg-[#F5F5F5] px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-[#737373]">Inactive</span>
                      )}
                    </div>
                    {t.description && <p className="mt-0.5 truncate text-[12.5px] text-[#737373]">{t.description}</p>}
                    <p className="mt-1 text-[11.5px] tabular-nums text-[#A3A3A3]">
                      {t.slotCount} slot{t.slotCount === 1 ? '' : 's'} · {t.dayCount} day{t.dayCount === 1 ? '' : 's'}
                    </p>
                  </div>
                  <div className="flex shrink-0 gap-1">
                    <button type="button" onClick={() => setEditing(t)} className="rounded-md p-1.5 text-[#525252] hover:bg-[#F5F5F5]" title="Edit"><Pencil className="h-3.5 w-3.5" strokeWidth={2} /></button>
                    <button type="button" onClick={() => remove(t)} className="rounded-md p-1.5 text-[#B91C1C] hover:bg-[#FEF2F2]" title="Delete"><Trash2 className="h-3.5 w-3.5" strokeWidth={2} /></button>
                  </div>
                </div>

                <div className="mt-3 flex flex-col gap-1 border-t border-[#F5F5F5] pt-3">
                  {DAYS.filter((d) => t.slots.some((s) => s.day_of_week === d.v)).map((d) => (
                    <div key={d.v} className="flex items-start gap-2">
                      <span className="w-8 shrink-0 pt-0.5 text-[10.5px] font-semibold text-[#737373]">{d.label}</span>
                      <div className="flex flex-wrap gap-1">
                        {t.slots
                          .filter((s) => s.day_of_week === d.v)
                          .sort((a, b) => String(a.start_time).localeCompare(String(b.start_time)))
                          .map((s, i) => (
                            <span key={i} className="rounded-full bg-[#F5F3FF] px-2 py-0.5 text-[10.5px] font-medium text-[#6D28D9] tabular-nums">{s.start_time}–{s.end_time}</span>
                          ))}
                      </div>
                    </div>
                  ))}
                  {t.slots.length === 0 && (
                    <div className="flex items-center gap-1.5 text-[11.5px] text-[#A3A3A3]">
                      <CalendarClock className="h-3.5 w-3.5" strokeWidth={2} /> No time windows
                    </div>
                  )}
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      {editing !== undefined && (
        <SlotTemplateModal template={editing} onClose={() => setEditing(undefined)} />
      )}
    </>
  );
}

SlotTemplatesIndex.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;
