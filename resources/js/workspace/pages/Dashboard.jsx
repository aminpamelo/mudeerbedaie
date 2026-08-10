import { Link } from '@inertiajs/react';
import WorkspaceLayout from '@/workspace/layouts/WorkspaceLayout';
import { CheckCircle2, Clock, AlertTriangle, FolderKanban, Plus, ArrowRight, TrendingUp, Zap, Target } from 'lucide-react';

function StatCard({ label, value, icon: Icon, gradient, iconBg, change }) {
    return (
        <div className="ws-stat-card group relative overflow-hidden rounded-2xl border border-slate-200/60 bg-white p-5">
            {/* Decorative gradient blob */}
            <div className={`absolute -right-6 -top-6 h-24 w-24 rounded-full opacity-[0.07] blur-2xl ${gradient}`} />

            <div className="relative flex items-start justify-between">
                <div>
                    <p className="text-[11.5px] font-semibold uppercase tracking-[0.08em] text-slate-400">{label}</p>
                    <p className="mt-2 text-[32px] font-extrabold tabular-nums leading-none text-slate-900">{value}</p>
                    {change !== undefined && (
                        <div className="mt-2 flex items-center gap-1">
                            <TrendingUp className="h-3 w-3 text-emerald-500" />
                            <span className="text-[11px] font-semibold text-emerald-600">+{change}%</span>
                            <span className="text-[11px] text-slate-400">vs last week</span>
                        </div>
                    )}
                </div>
                <div className={`grid h-12 w-12 place-items-center rounded-2xl ${iconBg} transition-transform duration-200 group-hover:scale-110`}>
                    <Icon className="h-6 w-6" strokeWidth={2} />
                </div>
            </div>
        </div>
    );
}

function QuickAction({ icon: Icon, label, href, color }) {
    return (
        <Link href={href} className="group flex items-center gap-3 rounded-xl border border-slate-200/60 bg-white px-4 py-3.5 transition-all duration-200 hover:border-indigo-200 hover:shadow-md hover:shadow-indigo-500/5">
            <div className={`grid h-9 w-9 shrink-0 place-items-center rounded-lg ${color} transition-transform duration-200 group-hover:scale-110`}>
                <Icon className="h-[18px] w-[18px]" strokeWidth={2} />
            </div>
            <div className="min-w-0 flex-1">
                <p className="text-[13px] font-semibold text-slate-800">{label}</p>
            </div>
            <ArrowRight className="h-4 w-4 text-slate-300 transition-all duration-200 group-hover:translate-x-0.5 group-hover:text-indigo-400" />
        </Link>
    );
}

export default function Dashboard({ stats = {} }) {
    return (
        <WorkspaceLayout
            title="Dashboard"
            subtitle="Ringkasan tugasan dan prestasi pasukan"
            actions={
                <Link href="/workspace/board" className="flex items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-500 via-violet-500 to-purple-500 px-4 py-2.5 text-[13px] font-semibold text-white shadow-lg shadow-indigo-500/25 transition-all duration-200 hover:shadow-xl hover:shadow-indigo-500/30">
                    <Plus className="h-4 w-4" strokeWidth={2.4} />
                    New Task
                </Link>
            }
        >
            {/* Hero welcome card */}
            <div className="ws-hero-grad relative overflow-hidden rounded-2xl p-6 text-white shadow-xl shadow-indigo-500/20 lg:p-8">
                <div className="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxnIGZpbGw9IiNmZmYiIGZpbGwtb3BhY2l0eT0iMC4wNSI+PHBhdGggZD0iTTM2IDM0djZoLTZ2LTZoNnptMC0zMHY2aC02VjRoNnptMCAzMHY2aC02di02aDZ6Ii8+PC9nPjwvZz48L3N2Zz4=')] opacity-50" />
                <div className="relative">
                    <p className="text-[13px] font-medium text-white/70">Selamat datang kembali</p>
                    <h2 className="mt-1 text-[24px] font-extrabold tracking-tight lg:text-[28px]">
                        Apa yang perlu diselesaikan hari ini?
                    </h2>
                    <p className="mt-2 max-w-lg text-[14px] text-white/80">
                        Urus tugasan pasukan, pantau kemajuan projek, dan capai matlamat harian anda.
                    </p>
                    <div className="mt-5 flex flex-wrap gap-3">
                        <Link href="/workspace/board" className="inline-flex items-center gap-2 rounded-xl bg-white/20 px-4 py-2.5 text-[13px] font-semibold text-white backdrop-blur-sm transition hover:bg-white/30">
                            <Columns3 className="h-4 w-4" /> Kanban Board
                        </Link>
                        <Link href="/workspace/my-tasks" className="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-[13px] font-semibold text-indigo-600 shadow-sm transition hover:shadow-md">
                            <CheckCircle2 className="h-4 w-4" /> My Tasks
                        </Link>
                    </div>
                </div>
            </div>

            {/* Stat cards */}
            <div className="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    label="Active Tasks"
                    value={stats.activeTasks ?? 0}
                    icon={FolderKanban}
                    gradient="bg-indigo-500"
                    iconBg="bg-indigo-50 text-indigo-600"
                />
                <StatCard
                    label="Completed Today"
                    value={stats.completedToday ?? 0}
                    icon={CheckCircle2}
                    gradient="bg-emerald-500"
                    iconBg="bg-emerald-50 text-emerald-600"
                />
                <StatCard
                    label="Overdue"
                    value={stats.overdue ?? 0}
                    icon={AlertTriangle}
                    gradient="bg-amber-500"
                    iconBg="bg-amber-50 text-amber-600"
                />
                <StatCard
                    label="Total Tasks"
                    value={stats.total ?? 0}
                    icon={Clock}
                    gradient="bg-violet-500"
                    iconBg="bg-violet-50 text-violet-600"
                />
            </div>

            {/* Quick actions */}
            <div className="mt-6">
                <h3 className="mb-3 text-[13px] font-bold uppercase tracking-[0.08em] text-slate-400">Quick Actions</h3>
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <QuickAction icon={Plus} label="Create New Task" href="/workspace/board" color="bg-indigo-50 text-indigo-600" />
                    <QuickAction icon={FolderKanban} label="View Projects" href="/workspace/projects" color="bg-violet-50 text-violet-600" />
                    <QuickAction icon={Target} label="KPI Dashboard" href="/workspace/kpi" color="bg-emerald-50 text-emerald-600" />
                    <QuickAction icon={Trophy} label="Leaderboard" href="/workspace/leaderboard" color="bg-amber-50 text-amber-600" />
                    <QuickAction icon={Zap} label="AI Assistant" href="#" color="bg-purple-50 text-purple-600" />
                    <QuickAction icon={Calendar} label="Calendar View" href="/workspace/calendar" color="bg-blue-50 text-blue-600" />
                </div>
            </div>
        </WorkspaceLayout>
    );
}
