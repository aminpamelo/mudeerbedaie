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
import { fetchMyAttendance, fetchMyAttendanceSummary } from '../../lib/api';
import { cn } from '../../lib/utils';
import { Card, CardContent } from '../../components/ui/card';
import { EmployeePageHeader } from '../../components/ui/employee-page-header';
import { BalanceRing } from '../../components/ui/balance-ring';
import { StatusBadge } from '../../components/ui/status-badge';
import { EmptyState } from '../../components/ui/empty-state';
import { RecordCard, RecordList } from '../../components/ui/record-card';

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
    holiday: 'bg-slate-300',
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
                    <div className="text-[10px] font-bold uppercase tracking-widest text-indigo-600">
                        Attendance
                    </div>
                    <div className="mt-2 flex items-baseline tabular-nums leading-none">
                        <span className="text-5xl font-bold tracking-tight text-slate-900">{attendanceRate}</span>
                        <span className="ml-1 text-xl font-bold text-slate-500">%</span>
                    </div>
                    <p className="mt-2 text-[11px] font-medium text-slate-500">
                        {attendedDays} of {workingDays} working days
                    </p>
                </BalanceRing>
            </div>

            {/* Quick stat chips below ring */}
            <div className="flex flex-wrap items-center justify-center gap-2">
                {presentCount > 0 && (
                    <div className="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1.5 text-[11px] font-semibold text-emerald-800 ring-1 ring-emerald-200">
                        <CheckCircle2 className="h-3 w-3" strokeWidth={2.5} />
                        <span className="tabular-nums">{presentCount}</span> present
                    </div>
                )}
                {lateCount > 0 && (
                    <div className="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1.5 text-[11px] font-semibold text-amber-800 ring-1 ring-amber-200">
                        <AlertTriangle className="h-3 w-3" strokeWidth={2.5} />
                        <span className="tabular-nums">{lateCount}</span> late
                    </div>
                )}
                {wfhCount > 0 && (
                    <div className="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 px-3 py-1.5 text-[11px] font-semibold text-indigo-800 ring-1 ring-indigo-200">
                        <Home className="h-3 w-3" strokeWidth={2.5} />
                        <span className="tabular-nums">{wfhCount}</span> WFH
                    </div>
                )}
                {absentCount > 0 && (
                    <div className="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-3 py-1.5 text-[11px] font-semibold text-rose-800 ring-1 ring-rose-200">
                        <XCircle className="h-3 w-3" strokeWidth={2.5} />
                        <span className="tabular-nums">{absentCount}</span> absent
                    </div>
                )}
                {(summary.on_leave ?? 0) > 0 && (
                    <div className="inline-flex items-center gap-1.5 rounded-full bg-violet-50 px-3 py-1.5 text-[11px] font-semibold text-violet-800 ring-1 ring-violet-200">
                        <CalendarOff className="h-3 w-3" strokeWidth={2.5} />
                        <span className="tabular-nums">{summary.on_leave}</span> on leave
                    </div>
                )}
            </div>

            {/* Calendar */}
            <Card className="border-slate-200/80">
                <CardContent className="pt-4">
                    <div className="mb-3 flex items-center justify-between">
                        <button
                            onClick={prevMonth}
                            aria-label="Previous month"
                            className="flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                        >
                            <ChevronLeft className="h-4 w-4" />
                        </button>
                        <h3 className="text-sm font-bold text-slate-900">{monthLabel}</h3>
                        <button
                            onClick={nextMonth}
                            aria-label="Next month"
                            className="flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                        >
                            <ChevronRight className="h-4 w-4" />
                        </button>
                    </div>

                    <div className="grid grid-cols-7 gap-1">
                        {['M', 'T', 'W', 'T', 'F', 'S', 'S'].map((d, i) => (
                            <div key={i} className="pb-2 text-center text-[10px] font-bold uppercase tracking-wider text-slate-400">{d}</div>
                        ))}
                        {calendarDays.map((date, i) => {
                            const dateKey = toLocalDateKey(date);
                            const record = recordsByDate[dateKey];
                            const isCurrentMonth = date.getMonth() === month;
                            const isToday = dateKey === toLocalDateKey(new Date());
                            const status = record?.status;

                            return (
                                <div
                                    key={i}
                                    className={cn(
                                        'relative aspect-square flex flex-col items-center justify-center rounded-xl text-xs transition-all',
                                        !isCurrentMonth && 'opacity-30',
                                        isToday && 'bg-gradient-to-br from-indigo-50 to-pink-50 ring-2 ring-pink-300',
                                        status && isCurrentMonth && !isToday && 'hover:bg-slate-50'
                                    )}
                                >
                                    <span className={cn(
                                        'text-xs font-semibold tabular-nums',
                                        isToday ? 'text-pink-700' :
                                        isCurrentMonth ? 'text-slate-700' :
                                        'text-slate-400'
                                    )}>
                                        {date.getDate()}
                                    </span>
                                    {status && (
                                        <div className={cn(
                                            'mt-0.5 h-1.5 w-1.5 rounded-full ring-1 ring-white shadow-sm',
                                            STATUS_DOT[status] || 'bg-slate-300'
                                        )} />
                                    )}
                                </div>
                            );
                        })}
                    </div>

                    {/* Legend */}
                    <div className="mt-4 flex flex-wrap gap-x-3 gap-y-1 border-t border-slate-100 pt-3">
                        {[
                            { key: 'present', label: 'Present' },
                            { key: 'late', label: 'Late' },
                            { key: 'wfh', label: 'WFH' },
                            { key: 'leave', label: 'Leave' },
                            { key: 'absent', label: 'Absent' },
                            { key: 'holiday', label: 'Holiday' },
                        ].map((item) => (
                            <div key={item.key} className="flex items-center gap-1">
                                <div className={cn('h-1.5 w-1.5 rounded-full ring-1 ring-white shadow-sm', STATUS_DOT[item.key])} />
                                <span className="text-[10px] font-medium text-slate-500">{item.label}</span>
                            </div>
                        ))}
                    </div>
                </CardContent>
            </Card>

            {/* Penalty Summary (if any) */}
            {(summary.total_lates > 0 || summary.total_late_minutes > 0) && (
                <div className="grid grid-cols-2 gap-2">
                    <div className="rounded-2xl border border-amber-100 bg-gradient-to-br from-amber-50 to-amber-50/40 p-3 text-center">
                        <div className="mx-auto mb-1 flex h-8 w-8 items-center justify-center rounded-xl bg-gradient-to-br from-amber-500 to-orange-500 shadow-sm shadow-amber-500/30">
                            <AlertTriangle className="h-4 w-4 text-white" strokeWidth={2.25} />
                        </div>
                        <p className="text-lg font-bold tabular-nums text-slate-900">{summary.total_lates ?? 0}</p>
                        <p className="text-[10px] font-semibold uppercase tracking-wider text-amber-700">Total lates</p>
                    </div>
                    <div className="rounded-2xl border border-amber-100 bg-gradient-to-br from-amber-50 to-amber-50/40 p-3 text-center">
                        <div className="mx-auto mb-1 flex h-8 w-8 items-center justify-center rounded-xl bg-gradient-to-br from-amber-500 to-orange-500 shadow-sm shadow-amber-500/30">
                            <TrendingUp className="h-4 w-4 text-white" strokeWidth={2.25} />
                        </div>
                        <p className="text-lg font-bold tabular-nums text-slate-900">{summary.total_late_minutes ?? 0}</p>
                        <p className="text-[10px] font-semibold uppercase tracking-wider text-amber-700">Late minutes</p>
                    </div>
                </div>
            )}

            {/* Attendance Log — as RecordCard list */}
            <div>
                <div className="mb-2 flex items-center justify-between px-1">
                    <h3 className="text-sm font-bold text-slate-900">Recent activity</h3>
                    {sortedRecords.length > 0 && (
                        <span className="text-[11px] font-semibold text-slate-500">
                            <span className="tabular-nums text-slate-800">{sortedRecords.length}</span> records
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
        </div>
    );
}
