import { useState } from 'react';
import { router } from '@inertiajs/react';
import WorkspaceLayout from '@/workspace/layouts/WorkspaceLayout';
import { cn } from '@/workspace/lib/utils';

export default function Gantt({ tasks = [], projects = [] }) {
    const [filterProject, setFilterProject] = useState('');

    const filtered = filterProject
        ? tasks.filter(t => t.project === projects.find(p => p.id == filterProject)?.name)
        : tasks;

    // Calculate date range
    const dates = filtered.flatMap(t => [t.start, t.end]).filter(Boolean).sort();
    const minDate = dates[0] ? new Date(dates[0]) : new Date();
    const maxDate = dates[dates.length - 1] ? new Date(dates[dates.length - 1]) : new Date();
    const totalDays = Math.max(Math.ceil((maxDate - minDate) / (1000 * 60 * 60 * 24)) + 1, 7);

    const getPos = (dateStr) => {
        const d = new Date(dateStr);
        const dayOffset = Math.ceil((d - minDate) / (1000 * 60 * 60 * 24));
        return (dayOffset / totalDays) * 100;
    };

    const getWidth = (start, end) => {
        const s = new Date(start);
        const e = new Date(end);
        const days = Math.max(Math.ceil((e - s) / (1000 * 60 * 60 * 24)), 1);
        return (days / totalDays) * 100;
    };

    // Generate date headers (weekly)
    const headers = [];
    const d = new Date(minDate);
    while (d <= maxDate) {
        headers.push(new Date(d));
        d.setDate(d.getDate() + 7);
    }

    const priorityBarColors = {
        low: 'bg-slate-400',
        medium: 'bg-blue-500',
        high: 'bg-amber-500',
        urgent: 'bg-red-500',
    };

    return (
        <WorkspaceLayout title="Gantt Chart" subtitle="Timeline view of all tasks">
            <div className="mb-4 flex items-center gap-3">
                <select
                    value={filterProject}
                    onChange={e => setFilterProject(e.target.value)}
                    className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-[13px] outline-none focus:border-indigo-400"
                >
                    <option value="">All Projects</option>
                    {projects.map(p => (
                        <option key={p.id} value={p.id}>{p.name}</option>
                    ))}
                </select>
            </div>

            <div className="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
                {/* Date headers */}
                <div
                    className="relative border-b border-slate-100 px-48"
                    style={{ minWidth: `${Math.max(totalDays * 30, 600)}px` }}
                >
                    <div className="flex">
                        {headers.map((h, i) => (
                            <div
                                key={i}
                                className="shrink-0 border-r border-slate-100 px-2 py-2 text-[10px] font-medium text-slate-400"
                                style={{ width: `${(7 / totalDays) * 100}%` }}
                            >
                                {h.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}
                            </div>
                        ))}
                    </div>
                </div>

                {/* Task rows */}
                <div style={{ minWidth: `${Math.max(totalDays * 30, 600)}px` }}>
                    {filtered.length === 0 ? (
                        <div className="px-6 py-12 text-center text-[13px] text-slate-400">
                            No tasks with deadlines to show.
                        </div>
                    ) : (
                        filtered.map(task => (
                            <div
                                key={task.id}
                                className="group flex items-center border-b border-slate-50 hover:bg-slate-50/50"
                            >
                                <div className="w-48 shrink-0 truncate border-r border-slate-100 px-4 py-2.5">
                                    <button
                                        onClick={() => router.visit(`/workspace/tasks/${task.id}`)}
                                        className="block truncate text-left text-[12.5px] font-medium text-slate-700 hover:text-indigo-600"
                                    >
                                        {task.title}
                                    </button>
                                    <span className="text-[10px] text-slate-400">
                                        {task.project ?? 'No project'}
                                    </span>
                                </div>
                                <div className="relative flex-1 px-1 py-2.5" style={{ height: '36px' }}>
                                    <div
                                        className={cn(
                                            'absolute top-1/2 h-5 -translate-y-1/2 rounded-full transition-all',
                                            priorityBarColors[task.priority] ?? 'bg-slate-400',
                                        )}
                                        style={{
                                            left: `${getPos(task.start)}%`,
                                            width: `${Math.max(getWidth(task.start, task.end), 1)}%`,
                                            opacity: task.status === 'completed' ? 0.5 : 0.85,
                                        }}
                                    >
                                        {/* Progress overlay */}
                                        <div
                                            className="h-full rounded-full bg-white/30"
                                            style={{ width: `${task.progress}%` }}
                                        />
                                    </div>
                                </div>
                            </div>
                        ))
                    )}
                </div>
            </div>
        </WorkspaceLayout>
    );
}
