import { useState } from 'react';
import { FileText, Download, Filter, TrendingUp, CheckCircle2, AlertTriangle, Clock } from 'lucide-react';
import WorkspaceLayout from '@/workspace/layouts/WorkspaceLayout';
import { workspaceJson } from '@/workspace/lib/api';
import { cn } from '@/workspace/lib/utils';

function StatCard({ label, value, icon: Icon, color }) {
    const colors = {
        indigo: 'from-indigo-500 to-violet-500',
        emerald: 'from-emerald-500 to-teal-500',
        amber: 'from-amber-500 to-orange-500',
        rose: 'from-rose-500 to-pink-500',
        blue: 'from-blue-500 to-cyan-500',
    };

    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-4">
            <div className="flex items-center gap-3">
                <div className={`grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-gradient-to-br ${colors[color]} text-white`}>
                    <Icon className="h-5 w-5" strokeWidth={2} />
                </div>
                <div>
                    <p className="text-[11px] font-medium uppercase tracking-wider text-slate-400">{label}</p>
                    <p className="text-xl font-bold tabular-nums text-slate-900">{value}</p>
                </div>
            </div>
        </div>
    );
}

export default function Reports({ departments = [], staff = [] }) {
    const [period, setPeriod] = useState('weekly');
    const [departmentId, setDepartmentId] = useState('');
    const [employeeId, setEmployeeId] = useState('');
    const [loading, setLoading] = useState(false);
    const [report, setReport] = useState(null);

    const generate = async () => {
        setLoading(true);
        try {
            const params = new URLSearchParams({ period });
            if (departmentId) params.append('department_id', departmentId);
            if (employeeId) params.append('employee_id', employeeId);

            const data = await workspaceJson(`/workspace/reports/generate?${params}`);
            setReport(data);
        } catch (err) {
            console.error('Failed to generate report:', err);
        } finally {
            setLoading(false);
        }
    };

    return (
        <WorkspaceLayout title="Reports" subtitle="Generate task performance reports">
            {/* Filters */}
            <div className="mb-6 rounded-2xl border border-slate-200 bg-white p-5">
                <div className="flex items-center gap-2 mb-4">
                    <Filter className="h-4 w-4 text-slate-400" />
                    <h3 className="text-[13px] font-bold text-slate-900">Report Filters</h3>
                </div>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label className="mb-1.5 block text-[11px] font-semibold uppercase tracking-wider text-slate-400">Period</label>
                        <select
                            value={period}
                            onChange={(e) => setPeriod(e.target.value)}
                            className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-[13px] font-medium text-slate-700 focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                        >
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                        </select>
                    </div>

                    <div>
                        <label className="mb-1.5 block text-[11px] font-semibold uppercase tracking-wider text-slate-400">Department</label>
                        <select
                            value={departmentId}
                            onChange={(e) => setDepartmentId(e.target.value)}
                            className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-[13px] font-medium text-slate-700 focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                        >
                            <option value="">All Departments</option>
                            {departments.map((d) => (
                                <option key={d.id} value={d.id}>{d.name}</option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label className="mb-1.5 block text-[11px] font-semibold uppercase tracking-wider text-slate-400">Staff</label>
                        <select
                            value={employeeId}
                            onChange={(e) => setEmployeeId(e.target.value)}
                            className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-[13px] font-medium text-slate-700 focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                        >
                            <option value="">All Staff</option>
                            {staff.map((s) => (
                                <option key={s.id} value={s.id}>{s.name}</option>
                            ))}
                        </select>
                    </div>

                    <div className="flex items-end">
                        <button
                            onClick={generate}
                            disabled={loading}
                            className="w-full rounded-xl bg-gradient-to-r from-indigo-500 to-violet-500 px-4 py-2.5 text-[13px] font-semibold text-white shadow-lg shadow-indigo-500/25 transition hover:shadow-xl disabled:opacity-50"
                        >
                            {loading ? 'Generating...' : 'Generate Report'}
                        </button>
                    </div>
                </div>
            </div>

            {/* Report results */}
            {report && (
                <>
                    {/* Date range */}
                    <div className="mb-4 flex items-center gap-2 text-[12.5px] text-slate-500">
                        <FileText className="h-4 w-4" />
                        <span>Report for <strong className="text-slate-700">{report.date_range?.start}</strong> to <strong className="text-slate-700">{report.date_range?.end}</strong></span>
                        <span className="rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-bold uppercase text-indigo-600">{report.period}</span>
                    </div>

                    {/* Summary cards */}
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                        <StatCard label="Created" value={report.summary?.created ?? 0} icon={FileText} color="indigo" />
                        <StatCard label="Completed" value={report.summary?.completed ?? 0} icon={CheckCircle2} color="emerald" />
                        <StatCard label="Overdue" value={report.summary?.overdue ?? 0} icon={AlertTriangle} color="amber" />
                        <StatCard label="In Progress" value={report.summary?.in_progress ?? 0} icon={Clock} color="blue" />
                        <StatCard label="In Review" value={report.summary?.review ?? 0} icon={TrendingUp} color="rose" />
                        <StatCard label="Completion Rate" value={`${report.summary?.completion_rate ?? 0}%`} icon={TrendingUp} color="emerald" />
                    </div>

                    {/* Staff breakdown table */}
                    {report.staff_breakdown?.length > 0 && (
                        <div className="mt-6 overflow-x-auto rounded-2xl border border-slate-200 bg-white">
                            <div className="border-b border-slate-100 px-5 py-3">
                                <h3 className="text-[13px] font-bold text-slate-900">Staff Breakdown</h3>
                            </div>
                            <table className="w-full text-left">
                                <thead>
                                    <tr className="border-b border-slate-100">
                                        <th className="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-400">Name</th>
                                        <th className="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-400 text-right">Total Tasks</th>
                                        <th className="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-400 text-right">Completed</th>
                                        <th className="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-400 text-right">Overdue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {report.staff_breakdown.map((s) => (
                                        <tr key={s.employee_id} className="border-b border-slate-50 last:border-0 hover:bg-slate-50/50">
                                            <td className="px-4 py-3 text-[13px] font-medium text-slate-700">{s.name}</td>
                                            <td className="px-4 py-3 text-right text-[13px] tabular-nums text-slate-500">{s.total_tasks}</td>
                                            <td className="px-4 py-3 text-right text-[13px] font-semibold tabular-nums text-emerald-600">{s.completed}</td>
                                            <td className="px-4 py-3 text-right">
                                                <span className={cn('text-[13px] font-semibold tabular-nums', s.overdue > 0 ? 'text-red-600' : 'text-slate-400')}>
                                                    {s.overdue}
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </>
            )}

            {/* Empty state */}
            {!report && (
                <div className="mt-8 rounded-2xl border border-slate-200 bg-white p-8 text-center">
                    <FileText className="mx-auto h-12 w-12 text-slate-200" strokeWidth={1.5} />
                    <h3 className="mt-3 text-[15px] font-bold text-slate-700">No report generated</h3>
                    <p className="mt-1 text-[13px] text-slate-400">Select your filters above and click "Generate Report" to view performance data.</p>
                </div>
            )}
        </WorkspaceLayout>
    );
}
