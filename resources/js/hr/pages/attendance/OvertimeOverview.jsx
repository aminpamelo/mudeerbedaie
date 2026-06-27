import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Timer,
    Clock,
    CheckCircle,
    XCircle,
    Scale,
    Users,
    Building2,
    TrendingUp,
    SlidersHorizontal,
    Loader2,
    Search,
    AlertCircle,
    Plus,
    Minus,
    Trash2,
} from 'lucide-react';
import {
    fetchOvertimeOverview,
    fetchOvertimeByEmployee,
    fetchOvertimeRequests,
    approveOvertime,
    rejectOvertime,
    completeOvertime,
    adjustOvertime,
    fetchOvertimeAdjustments,
    createOvertimeAdjustment,
    deleteOvertimeAdjustment,
} from '../../lib/api';
import { cn } from '../../lib/utils';
import PageHeader from '../../components/PageHeader';
import { StatCard } from '../../components/ui/stat-card';
import { Card, CardContent } from '../../components/ui/card';
import { Button } from '../../components/ui/button';
import { Input } from '../../components/ui/input';
import { Label } from '../../components/ui/label';
import { Badge } from '../../components/ui/badge';
import { Textarea } from '../../components/ui/textarea';
import {
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableHead,
    TableCell,
} from '../../components/ui/table';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '../../components/ui/dialog';

const PERIODS = [
    { value: 'this_month', label: 'This Month' },
    { value: 'last_month', label: 'Last Month' },
    { value: 'this_year', label: 'This Year' },
    { value: 'all', label: 'All Time' },
];

const STATUS_META = [
    { key: 'pending', label: 'Pending', dot: 'bg-amber-400' },
    { key: 'approved', label: 'Approved', dot: 'bg-blue-400' },
    { key: 'completed', label: 'Completed', dot: 'bg-emerald-500' },
    { key: 'rejected', label: 'Rejected', dot: 'bg-red-400' },
    { key: 'cancelled', label: 'Cancelled', dot: 'bg-slate-400' },
];

const STATUS_VARIANT = {
    pending: 'warning',
    approved: 'success',
    completed: 'secondary',
    rejected: 'destructive',
    cancelled: 'outline',
};

function OTStatusBadge({ status }) {
    return <Badge variant={STATUS_VARIANT[status] || 'secondary'}>{status}</Badge>;
}

