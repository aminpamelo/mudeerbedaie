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
    AlertCircle,
} from 'lucide-react';
import { fetchMyAttendance, fetchMyAttendanceSummary } from '../../lib/api';
import { cn } from '../../lib/utils';
import { Card, CardContent, CardHeader, CardTitle } from '../../components/ui/card';
import { Button } from '../../components/ui/button';
import { Badge } from '../../components/ui/badge';

// ---- Helpers ----
function formatTime(dateStr) {
    if (!dateStr) return '--:--';
    return new Date(dateStr).toLocaleTimeString('en-MY', { hour: '2-digit', minute: '2-digit' });
}

function formatDuration(minutes) {
    if (!minutes && minutes !== 0) return '-';
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    return `${h}h ${m}m`;
}

function getMonthDates(year, month) {
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const startPad = (firstDay.getDay() + 6) % 7; // Monday = 0
    const days = [];
    for (let i = -startPad; i <= lastDay.getDate() - 1; i++) {
        const d = new Date(year, month, i + 1);
        days.push(d);
    }
    // pad end to complete the week
    while (days.length % 7 !== 0) {
        days.push(new Date(year, month, days.length - startPad + 1));
    }
    return days;
}

const STATUS_COLORS = {
    present: 'bg-emerald-500',
    late: 'bg-amber-500',
    absent: 'bg-red-500',
    wfh: 'bg-blue-500',
    leave: 'bg-purple-500',
    holiday: 'bg-zinc-400',
};

const STATUS_BADGE = {
    present: 'default',
    late: 'secondary',
    absent: 'destructive',
    wfh: 'outline',
    leave: 'secondary',
    holiday: 'outline',
};

