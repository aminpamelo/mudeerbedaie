import { useState } from 'react';
import { router, Link } from '@inertiajs/react';
import { ArrowLeft, Plus, Users, Calendar, FolderKanban } from 'lucide-react';
import WorkspaceLayout from '@/workspace/layouts/WorkspaceLayout';
import TaskCard from '@/workspace/components/TaskCard';
import TaskForm from '@/workspace/components/TaskForm';
import { cn, formatDate } from '@/workspace/lib/utils';

const STATUS_COLORS = {
    active: 'bg-emerald-100 text-emerald-700',
    on_hold: 'bg-amber-100 text-amber-700',
    completed: 'bg-blue-100 text-blue-700',
    archived: 'bg-slate-100 text-slate-500',
};

export default function ProjectDetail({ project, tasks = [] }) {
    const [showForm, setShowForm] = useState(false);
    const reload = () => router.reload({ preserveScroll: true });

    const completed = tasks.filter((t) => t.status === 'completed').length;
    const progress =
        tasks.length > 0 ? Math.round((completed / tasks.length) * 100) : 0;

    return (
        <WorkspaceLayout
            title={project.name}
            subtitle={project.department?.name ?? 'No department'}
        >
            <Link
                href="/workspace/projects"
                className="mb-4 inline-flex items-center gap-1.5 text-[13px] font-medium text-slate-400 hover:text-slate-600"
            >
                <ArrowLeft className="h-4 w-4" /> Back to projects
            </Link>

            {/* Project header */}
            <div className="mb-6 rounded-2xl border border-slate-200 bg-white p-5">
                <div className="flex items-start justify-between">
                    <div className="flex items-center gap-3">
                        <div
                            className="grid h-12 w-12 place-items-center rounded-xl text-white"
                            style={{ background: project.color }}
                        >
                            <FolderKanban className="h-6 w-6" />
                        </div>
                        <div>
                            <h2 className="text-[18px] font-bold text-slate-900">
                                {project.name}
                            </h2>
                            <span
                                className={cn(
                                    'mt-1 inline-block rounded-full px-2 py-0.5 text-[10px] font-bold uppercase',
                                    STATUS_COLORS[project.status]
                                )}
                            >
                                {project.status}
                            </span>
                        </div>
                    </div>
                    <button
                        onClick={() => setShowForm(true)}
                        className="flex items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-500 to-violet-500 px-4 py-2.5 text-[13px] font-semibold text-white shadow-lg shadow-indigo-500/25"
                    >
                        <Plus className="h-4 w-4" /> Add Task
                    </button>
                </div>

                {project.description && (
                    <p className="mt-3 text-[13.5px] text-slate-600">
                        {project.description}
                    </p>
                )}

                <div className="mt-4 flex items-center gap-6 text-[12px] text-slate-400">
                    <span className="flex items-center gap-1">
                        <Users className="h-3.5 w-3.5" />{' '}
                        {project.members?.length ?? 0} members
                    </span>
                    {project.target_date && (
                        <span className="flex items-center gap-1">
                            <Calendar className="h-3.5 w-3.5" /> Due:{' '}
                            {formatDate(project.target_date)}
                        </span>
                    )}
                    <span>
                        {tasks.length} tasks &middot; {completed} completed
                    </span>
                </div>

                {/* Progress bar */}
                <div className="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">
                    <div
                        className="h-full rounded-full bg-gradient-to-r from-indigo-500 to-violet-500 transition-all"
                        style={{ width: `${progress}%` }}
                    />
                </div>
                <p className="mt-1 text-right text-[11px] text-slate-400">
                    {progress}% complete
                </p>
            </div>

            {/* Tasks */}
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                {tasks.map((task) => (
                    <TaskCard
                        key={task.id}
                        task={task}
                        onClick={() =>
                            router.visit(`/workspace/tasks/${task.id}`)
                        }
                    />
                ))}
            </div>

            {tasks.length === 0 && (
                <div className="rounded-2xl border border-dashed border-slate-200 bg-white px-6 py-12 text-center">
                    <p className="text-[14px] font-medium text-slate-500">
                        No tasks in this project yet.
                    </p>
                </div>
            )}

            {showForm && (
                <TaskForm
                    projectId={project.id}
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
