import { useState } from 'react';
import { Repeat, Trash2, Save, Plus } from 'lucide-react';
import { workspaceSend } from '@/workspace/lib/api';
import { cn, formatDate } from '@/workspace/lib/utils';

const FREQUENCIES = [
    { value: 'daily', label: 'Daily' },
    { value: 'weekly', label: 'Weekly' },
    { value: 'monthly', label: 'Monthly' },
    { value: 'yearly', label: 'Yearly' },
];

const DAYS_OF_WEEK = [
    { value: 0, label: 'Sunday' },
    { value: 1, label: 'Monday' },
    { value: 2, label: 'Tuesday' },
    { value: 3, label: 'Wednesday' },
    { value: 4, label: 'Thursday' },
    { value: 5, label: 'Friday' },
    { value: 6, label: 'Saturday' },
];

export default function RecurringConfig({ task, onUpdate }) {
    const config = task.recurring_config;
    const [editing, setEditing] = useState(false);
    const [loading, setLoading] = useState(false);
    const [form, setForm] = useState({
        frequency: config?.frequency || 'weekly',
        day_of_week: config?.day_of_week ?? 1,
        day_of_month: config?.day_of_month ?? 1,
        time_of_day: config?.time_of_day || '09:00',
    });

    const save = async () => {
        setLoading(true);
        try {
            if (config) {
                await workspaceSend(`/workspace/tasks/${task.id}/recurring/${config.id}`, { method: 'PATCH', body: form });
            } else {
                await workspaceSend(`/workspace/tasks/${task.id}/recurring`, { method: 'POST', body: form });
            }
            onUpdate?.();
            setEditing(false);
        } catch (err) {
            console.error('Failed to save recurring config:', err);
        } finally {
            setLoading(false);
        }
    };

    const remove = async () => {
        if (!config || !confirm('Remove recurring configuration?')) return;
        setLoading(true);
        try {
            await workspaceSend(`/workspace/tasks/${task.id}/recurring/${config.id}`, { method: 'DELETE' });
            onUpdate?.();
        } catch (err) {
            console.error('Failed to delete recurring config:', err);
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5">
            <div className="flex items-center justify-between mb-3">
                <div className="flex items-center gap-2">
                    <Repeat className="h-4 w-4 text-slate-400" />
                    <h4 className="text-[12px] font-semibold uppercase tracking-wider text-slate-400">Recurring</h4>
                </div>
                {!config && !editing && (
                    <button
                        onClick={() => setEditing(true)}
                        className="flex items-center gap-1 rounded-lg border border-indigo-200 bg-indigo-50 px-2 py-1 text-[11px] font-semibold text-indigo-600 hover:bg-indigo-100"
                    >
                        <Plus className="h-3 w-3" />
                        Setup
                    </button>
                )}
            </div>

            {/* Existing config display */}
            {config && !editing && (
                <div>
                    <div className="space-y-1.5">
                        <div className="flex items-center justify-between">
                            <span className="text-[12px] text-slate-400">Frequency</span>
                            <span className="text-[12.5px] font-medium text-slate-700 capitalize">{config.frequency}</span>
                        </div>
                        {config.frequency === 'weekly' && (
                            <div className="flex items-center justify-between">
                                <span className="text-[12px] text-slate-400">Day</span>
                                <span className="text-[12.5px] font-medium text-slate-700">{DAYS_OF_WEEK.find(d => d.value === config.day_of_week)?.label ?? '-'}</span>
                            </div>
                        )}
                        {config.frequency === 'monthly' && (
                            <div className="flex items-center justify-between">
                                <span className="text-[12px] text-slate-400">Day of Month</span>
                                <span className="text-[12.5px] font-medium text-slate-700">{config.day_of_month ?? '-'}</span>
                            </div>
                        )}
                        <div className="flex items-center justify-between">
                            <span className="text-[12px] text-slate-400">Time</span>
                            <span className="text-[12.5px] font-medium text-slate-700">{config.time_of_day ?? '09:00'}</span>
                        </div>
                        {config.next_due_at && (
                            <div className="flex items-center justify-between">
                                <span className="text-[12px] text-slate-400">Next Due</span>
                                <span className="text-[12.5px] font-medium text-indigo-600">{formatDate(config.next_due_at)}</span>
                            </div>
                        )}
                        <div className="flex items-center justify-between">
                            <span className="text-[12px] text-slate-400">Status</span>
                            <span className={cn('rounded-full px-2 py-0.5 text-[10px] font-bold uppercase', config.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500')}>
                                {config.is_active ? 'Active' : 'Paused'}
                            </span>
                        </div>
                    </div>

                    <div className="mt-3 flex gap-2">
                        <button
                            onClick={() => { setForm({ frequency: config.frequency, day_of_week: config.day_of_week ?? 1, day_of_month: config.day_of_month ?? 1, time_of_day: config.time_of_day || '09:00' }); setEditing(true); }}
                            className="flex-1 rounded-lg border border-slate-200 px-2.5 py-1.5 text-[11px] font-semibold text-slate-500 hover:bg-slate-50"
                        >
                            Edit
                        </button>
                        <button
                            onClick={remove}
                            disabled={loading}
                            className="rounded-lg border border-red-200 px-2.5 py-1.5 text-[11px] font-semibold text-red-500 hover:bg-red-50 disabled:opacity-50"
                        >
                            <Trash2 className="h-3.5 w-3.5" />
                        </button>
                    </div>
                </div>
            )}

            {/* No config */}
            {!config && !editing && (
                <p className="text-[12.5px] text-slate-400">Not configured as recurring.</p>
            )}

            {/* Edit form */}
            {editing && (
                <div className="space-y-3">
                    <div>
                        <label className="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-slate-400">Frequency</label>
                        <select
                            value={form.frequency}
                            onChange={(e) => setForm({ ...form, frequency: e.target.value })}
                            className="w-full rounded-lg border border-slate-200 px-2.5 py-2 text-[13px] text-slate-700 focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                        >
                            {FREQUENCIES.map(f => (
                                <option key={f.value} value={f.value}>{f.label}</option>
                            ))}
                        </select>
                    </div>

                    {form.frequency === 'weekly' && (
                        <div>
                            <label className="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-slate-400">Day of Week</label>
                            <select
                                value={form.day_of_week}
                                onChange={(e) => setForm({ ...form, day_of_week: Number(e.target.value) })}
                                className="w-full rounded-lg border border-slate-200 px-2.5 py-2 text-[13px] text-slate-700 focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                            >
                                {DAYS_OF_WEEK.map(d => (
                                    <option key={d.value} value={d.value}>{d.label}</option>
                                ))}
                            </select>
                        </div>
                    )}

                    {form.frequency === 'monthly' && (
                        <div>
                            <label className="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-slate-400">Day of Month</label>
                            <input
                                type="number"
                                min="1"
                                max="31"
                                value={form.day_of_month}
                                onChange={(e) => setForm({ ...form, day_of_month: Number(e.target.value) })}
                                className="w-full rounded-lg border border-slate-200 px-2.5 py-2 text-[13px] text-slate-700 focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                            />
                        </div>
                    )}

                    <div>
                        <label className="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-slate-400">Time of Day</label>
                        <input
                            type="time"
                            value={form.time_of_day}
                            onChange={(e) => setForm({ ...form, time_of_day: e.target.value })}
                            className="w-full rounded-lg border border-slate-200 px-2.5 py-2 text-[13px] text-slate-700 focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                        />
                    </div>

                    <div className="flex gap-2 pt-1">
                        <button
                            onClick={save}
                            disabled={loading}
                            className="flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-gradient-to-r from-indigo-500 to-violet-500 px-3 py-2 text-[12px] font-semibold text-white shadow-sm disabled:opacity-50"
                        >
                            <Save className="h-3.5 w-3.5" />
                            {loading ? 'Saving...' : 'Save'}
                        </button>
                        <button
                            onClick={() => setEditing(false)}
                            className="rounded-lg border border-slate-200 px-3 py-2 text-[12px] font-semibold text-slate-500 hover:bg-slate-50"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
