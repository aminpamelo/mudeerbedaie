import { useCallback, useEffect, useState } from 'react';
import { CalendarClock, Loader2, Pencil, Plus, RotateCcw, Trash2, X } from 'lucide-react';
import { Button } from '@/livehost/components/ui/button';

const DAYS = [
  { v: 0, label: 'Sun' }, { v: 1, label: 'Mon' }, { v: 2, label: 'Tue' }, { v: 3, label: 'Wed' },
  { v: 4, label: 'Thu' }, { v: 5, label: 'Fri' }, { v: 6, label: 'Sat' },
];
function csrf() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

async function jsonFetch(url, options = {}) {
  const res = await fetch(url, {
    credentials: 'same-origin',
    ...options,
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': csrf(),
      'X-Requested-With': 'XMLHttpRequest',
      ...(options.headers ?? {}),
    },
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) {
    throw { ...data, status: res.status };
  }
  return data;
}

let _uid = 0;
const uid = () => `k${(_uid += 1)}`;
const withKeys = (slots) => (slots ?? []).map((s) => ({ _k: uid(), day_of_week: s.day_of_week, start_time: s.start_time, end_time: s.end_time }));
const emptyForm = (suggested = []) => ({ id: null, effective_from: '', effective_until: '', label: '', slots: withKeys(suggested) });

function fmtRange(o) {
  return o.effective_until ? `${o.effective_from} → ${o.effective_until}` : `${o.effective_from} → open-ended`;
}

/**
 * Manage a creator account's date-ranged slot overrides. While an override is
 * active for a date, its slots replace the account's normal slots on the calendar.
 */
