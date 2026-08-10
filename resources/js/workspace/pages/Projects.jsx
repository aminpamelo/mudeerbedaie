import { useState } from 'react';
import { router, Link } from '@inertiajs/react';
import { Plus, FolderKanban, Users, CheckSquare } from 'lucide-react';
import WorkspaceLayout from '@/workspace/layouts/WorkspaceLayout';
import { workspaceSend } from '@/workspace/lib/api';
import { cn, formatDate } from '@/workspace/lib/utils';

const STATUS_COLORS = {
    active: 'bg-emerald-100 text-emerald-700',
    on_hold: 'bg-amber-100 text-amber-700',
    completed: 'bg-blue-100 text-blue-700',
    archived: 'bg-slate-100 text-slate-500',
};

export default function Projects({ projects = [], departments = [] }) {
    const [showForm, setShowForm] = useState(false);
    const [form, setForm] = useState({
        name: '',
        description: '',
        color: '#6366f1',
        department_id: '',
    });
    const [busy, setBusy] = useState(false);

    const submit = async () => {
        setBusy(true);
        try {
            await workspaceSend('/workspace/projects', {
                body: {
                    ...form,
                    department_id: form.department_id || null,
                },
            });
            setShowForm(false);
            setForm({
                name: '',
                description: '',
                color: '#6366f1',
                department_id: '',
            });
            router.reload({ preserveScroll: true });
        } finally {
            setBusy(false);
        }
    };

    return (
        <WorkspaceLayout
            title="Projects"
            subtitle="Organize tasks into projects"
            actions={
                <button
                    onClick={() => setShowForm(true)}
                    className="flex items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-500 to-violet-500 px-4 py-2.5 text-[13px] font-semibold text-white shadow-lg shadow-indigo-500/25"
                >
                    <Plus className="h-4 w-4" strokeWidth={2.4} /> New Project
                </button>
            }
        >
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {projects.map((p) => (
                    <Link
                        key={p.id}
                        href={`/workspace/projects/${p.id}`}
                        className="group rounded-2xl border border-slate-200 bg-white p-5 transition-all hover:border-indigo-300 hover:shadow-md"
                    >
                        <div className="flex items-start gap-3">
                            <div
                                className="grid h-10 w-10 shrink-0 place-items-center rounded-xl text-white"
                                style={{ background: p.color }}
                            >
                                <FolderKanban
                                    className="h-5 w-5"
                                    strokeWidth={2}
                                />
                            </div>
                            <div className="min-w-0 flex-1">
                                <h3 className="truncate text-[14px] font-bold text-slate-900">
                                    {p.name}
                                </h3>
                                <p className="mt-0.5 text-[12px] text-slate-400">
                                    {p.department?.name ?? 'No department'}
                                </p>
                            </div>
                            <span
                                className={cn(
                                    'shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase',
                                    STATUS_COLORS[p.status]
                                )}
                            >
                                {p.status}
                            </span>
                        </div>
                        {p.description && (
                            <p className="mt-3 line-clamp-2 text-[12.5px] text-slate-500">
                                {p.description}
                            </p>
                        )}
                        <div className="mt-4 flex items-center gap-4 text-[11px] text-slate-400">
                            <span className="flex items-center gap-1">
                                <CheckSquare className="h-3 w-3" />{' '}
                                {p.tasks_count} tasks
                            </span>
                            <span className="flex items-center gap-1">
                                <Users className="h-3 w-3" />{' '}
                                {p.members_count} members
                            </span>
                            {p.target_date && (
                                <span>Due: {formatDate(p.target_date)}</span>
                            )}
                        </div>
                    </Link>
                ))}
            </div>

            {projects.length === 0 && (
                <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-white px-6 py-16 text-center">
                    <div className="grid h-14 w-14 place-items-center rounded-2xl bg-indigo-50 text-indigo-500">
                        <FolderKanban className="h-7 w-7" strokeWidth={1.8} />
                    </div>
                    <h3 className="mt-4 text-[16px] font-semibold text-slate-900">
                        No projects yet
                    </h3>
                    <p className="mt-1 text-[13.5px] text-slate-400">
                        Create your first project to organize tasks.
                    </p>
                </div>
            )}

            {showForm && (
                <div className="fixed inset-0 z-[70] flex items-center justify-center p-4">
                    <div
                        className="absolute inset-0 bg-black/40 backdrop-blur-sm"
                        onClick={() => setShowForm(false)}
                    />
                    <div className="relative z-10 w-full max-w-md rounded-2xl bg-white p-5 shadow-xl">
                        <h3 className="mb-4 text-[16px] font-bold text-slate-900">
                            New Project
                        </h3>
                        <div className="space-y-3">
                            <input
                                value={form.name}
                                onChange={(e) =>
                                    setForm((f) => ({
                                        ...f,
                                        name: e.target.value,
                                    }))
                                }
                                placeholder="Project name"
                                className="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-[13.5px] outline-none focus:border-indigo-400"
                            />
                            <textarea
                                value={form.description}
                                onChange={(e) =>
                                    setForm((f) => ({
                                        ...f,
                                        description: e.target.value,
                                    }))
                                }
                                placeholder="Description (optional)"
                                rows={2}
                                className="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-[13.5px] outline-none focus:border-indigo-400"
                            />
                            <div className="flex gap-3">
                                <div className="flex-1">
                                    <label className="mb-1 block text-[11px] font-semibold uppercase text-slate-400">
                                        Department
                                    </label>
                                    <select
                                        value={form.department_id}
                                        onChange={(e) =>
                                            setForm((f) => ({
                                                ...f,
                                                department_id: e.target.value,
                                            }))
                                        }
                                        className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-[13.5px] outline-none focus:border-indigo-400"
                                    >
                                        <option value="">None</option>
                                        {departments.map((d) => (
                                            <option key={d.id} value={d.id}>
                                                {d.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="mb-1 block text-[11px] font-semibold uppercase text-slate-400">
                                        Color
                                    </label>
                                    <input
                                        type="color"
                                        value={form.color}
                                        onChange={(e) =>
                                            setForm((f) => ({
                                                ...f,
                                                color: e.target.value,
                                            }))
                                        }
                                        className="h-10 w-14 rounded-lg border border-slate-200 p-1"
                                    />
                                </div>
                            </div>
                        </div>
                        <div className="mt-4 flex gap-2">
                            <button
                                onClick={() => setShowForm(false)}
                                className="flex-1 rounded-xl bg-slate-100 py-2.5 text-[13.5px] font-semibold text-slate-600"
                            >
                                Cancel
                            </button>
                            <button
                                onClick={submit}
                                disabled={busy || !form.name}
                                className="flex-1 rounded-xl bg-gradient-to-r from-indigo-500 to-violet-500 py-2.5 text-[13.5px] font-semibold text-white shadow-lg disabled:opacity-50"
                            >
                                Create
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </WorkspaceLayout>
    );
}
