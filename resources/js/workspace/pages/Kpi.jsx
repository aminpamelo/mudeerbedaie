import { useState } from 'react';
import { router } from '@inertiajs/react';
import { BarChart3, Users, Clock, Trophy, AlertTriangle, TrendingUp } from 'lucide-react';
import WorkspaceLayout from '@/workspace/layouts/WorkspaceLayout';
import { cn } from '@/workspace/lib/utils';

const MONTHS = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December',
];

function StatCard({ label, value, icon: Icon, color, subtitle }) {
    const colors = {
        indigo: 'from-indigo-500 to-violet-500 shadow-indigo-500/20',
        emerald: 'from-emerald-500 to-teal-500 shadow-emerald-500/20',
        amber: 'from-amber-500 to-orange-500 shadow-amber-500/20',
        rose: 'from-rose-500 to-pink-500 shadow-rose-500/20',
    };

    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5">
            <div className="flex items-center justify-between">
                <div>
                    <p className="text-[12px] font-medium uppercase tracking-wider text-slate-400">{label}</p>
                    <p className="mt-1.5 text-3xl font-bold tabular-nums text-slate-900">{value}</p>
                    {subtitle && <p className="mt-0.5 text-[11px] text-slate-400">{subtitle}</p>}
                </div>
                <div className={`grid h-12 w-12 place-items-center rounded-2xl bg-gradient-to-br ${colors[color]} text-white shadow-lg`}>
                    <Icon className="h-6 w-6" strokeWidth={2} />
                </div>
            </div>
        </div>
    );
}

function formatTime(seconds) {
    if (!seconds || seconds === 0) return '0h';
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    if (h === 0) return `${m}m`;
    return `${h}h ${m}m`;
}

