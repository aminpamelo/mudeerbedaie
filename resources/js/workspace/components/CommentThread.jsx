import { useState } from 'react';
import { Send, Trash2 } from 'lucide-react';
import { workspaceSend } from '@/workspace/lib/api';
import { formatDate } from '@/workspace/lib/utils';

export default function CommentThread({ taskId, comments = [], onUpdate }) {
    const [text, setText] = useState('');
    const [busy, setBusy] = useState(false);

    const submit = async () => {
        if (!text.trim()) return;
        setBusy(true);
        try {
            await workspaceSend(`/workspace/tasks/${taskId}/comments`, { body: { content: text.trim() } });
            setText('');
            onUpdate?.();
        } finally { setBusy(false); }
    };

    const remove = async (id) => {
        await workspaceSend(`/workspace/tasks/${taskId}/comments/${id}`, { method: 'DELETE' });
        onUpdate?.();
    };

    return (
        <div>
            <h4 className="text-[12px] font-semibold uppercase tracking-wider text-slate-400 mb-3">Comments ({comments.length})</h4>

            <div className="space-y-3 max-h-64 overflow-y-auto">
                {comments.map((c) => (
                    <div key={c.id} className="group flex gap-2.5">
                        <div className="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-gradient-to-br from-slate-300 to-slate-400 text-[10px] font-bold text-white">
                            {(c.user?.name ?? c.employee?.user?.name ?? '?').charAt(0).toUpperCase()}
                        </div>
                        <div className="flex-1 min-w-0">
                            <div className="flex items-center gap-2">
                                <span className="text-[12.5px] font-semibold text-slate-700">{c.user?.name ?? c.employee?.user?.name ?? 'Unknown'}</span>
                                <span className="text-[11px] text-slate-400">{formatDate(c.created_at)}</span>
                            </div>
                            <p className="mt-0.5 text-[13px] text-slate-600 whitespace-pre-wrap">{c.content}</p>
                        </div>
                        <button onClick={() => remove(c.id)} className="invisible shrink-0 rounded p-1 text-slate-300 hover:text-red-500 group-hover:visible">
                            <Trash2 className="h-3.5 w-3.5" />
                        </button>
                    </div>
                ))}
            </div>

            <div className="mt-3 flex items-end gap-2">
                <textarea
                    value={text}
                    onChange={e => setText(e.target.value)}
                    onKeyDown={e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); submit(); } }}
                    placeholder="Write a comment..."
                    rows={2}
                    className="flex-1 resize-none rounded-xl border border-slate-200 px-3.5 py-2.5 text-[13px] outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100"
                />
                <button onClick={submit} disabled={busy || !text.trim()} className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-gradient-to-r from-indigo-500 to-violet-500 text-white shadow transition hover:shadow-lg disabled:opacity-40">
                    <Send className="h-4 w-4" />
                </button>
            </div>
        </div>
    );
}
