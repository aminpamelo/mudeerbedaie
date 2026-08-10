import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import WorkspaceLayout from '@/workspace/layouts/WorkspaceLayout';
import TaskCard from '@/workspace/components/TaskCard';
import TaskForm from '@/workspace/components/TaskForm';
import { workspaceSend } from '@/workspace/lib/api';

const DOT_COLORS = {
    slate: 'bg-slate-400',
    blue: 'bg-blue-500',
    purple: 'bg-purple-500',
    emerald: 'bg-emerald-500',
};

const MOVE_COLORS = {
    slate: 'bg-slate-400',
    blue: 'bg-blue-500',
    purple: 'bg-purple-500',
    emerald: 'bg-emerald-500',
};

export default function Board({ columns = [], tasks = {}, projects = [] }) {
    const [showForm, setShowForm] = useState(false);
    const reload = () => router.reload({ preserveScroll: true });

    const moveTask = async (taskId, newStatus) => {
        await workspaceSend(`/workspace/tasks/${taskId}/status`, {
            method: 'PATCH',
            body: { status: newStatus },
        });
        reload();
    };

    return (
        <WorkspaceLayout
            title="Board"
            subtitle="Kanban view — drag tasks between columns"
            actions={
                <button
                    onClick={() => setShowForm(true)}
                    className="flex items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-500 to-violet-500 px-4 py-2.5 text-[13px] font-semibold text-white shadow-lg shadow-indigo-500/25"
                >
                    <Plus className="h-4 w-4" strokeWidth={2.4} /> New Task
                </button>
            }
        >
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {columns.map((col) => {
                    const colTasks = tasks[col.key] ?? [];
                    return (
                        <div
                            key={col.key}
                            className="flex flex-col rounded-2xl bg-slate-50 p-3"
                        >
                            <div className="mb-3 flex items-center justify-between px-1">
                                <div className="flex items-center gap-2">
                                    <div
                                        className={`h-2.5 w-2.5 rounded-full ${DOT_COLORS[col.color]}`}
                                    />
                                    <span className="text-[13px] font-bold text-slate-700">
                                        {col.label}
                                    </span>
                                    <span className="grid h-5 min-w-[20px] place-items-center rounded-full bg-slate-200 px-1.5 text-[10px] font-bold text-slate-600">
                                        {colTasks.length}
                                    </span>
                                </div>
                            </div>
                            <div className="max-h-[calc(100vh-220px)] flex-1 space-y-2.5 overflow-y-auto">
                                {colTasks.map((task) => (
                                    <div key={task.id} className="group relative">
                                        <TaskCard
                                            task={task}
                                            onClick={() =>
                                                router.visit(
                                                    `/workspace/tasks/${task.id}`
                                                )
                                            }
                                        />
                                        {/* Quick status move buttons */}
                                        <div className="absolute -top-1 right-1 hidden gap-0.5 group-hover:flex">
                                            {columns
                                                .filter(
                                                    (c) => c.key !== col.key
                                                )
                                                .map((c) => (
                                                    <button
                                                        key={c.key}
                                                        onClick={(e) => {
                                                            e.stopPropagation();
                                                            moveTask(
                                                                task.id,
                                                                c.key
                                                            );
                                                        }}
                                                        className={`rounded px-1.5 py-0.5 text-[9px] font-bold text-white ${MOVE_COLORS[c.color]} opacity-80 hover:opacity-100`}
                                                        title={`Move to ${c.label}`}
                                                    >
                                                        {c.label.charAt(0)}
                                                    </button>
                                                ))}
                                        </div>
                                    </div>
                                ))}
                                {colTasks.length === 0 && (
                                    <div className="rounded-xl border-2 border-dashed border-slate-200 px-3 py-8 text-center text-[12px] text-slate-400">
                                        No tasks
                                    </div>
                                )}
                            </div>
                        </div>
                    );
                })}
            </div>
            {showForm && (
                <TaskForm
                    onSaved={() => {
                        setShowForm(false);
                        reload();
                    }}
                    onClose={() => setShowForm(false)}
                />
            )}
        </WorkspaceLayout>
    );
}
