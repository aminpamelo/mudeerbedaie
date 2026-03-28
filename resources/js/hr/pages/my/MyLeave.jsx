import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import {
    CalendarOff,
    Plus,
    CheckCircle2,
    XCircle,
    Hourglass,
    Loader2,
    CalendarDays,
    Trash2,
    ChevronLeft,
    ChevronRight,
} from 'lucide-react';
import { fetchMyLeaveBalances, fetchMyLeaveRequests, cancelMyLeave } from '../../lib/api';
import { cn } from '../../lib/utils';
import { Card, CardContent, CardHeader, CardTitle } from '../../components/ui/card';
import { Button } from '../../components/ui/button';
import { Badge } from '../../components/ui/badge';

// ---- Helpers ----
function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' });
}

function formatDateShort(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-MY', { day: 'numeric', month: 'short' });
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

const STATUS_CONFIG = {
    pending: { label: 'Pending', variant: 'secondary', color: 'text-amber-600' },
    approved: { label: 'Approved', variant: 'default', color: 'text-emerald-600' },
    rejected: { label: 'Rejected', variant: 'destructive', color: 'text-red-600' },
    cancelled: { label: 'Cancelled', variant: 'outline', color: 'text-zinc-500' },
};

// ========== MAIN COMPONENT ==========
export default function MyLeave() {
    const queryClient = useQueryClient();
    const [calDate, setCalDate] = useState(new Date());
    const calYear = calDate.getFullYear();
    const calMonth = calDate.getMonth();

    const { data: balancesData, isLoading: loadingBalances } = useQuery({
        queryKey: ['my-leave-balances'],
        queryFn: fetchMyLeaveBalances,
    });
    const balances = balancesData?.data ?? [];

    const { data: requestsData, isLoading: loadingRequests } = useQuery({
        queryKey: ['my-leave-requests'],
        queryFn: () => fetchMyLeaveRequests(),
    });
    const requests = requestsData?.data ?? [];

    const cancelMut = useMutation({
        mutationFn: cancelMyLeave,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['my-leave-requests'] });
            queryClient.invalidateQueries({ queryKey: ['my-leave-balances'] });
        },
    });

    // Build set of leave dates for calendar highlight
    const leaveDates = new Set();
    requests.forEach((r) => {
        if (r.status === 'approved' || r.status === 'pending') {
            const start = new Date(r.start_date);
            const end = new Date(r.end_date);
            for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
                leaveDates.add(d.toISOString().substring(0, 10));
            }
        }
    });

    // OT replacement balance from balances
    const otBalance = balances.find((b) => b.type_code === 'ot_replacement' || b.leave_type?.code === 'ot_replacement');

    const calendarDays = getMonthDates(calYear, calMonth);
    const calLabel = calDate.toLocaleDateString('en-MY', { month: 'long', year: 'numeric' });

    return (
        <div className="space-y-4">
            {/* Header */}
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-bold text-zinc-900">My Leave</h1>
                    <p className="text-sm text-zinc-500 mt-0.5">View balances and manage requests</p>
                </div>
                <Link to="/my/leave/apply">
                    <Button size="sm">
                        <Plus className="h-4 w-4 mr-1" /> Apply Leave
                    </Button>
                </Link>
            </div>

            {/* Balance Cards */}
            {loadingBalances ? (
                <div className="flex justify-center py-4">
                    <Loader2 className="h-6 w-6 animate-spin text-zinc-400" />
                </div>
            ) : (
                <div className="grid grid-cols-2 gap-2">
                    {balances.map((bal) => {
                        const entitled = parseFloat(bal.entitled_days) || 0;
                        const carried = parseFloat(bal.carried_forward_days) || 0;
                        const used = parseFloat(bal.used_days) || 0;
                        const available = parseFloat(bal.available_days) || 0;
                        const total = entitled + carried;
                        const usedPct = total > 0 ? Math.min((used / total) * 100, 100) : 0;

                        return (
                            <Card key={bal.id || bal.leave_type_id}>
                                <CardContent className="py-3.5 px-3.5">
                                    <p className="text-xs font-medium text-zinc-700 truncate">
                                        {bal.leave_type?.name || bal.type_name || 'Leave'}
                                    </p>
                                    <div className="flex items-baseline gap-1 mt-1">
                                        <span className="text-xl font-bold text-zinc-900">{available}</span>
                                        <span className="text-xs text-zinc-500">/ {total} days</span>
                                    </div>
                                    {/* Progress bar */}
                                    <div className="mt-2 h-1.5 rounded-full bg-zinc-100 overflow-hidden">
                                        <div
                                            className={cn(
                                                'h-full rounded-full transition-all',
                                                usedPct > 80 ? 'bg-red-500' : usedPct > 50 ? 'bg-amber-500' : 'bg-emerald-500'
                                            )}
                                            style={{ width: `${usedPct}%` }}
                                        />
                                    </div>
                                    <p className="text-[10px] text-zinc-400 mt-1">
                                        Used: {used} | Carried: {carried}
                                    </p>
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>
            )}

            {/* OT Replacement Balance */}
            {otBalance && (
                <Card>
                    <CardContent className="py-3">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <div className="rounded-lg bg-blue-50 p-1.5">
                                    <CalendarOff className="h-4 w-4 text-blue-600" />
                                </div>
                                <div>
                                    <p className="text-sm font-medium text-zinc-900">OT Replacement</p>
                                    <p className="text-xs text-zinc-500">Available balance from overtime</p>
                                </div>
                            </div>
                            <span className="text-lg font-bold text-blue-600">
                                {otBalance.available ?? 0} days
                            </span>
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Mini Calendar */}
            <Card>
                <CardHeader className="pb-2">
                    <div className="flex items-center justify-between">
                        <Button variant="ghost" size="sm" onClick={() => setCalDate(new Date(calYear, calMonth - 1, 1))}>
                            <ChevronLeft className="h-4 w-4" />
                        </Button>
                        <CardTitle className="text-sm">{calLabel}</CardTitle>
                        <Button variant="ghost" size="sm" onClick={() => setCalDate(new Date(calYear, calMonth + 1, 1))}>
                            <ChevronRight className="h-4 w-4" />
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <div className="grid grid-cols-7 gap-1">
                        {['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'].map((d) => (
                            <div key={d} className="text-center text-[10px] font-medium text-zinc-400 pb-1">{d}</div>
                        ))}
                        {calendarDays.map((date, i) => {
                            const dateKey = date.toISOString().substring(0, 10);
                            const isCurrentMonth = date.getMonth() === calMonth;
                            const isToday = dateKey === new Date().toISOString().substring(0, 10);
                            const isLeave = leaveDates.has(dateKey);

                            return (
                                <div
                                    key={i}
                                    className={cn(
                                        'flex items-center justify-center rounded-md py-1 text-xs',
                                        !isCurrentMonth && 'opacity-30',
                                        isToday && 'ring-1 ring-zinc-900',
                                        isLeave && isCurrentMonth && 'bg-purple-100 text-purple-700 font-medium'
                                    )}
                                >
                                    {date.getDate()}
                                </div>
                            );
                        })}
                    </div>
                </CardContent>
            </Card>

            {/* Leave Requests */}
            <Card>
                <CardHeader className="pb-2">
                    <CardTitle className="text-sm">My Requests</CardTitle>
                </CardHeader>
                <CardContent>
                    {loadingRequests ? (
                        <div className="flex justify-center py-8">
                            <Loader2 className="h-6 w-6 animate-spin text-zinc-400" />
                        </div>
                    ) : requests.length === 0 ? (
                        <div className="py-8 text-center">
                            <CalendarDays className="h-8 w-8 text-zinc-300 mx-auto mb-2" />
                            <p className="text-sm text-zinc-500">No leave requests yet</p>
                        </div>
                    ) : (
                        <div className="space-y-2">
                            {requests.map((req) => {
                                const cfg = STATUS_CONFIG[req.status] || STATUS_CONFIG.pending;
                                const canCancel = req.status === 'pending' ||
                                    (req.status === 'approved' && new Date(req.start_date) > new Date());

                                return (
                                    <div
                                        key={req.id}
                                        className="flex items-center justify-between rounded-lg border border-zinc-100 p-3"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center gap-2">
                                                <p className="text-sm font-medium text-zinc-900">
                                                    {req.leave_type?.name || req.type_name || 'Leave'}
                                                </p>
                                                <Badge variant={cfg.variant} className="text-[10px]">
                                                    {cfg.label}
                                                </Badge>
                                            </div>
                                            <p className="text-xs text-zinc-500 mt-0.5">
                                                {formatDateShort(req.start_date)} - {formatDateShort(req.end_date)}
                                                {req.total_days && ` (${req.total_days} day${req.total_days > 1 ? 's' : ''})`}
                                            </p>
                                            {req.reason && (
                                                <p className="text-xs text-zinc-400 mt-0.5 truncate">{req.reason}</p>
                                            )}
                                        </div>
                                        {canCancel && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="h-7 w-7 p-0 text-red-500 hover:text-red-700 shrink-0 ml-2"
                                                onClick={() => {
                                                    if (window.confirm('Cancel this leave request?')) {
                                                        cancelMut.mutate(req.id);
                                                    }
                                                }}
                                                disabled={cancelMut.isPending}
                                            >
                                                <Trash2 className="h-3.5 w-3.5" />
                                            </Button>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