export default function SlotOverrideModal({ account, suggestedSlots = [], onClose, onSaved }) {
  const [loading, setLoading] = useState(true);
  const [overrides, setOverrides] = useState([]);
  const [form, setForm] = useState(null); // null = list view; object = editing
  const [busy, setBusy] = useState(false);
  const [errors, setErrors] = useState({});

  const load = useCallback(() => {
    setLoading(true);
    jsonFetch(`/livehost/slot-overrides?live_account=${account.id}`)
      .then((d) => setOverrides(d.overrides ?? []))
      .catch(() => setOverrides([]))
      .finally(() => setLoading(false));
  }, [account.id]);

  useEffect(() => { load(); }, [load]);
  useEffect(() => {
    const onKey = (e) => { if (e.key === 'Escape') onClose(); };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [onClose]);

  const startEdit = (o) => {
    setErrors({});
    setForm({
      id: o.id,
      effective_from: o.effective_from,
      effective_until: o.effective_until ?? '',
      label: o.label ?? '',
      slots: withKeys(o.slots),
    });
  };

  const save = async () => {
    setBusy(true);
    setErrors({});
    const payload = {
      live_account_id: account.id,
      effective_from: form.effective_from,
      effective_until: form.effective_until || null,
      label: form.label || null,
      slots: form.slots.map(({ day_of_week, start_time, end_time }) => ({ day_of_week, start_time, end_time })),
    };
    try {
      if (form.id) {
        await jsonFetch(`/livehost/slot-overrides/${form.id}`, { method: 'PUT', body: JSON.stringify(payload) });
      } else {
        await jsonFetch('/livehost/slot-overrides', { method: 'POST', body: JSON.stringify(payload) });
      }
      setForm(null);
      load();
      onSaved?.();
    } catch (e) {
      const detail = e.status === 419
        ? 'Your session expired — refresh the page and try again.'
        : [e.status ? `(${e.status})` : null, e.message].filter(Boolean).join(' ') || 'Could not save. Please try again.';
      setErrors(e.errors ?? { _: [detail] });
    } finally {
      setBusy(false);
    }
  };

  const remove = async (o) => {
    if (!window.confirm('Delete this slot override?')) {
      return;
    }
    await jsonFetch(`/livehost/slot-overrides/${o.id}`, { method: 'DELETE' });
    load();
    onSaved?.();
  };

  const updateSlot = (k, patch) => setForm((f) => ({ ...f, slots: f.slots.map((s) => (s._k === k ? { ...s, ...patch } : s)) }));
  const removeSlot = (k) => setForm((f) => ({ ...f, slots: f.slots.filter((s) => s._k !== k) }));
  const addSlotForDay = (day) => setForm((f) => ({ ...f, slots: [...f.slots, { _k: uid(), day_of_week: day, start_time: '', end_time: '' }] }));

  const canSave = form && form.effective_from && form.slots.length > 0
    && form.slots.every((s) => s.start_time && s.end_time && s.end_time > s.start_time) && !busy;

  const input = 'h-9 rounded-lg border border-[#EAEAEA] bg-white px-2.5 text-[13px] text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20';

  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4" onMouseDown={(e) => { if (e.target === e.currentTarget) onClose(); }}>
      <div className="flex max-h-[88vh] w-full max-w-xl flex-col overflow-hidden rounded-[16px] bg-white shadow-[0_20px_60px_rgba(0,0,0,0.18)]">
        <div className="flex items-start justify-between gap-3 border-b border-[#F0F0F0] px-5 py-4">
          <div className="flex items-center gap-2.5">
            <span className="grid h-9 w-9 place-items-center rounded-xl bg-[#EEF2FF] text-[#4338CA]"><CalendarClock className="h-4 w-4" strokeWidth={2.25} /></span>
            <div>
              <h3 className="text-[15px] font-semibold text-[#0A0A0A]">Slot override</h3>
              <p className="mt-0.5 text-[12px] text-[#737373]">{account.label} · slots that replace the normal ones for a date range</p>
            </div>
          </div>
          <button type="button" onClick={onClose} className="rounded-md p-1 text-[#737373] hover:bg-[#F5F5F5] hover:text-[#0A0A0A]"><X className="h-4 w-4" strokeWidth={2} /></button>
        </div>

        <div className="min-h-0 flex-1 overflow-y-auto p-5">
          {form === null ? (
            <>
              {loading ? (
                <div className="grid place-items-center py-12 text-[#A3A3A3]"><Loader2 className="h-5 w-5 animate-spin" /></div>
              ) : overrides.length === 0 ? (
                <div className="rounded-[12px] border border-dashed border-[#E5E5E5] bg-[#FAFAFA] py-10 text-center text-[12.5px] text-[#A3A3A3]">No overrides yet. Add one below.</div>
              ) : (
                <div className="flex flex-col gap-2.5">
                  {overrides.map((o) => (
                    <div key={o.id} className="rounded-[12px] border border-[#EAEAEA] p-3">
                      <div className="flex items-start justify-between gap-2">
                        <div className="min-w-0">
                          <div className="text-[13px] font-semibold text-[#0A0A0A]">{o.label || 'Override'}</div>
                          <div className="mt-0.5 text-[11.5px] tabular-nums text-[#737373]">{fmtRange(o)}</div>
                        </div>
                        <div className="flex shrink-0 gap-1">
                          <button type="button" onClick={() => startEdit(o)} className="rounded-md p-1.5 text-[#525252] hover:bg-[#F5F5F5]" title="Edit"><Pencil className="h-3.5 w-3.5" strokeWidth={2} /></button>
                          <button type="button" onClick={() => remove(o)} className="rounded-md p-1.5 text-[#B91C1C] hover:bg-[#FEF2F2]" title="Delete"><Trash2 className="h-3.5 w-3.5" strokeWidth={2} /></button>
                        </div>
                      </div>
                      <div className="mt-2 flex flex-col gap-1">
                        {DAYS.filter((d) => o.slots.some((s) => s.day_of_week === d.v)).map((d) => (
                          <div key={d.v} className="flex items-start gap-2">
                            <span className="w-8 shrink-0 pt-0.5 text-[10.5px] font-semibold text-[#737373]">{d.label}</span>
                            <div className="flex flex-wrap gap-1">
                              {o.slots
                                .filter((s) => s.day_of_week === d.v)
                                .sort((a, b) => String(a.start_time).localeCompare(String(b.start_time)))
                                .map((s) => (
                                  <span key={s.id} className="rounded-full bg-[#F5F5F5] px-2 py-0.5 text-[10.5px] font-medium text-[#525252] tabular-nums">{s.start_time}–{s.end_time}</span>
                                ))}
                            </div>
                          </div>
                        ))}
                      </div>
                    </div>
                  ))}
                </div>
              )}
              <Button type="button" onClick={() => { setErrors({}); setForm(emptyForm(suggestedSlots)); }} className="mt-4 h-9 w-full gap-1.5 bg-[#0A0A0A] text-white hover:bg-[#262626]">
                <Plus className="h-3.5 w-3.5" strokeWidth={2.5} /> New override
              </Button>
            </>
          ) : (
            <div className="flex flex-col gap-3">
              <div className="grid grid-cols-2 gap-3">
                <label className="text-[11px] font-semibold uppercase tracking-wide text-[#737373]">
                  From
                  <input type="date" value={form.effective_from} onChange={(e) => setForm((f) => ({ ...f, effective_from: e.target.value }))} className={`${input} mt-1 w-full font-normal normal-case tracking-normal`} />
                </label>
                <label className="text-[11px] font-semibold uppercase tracking-wide text-[#737373]">
                  Until <span className="font-normal normal-case text-[#A3A3A3]">· optional</span>
                  <input type="date" value={form.effective_until} min={form.effective_from || undefined} onChange={(e) => setForm((f) => ({ ...f, effective_until: e.target.value }))} className={`${input} mt-1 w-full font-normal normal-case tracking-normal`} />
                </label>
              </div>
              {errors.effective_from && <p className="-mt-1 text-[11px] text-[#B91C1C]">{errors.effective_from[0]}</p>}
              {errors.effective_until && <p className="-mt-1 text-[11px] text-[#B91C1C]">{errors.effective_until[0]}</p>}

              <label className="text-[11px] font-semibold uppercase tracking-wide text-[#737373]">
                Label <span className="font-normal normal-case text-[#A3A3A3]">· optional</span>
                <input type="text" value={form.label} onChange={(e) => setForm((f) => ({ ...f, label: e.target.value }))} placeholder="e.g. Ramadan hours" className={`${input} mt-1 w-full font-normal normal-case tracking-normal`} />
              </label>

              <div className="rounded-[12px] border border-[#EAEAEA] p-3">
                <div className="mb-2 flex items-center justify-between">
                  <span className="text-[11px] font-semibold uppercase tracking-wide text-[#737373]">Slots · per day</span>
                  {suggestedSlots.length > 0 && (
                    <button type="button" onClick={() => setForm((f) => ({ ...f, slots: withKeys(suggestedSlots) }))} className="inline-flex items-center gap-1 rounded-md px-2 py-1 text-[11px] font-medium text-[#4338CA] hover:bg-[#EEF2FF]" title="Fill every day from this account's normal slots">
                      <RotateCcw className="h-3 w-3" strokeWidth={2.25} /> Prefill from normal
                    </button>
                  )}
                </div>
                <div className="flex flex-col gap-1.5">
                  {DAYS.map((d) => {
                    const daySlots = form.slots.filter((s) => s.day_of_week === d.v);
                    return (
                      <div key={d.v} className="flex gap-2.5 rounded-lg border border-[#F0F0F0] bg-[#FCFCFC] px-2.5 py-2">
                        <div className="w-9 shrink-0 pt-1.5 text-[11.5px] font-semibold text-[#525252]">{d.label}</div>
                        <div className="flex flex-1 flex-col gap-1.5">
                          {daySlots.length === 0 && <span className="py-0.5 text-[11px] text-[#C4C4C4]">No slots</span>}
                          {daySlots.map((s) => (
                            <div key={s._k} className="flex items-center gap-1.5">
                              <input type="time" value={s.start_time} onChange={(e) => updateSlot(s._k, { start_time: e.target.value })} className={`${input} flex-1`} />
                              <span className="text-[#A3A3A3]">–</span>
                              <input type="time" value={s.end_time} onChange={(e) => updateSlot(s._k, { end_time: e.target.value })} className={`${input} flex-1`} />
                              <button type="button" onClick={() => removeSlot(s._k)} className="rounded-md p-1.5 text-[#B91C1C] hover:bg-[#FEF2F2]"><Trash2 className="h-3.5 w-3.5" strokeWidth={2} /></button>
                            </div>
                          ))}
                          <button type="button" onClick={() => addSlotForDay(d.v)} className="inline-flex w-fit items-center gap-1 rounded-md px-1.5 py-0.5 text-[11px] font-medium text-[#047857] hover:bg-[#ECFDF5]">
                            <Plus className="h-3 w-3" strokeWidth={2.5} /> Add time
                          </button>
                        </div>
                      </div>
                    );
                  })}
                </div>
                {errors.slots && <p className="mt-1.5 text-[11px] text-[#B91C1C]">Add at least one valid slot (end after start).</p>}
              </div>
              {errors._ && <p className="text-[11px] text-[#B91C1C]">{errors._[0]}</p>}

              <div className="mt-1 flex items-center justify-between">
                <Button type="button" variant="ghost" onClick={() => setForm(null)} className="h-9 text-[#737373]">Cancel</Button>
                <Button type="button" onClick={save} disabled={!canSave} className="h-9 gap-1.5 bg-[#10B981] text-white hover:bg-[#059669] disabled:opacity-40">
                  {busy ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : null} {form.id ? 'Save changes' : 'Create override'}
                </Button>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
