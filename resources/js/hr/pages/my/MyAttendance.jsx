import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import {
    Clock,
    CalendarDays,
    CheckCircle2,
    AlertTriangle,
    XCircle,
    Home,
    CalendarOff,
    ChevronLeft,
    ChevronRight,
    Loader2,
    ArrowRight,
    TrendingUp,
} from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { fetchMyAttendance, fetchMyAttendanceSummary } from '../../lib/api';
import { cn } from '../../lib/utils';
import { Card, CardContent } from '../../components/ui/card';
import { EmployeePageHeader } from '../../components/ui/employee-page-header';
import { BalanceRing } from '../../components/ui/balance-ring';
import { StatusBadge } from '../../components/ui/status-badge';
import { EmptyState } from '../../components/ui/empty-state';
import { RecordCard, RecordList } from '../../components/ui/record-card';
import { Fab } from '../../components/ui/fab';

// ---- Helpers ----

function toLocalDateKey(d) {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function formatTime(dateStr) {
    if (!dateStr) return '–';
    return new Date(dateStr).toLocaleTimeString('en-MY', { hour: '2-digit', minute: '2-digit' });
}

function formatDuration(minutes) {
    if (!minutes && minutes !== 0) return '–';
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    return `${h}h ${m}m`;
}

function getMonthDates(year, month) {
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const startPad = (firstDay.getDay() + 6) % 7;
    const days = [];
    for (let i = -startPad; i <= lastDay.getDate() - 1; i++) {
        days.push(new Date(year, month, i + 1));
    }
    while (days.length % 7 !== 0) {
        days.push(new Date(year, month, days.length - startPad + 1));
    }
    return days;
}

const STATUS_DOT = {
    present: 'bg-gradient-to-br from-emerald-400 to-emerald-600',
    late: 'bg-gradient-to-br from-amber-400 to-amber-600',
    absent: 'bg-gradient-to-br from-rose-400 to-rose-600',
    wfh: 'bg-gradient-to-br from-indigo-400 to-indigo-600',
    leave: 'bg-gradient-to-br from-violet-400 to-violet-600',
    on_leave: 'bg-gradient-to-br from-violet-400 to-violet-600',
    holiday: 'bg-slate-300 dark:bg-slate-600',
};

// Subtle background tint per status (for heatmap-style day cells)
const STATUS_BG = {
    present: 'bg-emerald-50 border-emerald-200 dark:bg-emerald-500/10 dark:border-emerald-500/25',
    late: 'bg-amber-50 border-amber-200 dark:bg-amber-500/10 dark:border-amber-500/25',
    absent: 'bg-rose-50 border-rose-200 dark:bg-rose-500/10 dark:border-rose-500/25',
    wfh: 'bg-indigo-50 border-indigo-200 dark:bg-indigo-500/10 dark:border-indigo-500/25',
    leave: 'bg-violet-50 border-violet-200 dark:bg-violet-500/10 dark:border-violet-500/25',
    on_leave: 'bg-violet-50 border-violet-200 dark:bg-violet-500/10 dark:border-violet-500/25',
    holiday: 'bg-slate-100 border-slate-200 dark:bg-white/[0.05] dark:border-white/10',
};

const STATUS_TEXT = {
    present: 'text-emerald-700 dark:text-emerald-300',
    late: 'text-amber-700 dark:text-amber-300',
    absent: 'text-rose-700 dark:text-rose-300',
    wfh: 'text-indigo-700 dark:text-indigo-300',
    leave: 'text-violet-700 dark:text-violet-300',
    on_leave: 'text-violet-700 dark:text-violet-300',
    holiday: 'text-slate-600 dark:text-slate-400',
};

const STATUS_ICON = {
    present: CheckCircle2,
    late: AlertTriangle,
    absent: XCircle,
    wfh: Home,
    leave: CalendarOff,
    on_leave: CalendarOff,
    holiday: CalendarDays,
};

const STATUS_ACCENT = {
    present: 'emerald',
    late: 'amber',
    absent: 'rose',
    wfh: 'indigo',
    leave: 'violet',
    on_leave: 'violet',
    holiday: 'slate',
};

export default function MyAttendance() {
    const navigate = useNavigate();
    const [currentDate, setCurrentDate] = useState(new Date());
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();

    const { data: attendanceData, isLoading } = useQuery({
        queryKey: ['my-attendance', year, month + 1],
        queryFn: () => fetchMyAttendance({ year, month: month + 1 }),
    });
    const records = attendanceData?.data ?? [];

    const { data: summaryData } = useQuery({
        queryKey: ['my-attendance-summary', year, month + 1],
        queryFn: () => fetchMyAttendanceSummary({ year, month: month + 1 }),
    });
    const summary = summaryData?.data ?? {};

    const recordsByDate = {};
    records.forEach((r) => {
        const raw = r.date || r.clock_in;
        const dateKey = raw ? toLocalDateKey(new Date(raw)) : null;
        if (dateKey) recordsByDate[dateKey] = r;
    });

    const calendarDays = getMonthDates(year, month);
    const monthLabel = currentDate.toLocaleDateString('en-MY', { month: 'long', year: 'numeric' });
    const now = new Date();
    const isViewingCurrentMonth = year === now.getFullYear() && month === now.getMonth();

    function prevMonth() { setCurrentDate(new Date(year, month - 1, 1)); }
    function nextMonth() { setCurrentDate(new Date(year, month + 1, 1)); }

    // Compute attendance health for the ring
    const presentCount = summary.present ?? 0;
    const lateCount = summary.late ?? 0;
    const wfhCount = summary.wfh ?? 0;
    const absentCount = summary.absent ?? 0;
    const workingDays = presentCount + lateCount + wfhCount + absentCount;
    const attendedDays = presentCount + lateCount + wfhCount;
    const attendanceRate = workingDays > 0 ? Math.round((attendedDays / workingDays) * 100) : 0;

    // Sort records descending (newest first)
    const sortedRecords = [...records].sort((a, b) => {
        const dateA = new Date(a.date || a.clock_in);
        const dateB = new Date(b.date || b.clock_in);
        return dateB - dateA;
    });

    return (
        <div className="space-y-5 pb-4">
            <EmployeePageHeader
                icon={Clock}
                accent="indigo"
                title="My Attendance"
                context={monthLabel}
                action={
                    <Link
                        to="/clock"
                        className="inline-flex items-center gap-1 rounded-full bg-gradient-to-r from-indigo-500 via-pink-500 to-orange-400 px-3 py-1.5 text-[11px] font-bold uppercase tracking-wider text-white shadow-md shadow-pink-500/30 transition-all hover:shadow-lg"
                    >
                        Clock <ArrowRight className="h-3 w-3" />
                    </Link>
                }
            />

            {/* Hero: Attendance Ring */}
            <div className="relative py-2">
                <BalanceRing
                    value={attendanceRate}
                    max={100}
                    accent="indigo"
                    size={232}
                    stroke={12}
                >
                    <div className="text-[10px] font-bold uppercase tracking-widest text-indigo-600 dark:text-indigo-400">
                        Attendance
                    </div>
                    <div className="mt-2 flex items-baseline tabular-nums leading-none">
                        <span className="text-5xl font-bold tracking-tight text-slate-900 dark:text-white">{attendanceRate}</span>
                        <span className="ml-1 text-xl font-bold text-slate-500 dark:text-slate-400">%</span>
                    </div>
                    <p className="mt-2 text-[11px] font-medium text-slate-500 dark:text-slate-400">
                        {attendedDays} of {workingDays} working days
                    </p>
                </BalanceRing>
            </div>

            {/* Stat tiles — 5-up grid with ratio bars */}
            <div className="grid grid-cols-5 gap-1.5">
                {[
                    { count: presentCount, label: 'Present', icon: CheckCircle2, accent: 'emerald' },
                    { count: lateCount, label: 'Late', icon: AlertTriangle, accent: 'amber' },
                    { count: wfhCount, label: 'WFH', icon: Home, accent: 'indigo' },
                    { count: absentCount, label: 'Absent', icon: XCircle, accent: 'rose' },
                    { count: summary.on_leave ?? 0, label: 'Leave', icon: CalendarOff, accent: 'violet' },
                ].map((stat) => {
                    const ratio = workingDays > 0 ? Math.round((stat.count / workingDays) * 100) : 0;
                    const colors = {
                        emerald: { bg: 'bg-emerald-50 dark:bg-emerald-500/10', border: 'border-emerald-100 dark:border-emerald-500/20', text: 'text-emerald-800 dark:text-emerald-300', icon: 'text-emerald-600 dark:text-emerald-400', bar: 'from-emerald-400 to-emerald-500', dim: 'bg-emerald-100 dark:bg-emerald-500/20' },
                        amber: { bg: 'bg-amber-50 dark:bg-amber-500/10', border: 'border-amber-100 dark:border-amber-500/20', text: 'text-amber-800 dark:text-amber-300', icon: 'text-amber-600 dark:text-amber-400', bar: 'from-amber-400 to-orange-500', dim: 'bg-amber-100 dark:bg-amber-500/20' },
                        indigo: { bg: 'bg-indigo-50 dark:bg-indigo-500/10', border: 'border-indigo-100 dark:border-indigo-500/20', text: 'text-indigo-800 dark:text-indigo-300', icon: 'text-indigo-600 dark:text-indigo-400', bar: 'from-indigo-400 to-indigo-500', dim: 'bg-indigo-100 dark:bg-indigo-500/20' },
                        rose: { bg: 'bg-rose-50 dark:bg-rose-500/10', border: 'border-rose-100 dark:border-rose-500/20', text: 'text-rose-800 dark:text-rose-300', icon: 'text-rose-600 dark:text-rose-400', bar: 'from-rose-400 to-rose-500', dim: 'bg-rose-100 dark:bg-rose-500/20' },
                        violet: { bg: 'bg-violet-50 dark:bg-violet-500/10', border: 'border-violet-100 dark:border-violet-500/20', text: 'text-violet-800 dark:text-violet-300', icon: 'text-violet-600 dark:text-violet-400', bar: 'from-violet-400 to-fuchsia-500', dim: 'bg-violet-100 dark:bg-violet-500/20' },
                    };
                    const c = colors[stat.accent];
                    const Icon = stat.icon;
                    const hasData = stat.count > 0;
                    return (
                        <div
                            key={stat.label}
                            className={cn(
                                'flex flex-col rounded-2xl border p-2.5 transition-all',
                                hasData ? `${c.bg} ${c.border}` : 'bg-slate-50/60 border-slate-100 dark:bg-white/[0.03] dark:border-white/[0.06]'
                            )}
                        >
                            <Icon className={cn('h-3.5 w-3.5', hasData ? c.icon : 'text-slate-300 dark:text-slate-600')} strokeWidth={2.5} />
                            <p className={cn(
                                'mt-1 text-xl font-bold tabular-nums leading-none',
                                hasData ? 'text-slate-900 dark:text-white' : 'text-slate-300 dark:text-slate-600'
                            )}>{stat.count}</p>
                            <p className={cn(
                                'mt-0.5 text-[9px] font-bold uppercase tracking-wider',
                                hasData ? c.text : 'text-slate-400 dark:text-slate-500'
                            )}>{stat.label}</p>
                            <div className={cn('mt-1.5 h-1 overflow-hidden rounded-full', hasData ? c.dim : 'bg-slate-100 dark:bg-white/[0.06]')}>
                                <div
                                    className={cn('h-full rounded-full bg-gradient-to-r transition-all', hasData ? c.bar : 'bg-slate-300 dark:bg-slate-600')}
                                    style={{ width: `${Math.min(ratio, 100)}%` }}
                                />
                            </div>
                        </div>
                    );
                })}
            </div>

            {/* Calendar */}
            <Card className="border-slate-200/80">
                <CardContent className="pt-4">
                    <div className="mb-3 flex items-center justify-between gap-2">
                        <button
                            onClick={prevMonth}
                            aria-label="Previous month"
                            className="flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:text-slate-400 dark:hover:bg-white/[0.06] dark:hover:text-slate-200"
                        >
                            <ChevronLeft className="h-4 w-4" />
                        </button>
                        <div className="flex items-center gap-2">
                            <h3 className="text-sm font-bold text-slate-900 dark:text-slate-100">{monthLabel}</h3>
                            {!isViewingCurrentMonth && (
                                <button
                                    onClick={() => setCurrentDate(new Date())}
                                    className="inline-flex items-center gap-1 rounded-full bg-gradient-to-r from-indigo-500 via-pink-500 to-orange-400 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-white shadow-sm shadow-pink-500/30 transition-all hover:shadow-md"
                                >
                                    Today
                                </button>
                            )}
                        </div>
                        <button
                            onClick={nextMonth}
                            aria-label="Next month"
                            className="flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:text-slate-400 dark:hover:bg-white/[0.06] dark:hover:text-slate-200"
                        >
                            <ChevronRight className="h-4 w-4" />
                        </button>
                    </div>

                    <div className="grid grid-cols-7 gap-1">
                        {['M', 'T', 'W', 'T', 'F', 'S', 'S'].map((d, i) => (
                            <div key={i} className={cn(
                                'pb-2 text-center text-[10px] font-bold uppercase tracking-wider',
                                i >= 5 ? 'text-slate-300 dark:text-slate-600' : 'text-slate-400 dark:text-slate-500'
                            )}>{d}</div>
                        ))}
                        {calendarDays.map((date, i) => {
                            const dateKey = toLocalDateKey(date);
                            const record = recordsByDate[dateKey];
                            const isCurrentMonth = date.getMonth() === month;
                            const isToday = dateKey === toLocalDateKey(new Date());
                            const status = record?.status;
                            const isWeekend = date.getDay() === 0 || date.getDay() === 6;
                            const isPast = date < new Date(new Date().setHours(0, 0, 0, 0)) && isCurrentMonth;

                            return (
                                <div
                                    key={i}
                                    className={cn(
                                        'relative aspect-square flex flex-col items-center justify-center rounded-xl text-xs transition-all border',
                                        !isCurrentMonth && 'opacity-30 border-transparent',
                                        // Status-driven fill (heatmap)
                                        isCurrentMonth && status && (STATUS_BG[status] || 'bg-slate-50 border-slate-200 dark:bg-white/[0.04] dark:border-white/10'),
                                        // Weekend (only if no status data and current month)
                                        isCurrentMonth && !status && isWeekend && 'bg-slate-50/60 border-slate-100 dark:bg-white/[0.02] dark:border-white/[0.05]',
                                        // Empty current-month day (weekday, no record yet)
                                        isCurrentMonth && !status && !isWeekend && 'bg-white border-slate-100 dark:bg-white/[0.05] dark:border-white/[0.07]',
                                        // Today — strongest accent (overrides above)
                                        isToday && 'ring-2 ring-pink-400 ring-offset-1 shadow-lg shadow-pink-200/50 dark:ring-offset-[#0F1626] dark:shadow-pink-500/20',
                                    )}
                                >
                                    <span className={cn(
                                        'text-xs font-bold tabular-nums',
                                        isToday ? 'text-pink-700 dark:text-pink-300' :
                                        status ? STATUS_TEXT[status] :
                                        isCurrentMonth && isPast ? 'text-slate-400 dark:text-slate-500' :
                                        isCurrentMonth ? 'text-slate-700 dark:text-slate-200' :
                                        'text-slate-400 dark:text-slate-500'
                                    )}>
                                        {date.getDate()}
                                    </span>
                                    {status && (
                                        <div className={cn(
                                            'mt-0.5 h-1 w-1 rounded-full shadow-sm',
                                            STATUS_DOT[status] || 'bg-slate-300'
                                        )} />
                                    )}
                                </div>
                            );
                        })}
                    </div>

                    {/* Compact legend */}
                    <div className="mt-3 flex flex-wrap items-center justify-center gap-x-2.5 gap-y-1 border-t border-slate-100 pt-3 dark:border-white/[0.06]">
                        {[
                            { key: 'present', label: 'Present' },
                            { key: 'late', label: 'Late' },
                            { key: 'wfh', label: 'WFH' },
                            { key: 'leave', label: 'Leave' },
                            { key: 'absent', label: 'Absent' },
                            { key: 'holiday', label: 'Holiday' },
                        ].map((item) => (
                            <div key={item.key} className="inline-flex items-center gap-1">
                                <div className={cn('h-2 w-2 rounded-sm', STATUS_DOT[item.key])} />
                                <span className="text-[10px] font-semibold text-slate-500 dark:text-slate-400">{item.label}</span>
                            </div>
                        ))}
                    </div>
                </CardContent>
            </Card>

            {/* Penalty Summary (if any) */}
            {(summary.total_lates > 0 || summary.total_late_minutes > 0) && (
                <div className="grid grid-cols-2 gap-2">
                    <div className="rounded-2xl border border-amber-100 bg-gradient-to-br from-amber-50 to-amber-50/40 p-3 text-center dark:border-amber-500/20 dark:from-amber-500/10 dark:to-amber-500/5">
                        <div className="mx-auto mb-1 flex h-8 w-8 items-center justify-center rounded-xl bg-gradient-to-br from-amber-500 to-orange-500 shadow-sm shadow-amber-500/30">
                            <AlertTriangle className="h-4 w-4 text-white" strokeWidth={2.25} />
                        </div>
                        <p className="text-lg font-bold tabular-nums text-slate-900 dark:text-white">{summary.total_lates ?? 0}</p>
                        <p className="text-[10px] font-semibold uppercase tracking-wider text-amber-700 dark:text-amber-300">Total lates</p>
                    </div>
                    <div className="rounded-2xl border border-amber-100 bg-gradient-to-br from-amber-50 to-amber-50/40 p-3 text-center dark:border-amber-500/20 dark:from-amber-500/10 dark:to-amber-500/5">
                        <div className="mx-auto mb-1 flex h-8 w-8 items-center justify-center rounded-xl bg-gradient-to-br from-amber-500 to-orange-500 shadow-sm shadow-amber-500/30">
                            <TrendingUp className="h-4 w-4 text-white" strokeWidth={2.25} />
                        </div>
                        <p className="text-lg font-bold tabular-nums text-slate-900 dark:text-white">{summary.total_late_minutes ?? 0}</p>
                        <p className="text-[10px] font-semibold uppercase tracking-wider text-amber-700 dark:text-amber-300">Late minutes</p>
                    </div>
                </div>
            )}

            {/* Attendance Log — as RecordCard list */}
            <div>
                <div className="mb-2 flex items-center justify-between px-1">
                    <h3 className="text-sm font-bold text-slate-900 dark:text-slate-100">Recent activity</h3>
                    {sortedRecords.length > 0 && (
                        <span className="text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                            <span className="tabular-nums text-slate-800 dark:text-slate-200">{sortedRecords.length}</span> records
                        </span>
                    )}
                </div>
                <RecordList
                    items={sortedRecords}
                    isLoading={isLoading}
                    emptyIcon={CalendarDays}
                    emptyAccent="slate"
                    emptyTitle="No records this month"
                    emptyDescription="Clock-in entries will show here"
                    renderItem={(r, i) => {
                        const Icon = STATUS_ICON[r.status] || Clock;
                        const accent = STATUS_ACCENT[r.status] || 'slate';
                        const dateLabel = new Date(r.date || r.clock_in).toLocaleDateString('en-MY', {
                            day: 'numeric', month: 'short', weekday: 'short',
                        });
                        return (
                            <RecordCard
                                key={i}
                                icon={Icon}
                                accent={accent}
                                title={dateLabel}
                                subtitle={`${formatTime(r.clock_in)} – ${formatTime(r.clock_out)} · ${formatDuration(r.total_minutes)}`}
                                badge={r.status && <StatusBadge status={r.status === 'on_leave' ? 'on_leave' : r.status} size="sm" />}
                            />
                        );
                    }}
                />
            </div>

            {/* Mobile FAB — quick clock action (hidden on desktop where header pill is enough) */}
            <Fab
                icon={Clock}
                onClick={() => navigate('/clock')}
                ariaLabel="Clock in or out"
                className="lg:hidden"
            >
                Clock
            </Fab>
        </div>
    );
}
