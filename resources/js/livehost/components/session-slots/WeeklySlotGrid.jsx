import { DragDropContext, Draggable, Droppable } from '@hello-pangea/dnd';
import { GripVertical, Plus, Trash2 } from 'lucide-react';

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
  const updateSlot = (k, patch) =>
    onChange(slots.map((s) => (s._k === k ? { ...s, ...patch } : s)));

  const removeSlot = (k) => onChange(slots.filter((s) => s._k !== k));

  const addSlotForDay = (day) =>
    onChange([...slots, { _k: uid(), day_of_week: day, start_time: '', end_time: '' }]);

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
        {DAYS.map((d) => {
          const daySlots = slots.filter((s) => s.day_of_week === d.v);
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
                <button type="button" onClick={() => addSlotForDay(d.v)} className="inline-flex w-fit items-center gap-1 rounded-md px-1.5 py-0.5 text-[11px] font-medium text-[#047857] hover:bg-[#ECFDF5]">
                  <Plus className="h-3 w-3" strokeWidth={2.5} /> Add time
                </button>
              </div>
            </div>
          );
        })}
      </div>
    </DragDropContext>
  );
}
