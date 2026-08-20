import { DragDropContext, Draggable, Droppable } from '@hello-pangea/dnd';
import { Copy, ClipboardPaste, GripVertical, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

const DAYS = [
  { v: 0, label: 'Sun' }, { v: 1, label: 'Mon' }, { v: 2, label: 'Tue' }, { v: 3, label: 'Wed' },
  { v: 4, label: 'Thu' }, { v: 5, label: 'Fri' }, { v: 6, label: 'Sat' },
];

let _uid = 0;
export const uid = () => `k${(_uid += 1)}`;

/** Attach stable local keys so React rows survive reorders/edits. */
export const withKeys = (slots) =>
  (slots ?? []).map((s) => ({
    _k: uid(),
    day_of_week: s.day_of_week,
    start_time: s.start_time,
    end_time: s.end_time,
  }));

const inputClass =
  'h-9 rounded-lg border border-[#EAEAEA] bg-white px-2.5 text-[13px] text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20';

/**
 * A per-day weekly grid of editable time windows (start → end), with add/remove
 * and drag-to-reorder within each day. Pure controlled component: `slots` is the
 * keyed array, `onChange(next)` returns a new keyed array. Shared by the slot
 * override editor and the reusable slot-template editor.
 */
export default function WeeklySlotGrid({ slots, onChange }) {
  // Clipboard of a copied day's time windows (null when nothing copied).
  const [copied, setCopied] = useState(null);

  const updateSlot = (k, patch) =>
    onChange(slots.map((s) => (s._k === k ? { ...s, ...patch } : s)));

  const removeSlot = (k) => onChange(slots.filter((s) => s._k !== k));

  const addSlotForDay = (day) =>
    onChange([...slots, { _k: uid(), day_of_week: day, start_time: '', end_time: '' }]);

  const copyDay = (day) => {
    const times = slots
      .filter((s) => s.day_of_week === day)
      .map((s) => ({ start_time: s.start_time, end_time: s.end_time }));
    setCopied({ day, times });
  };

  // Replace a single day's windows with the copied ones.
  const pasteToDay = (day) => {
    if (!copied) {
      return;
    }
    const pasted = copied.times.map((t) => ({ _k: uid(), day_of_week: day, ...t }));
    onChange([...slots.filter((s) => s.day_of_week !== day), ...pasted]);
  };

  // Replace every OTHER day's windows with the copied ones.
  const pasteToAll = () => {
    if (!copied) {
      return;
    }
    const kept = slots.filter((s) => s.day_of_week === copied.day);
    const pasted = DAYS.filter((d) => d.v !== copied.day).flatMap((d) =>
      copied.times.map((t) => ({ _k: uid(), day_of_week: d.v, ...t })),
    );
    onChange([...kept, ...pasted]);
  };

  // Drag to reorder time rows within a day (each day is its own drop list).
  const onDragEnd = (result) => {
    const { source, destination } = result;
    if (!destination || source.droppableId !== destination.droppableId || source.index === destination.index) {
      return;
    }
    const day = Number(source.droppableId.replace('day-', ''));
    const dayKeys = slots.filter((s) => s.day_of_week === day).map((s) => s._k);
    const [movedKey] = dayKeys.splice(source.index, 1);
    dayKeys.splice(destination.index, 0, movedKey);
    const byKey = new Map(slots.filter((s) => s.day_of_week === day).map((s) => [s._k, s]));
    let di = 0;
    onChange(slots.map((s) => (s.day_of_week === day ? byKey.get(dayKeys[di++]) : s)));
  };

  return (
    <DragDropContext onDragEnd={onDragEnd}>
      <div className="flex flex-col gap-1.5">
        {copied && copied.times.length > 0 && (
          <div className="flex items-center justify-between gap-2 rounded-lg border border-[#E0E7FF] bg-[#EEF2FF] px-2.5 py-1.5 text-[11.5px] text-[#4338CA]">
            <span>Copied {DAYS.find((d) => d.v === copied.day)?.label} · {copied.times.length} slot{copied.times.length === 1 ? '' : 's'}</span>
            <div className="flex items-center gap-1.5">
              <button type="button" onClick={pasteToAll} className="inline-flex items-center gap-1 rounded-md bg-[#4338CA] px-2 py-1 font-semibold text-white hover:bg-[#3730A3]">
                <ClipboardPaste className="h-3 w-3" strokeWidth={2.5} /> Paste to all other days
              </button>
              <button type="button" onClick={() => setCopied(null)} className="rounded-md px-1.5 py-1 font-medium hover:bg-white/60">Clear</button>
            </div>
          </div>
        )}
        {DAYS.map((d) => {
          const daySlots = slots.filter((s) => s.day_of_week === d.v);
          const canPasteHere = copied && copied.times.length > 0 && copied.day !== d.v;
          return (
            <div key={d.v} className="flex gap-2.5 rounded-lg border border-[#F0F0F0] bg-[#FCFCFC] px-2.5 py-2">
              <div className="w-9 shrink-0 pt-1.5 text-[11.5px] font-semibold text-[#525252]">{d.label}</div>
              <div className="flex flex-1 flex-col gap-1.5">
                <Droppable droppableId={`day-${d.v}`}>
                  {(provided) => (
                    <div ref={provided.innerRef} {...provided.droppableProps} className="flex flex-col gap-1.5">
                      {daySlots.map((s, i) => (
                        <Draggable key={s._k} draggableId={s._k} index={i}>
                          {(dp, snapshot) => (
                            <div
                              ref={dp.innerRef}
                              {...dp.draggableProps}
                              className={`flex items-center gap-1 rounded-lg ${snapshot.isDragging ? 'bg-white shadow-md ring-1 ring-[#EAEAEA]' : ''}`}
                            >
                              <button
                                type="button"
                                {...dp.dragHandleProps}
                                aria-label="Drag to reorder"
                                className="cursor-grab touch-none rounded px-0.5 text-[#C4C4C4] hover:text-[#737373] active:cursor-grabbing"
                              >
                                <GripVertical className="h-4 w-4" strokeWidth={2} />
                              </button>
                              <input type="time" value={s.start_time} onChange={(e) => updateSlot(s._k, { start_time: e.target.value })} className={`${inputClass} flex-1`} />
                              <span className="text-[#A3A3A3]">–</span>
                              <input type="time" value={s.end_time} onChange={(e) => updateSlot(s._k, { end_time: e.target.value })} className={`${inputClass} flex-1`} />
                              <button type="button" onClick={() => removeSlot(s._k)} className="rounded-md p-1.5 text-[#B91C1C] hover:bg-[#FEF2F2]"><Trash2 className="h-3.5 w-3.5" strokeWidth={2} /></button>
                            </div>
                          )}
                        </Draggable>
                      ))}
                      {provided.placeholder}
                    </div>
                  )}
                </Droppable>
                {daySlots.length === 0 && <span className="py-0.5 text-[11px] text-[#C4C4C4]">No slots</span>}
                <div className="flex flex-wrap items-center gap-1">
                  <button type="button" onClick={() => addSlotForDay(d.v)} className="inline-flex w-fit items-center gap-1 rounded-md px-1.5 py-0.5 text-[11px] font-medium text-[#047857] hover:bg-[#ECFDF5]">
                    <Plus className="h-3 w-3" strokeWidth={2.5} /> Add time
                  </button>
                  {daySlots.length > 0 && (
                    <button type="button" onClick={() => copyDay(d.v)} className="inline-flex w-fit items-center gap-1 rounded-md px-1.5 py-0.5 text-[11px] font-medium text-[#525252] hover:bg-[#F5F5F5]">
                      <Copy className="h-3 w-3" strokeWidth={2.5} /> {copied?.day === d.v ? 'Copied' : 'Copy day'}
                    </button>
                  )}
                  {canPasteHere && (
                    <button type="button" onClick={() => pasteToDay(d.v)} className="inline-flex w-fit items-center gap-1 rounded-md px-1.5 py-0.5 text-[11px] font-medium text-[#4338CA] hover:bg-[#EEF2FF]">
                      <ClipboardPaste className="h-3 w-3" strokeWidth={2.5} /> Paste
                    </button>
                  )}
                </div>
              </div>
            </div>
          );
        })}
      </div>
    </DragDropContext>
  );
}
