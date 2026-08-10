import { useState } from 'react';
import { X, Loader2 } from 'lucide-react';
import { workspaceSend } from '@/workspace/lib/api';

export default function TaskForm({ task = null, projectId = null, onSaved, onClose }) {
    const isEdit = !!task;
    const [form, setForm] = useState({
        title: task?.title ?? '',
        description: task?.description ?? '',
        priority: task?.priority ?? 'medium',
        status: task?.status ?? 'pending',
        deadline: task?.deadline?.split('T')[0] ?? '',
        start_date: task?.start_date ?? '',
        estimated_minutes: task?.estimated_minutes ?? '',
        project_id: task?.project_id ?? projectId ?? '',
        tags: task?.tags?.join(', ') ?? '',
    });
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');

    const set = (k, v) => setForm(f => ({ ...f, [k]: v }));

    const submit = async (e) => {
        if (e) e.preventDefault();
        setBusy(true);
        setError('');
        try {
            const body = {
                ...form,
                estimated_minutes: form.estimated_minutes ? parseInt(form.estimated_minutes) : null,
                project_id: form.project_id || null,
                tags: form.tags ? form.tags.split(',').map(t => t.trim()).filter(Boolean) : [],
            };
            const url = isEdit ? `/workspace/tasks/${task.id}` : '/workspace/tasks';
            const method = isEdit ? 'PATCH' : 'POST';
            await workspaceSend(url, { method, body });
            onSaved?.();
        } catch (err) {
            setError(err.message);
        } finally {
            setBusy(false);
        }
    };

    const inputCls = 'w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-[13.5px] text-slate-900 outline-none transition focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100';
    const labelCls = 'block text-[12px] font-semibold uppercase tracking-wider text-slate-400 mb-1.5';

    return (
        <div className="fixed inset-0 z-[70] flex items-center justify-center p-4" role="dialog">
            <div className="absolute inset-0 bg-black/40 backdrop-blur-sm" onClick={onClose} />
            <div className="relative z-10 w-full max-w-lg rounded-2xl bg-white shadow-xl">
                <div className="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <h3 className="text-[16px] font-bold text-slate-900">{isEdit ? 'Edit Task' : 'New Task'}</h3>
                    <button onClick={onClose} className="grid h-8 w-8 place-items-center rounded-lg text-slate-400 hover:bg-slate-100"><X className="h-4 w-4" /></button>
                </div>

                <form onSubmit={submit} className="max-h-[70vh] overflow-y-auto px-5 py-4 space-y-4">
                    {error && <p className="rounded-xl bg-red-50 px-3 py-2 text-[12.5px] text-red-700">{error}</p>}

                    <div>
                        <label className={labelCls}>Title</label>
                        <input value={form.title} onChange={e => set('title', e.target.value)} className={inputCls} required />
                    </div>

                    <div>
                        <label className={labelCls}>Description</label>
                        <textarea value={form.description} onChange={e => set('description', e.target.value)} rows={3} className={inputCls} />
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className={labelCls}>Priority</label>
                            <select value={form.priority} onChange={e => set('priority', e.target.value)} className={inputCls}>
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        <div>
                            <label className={labelCls}>Status</label>
                            <select value={form.status} onChange={e => set('status', e.target.value)} className={inputCls}>
                                <option value="pending">To Do</option>
                                <option value="in_progress">In Progress</option>
                                <option value="review">Review</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className={labelCls}>Start Date</label>
                            <input type="date" value={form.start_date} onChange={e => set('start_date', e.target.value)} className={inputCls} />
                        </div>
                        <div>
                            <label className={labelCls}>Due Date</label>
                            <input type="date" value={form.deadline} onChange={e => set('deadline', e.target.value)} className={inputCls} />
                        </div>
                    </div>

                    <div>
                        <label className={labelCls}>Estimated Duration (minutes)</label>
                        <input type="number" value={form.estimated_minutes} onChange={e => set('estimated_minutes', e.target.value)} className={inputCls} min="1" />
                    </div>

                    <div>
                        <label className={labelCls}>Tags (comma separated)</label>
                        <input value={form.tags} onChange={e => set('tags', e.target.value)} className={inputCls} placeholder="design, video, ebook" />
                    </div>
                </form>

                <div className="flex gap-2 border-t border-slate-100 px-5 py-4">
                    <button type="button" onClick={onClose} className="flex-1 rounded-xl bg-slate-100 py-2.5 text-[13.5px] font-semibold text-slate-600 hover:bg-slate-200">Cancel</button>
                    <button type="button" onClick={submit} disabled={busy} className="flex flex-1 items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-indigo-500 to-violet-500 py-2.5 text-[13.5px] font-semibold text-white shadow-lg shadow-indigo-500/25 hover:shadow-xl disabled:opacity-50">
                        {busy && <Loader2 className="h-4 w-4 animate-spin" />}
                        {isEdit ? 'Update' : 'Create'}
                    </button>
                </div>
            </div>
        </div>
    );
}