export default function Kpi({ staffStats = [], departmentStats = [], filters = {} }) {
    const [tab, setTab] = useState('staff');
    const [month, setMonth] = useState(filters.month || new Date().getMonth() + 1);
    const [year, setYear] = useState(filters.year || new Date().getFullYear());

    const applyFilter = () => {
        router.get('/workspace/kpi', { month, year }, { preserveState: true });
    };

    const totalCompleted = staffStats.reduce((s, r) => s + r.tasks_completed, 0);
    const totalOverdue = staffStats.reduce((s, r) => s + r.overdue_count, 0);
    const totalTime = staffStats.reduce((s, r) => s + r.time_tracked_seconds, 0);
    const totalPoints = staffStats.reduce((s, r) => s + r.total_points, 0);

    return (
        <WorkspaceLayout title="KPI Dashboard" subtitle="Staff performance metrics and analytics">
            {/* Filters */}
            <div className="mb-6 flex flex-wrap items-center gap-3">
                <select
                    value={month}
                    onChange={(e) => setMonth(Number(e.target.value))}
                    className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-[13px] font-medium text-slate-700 focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                >
                    {MONTHS.map((m, i) => (
                        <option key={i} value={i + 1}>{m}</option>
                    ))}
                </select>
                <input
                    type="number"
                    value={year}
                    onChange={(e) => setYear(Number(e.target.value))}
                    className="w-24 rounded-xl border border-slate-200 bg-white px-3 py-2 text-[13px] font-medium text-slate-700 focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                />
                <button
                    onClick={applyFilter}
                    className="rounded-xl bg-gradient-to-r from-indigo-500 to-violet-500 px-4 py-2 text-[13px] font-semibold text-white shadow-lg shadow-indigo-500/25 hover:shadow-xl"
                >
                    Apply
                </button>
            </div>

            {/* Summary cards */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard label="Total Completed" value={totalCompleted} icon={BarChart3} color="indigo" />
                <StatCard label="Total Overdue" value={totalOverdue} icon={AlertTriangle} color="amber" />
                <StatCard label="Time Tracked" value={formatTime(totalTime)} icon={Clock} color="emerald" />
                <StatCard label="Total Points" value={totalPoints} icon={Trophy} color="rose" />
            </div>

            {/* Tabs */}
            <div className="mt-8 flex gap-1 rounded-xl bg-slate-100 p-1">
                {[
                    { key: 'staff', label: 'Per Staff', icon: Users },
                    { key: 'department', label: 'Per Department', icon: TrendingUp },
                ].map(({ key, label, icon: Icon }) => (
                    <button
                        key={key}
                        onClick={() => setTab(key)}
                        className={cn(
                            'flex items-center gap-2 rounded-lg px-4 py-2 text-[13px] font-semibold transition',
                            tab === key
                                ? 'bg-white text-slate-900 shadow-sm'
                                : 'text-slate-500 hover:text-slate-700'
                        )}
                    >
                        <Icon className="h-4 w-4" />
                        {label}
                    </button>
                ))}
            </div>

            {/* Staff table */}
            {tab === 'staff' && (
                <div className="mt-4 overflow-x-auto rounded-2xl border border-slate-200 bg-white">
                    <table className="w-full text-left">
                        <thead>
                            <tr className="border-b border-slate-100">
                                <th className="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-400">#</th>
                                <th className="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-400">Name</th>
                                <th className="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-400">Department</th>
                                <th className="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-400 text-right">Completed</th>
                                <th className="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-400 text-right">Overdue</th>
                                <th className="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-400 text-right">Avg Time</th>
                                <th className="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-400 text-right">Time Tracked</th>
                                <th className="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-400 text-right">Points</th>
                                <th className="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-400 text-right">Streak</th>
                            </tr>
                        </thead>
                        <tbody>
                            {staffStats.length === 0 ? (
                                <tr>
                                    <td colSpan="9" className="px-4 py-8 text-center text-[13px] text-slate-400">
                                        No staff data available.
                                    </td>
                                </tr>
                            ) : (
                                staffStats.map((s, i) => (
                                    <tr key={s.id} className="border-b border-slate-50 last:border-0 hover:bg-slate-50/50">
                                        <td className="px-4 py-3 text-[13px] tabular-nums text-slate-400">{i + 1}</td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-2.5">
                                                <div className="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-gradient-to-br from-indigo-400 to-violet-400 text-[10px] font-bold text-white">
                                                    {s.name?.charAt(0)?.toUpperCase() ?? '?'}
                                                </div>
                                                <span className="text-[13px] font-medium text-slate-700">{s.name}</span>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-[12.5px] text-slate-500">{s.department}</td>
                                        <td className="px-4 py-3 text-right text-[13px] font-semibold tabular-nums text-slate-700">{s.tasks_completed}</td>
                                        <td className="px-4 py-3 text-right">
                                            <span className={cn('text-[13px] font-semibold tabular-nums', s.overdue_count > 0 ? 'text-red-600' : 'text-slate-400')}>
                                                {s.overdue_count}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-right text-[13px] tabular-nums text-slate-500">
                                            {s.avg_completion_minutes > 0 ? `${s.avg_completion_minutes}m` : '-'}
                                        </td>
                                        <td className="px-4 py-3 text-right text-[13px] tabular-nums text-slate-500">
                                            {formatTime(s.time_tracked_seconds)}
                                        </td>
                                        <td className="px-4 py-3 text-right text-[13px] font-semibold tabular-nums text-indigo-600">
                                            {s.total_points}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            {s.streak_days > 0 && (
                                                <span className="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-bold text-amber-600">
                                                    {s.streak_days}d
                                                </span>
                                            )}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            )}

            {/* Department table */}
            {tab === 'department' && (
                <div className="mt-4 overflow-x-auto rounded-2xl border border-slate-200 bg-white">
                    <table className="w-full text-left">
                        <thead>
                            <tr className="border-b border-slate-100">
                                <th className="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-400">Department</th>
                                <th className="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-400 text-right">Staff</th>
                                <th className="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-400 text-right">Completed</th>
                                <th className="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-400 text-right">Overdue</th>
                                <th className="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-400 text-right">Completion Rate</th>
                                <th className="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-400 text-right">Time Tracked</th>
                            </tr>
                        </thead>
                        <tbody>
                            {departmentStats.length === 0 ? (
                                <tr>
                                    <td colSpan="6" className="px-4 py-8 text-center text-[13px] text-slate-400">
                                        No department data available.
                                    </td>
                                </tr>
                            ) : (
                                departmentStats.map((d) => (
                                    <tr key={d.department} className="border-b border-slate-50 last:border-0 hover:bg-slate-50/50">
                                        <td className="px-4 py-3 text-[13px] font-medium text-slate-700">{d.department}</td>
                                        <td className="px-4 py-3 text-right text-[13px] tabular-nums text-slate-500">{d.total_staff}</td>
                                        <td className="px-4 py-3 text-right text-[13px] font-semibold tabular-nums text-slate-700">{d.total_completed}</td>
                                        <td className="px-4 py-3 text-right">
                                            <span className={cn('text-[13px] font-semibold tabular-nums', d.total_overdue > 0 ? 'text-red-600' : 'text-slate-400')}>
                                                {d.total_overdue}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex items-center justify-end gap-2">
                                                <div className="h-2 w-16 overflow-hidden rounded-full bg-slate-100">
                                                    <div
                                                        className={cn(
                                                            'h-full rounded-full',
                                                            d.completion_rate >= 80 ? 'bg-emerald-500' :
                                                            d.completion_rate >= 50 ? 'bg-amber-500' : 'bg-red-500'
                                                        )}
                                                        style={{ width: `${d.completion_rate}%` }}
                                                    />
                                                </div>
                                                <span className="text-[13px] font-semibold tabular-nums text-slate-700">{d.completion_rate}%</span>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-right text-[13px] tabular-nums text-slate-500">
                                            {formatTime(d.total_time_tracked)}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            )}
        </WorkspaceLayout>
    );
}