function formatDate(value) {
    if (!value) return '-';
    return new Date(value).toLocaleDateString('en-MY', { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatHours(value) {
    if (value === null || value === undefined || value === '') return '-';
    return `${Number(value).toFixed(1)}h`;
}

/** Render a signed minutes value as "+1h 30min" / "-45min". */
function formatSignedMinutes(value) {
    const total = Number(value) || 0;
    const sign = total < 0 ? '-' : '+';
    const abs = Math.abs(total);
    const h = Math.floor(abs / 60);
    const m = abs % 60;
    const body = h === 0 ? `${m}min` : m === 0 ? `${h}h` : `${h}h ${m}min`;
    return `${sign}${body}`;
}

function SkeletonCards() {
    return (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {Array.from({ length: 4 }).map((_, i) => (
                <div key={i} className="h-[120px] animate-pulse rounded-2xl bg-slate-100" />
            ))}
        </div>
    );
}

/** Compact vertical bar chart for the 6-month trend. */
function TrendChart({ data }) {
    const max = Math.max(1, ...data.map((d) => d.hours));
    return (
        <div className="flex items-end justify-between gap-3 h-32 pt-2">
            {data.map((d) => (
                <div key={d.label} className="flex flex-1 flex-col items-center gap-2">
                    <span className="text-[11px] font-medium tabular-nums text-slate-500">
                        {d.hours > 0 ? d.hours : ''}
                    </span>
                    <div className="flex w-full flex-1 items-end">
                        <div
                            className="w-full rounded-t-md bg-gradient-to-t from-indigo-500 to-violet-400 transition-all"
                            style={{ height: `${Math.max(2, (d.hours / max) * 100)}%` }}
                            title={`${d.label}: ${d.hours}h`}
                        />
                    </div>
                    <span className="text-[11px] font-medium text-slate-400">{d.label}</span>
                </div>
            ))}
        </div>
    );
}

export default function OvertimeOverview() {
    const queryClient = useQueryClient();
    const [period, setPeriod] = useState('this_year');
    const [departmentId, setDepartmentId] = useState('');
    const [search, setSearch] = useState('');
    const [selectedEmployee, setSelectedEmployee] = useState(null);
    const [actionTarget, setActionTarget] = useState(null); // { type, entry }
    const [actualHours, setActualHours] = useState('');
    const [reason, setReason] = useState('');
    const [adjSign, setAdjSign] = useState('add'); // 'add' | 'minus'
    const [adjMinutes, setAdjMinutes] = useState('');
    const [adjReason, setAdjReason] = useState('');

    const filters = { period, department_id: departmentId || undefined };

    const { data: overview, isLoading: overviewLoading } = useQuery({
        queryKey: ['hr', 'ot', 'overview', period, departmentId],
        queryFn: () => fetchOvertimeOverview(filters),
    });

    const { data: employeeRows = [], isLoading: rowsLoading } = useQuery({
        queryKey: ['hr', 'ot', 'by-employee', period, departmentId, search],
        queryFn: () => fetchOvertimeByEmployee({ ...filters, search: search || undefined }),
    });

    const { data: entriesData, isLoading: entriesLoading } = useQuery({
        queryKey: ['hr', 'ot', 'employee-entries', selectedEmployee?.employee_id],
        queryFn: () => fetchOvertimeRequests({ employee_id: selectedEmployee.employee_id, per_page: 100 }),
        enabled: !!selectedEmployee,
    });

    const { data: adjustments = [], isLoading: adjustmentsLoading } = useQuery({
        queryKey: ['hr', 'ot', 'adjustments', selectedEmployee?.employee_id],
        queryFn: () => fetchOvertimeAdjustments({ employee_id: selectedEmployee.employee_id }),
        enabled: !!selectedEmployee,
    });

    const stats = overview?.stats;
    const departments = overview?.departments || [];
    const statusBreakdown = overview?.status_breakdown || {};
    const byDepartment = overview?.by_department || [];
    const trend = overview?.trend || [];
    const maxDeptHours = Math.max(1, ...byDepartment.map((d) => Number(d.hours)));
    const entries = entriesData?.data || [];

    // Keep the drill-in summary in sync with the live aggregates after an adjust.
    const liveRow = selectedEmployee
        ? employeeRows.find((r) => r.employee_id === selectedEmployee.employee_id) || selectedEmployee
        : null;

    function invalidateAll() {
        queryClient.invalidateQueries({ queryKey: ['hr', 'ot'] });
        queryClient.invalidateQueries({ queryKey: ['hr', 'attendance', 'overtime'] });
    }

    function closePanel() {
        setActionTarget(null);
        setActualHours('');
        setReason('');
    }

    const approveMutation = useMutation({
        mutationFn: (id) => approveOvertime(id),
        onSuccess: () => { invalidateAll(); closePanel(); },
        onError: (e) => alert(e?.response?.data?.message || 'Failed to approve'),
    });

    const rejectMutation = useMutation({
        mutationFn: ({ id, rejection_reason }) => rejectOvertime(id, { rejection_reason }),
        onSuccess: () => { invalidateAll(); closePanel(); },
        onError: (e) => alert(e?.response?.data?.message || 'Failed to reject'),
    });

    const completeMutation = useMutation({
        mutationFn: ({ id, actual_hours }) => completeOvertime(id, { actual_hours }),
        onSuccess: () => { invalidateAll(); closePanel(); },
        onError: (e) => alert(e?.response?.data?.message || 'Failed to complete'),
    });

    const adjustMutation = useMutation({
        mutationFn: ({ id, actual_hours, adjustment_reason }) => adjustOvertime(id, { actual_hours, adjustment_reason }),
        onSuccess: () => { invalidateAll(); closePanel(); },
        onError: (e) => alert(e?.response?.data?.message || 'Failed to adjust'),
    });

    const createAdjustmentMutation = useMutation({
        mutationFn: (data) => createOvertimeAdjustment(data),
        onSuccess: () => {
            invalidateAll();
            setAdjMinutes('');
            setAdjReason('');
            setAdjSign('add');
        },
        onError: (e) => alert(e?.response?.data?.message || 'Failed to record adjustment'),
    });

    const deleteAdjustmentMutation = useMutation({
        mutationFn: (id) => deleteOvertimeAdjustment(id),
        onSuccess: () => invalidateAll(),
        onError: (e) => alert(e?.response?.data?.message || 'Failed to remove adjustment'),
    });

    function submitAdjustment() {
        const magnitude = parseInt(adjMinutes, 10);
        if (!magnitude || magnitude <= 0) return;
        createAdjustmentMutation.mutate({
            employee_id: selectedEmployee.employee_id,
            minutes: adjSign === 'minus' ? -magnitude : magnitude,
            reason: adjReason,
        });
    }

    const savingPanel = completeMutation.isPending || adjustMutation.isPending || rejectMutation.isPending;

    function openAction(type, entry) {
        setActionTarget({ type, entry });
        setReason('');
        if (type === 'complete') {
            setActualHours(String(entry.estimated_hours ?? ''));
        } else if (type === 'adjust') {
            setActualHours(String(entry.actual_hours ?? entry.estimated_hours ?? ''));
        }
    }

    function submitPanel() {
        const { type, entry } = actionTarget;
        if (type === 'reject') {
            rejectMutation.mutate({ id: entry.id, rejection_reason: reason });
        } else if (type === 'complete') {
            completeMutation.mutate({ id: entry.id, actual_hours: parseFloat(actualHours) });
        } else if (type === 'adjust') {
            adjustMutation.mutate({ id: entry.id, actual_hours: parseFloat(actualHours), adjustment_reason: reason });
        }
    }

    function closeDialog() {
        setSelectedEmployee(null);
        closePanel();
    }

    return (
        <div className="space-y-6">
            <PageHeader
                title="Overtime Overview"
                description="System-wide overtime totals — view and directly adjust any staff member's OT."
            />

            {/* Filters */}
            <div className="flex flex-wrap items-center gap-3">
                <div className="flex rounded-lg border border-slate-200 bg-slate-50 p-0.5">
                    {PERIODS.map((p) => (
                        <button
                            key={p.value}
                            onClick={() => setPeriod(p.value)}
                            className={cn(
                                'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                                period === p.value
                                    ? 'bg-white text-slate-900 shadow-sm'
                                    : 'text-slate-500 hover:text-slate-700'
                            )}
                        >
                            {p.label}
                        </button>
                    ))}
                </div>
                <select
                    value={departmentId}
                    onChange={(e) => setDepartmentId(e.target.value)}
                    className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"
                >
                    <option value="">All Departments</option>
                    {departments.map((d) => (
                        <option key={d.id} value={d.id}>{d.name}</option>
                    ))}
                </select>
            </div>

            {/* Stat cards */}
            {overviewLoading ? (
                <SkeletonCards />
            ) : (
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        label="Total OT Hours"
                        value={formatHours(stats?.total_ot_hours)}
                        sub={stats?.adjustment_total
                            ? `${stats?.employees_on_ot || 0} staff · incl. ${stats.adjustment_total > 0 ? '+' : ''}${stats.adjustment_total}h adj`
                            : `${stats?.employees_on_ot || 0} staff on overtime`}
                        icon={Timer}
                        accent="sky"
                    />
                    <StatCard
                        label="Pending Requests"
                        value={stats?.pending_count ?? 0}
                        sub={`${formatHours(stats?.pending_hours)} planned`}
                        icon={Clock}
                        accent="amber"
                    />
                    <StatCard
                        label="Completed"
                        value={stats?.completed_count ?? 0}
                        sub={`${stats?.total_requests ?? 0} total requests`}
                        icon={CheckCircle}
                        accent="emerald"
                    />
                    <StatCard
                        label="Replacement Balance"
                        value={formatHours(stats?.replacement_balance)}
                        sub={`${formatHours(stats?.replacement_earned)} earned`}
                        icon={Scale}
                        accent="violet"
                    />
                </div>
            )}

            {/* Trend */}
            <Card>
                <CardContent className="p-6">
                    <div className="mb-1 flex items-center gap-2">
                        <TrendingUp className="h-4 w-4 text-indigo-500" />
                        <h3 className="text-sm font-semibold text-slate-900">Overtime Hours — Last 6 Months</h3>
                    </div>
                    {overviewLoading ? (
                        <div className="h-32 animate-pulse rounded-lg bg-slate-100" />
                    ) : (
                        <TrendChart data={trend} />
                    )}
                </CardContent>
            </Card>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                {/* By employee */}
                <Card className="lg:col-span-2">
                    <CardContent className="p-6">
                        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <div className="flex items-center gap-2">
                                <Users className="h-4 w-4 text-slate-400" />
                                <h3 className="text-sm font-semibold text-slate-900">Overtime by Employee</h3>
                            </div>
                            <div className="relative">
                                <Search className="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                <Input
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Search staff…"
                                    className="h-9 w-48 pl-8"
                                />
                            </div>
                        </div>

                        {rowsLoading ? (
                            <div className="space-y-3">
                                {Array.from({ length: 6 }).map((_, i) => (
                                    <div key={i} className="h-10 animate-pulse rounded bg-slate-100" />
                                ))}
                            </div>
                        ) : employeeRows.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-12 text-center">
                                <Clock className="mb-3 h-10 w-10 text-slate-300" />
                                <p className="text-sm font-medium text-slate-500">No overtime in this period</p>
                                <p className="text-xs text-slate-400">Try a wider period or a different department.</p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Employee</TableHead>
                                            <TableHead className="text-center">Requests</TableHead>
                                            <TableHead className="text-center">Pending</TableHead>
                                            <TableHead className="text-right">OT Hours</TableHead>
                                            <TableHead className="text-right">Repl. Balance</TableHead>
                                            <TableHead className="text-right">Action</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {employeeRows.map((row) => (
                                            <TableRow key={row.employee_id}>
                                                <TableCell>
                                                    <p className="text-sm font-medium text-slate-900">{row.full_name}</p>
                                                    <p className="text-xs text-slate-500">{row.department || '—'}</p>
                                                </TableCell>
                                                <TableCell className="text-center text-sm text-slate-600 tabular-nums">
                                                    {row.total_requests}
                                                </TableCell>
                                                <TableCell className="text-center">
                                                    {row.pending_count > 0 ? (
                                                        <Badge variant="warning">{row.pending_count}</Badge>
                                                    ) : (
                                                        <span className="text-sm text-slate-400">0</span>
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    <span className="text-sm font-semibold text-slate-900">
                                                        {formatHours(row.ot_hours ?? row.completed_hours)}
                                                    </span>
                                                    {!!row.adjustment_minutes && (
                                                        <span className={cn('block text-[11px] font-medium', row.adjustment_minutes > 0 ? 'text-emerald-600' : 'text-red-500')}>
                                                            {formatSignedMinutes(row.adjustment_minutes)} adj
                                                        </span>
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right text-sm text-slate-600 tabular-nums">
                                                    {formatHours(row.replacement_balance)}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => { setSelectedEmployee(row); closePanel(); }}
                                                    >
                                                        <SlidersHorizontal className="mr-1.5 h-3.5 w-3.5" />
                                                        View &amp; Adjust
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Right column: status + departments */}
                <div className="space-y-6">
                    <Card>
                        <CardContent className="p-6">
                            <h3 className="mb-4 text-sm font-semibold text-slate-900">Status Breakdown</h3>
                            <div className="space-y-3">
                                {STATUS_META.map((s) => (
                                    <div key={s.key} className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <span className={cn('h-2 w-2 rounded-full', s.dot)} />
                                            <span className="text-sm text-slate-600">{s.label}</span>
                                        </div>
                                        <span className="text-sm font-semibold text-slate-900 tabular-nums">
                                            {statusBreakdown[s.key] ?? 0}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <div className="mb-4 flex items-center gap-2">
                                <Building2 className="h-4 w-4 text-slate-400" />
                                <h3 className="text-sm font-semibold text-slate-900">By Department</h3>
                            </div>
                            {byDepartment.length === 0 ? (
                                <p className="py-6 text-center text-sm text-slate-400">No completed overtime yet.</p>
                            ) : (
                                <div className="space-y-3">
                                    {byDepartment.slice(0, 6).map((d) => (
                                        <div key={d.id}>
                                            <div className="mb-1 flex items-center justify-between text-xs">
                                                <span className="font-medium text-slate-600">{d.name}</span>
                                                <span className="tabular-nums text-slate-500">{formatHours(d.hours)}</span>
                                            </div>
                                            <div className="h-2 overflow-hidden rounded-full bg-slate-100">
                                                <div
                                                    className="h-full rounded-full bg-gradient-to-r from-indigo-500 to-violet-500"
                                                    style={{ width: `${(Number(d.hours) / maxDeptHours) * 100}%` }}
                                                />
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>

            {/* View & Adjust dialog */}
            <Dialog open={!!selectedEmployee} onOpenChange={(open) => { if (!open) closeDialog(); }}>
                <DialogContent className="max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>{selectedEmployee?.full_name}</DialogTitle>
                        <DialogDescription>
                            {selectedEmployee?.department || 'No department'} · Manage and adjust overtime entries
                        </DialogDescription>
                    </DialogHeader>

                    {/* Summary strip */}
                    <div className="grid grid-cols-3 gap-3">
                        {[
                            { label: 'Requests', value: liveRow?.total_requests ?? 0 },
                            { label: 'OT Hours', value: formatHours(liveRow?.ot_hours ?? liveRow?.completed_hours) },
                            { label: 'Repl. Balance', value: formatHours(liveRow?.replacement_balance) },
                        ].map((m) => (
                            <div key={m.label} className="rounded-xl border border-slate-200 bg-slate-50 p-3 text-center">
                                <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{m.label}</p>
                                <p className="mt-1 text-lg font-bold text-slate-900 tabular-nums">{m.value}</p>
                            </div>
                        ))}
                    </div>

                    {/* Inline action panel */}
                    {actionTarget && (
                        <div className="rounded-xl border border-indigo-200 bg-indigo-50/60 p-4">
                            <div className="mb-3 flex items-center gap-2">
                                <AlertCircle className="h-4 w-4 text-indigo-500" />
                                <p className="text-sm font-semibold text-slate-900">
                                    {actionTarget.type === 'reject' && 'Reject overtime'}
                                    {actionTarget.type === 'complete' && 'Complete overtime — record actual hours'}
                                    {actionTarget.type === 'adjust' && 'Adjust overtime hours'}
                                </p>
                                <span className="text-xs text-slate-500">· {formatDate(actionTarget.entry.requested_date)}</span>
                            </div>

                            <div className="space-y-3">
                                {(actionTarget.type === 'complete' || actionTarget.type === 'adjust') && (
                                    <div>
                                        <Label>Actual Hours</Label>
                                        <Input
                                            type="number"
                                            step="0.5"
                                            min="0"
                                            max="24"
                                            value={actualHours}
                                            onChange={(e) => setActualHours(e.target.value)}
                                            placeholder="e.g. 2.5"
                                            className="mt-1 w-40"
                                        />
                                    </div>
                                )}
                                {(actionTarget.type === 'reject' || actionTarget.type === 'adjust') && (
                                    <div>
                                        <Label>{actionTarget.type === 'reject' ? 'Reason for rejection' : 'Reason for adjustment'}</Label>
                                        <Textarea
                                            value={reason}
                                            onChange={(e) => setReason(e.target.value)}
                                            placeholder={actionTarget.type === 'reject' ? 'Why is this rejected?' : 'Why are you changing the hours?'}
                                            rows={2}
                                            className="mt-1"
                                        />
                                    </div>
                                )}
                            </div>

                            <div className="mt-4 flex justify-end gap-2">
                                <Button variant="outline" size="sm" onClick={closePanel} disabled={savingPanel}>
                                    Cancel
                                </Button>
                                <Button
                                    size="sm"
                                    variant={actionTarget.type === 'reject' ? 'destructive' : 'default'}
                                    onClick={submitPanel}
                                    disabled={
                                        savingPanel
                                        || ((actionTarget.type === 'complete' || actionTarget.type === 'adjust') && !actualHours)
                                        || ((actionTarget.type === 'reject' || actionTarget.type === 'adjust') && reason.trim().length < 3)
                                    }
                                >
                                    {savingPanel && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                                    {actionTarget.type === 'reject' ? 'Reject' : actionTarget.type === 'complete' ? 'Mark Complete' : 'Save Adjustment'}
                                </Button>
                            </div>
                        </div>
                    )}

                    {/* Entries table */}
                    <div className="max-h-[360px] overflow-y-auto rounded-xl border border-slate-200">
                        {entriesLoading ? (
                            <div className="space-y-2 p-4">
                                {Array.from({ length: 4 }).map((_, i) => (
                                    <div key={i} className="h-10 animate-pulse rounded bg-slate-100" />
                                ))}
                            </div>
                        ) : entries.length === 0 ? (
                            <p className="py-10 text-center text-sm text-slate-400">No overtime entries for this staff.</p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Date</TableHead>
                                        <TableHead className="text-right">Planned</TableHead>
                                        <TableHead className="text-right">Actual</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {entries.map((entry) => (
                                        <TableRow key={entry.id}>
                                            <TableCell className="text-sm text-slate-900">
                                                {formatDate(entry.requested_date)}
                                                {entry.adjusted_at && (
                                                    <span className="ml-1 text-[10px] font-medium uppercase text-indigo-500" title={entry.adjustment_reason || ''}>
                                                        · adjusted
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right text-sm text-slate-600 tabular-nums">
                                                {formatHours(entry.estimated_hours)}
                                            </TableCell>
                                            <TableCell className="text-right text-sm text-slate-600 tabular-nums">
                                                {formatHours(entry.actual_hours)}
                                            </TableCell>
                                            <TableCell><OTStatusBadge status={entry.status} /></TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex items-center justify-end gap-1">
                                                    {entry.status === 'pending' && (
                                                        <>
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                className="text-emerald-600 hover:text-emerald-700"
                                                                onClick={() => approveMutation.mutate(entry.id)}
                                                                disabled={approveMutation.isPending}
                                                                title="Approve"
                                                            >
                                                                <CheckCircle className="h-4 w-4" />
                                                            </Button>
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                className="text-red-500 hover:text-red-700"
                                                                onClick={() => openAction('reject', entry)}
                                                                title="Reject"
                                                            >
                                                                <XCircle className="h-4 w-4" />
                                                            </Button>
                                                        </>
                                                    )}
                                                    {entry.status === 'approved' && (
                                                        <Button variant="outline" size="sm" onClick={() => openAction('complete', entry)}>
                                                            Complete
                                                        </Button>
                                                    )}
                                                    {(entry.status === 'approved' || entry.status === 'completed') && (
                                                        <Button variant="ghost" size="sm" onClick={() => openAction('adjust', entry)} title="Adjust hours">
                                                            <SlidersHorizontal className="h-4 w-4 text-slate-500" />
                                                        </Button>
                                                    )}
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </div>

                    {/* Admin Adjustment — separate add / minus ledger */}
                    <div className="rounded-xl border border-slate-200 p-4">
                        <div className="mb-1 flex items-center gap-2">
                            <SlidersHorizontal className="h-4 w-4 text-indigo-500" />
                            <h4 className="text-sm font-semibold text-slate-900">Admin Adjustment</h4>
                        </div>
                        <p className="mb-3 text-xs text-slate-500">
                            Add or deduct OT hours for this staff as a separate record — independent of the entries above.
                        </p>

                        <div className="flex flex-wrap items-end gap-2">
                            <div>
                                <Label>Type</Label>
                                <div className="mt-1 flex rounded-lg border border-slate-200 bg-slate-50 p-0.5">
                                    <button
                                        type="button"
                                        onClick={() => setAdjSign('add')}
                                        className={cn(
                                            'flex items-center gap-1 rounded-md px-2.5 py-1.5 text-sm font-medium transition-colors',
                                            adjSign === 'add' ? 'bg-white text-emerald-700 shadow-sm' : 'text-slate-500 hover:text-slate-700'
                                        )}
                                    >
                                        <Plus className="h-3.5 w-3.5" /> Add
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setAdjSign('minus')}
                                        className={cn(
                                            'flex items-center gap-1 rounded-md px-2.5 py-1.5 text-sm font-medium transition-colors',
                                            adjSign === 'minus' ? 'bg-white text-red-600 shadow-sm' : 'text-slate-500 hover:text-slate-700'
                                        )}
                                    >
                                        <Minus className="h-3.5 w-3.5" /> Minus
                                    </button>
                                </div>
                            </div>
                            <div>
                                <Label>Minutes</Label>
                                <Input
                                    type="number"
                                    step="5"
                                    min="0"
                                    max="1440"
                                    value={adjMinutes}
                                    onChange={(e) => setAdjMinutes(e.target.value)}
                                    placeholder="e.g. 30"
                                    className="mt-1 w-24"
                                />
                            </div>
                            <div className="min-w-[160px] flex-1">
                                <Label>Reason</Label>
                                <Input
                                    value={adjReason}
                                    onChange={(e) => setAdjReason(e.target.value)}
                                    placeholder="Why this adjustment?"
                                    className="mt-1"
                                />
                            </div>
                            <Button
                                onClick={submitAdjustment}
                                disabled={createAdjustmentMutation.isPending || !adjMinutes || parseInt(adjMinutes, 10) <= 0 || adjReason.trim().length < 3}
                            >
                                {createAdjustmentMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                                Add
                            </Button>
                        </div>

                        <div className="mt-4">
                            {adjustmentsLoading ? (
                                <div className="h-10 animate-pulse rounded bg-slate-100" />
                            ) : adjustments.length === 0 ? (
                                <p className="text-xs text-slate-400">No admin adjustments recorded.</p>
                            ) : (
                                <div className="space-y-2">
                                    {adjustments.map((a) => {
                                        const mins = Number(a.minutes);
                                        return (
                                            <div key={a.id} className="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2">
                                                <div className="min-w-0">
                                                    <span className={cn('text-sm font-semibold tabular-nums', mins > 0 ? 'text-emerald-600' : 'text-red-500')}>
                                                        {formatSignedMinutes(mins)}
                                                    </span>
                                                    <span className="ml-2 text-sm text-slate-600">{a.reason}</span>
                                                    <p className="text-xs text-slate-400">
                                                        {formatDate(a.effective_date)}{a.adjuster?.name ? ` · by ${a.adjuster.name}` : ''}
                                                    </p>
                                                </div>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="text-slate-400 hover:text-red-600"
                                                    onClick={() => { if (window.confirm('Remove this adjustment?')) { deleteAdjustmentMutation.mutate(a.id); } }}
                                                    title="Remove adjustment"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </div>
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    );
}
