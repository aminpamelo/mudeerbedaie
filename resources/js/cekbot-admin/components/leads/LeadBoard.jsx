import { useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { Plus, MoreVertical, Pencil, Trash2, MessageCircle, MoveRight, Check } from 'lucide-react';
import { cn, contactDisplay, formatPhone, timeAgo, initialsFrom } from '@/cekbot-admin/lib/utils';
import { leadColor, avatarTint } from '@/cekbot-admin/lib/leadColors';

const keyOf = (id) => (id == null ? 'none' : String(id));

export default function LeadBoard({ board, colorOptions, onAddCategory, onEditCategory }) {
  const [columns, setColumns] = useState(board);
  const [overKey, setOverKey] = useState(null);
  const [cardMenu, setCardMenu] = useState(null); // lead id whose move-menu is open
  const [colMenu, setColMenu] = useState(null); // category id whose menu is open
  const dragRef = useRef(null); // { leadId, fromColId }

  useEffect(() => { setColumns(board); }, [board]);

  function move(leadId, fromColId, toColId) {
    if (keyOf(fromColId) === keyOf(toColId)) return;
    setCardMenu(null);

    setColumns((prev) => {
      let moved = null;
      const stripped = prev.map((col) => {
        if (col.leads.some((l) => l.id === leadId)) {
          moved = col.leads.find((l) => l.id === leadId);
          return { ...col, leads: col.leads.filter((l) => l.id !== leadId), total: Math.max(0, col.total - 1) };
        }
        return col;
      });
      if (!moved) return prev;
      return stripped.map((col) => {
        if (keyOf(col.id) === keyOf(toColId)) {
          const cat = col.id == null ? null : { id: col.id, name: col.name, color: col.color };
          return { ...col, leads: [{ ...moved, category_id: col.id, category: cat }, ...col.leads], total: col.total + 1 };
        }
        return col;
      });
    });

    router.post(route('cekbot.leads.move', leadId), { lead_category_id: toColId }, {
      preserveScroll: true, preserveState: true, only: ['board', 'categories', 'stats', 'flash'],
    });
  }

  function deleteCategory(col) {
    setColMenu(null);
    if (!window.confirm(`Padam kategori "${col.name}"? Leads di dalamnya kembali ke "Tiada kategori".`)) return;
    router.delete(route('cekbot.leads.categories.destroy', col.id), { preserveScroll: true });
  }

  function onDrop(e, toColId) {
    e.preventDefault();
    setOverKey(null);
    const d = dragRef.current;
    if (d) move(d.leadId, d.fromColId, toColId);
    dragRef.current = null;
  }

  return (
    <div className="flex gap-4 overflow-x-auto pb-4">
      {columns.map((col) => {
        const c = leadColor(col.color);
        const isOver = overKey === keyOf(col.id);
        return (
          <div
            key={keyOf(col.id)}
            className="flex w-[300px] shrink-0 flex-col"
            onDragOver={(e) => { e.preventDefault(); setOverKey(keyOf(col.id)); }}
            onDragLeave={(e) => { if (e.currentTarget === e.target) setOverKey(null); }}
            onDrop={(e) => onDrop(e, col.id)}
          >
            {/* Column header */}
            <div className="mb-2.5 flex items-center gap-2 px-1">
              <span className={cn('h-2.5 w-2.5 shrink-0 rounded-full', c.dot)} />
              <h3 className="truncate text-[13.5px] font-bold text-white">{col.name}</h3>
              <span className="rounded-md bg-white/8 px-1.5 py-0.5 text-[11px] font-semibold tabular-nums text-white/50">{col.total}</span>
              <div className="ml-auto">
                {col.id != null && (
                  <div className="relative">
                    <button type="button" onClick={() => setColMenu(colMenu === col.id ? null : col.id)} className="grid h-7 w-7 place-items-center rounded-lg text-white/40 hover:bg-white/8 hover:text-white" aria-label="Menu kategori">
                      <MoreVertical className="h-4 w-4" />
                    </button>
                    {colMenu === col.id && (
                      <>
                        <div className="fixed inset-0 z-10" onClick={() => setColMenu(null)} aria-hidden="true" />
                        <div className="absolute right-0 top-8 z-20 w-36 overflow-hidden rounded-xl border border-white/10 bg-[#0B1A14] py-1 shadow-2xl">
                          <button type="button" onClick={() => { setColMenu(null); onEditCategory(col); }} className="flex w-full items-center gap-2 px-3 py-2 text-left text-[12.5px] text-white/70 hover:bg-white/8"><Pencil className="h-3.5 w-3.5" /> Edit</button>
                          <button type="button" onClick={() => deleteCategory(col)} className="flex w-full items-center gap-2 px-3 py-2 text-left text-[12.5px] text-rose-300 hover:bg-rose-500/10"><Trash2 className="h-3.5 w-3.5" /> Padam</button>
                        </div>
                      </>
                    )}
                  </div>
                )}
              </div>
            </div>

            {/* Column body (drop zone) */}
            <div className={cn(
              'flex min-h-[140px] flex-1 flex-col gap-2 rounded-2xl border p-2 transition-colors',
              isOver ? cn('border-transparent ring-2', c.ring, c.soft) : 'border-white/8 bg-white/[0.02]'
            )}>
              {col.leads.length === 0 && (
                <div className="grid flex-1 place-items-center rounded-xl border border-dashed border-white/8 py-6 text-center text-[12px] text-white/25">
                  {isOver ? 'Lepaskan di sini' : 'Tiada lead'}
                </div>
              )}

              {col.leads.map((lead) => (
                <div
                  key={lead.id}
                  draggable
                  onDragStart={(e) => { dragRef.current = { leadId: lead.id, fromColId: col.id }; e.dataTransfer.effectAllowed = 'move'; }}
                  onDragEnd={() => { dragRef.current = null; setOverKey(null); }}
                  className="group cursor-grab rounded-xl border border-white/8 bg-white/[0.04] p-3 transition hover:border-white/15 hover:bg-white/[0.07] active:cursor-grabbing"
                >
                  <div className="flex items-start gap-2.5">
                    <div className={cn('grid h-9 w-9 shrink-0 place-items-center rounded-full text-[12px] font-bold', avatarTint(lead.name || lead.phone))}>
                      {initialsFrom(contactDisplay(lead.name, lead.phone, false))}
                    </div>
                    <div className="min-w-0 flex-1">
                      <p className="truncate text-[13px] font-semibold text-white">{contactDisplay(lead.name, lead.phone, false)}</p>
                      <p className="truncate text-[11.5px] text-white/45">{formatPhone(lead.phone)}</p>
                    </div>
                    {/* Move-to menu (keyboard / mobile alternative to drag) */}
                    <div className="relative">
                      <button type="button" onClick={() => setCardMenu(cardMenu === lead.id ? null : lead.id)} className="grid h-6 w-6 place-items-center rounded-md text-white/30 opacity-0 transition hover:bg-white/10 hover:text-white group-hover:opacity-100" aria-label="Pindah lead">
                        <MoveRight className="h-3.5 w-3.5" />
                      </button>
                      {cardMenu === lead.id && (
                        <>
                          <div className="fixed inset-0 z-10" onClick={() => setCardMenu(null)} aria-hidden="true" />
                          <div className="absolute right-0 top-7 z-20 w-44 overflow-hidden rounded-xl border border-white/10 bg-[#0B1A14] py-1 shadow-2xl">
                            <p className="px-3 py-1.5 text-[10.5px] font-semibold uppercase tracking-wide text-white/35">Pindah ke</p>
                            {columns.map((target) => {
                              const tc = leadColor(target.color);
                              const current = keyOf(target.id) === keyOf(col.id);
                              return (
                                <button key={keyOf(target.id)} type="button" disabled={current} onClick={() => move(lead.id, col.id, target.id)}
                                  className={cn('flex w-full items-center gap-2 px-3 py-1.5 text-left text-[12.5px]', current ? 'text-white/30' : 'text-white/75 hover:bg-white/8')}>
                                  <span className={cn('h-2 w-2 rounded-full', tc.dot)} />
                                  <span className="flex-1 truncate">{target.name}</span>
                                  {current && <Check className="h-3.5 w-3.5 text-emerald-400" />}
                                </button>
                              );
                            })}
                          </div>
                        </>
                      )}
                    </div>
                  </div>

                  <div className="mt-2.5 flex items-center justify-between text-[11px] text-white/40">
                    <span className="inline-flex items-center gap-1"><MessageCircle className="h-3 w-3" /> {lead.messages_count}</span>
                    {lead.last_message_at && <span>{timeAgo(lead.last_message_at)}</span>}
                  </div>
                </div>
              ))}
            </div>
          </div>
        );
      })}

      {/* Add category column */}
      <div className="w-[300px] shrink-0">
        <button type="button" onClick={onAddCategory} className="flex h-full min-h-[180px] w-full flex-col items-center justify-center gap-2 rounded-2xl border border-dashed border-white/12 text-white/40 transition hover:border-emerald-500/40 hover:text-emerald-300">
          <Plus className="h-5 w-5" />
          <span className="text-[13px] font-medium">Tambah kategori</span>
        </button>
      </div>
    </div>
  );
}
