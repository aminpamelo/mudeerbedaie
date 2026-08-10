import { useState } from 'react';
import { Plus, Trash2 } from 'lucide-react';
import { workspaceSend } from '@/workspace/lib/api';

export default function ChecklistEditor({ taskId, items = [], onUpdate }) {
    const [newTitle, setNewTitle] = useState('');
    const [busy, setBusy] = useState(false);

    const add = async () => {
        if (!newTitle.trim()) return;
        setBusy(true);
        try {
            await workspaceSend(`/workspace/tasks/${taskId}/checklists`, { body: { title: newTitle.trim() } });
            setNewTitle('');
            onUpdate?.();
        } finally { setBusy(false); }
    };

    const toggle = async (id) => {
        await workspaceSend(`/workspace/tasks/${taskId}/checklists/${id}/toggle`, { method: 'PATCH' });
        onUpdate?.();
    };

    const remove = async (id) => {
        await workspaceSend(`/workspace/tasks/${taskId}/checklists/${id}`, { method: 'DELETE' });
        onUpdate?.();
    };

    const done = items.filter(i => i.is_completed).length;

    return (
        <div>
            <div className="flex items-center justify-between mb-2">
                <h4 className="text-[12px] font-semibold uppercase tracking-wider text-slate-400">Checklist</h4>
                {items.length > 0 && <span className="text-[11px] font-medium text-slate-400">{done}/{items.length}</span>}
            </div>

            {items.length > 0 && (
                <div className="mb-3 h-1.5 overflow-hidden rounded-full bg-slate-100">
                    <div className="h-full rounded-full bg-gradient-to-r from-indigo-500 to-violet-500 transition-all" style={{ width: `${items.length > 0 ? (done / items.length) * 100 : 0}%` }} />
                </div>
            )}

            <div className="space-y-1">
                {items.map((item) => (
                    <div key={item.id} className="group flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-slate-50">
                        <button onClick={() => toggle(item.id)} className={`grid h-5 w-5 shrink-0 place-items-center rounded border transition ${item.is_completed ? 'border-indigo-500 bg-indigo-500 text-white' : 'border-slate-300 hover:border-indigo-400'}`}>
                            {item.is_completed && <svg className="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={3}><path d="M5 13l4 4L19 7" /></svg>}
                        </button>
                        <span className={`flex-1 text-[13px] ${item.is_completed ? 'text-slate-400 line-through' : 'text-slate-700'}`}>{item.title}</span>
                        <button onClick={() => remove(item.id)} className="invisible rounded p-1 text-slate-300 hover:text-red-500 group-hover:visible">
                            <Trash2 className="h-3.5 w-3.5" />
                        </button>
                    </div>
                ))}
            </div>

            <div className="mt-2 flex items-center gap-2">
                <input
                    value={newTitle}
                    onChange={e => setNewTitle(e.target.value)}
                    onKeyDown={e => e.key === 'Enter' && add()}
                    placeholder="Add item..."
                    className="flex-1 rounded-lg border border-slate-200 px-3 py-2 text-[13px] outline-none focus:border-indigo-400"
                />
                <button onClick={add} disabled={busy || !newTitle.trim()} className="grid h-9 w-9 place-items-center rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-100 disabled:opacity-40">
                    <Plus className="h-4 w-4" strokeWidth={2.4} />
                </button>
            </div>
        </div>
    );
}
