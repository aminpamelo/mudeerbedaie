import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Plus, Filter } from 'lucide-react';
import WorkspaceLayout from '@/workspace/layouts/WorkspaceLayout';
import TaskCard from '@/workspace/components/TaskCard';
import TaskForm from '@/workspace/components/TaskForm';
import { cn } from '@/workspace/lib/utils';

const TABS = [
    { key: 'all', label: 'All' },
    { key: 'pending', label: 'To Do' },
    { key: 'in_progress', label: 'In Progress' },
    { key: 'review', label: 'Review' },
    { key: 'completed', label: 'Completed' },
];

export default function MyTasks({ tasks }) {
    const [showForm, setShowForm] = useState(false);
    const [tab, setTab] = useState('all');
    const rows = tasks?.data ?? [];

    const filtered = tab === 'all' ? rows : rows.filter(t => t.status === tab);

    const reload = () => router.reload({ preserveScroll: true });

    return (
        <WorkspaceLayout
            title="My Tasks"
            subtitle="All tasks assigned to you"
            actions={
                <button onClick={() => setShowForm(true)} className="flex items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-500 to-violet-500 px-4 py-2.5 text-[13px] font-semibold text-white shadow-lg shadow-indigo-500/25">
                    <Plus className="h-4 w-4" strokeWidth={2.4} /> New Task
                </button>
            }
        >
            <div className="mb-4 flex items-center gap-1 rounded-xl bg-white p-1 border border-slate-200">
                {TABS.map(t => (
                    <button
                        key={t.key}
                        onClick={() => setTab(t.key)}
                        className={cn('rounded-lg px-3 py-1.5 text-[12.5px] font-semibold transition', tab === t.key ? 'bg-gradient-to-r from-indigo-500 to-violet-500 text-white shadow' : 'text-slate-500 hover:text-slate-700')}
                    >
                        {t.label}
                    </button>
                ))}
            </div>

            {filtered.length === 0 ? (
                <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-white px-6 py-16 text-center">
                    <div className="grid h-14 w-14 place-items-center rounded-2xl bg-indigo-50 text-indigo-500">
                        <Filter className="h-7 w-7" strokeWidth={1.8} />
                    </div>
                    <h3 className="mt-4 text-[16px] font-semibold text-slate-900">No tasks</h3>
                    <p className="mt-1 text-[13.5px] text-slate-400">Create a new task to get started.</p>
                </div>
            ) : (
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    {filtered.map(task => (
                        <TaskCard key={task.id} task={task} onClick={() => router.visit(`/workspace/tasks/${task.id}`)} />
                    ))}
                </div>
            )}

            {showForm && <TaskForm onSaved={() => { setShowForm(false); reload(); }} onClose={() => setShowForm(false)} />}
        </WorkspaceLayout>
    );
}
