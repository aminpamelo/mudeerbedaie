import WorkspaceLayout from '@/workspace/layouts/WorkspaceLayout';
import { CheckCircle2, Clock, AlertTriangle, FolderKanban, Plus } from 'lucide-react';

function StatCard({ label, value, icon: Icon, color }) {
    const colors = {
        indigo: 'from-indigo-500 to-violet-500 shadow-indigo-500/20',
        emerald: 'from-emerald-500 to-teal-500 shadow-emerald-500/20',
        amber: 'from-amber-500 to-orange-500 shadow-amber-500/20',
        slate: 'from-slate-600 to-slate-700 shadow-slate-500/20',
    };

    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5">
            <div className="flex items-center justify-between">
                <div>
                    <p className="text-[12px] font-medium uppercase tracking-wider text-slate-400">{label}</p>
                    <p className="mt-1.5 text-3xl font-bold tabular-nums text-slate-900">{value}</p>
                </div>
                <div className={`grid h-12 w-12 place-items-center rounded-2xl bg-gradient-to-br ${colors[color]} text-white shadow-lg`}>
                    <Icon className="h-6 w-6" strokeWidth={2} />
                </div>
            </div>
        </div>
    );
}

export default function Dashboard({ stats = {} }) {
    return (
        <WorkspaceLayout
            title="Dashboard"
            subtitle="Ringkasan tugasan dan prestasi pasukan"
            actions={
                <a href="/workspace/board" className="flex items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-500 to-violet-500 px-4 py-2.5 text-[13px] font-semibold text-white shadow-lg shadow-indigo-500/25 transition hover:shadow-xl">
                    <Plus className="h-4 w-4" strokeWidth={2.4} />
                    New Task
                </a>
            }
        >
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard label="Active Tasks" value={stats.activeTasks ?? 0} icon={FolderKanban} color="indigo" />
                <StatCard label="Completed Today" value={stats.completedToday ?? 0} icon={CheckCircle2} color="emerald" />
                <StatCard label="Overdue" value={stats.overdue ?? 0} icon={AlertTriangle} color="amber" />
                <StatCard label="Total Tasks" value={stats.total ?? 0} icon={Clock} color="slate" />
            </div>

            <div className="mt-8 rounded-2xl border border-slate-200 bg-white p-6">
                <h2 className="text-[15px] font-bold text-slate-900">Welcome to Workspace</h2>
                <p className="mt-2 text-[13.5px] text-slate-500">
                    Your task management system is ready. Start by creating your first project or task from the Board page.
                </p>
            </div>
        </WorkspaceLayout>
    );
}
