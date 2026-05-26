import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import {
    CalendarOff,
    Plus,
    CalendarDays,
    Trash2,
    ChevronLeft,
    ChevronRight,
    Palmtree,
    ArrowRight,
} from 'lucide-react';
import { fetchMyLeaveBalances, fetchMyLeaveRequests, cancelMyLeave } from '../../lib/api';
import { cn } from '../../lib/utils';
import { Card, CardContent } from '../../components/ui/card';
import { EmployeePageHeader } from '../../components/ui/employee-page-header';
import { BalanceRing } from '../../components/ui/balance-ring';
import { StatusBadge } from '../../components/ui/status-badge';
import { RecordCard, RecordList } from '../../components/ui/record-card';

// ---- Helpers ----
function formatDateShort(dateStr) {
    if (!dateStr) return '–';
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

// Map leave-type code/name to an accent color
function leaveAccent(typeName) {
    const t = (typeName || '').toLowerCase();
    if (t.includes('annual')) return 'indigo';
    if (t.includes('medical') || t.includes('sick')) return 'rose';
    if (t.includes('maternity') || t.includes('paternity')) return 'pink';
    if (t.includes('compassionate') || t.includes('bereavement')) return 'slate';
    if (t.includes('marriage')) return 'amber';
    if (t.includes('replacement') || t.includes('ot')) return 'sky';
    if (t.includes('unpaid')) return 'slate';
    return 'violet';
}

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

    // Build leave dates for calendar
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

    // Primary balance — pick "Annual" if exists, else first
    const primaryBalance = balances.find((b) =>
        (b.leave_type?.name || b.type_name || '').toLowerCase().includes('annual')
    ) || balances[0];

    const otBalance = balances.find((b) => b.type_code === 'ot_replacement' || b.leave_type?.code === 'ot_replacement');

    const calendarDays = getMonthDates(calYear, calMonth);
    const calLabel = calDate.toLocaleDateString('en-MY', { month: 'long', year: 'numeric' });

    const pendingCount = requests.filter((r) => r.status === 'pending').length;
    const approvedCount = requests.filter((r) => r.status === 'approved').length;

    // Sort by start date descending
    const sortedRequests = [...requests].sort((a, b) => {
        return new Date(b.start_date) - new Date(a.start_date);
    });

    return (
        <div className="space-y-5 pb-4">
            <EmployeePageHeader
                icon={CalendarOff}
                accent="violet"
                title="My Leave"
                context={`${requests.length} request${requests.length === 1 ? '' : 's'}`}
                action={
                    <Link
                        to="/my/leave/apply"
                        className="inline-flex items-center gap-1 rounded-full bg-gradient-to-r from-indigo-500 via-pink-500 to-orange-400 px-3 py-1.5 text-[11px] font-bold uppercase tracking-wider text-white shadow-md shadow-pink-500/30 transition-all hover:shadow-lg"
                    >
                        <Plus className="h-3 w-3" /> Apply
                    </Link>
                }
            />

            {/* Hero: Primary Leave Balance Ring */}
            {primaryBalance && (() => {
                const entitled = parseFloat(primaryBalance.entitled_days) || 0;
                const carried = parseFloat(primaryBalance.carried_forward_days) || 0;
                const used = parseFloat(primaryBalance.used_days) || 0;
                const available = parseFloat(primaryBalance.available_days) || 0;
                const total = entitled + carried;
                const accent = leaveAccent(primaryBalance.leave_type?.name || primaryBalance.type_name);

                return (
                    <div className="relative py-2">
                        <BalanceRing
                            value={available}
                            max={total}
                            accent={accent}
                            size={232}
                            stroke={12}
                        >
                            <div className="text-[10px] font-bold uppercase tracking-widest text-slate-500">
                                {primaryBalance.leave_type?.name || primaryBalance.type_name || 'Leave'}
                            </div>
                            <div className="mt-2 flex items-baseline tabular-nums leading-none">
                                <span className="text-5xl font-bold tracking-tight text-slate-900">{available}</span>
                                <span className="ml-1 text-base font-semibold text-slate-400">/ {total}</span>
                            </div>
                            <p className="mt-2 text-[11px] font-medium text-slate-500">
                                days available
                            </p>
                            <p className="mt-1 text-[10px] font-semibold tabular-nums text-slate-400">
                                {used} used · {carried} carried
                            </p>
                        </BalanceRing>
                    </div>
                );
            })()}

            {/* Quick stats chips */}
            <div className="flex flex-wrap items-center justify-center gap-2">
                {pendingCount > 0 && (
                    <div className="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1.5 text-[11px] font-semibold text-amber-800 ring-1 ring-amber-200">
                        <span className="relative flex h-1.5 w-1.5">
                            <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-amber-400 opacity-75" />
                            <span className="relative inline-flex h-1.5 w-1.5 rounded-full bg-amber-500" />
                        </span>
                        <span className="tabular-nums">{pendingCount}</span> pending
                    </div>
                )}
                {approvedCount > 0 && (
                    <div className="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1.5 text-[11px] font-semibold text-emerald-800 ring-1 ring-emerald-200">
                        <span className="tabular-nums">{approvedCount}</span> approved
                    </div>
                )}
                {otBalance && (
                    <div className="inline-flex items-center gap-1.5 rounded-full bg-sky-50 px-3 py-1.5 text-[11px] font-semibold text-sky-800 ring-1 ring-sky-200">
                        <span className="tabular-nums">{otBalance.available_days ?? otBalance.available ?? 0}</span> OT replacement
                    </div>
                )}
            </div>

            {/* All Balances grid */}
            {!loadingBalances && balances.length > 1 && (
                <div>
                    <h3 className="mb-2 px-1 text-sm font-bold text-slate-900">All balances</h3>
                    <div className="grid grid-cols-2 gap-2">
                        {balances.filter((b) => b !== primaryBalance).map((bal) => {
                            const entitled = parseFloat(bal.entitled_days) || 0;
                            const carried = parseFloat(bal.carried_forward_days) || 0;
                            const used = parseFloat(bal.used_days) || 0;
                            const available = parseFloat(bal.available_days) || 0;
                            const total = entitled + carried;
                            const usedPct = total > 0 ? Math.min((used / total) * 100, 100) : 0;
                            const accent = leaveAccent(bal.leave_type?.name || bal.type_name);
                            const accentColors = {
                                indigo: 'from-indigo-500 to-violet-500',
                                rose: 'from-rose-500 to-pink-500',
                                pink: 'from-pink-500 to-fuchsia-500',
                                amber: 'from-amber-500 to-orange-500',
                                violet: 'from-violet-500 to-fuchsia-500',
                                sky: 'from-sky-500 to-blue-500',
                                slate: 'from-slate-400 to-slate-500',
                            };

                            return (
                                <Card key={bal.id || bal.leave_type_id} className="border-slate-200/80">
                                    <CardContent className="p-3">
                                        <p className="truncate text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                                            {bal.leave_type?.name || bal.type_name || 'Leave'}
                                        </p>
                                        <div className="mt-1 flex items-baseline gap-1">
                                            <span className="text-2xl font-bold tabular-nums text-slate-900">{available}</span>
                                            <span className="text-xs font-semibold text-slate-400">/ {total}</span>
                                        </div>
                                        <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100">
                                            <div
                                                className={cn(
                                                    'h-full rounded-full bg-gradient-to-r transition-all',
                                                    accentColors[accent] || accentColors.violet
                                                )}
                                                style={{ width: `${usedPct}%` }}
                                            />
                                        </div>
                                        <p className="mt-1 text-[10px] text-slate-400 tabular-nums">
                                            {used} used · {carried} carried
                                        </p>
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                </div>
            )}

            {/* Calendar */}
            <Card className="border-slate-200/80">
                <CardContent className="pt-4">
                    <div className="mb-3 flex items-center justify-between">
                        <button
                            onClick={() => setCalDate(new Date(calYear, calMonth - 1, 1))}
                            aria-label="Previous month"
                            className="flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                        >
                            <ChevronLeft className="h-4 w-4" />
                        </button>
                        <h3 className="text-sm font-bold text-slate-900">{calLabel}</h3>
                        <button
                            onClick={() => setCalDate(new Date(calYear, calMonth + 1, 1))}
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
                            const dateKey = date.toISOString().substring(0, 10);
                            const isCurrentMonth = date.getMonth() === calMonth;
                            const isToday = dateKey === new Date().toISOString().substring(0, 10);
                            const isLeave = leaveDates.has(dateKey);

                            return (
                                <div
                                    key={i}
                                    className={cn(
                                        'relative flex aspect-square items-center justify-center rounded-xl text-xs transition-all',
                                        !isCurrentMonth && 'opacity-30',
                                        isToday && !isLeave && 'bg-gradient-to-br from-indigo-50 to-pink-50 ring-2 ring-pink-300',
                                        isLeave && isCurrentMonth && 'bg-gradient-to-br from-violet-100 to-fuchsia-100 text-violet-800 font-semibold ring-1 ring-violet-200',
                                        isLeave && isToday && 'ring-2 ring-pink-400'
                                    )}
                                >
                                    <span className={cn(
                                        'tabular-nums',
                                        isToday && !isLeave ? 'text-pink-700 font-bold' :
                                        isLeave && isCurrentMonth ? 'text-violet-800' :
                                        isCurrentMonth ? 'text-slate-700' :
                                        'text-slate-400'
                                    )}>
                                        {date.getDate()}
                                    </span>
                                </div>
                            );
                        })}
                    </div>

                    <div className="mt-4 flex flex-wrap gap-x-3 border-t border-slate-100 pt-3">
                        <div className="flex items-center gap-1.5">
                            <div className="h-2 w-2 rounded bg-gradient-to-br from-violet-400 to-fuchsia-500 ring-1 ring-white shadow-sm" />
                            <span className="text-[10px] font-medium text-slate-500">Leave</span>
                        </div>
                        <div className="flex items-center gap-1.5">
                            <div className="h-2 w-2 rounded ring-2 ring-pink-400" />
                            <span className="text-[10px] font-medium text-slate-500">Today</span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Requests */}
            <div>
                <div className="mb-2 flex items-center justify-between px-1">
                    <h3 className="text-sm font-bold text-slate-900">My requests</h3>
                    {requests.length > 0 && (
                        <span className="text-[11px] font-semibold text-slate-500">
                            <span className="tabular-nums text-slate-800">{requests.length}</span> total
                        </span>
                    )}
                </div>
                <RecordList
                    items={sortedRequests}
                    isLoading={loadingRequests}
                    emptyIcon={Palmtree}
                    emptyAccent="violet"
                    emptyTitle="No leave requests yet"
                    emptyDescription="Apply for time off when you need a break"
                    emptyAction={
                        <Link
                            to="/my/leave/apply"
                            className="inline-flex items-center gap-1.5 rounded-full bg-gradient-to-r from-indigo-500 via-pink-500 to-orange-400 px-4 py-2 text-xs font-bold uppercase tracking-wider text-white shadow-md shadow-pink-500/30"
                        >
                            <Plus className="h-3.5 w-3.5" /> Apply for leave
                            <ArrowRight className="h-3 w-3" />
                        </Link>
                    }
                    renderItem={(req) => {
                        const canCancel = req.status === 'pending' ||
                            (req.status === 'approved' && new Date(req.start_date) > new Date());
                        const accent = leaveAccent(req.leave_type?.name || req.type_name);

                        return (
                            <RecordCard
                                key={req.id}
                                icon={Palmtree}
                                accent={accent}
                                title={req.leave_type?.name || req.type_name || 'Leave'}
                                subtitle={`${formatDateShort(req.start_date)} – ${formatDateShort(req.end_date)}${req.total_days ? ` · ${req.total_days} day${req.total_days > 1 ? 's' : ''}` : ''}`}
                                meta={req.reason}
                                badge={<StatusBadge status={req.status} size="sm" />}
                            >
                                {canCancel && (
                                    <div className="mt-2 flex justify-end">
                                        <button
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                if (window.confirm('Cancel this leave request?')) {
                                                    cancelMut.mutate(req.id);
                                                }
                                            }}
                                            disabled={cancelMut.isPending}
                                            className="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2.5 py-1 text-[10px] font-semibold text-rose-700 ring-1 ring-rose-200 transition-colors hover:bg-rose-100 disabled:opacity-50"
                                        >
                                            <Trash2 className="h-3 w-3" />
                                            Cancel
                                        </button>
                                    </div>
                                )}
                            </RecordCard>
                        );
                    }}
                />
            </div>
        </div>
    );
}