// ========== MAIN COMPONENT ==========
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

    // Build lookup: date string -> record
    const recordsByDate = {};
    records.forEach((r) => {
        const dateKey = r.date || (r.clock_in ? r.clock_in.substring(0, 10) : null);
        if (dateKey) {
            recordsByDate[dateKey] = r;
        }
    });

    const calendarDays = getMonthDates(year, month);
    const monthLabel = currentDate.toLocaleDateString('en-MY', { month: 'long', year: 'numeric' });

    function prevMonth() {
        setCurrentDate(new Date(year, month - 1, 1));
    }
    function nextMonth() {
        setCurrentDate(new Date(year, month + 1, 1));
    }

    const summaryCards = [
        { label: 'Present', value: summary.present ?? 0, icon: CheckCircle2, color: 'text-emerald-600', bg: 'bg-emerald-50' },
        { label: 'Late', value: summary.late ?? 0, icon: AlertTriangle, color: 'text-amber-600', bg: 'bg-amber-50' },
        { label: 'Absent', value: summary.absent ?? 0, icon: XCircle, color: 'text-red-600', bg: 'bg-red-50' },
        { label: 'WFH', value: summary.wfh ?? 0, icon: Home, color: 'text-blue-600', bg: 'bg-blue-50' },
        { label: 'On Leave', value: summary.on_leave ?? 0, icon: CalendarOff, color: 'text-purple-600', bg: 'bg-purple-50' },
    ];

    return (
        <div className="space-y-4">
            {/* Header */}
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-bold text-zinc-900">My Attendance</h1>
                    <p className="text-sm text-zinc-500 mt-0.5">Track your attendance records</p>
                </div>
                <Link to="/clock">
                    <Button size="sm">
                        <Clock className="h-4 w-4 mr-1" /> Clock In/Out
                    </Button>
                </Link>
            </div>

            {/* Summary Cards */}
            <div className="grid grid-cols-5 gap-2">
                {summaryCards.map((card) => (
                    <Card key={card.label}>
                        <CardContent className="py-3 px-2 text-center">
                            <div className={cn('mx-auto mb-1.5 flex h-8 w-8 items-center justify-center rounded-lg', card.bg)}>
                                <card.icon className={cn('h-4 w-4', card.color)} />
                            </div>
                            <p className="text-lg font-bold text-zinc-900">{card.value}</p>
                            <p className="text-[10px] text-zinc-500">{card.label}</p>
                        </CardContent>
                    </Card>
                ))}
            </div>

            {/* Calendar */}
            <Card>
                <CardHeader className="pb-2">
                    <div className="flex items-center justify-between">
                        <Button variant="ghost" size="sm" onClick={prevMonth}>
                            <ChevronLeft className="h-4 w-4" />
                        </Button>
                        <CardTitle className="text-sm">{monthLabel}</CardTitle>
                        <Button variant="ghost" size="sm" onClick={nextMonth}>
                            <ChevronRight className="h-4 w-4" />
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <div className="grid grid-cols-7 gap-1">
                        {['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].map((d) => (
                            <div key={d} className="text-center text-[10px] font-medium text-zinc-400 pb-1">{d}</div>
                        ))}
                        {calendarDays.map((date, i) => {
                            const dateKey = date.toISOString().substring(0, 10);
                            const record = recordsByDate[dateKey];
                            const isCurrentMonth = date.getMonth() === month;
                            const isToday = dateKey === new Date().toISOString().substring(0, 10);
                            const status = record?.status;

                            return (
                                <div
                                    key={i}
                                    className={cn(
                                        'relative flex flex-col items-center justify-center rounded-lg py-1.5 text-xs',
                                        !isCurrentMonth && 'opacity-30',
                                        isToday && 'ring-2 ring-zinc-900'
                                    )}
                                >
                                    <span className={cn(
                                        'font-medium',
                                        isCurrentMonth ? 'text-zinc-900' : 'text-zinc-400'
                                    )}>
                                        {date.getDate()}
                                    </span>
                                    {status && (
                                        <div className={cn(
                                            'mt-0.5 h-1.5 w-1.5 rounded-full',
                                            STATUS_COLORS[status] || 'bg-zinc-300'
                                        )} />
                                    )}
                                </div>
                            );
                        })}
                    </div>
                    {/* Legend */}
                    <div className="flex flex-wrap gap-x-3 gap-y-1 mt-3 pt-3 border-t border-zinc-100">
                        {Object.entries(STATUS_COLORS).map(([key, color]) => (
                            <div key={key} className="flex items-center gap-1">
                                <div className={cn('h-2 w-2 rounded-full', color)} />
                                <span className="text-[10px] text-zinc-500 capitalize">{key}</span>
                            </div>
                        ))}
                    </div>
                </CardContent>
            </Card>

            {/* Attendance Log */}
            <Card>
                <CardHeader className="pb-2">
                    <CardTitle className="text-sm">Attendance Log</CardTitle>
                </CardHeader>
                <CardContent>
                    {isLoading ? (
                        <div className="flex justify-center py-8">
                            <Loader2 className="h-6 w-6 animate-spin text-zinc-400" />
                        </div>
                    ) : records.length === 0 ? (
                        <div className="py-8 text-center">
                            <CalendarDays className="h-8 w-8 text-zinc-300 mx-auto mb-2" />
                            <p className="text-sm text-zinc-500">No attendance records this month</p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto -mx-6">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-zinc-100">
                                        <th className="text-left px-6 py-2 text-xs font-medium text-zinc-500">Date</th>
                                        <th className="text-left px-3 py-2 text-xs font-medium text-zinc-500">In</th>
                                        <th className="text-left px-3 py-2 text-xs font-medium text-zinc-500">Out</th>
                                        <th className="text-left px-3 py-2 text-xs font-medium text-zinc-500">Hours</th>
                                        <th className="text-right px-6 py-2 text-xs font-medium text-zinc-500">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {records.map((r, i) => (
                                        <tr key={i} className="border-b border-zinc-50">
                                            <td className="px-6 py-2.5 text-zinc-900 font-medium">
                                                {new Date(r.date || r.clock_in).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', weekday: 'short' })}
                                            </td>
                                            <td className="px-3 py-2.5 text-zinc-600">{formatTime(r.clock_in)}</td>
                                            <td className="px-3 py-2.5 text-zinc-600">{formatTime(r.clock_out)}</td>
                                            <td className="px-3 py-2.5 text-zinc-600">{formatDuration(r.total_minutes)}</td>
                                            <td className="px-6 py-2.5 text-right">
                                                <Badge variant={STATUS_BADGE[r.status] || 'outline'} className="text-[10px] capitalize">
                                                    {r.status || '-'}
                                                </Badge>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Penalty Summary */}
            {(summary.total_lates > 0 || summary.total_late_minutes > 0) && (
                <Card>
                    <CardContent className="py-4">
                        <div className="flex items-center gap-2 mb-2">
                            <AlertTriangle className="h-4 w-4 text-amber-500" />
                            <h3 className="text-sm font-medium text-zinc-700">Penalty Summary</h3>
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div className="rounded-lg bg-amber-50 p-3 text-center">
                                <p className="text-lg font-bold text-amber-700">{summary.total_lates ?? 0}</p>
                                <p className="text-xs text-amber-600">Total Lates</p>
                            </div>
                            <div className="rounded-lg bg-amber-50 p-3 text-center">
                                <p className="text-lg font-bold text-amber-700">{summary.total_late_minutes ?? 0}</p>
                                <p className="text-xs text-amber-600">Late Minutes</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}
