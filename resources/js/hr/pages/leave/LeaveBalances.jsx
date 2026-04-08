import { useState, useCallback } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Download,
    Eye,
    Plus,
    Minus,
    RefreshCw,
    ChevronLeft,
    ChevronRight,
    Loader2,
    Wallet,
} from 'lucide-react';
import {
    fetchLeaveBalances,
    fetchEmployeeLeaveBalance,
    initializeLeaveBalances,
    adjustLeaveBalance,
    exportLeaveBalances,
    fetchLeaveTypes,
    fetchDepartments,
} from '../../lib/api';
import { cn } from '../../lib/utils';
import PageHeader from '../../components/PageHeader';
import SearchInput from '../../components/SearchInput';
import { Button } from '../../components/ui/button';
import { Card, CardContent } from '../../components/ui/card';
import { Badge } from '../../components/ui/badge';
import { Input } from '../../components/ui/input';
import {
    Select,
    SelectTrigger,
    SelectContent,
    SelectItem,
    SelectValue,
} from '../../components/ui/select';
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
    DialogFooter,
} from '../../components/ui/dialog';

function SkeletonTable() {
    return (
        <div className="space-y-3">
            {Array.from({ length: 8 }).map((_, i) => (
                <div key={i} className="flex items-center gap-4 px-4 py-3">
                    <div className="h-4 w-32 animate-pulse rounded bg-zinc-200" />
                    <div className="h-4 w-24 animate-pulse rounded bg-zinc-200" />
                    <div className="h-4 w-16 animate-pulse rounded bg-zinc-200" />
                    <div className="h-4 w-16 animate-pulse rounded bg-zinc-200" />
                    <div className="flex-1" />
                    <div className="h-8 w-20 animate-pulse rounded bg-zinc-200" />
                </div>
            ))}
        </div>
    );
}

function EmptyState({ hasFilters, onClearFilters }) {
    return (
        <div className="flex flex-col items-center justify-center py-16 text-center">
            <Wallet className="mb-4 h-12 w-12 text-zinc-300" />
            <h3 className="text-lg font-semibold text-zinc-900">
                {hasFilters ? 'No balances found' : 'No leave balances yet'}
            </h3>
            <p className="mt-1 text-sm text-zinc-500">
                {hasFilters
                    ? 'Try adjusting your filters.'
                    : 'Initialize year balances to get started.'}
            </p>
            {hasFilters && (
                <Button variant="outline" className="mt-4" onClick={onClearFilters}>
                    Clear Filters
                </Button>
            )}
        </div>
    );
}

export default function LeaveBalances() {
    const queryClient = useQueryClient();
    const currentYear = new Date().getFullYear();
    const [search, setSearch] = useState('');
    const [departmentFilter, setDepartmentFilter] = useState('all');
    const [yearFilter, setYearFilter] = useState(String(currentYear));
    const [page, setPage] = useState(1);
    const [detailDialog, setDetailDialog] = useState({ open: false, employee: null, loading: false, data: null });
    const [initDialog, setInitDialog] = useState(false);
    const [initYear, setInitYear] = useState(String(currentYear));
    const [adjustDialog, setAdjustDialog] = useState({ open: false, employee: null });
    const [adjustForm, setAdjustForm] = useState({ leave_type_id: '', days: '', type: 'add', reason: '' });
    const [exporting, setExporting] = useState(false);

    const hasFilters = search !== '' || departmentFilter !== 'all';

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'leave', 'balances', { search, department: departmentFilter, year: yearFilter, page }],
        queryFn: () =>
            fetchLeaveBalances({
                search,
                department_id: departmentFilter !== 'all' ? departmentFilter : undefined,
                year: yearFilter,
                page,
            }),
    });

    const { data: leaveTypesData } = useQuery({
        queryKey: ['hr', 'leave', 'types', 'list'],
        queryFn: () => fetchLeaveTypes({ per_page: 100 }),
    });

    const { data: departmentsData } = useQuery({
        queryKey: ['hr', 'departments', 'list'],
        queryFn: () => fetchDepartments({ per_page: 100 }),
    });

    const initMutation = useMutation({
        mutationFn: (data) => initializeLeaveBalances(data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'leave', 'balances'] });
            setInitDialog(false);
        },
    });

    const adjustMutation = useMutation({
        mutationFn: ({ id, data }) => adjustLeaveBalance(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'leave', 'balances'] });
            setAdjustDialog({ open: false, employee: null });
            setAdjustForm({ leave_type_id: '', days: '', type: 'add', reason: '' });
        },
    });

    const balances = data?.data || [];
    const pagination = data?.meta || data || {};
    const lastPage = pagination.last_page || 1;
    const leaveTypes = leaveTypesData?.data || [];
    const departments = departmentsData?.data || [];

    const resetPage = useCallback(() => setPage(1), []);

    function handleSearchChange(value) {
        setSearch(value);
        resetPage();
    }

    function clearFilters() {
        setSearch('');
        setDepartmentFilter('all');
        resetPage();
    }

    async function handleViewDetail(employee) {
        setDetailDialog({ open: true, employee, loading: true, data: null });
        try {
            const result = await fetchEmployeeLeaveBalance(employee.id, { year: yearFilter });
            setDetailDialog({ open: true, employee, loading: false, data: result.data || result });
        } catch {
            setDetailDialog({ open: true, employee, loading: false, data: null });
        }
    }

    function handleInitialize() {
        initMutation.mutate({ year: initYear });
    }

    function handleAdjust() {
        const days = adjustForm.type === 'deduct' ? -Math.abs(Number(adjustForm.days)) : Math.abs(Number(adjustForm.days));
        adjustMutation.mutate({
            id: adjustDialog.employee.id,
            data: {
                leave_type_id: adjustForm.leave_type_id,
                days,
                reason: adjustForm.reason,
                year: yearFilter,
            },
        });
    }

    async function handleExport() {
        setExporting(true);
        try {
            const blob = await exportLeaveBalances({
                department_id: departmentFilter !== 'all' ? departmentFilter : undefined,
                year: yearFilter,
            });
            const url = window.URL.createObjectURL(new Blob([blob]));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', `leave-balances-${yearFilter}.csv`);
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);
        } finally {
            setExporting(false);
        }
    }

    const yearOptions = [];
    for (let y = currentYear + 1; y >= currentYear - 3; y--) {
        yearOptions.push(String(y));
    }

    return (
        <div>
            <PageHeader
                title="Leave Balances"
                description="View and manage employee leave balances."
                action={
                    <div className="flex gap-2">
                        <Button variant="outline" onClick={() => setInitDialog(true)}>
                            <RefreshCw className="mr-1.5 h-4 w-4" />
                            Initialize Year
                        </Button>
                        <Button variant="outline" onClick={handleExport} disabled={exporting}>
                            {exporting ? (
                                <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />
                            ) : (
                                <Download className="mr-1.5 h-4 w-4" />
                            )}
                            Export CSV
                        </Button>
                    </div>
                }
            />

            <Card>
                <CardContent className="p-6">
                    <div className="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center">
                        <SearchInput
                            value={search}
                            onChange={handleSearchChange}
                            placeholder="Search employee..."
                            className="w-full lg:w-64"
                        />
                        <Select value={departmentFilter} onValueChange={(v) => { setDepartmentFilter(v); resetPage(); }}>
                            <SelectTrigger className="w-full lg:w-44">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Departments</SelectItem>
                                {departments.map((dept) => (
                                    <SelectItem key={dept.id} value={String(dept.id)}>{dept.name}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select value={yearFilter} onValueChange={(v) => { setYearFilter(v); resetPage(); }}>
                            <SelectTrigger className="w-full lg:w-32">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {yearOptions.map((y) => (
                                    <SelectItem key={y} value={y}>{y}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {isLoading ? (
                        <SkeletonTable />
                    ) : balances.length === 0 ? (
                        <EmptyState hasFilters={hasFilters} onClearFilters={clearFilters} />
                    ) : (
                        <>
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Employee</TableHead>
                                            <TableHead>Department</TableHead>
                                            {leaveTypes.map((lt) => (
                                                <TableHead key={lt.id} className="text-center">
                                                    <span className="text-xs">{lt.code || lt.name}</span>
                                                </TableHead>
                                            ))}
                                            <TableHead className="text-right">Actions</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {balances.map((employee) => (
                                            <TableRow key={employee.id}>
                                                <TableCell className="font-medium">{employee.full_name}</TableCell>
                                                <TableCell className="text-sm text-zinc-500">
                                                    {employee.department?.name || '-'}
                                                </TableCell>
                                                {leaveTypes.map((lt) => {
                                                    const balance = employee.balances?.find(
                                                        (b) => b.leave_type_id === lt.id
                                                    );
                                                    const used = balance?.used ?? 0;
                                                    const total = balance?.entitled ?? 0;
                                                    const remaining = total - used;
                                                    return (
                                                        <TableCell key={lt.id} className="text-center">
                                                            <span className={cn(
                                                                'text-sm font-medium',
                                                                remaining <= 0 ? 'text-red-600' : remaining <= 3 ? 'text-amber-600' : 'text-zinc-900'
                                                            )}>
                                                                {used}
                                                            </span>
                                                            <span className="text-xs text-zinc-400">/{total}</span>
                                                        </TableCell>
                                                    );
                                                })}
                                                <TableCell className="text-right">
                                                    <div className="flex items-center justify-end gap-1">
                                                        <Button variant="ghost" size="sm" onClick={() => handleViewDetail(employee)}>
                                                            <Eye className="h-4 w-4" />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => {
                                                                setAdjustDialog({ open: true, employee });
                                                                setAdjustForm({ leave_type_id: '', days: '', type: 'add', reason: '' });
                                                            }}
                                                        >
                                                            <Plus className="h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>

                            {lastPage > 1 && (
                                <div className="mt-4 flex items-center justify-between">
                                    <p className="text-sm text-zinc-500">
                                        Page {page} of {lastPage} ({pagination.total || 0} total)
                                    </p>
                                    <div className="flex gap-1">
                                        <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage((p) => Math.max(1, p - 1))}>
                                            <ChevronLeft className="h-4 w-4" />
                                        </Button>
                                        <Button variant="outline" size="sm" disabled={page >= lastPage} onClick={() => setPage((p) => p + 1)}>
                                            <ChevronRight className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </>
                    )}
                </CardContent>
            </Card>

            <Dialog open={detailDialog.open} onOpenChange={() => setDetailDialog({ open: false, employee: null, loading: false, data: null })}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Leave Balance Detail</DialogTitle>
                        <DialogDescription>
                            {detailDialog.employee?.full_name} - {yearFilter}
                        </DialogDescription>
                    </DialogHeader>
                    {detailDialog.loading ? (
                        <div className="flex items-center justify-center py-8">
                            <Loader2 className="h-6 w-6 animate-spin text-zinc-400" />
                        </div>
                    ) : detailDialog.data ? (
                        <div className="space-y-3">
                            {(detailDialog.data.balances || []).map((balance) => (
                                <div key={balance.leave_type_id} className="flex items-center justify-between rounded-lg border border-zinc-200 p-3">
                                    <div>
                                        <p className="text-sm font-medium text-zinc-900">{balance.leave_type_name || balance.leave_type?.name}</p>
                                        <p className="text-xs text-zinc-400">
                                            Carry forward: {balance.carry_forward ?? 0}
                                        </p>
                                    </div>
                                    <div className="text-right">
                                        <p className="text-sm font-semibold">
                                            <span className={cn(
                                                (balance.entitled - balance.used) <= 0 ? 'text-red-600' : 'text-emerald-600'
                                            )}>
                                                {balance.entitled - balance.used}
                                            </span>
                                            <span className="text-zinc-400"> remaining</span>
                                        </p>
                                        <p className="text-xs text-zinc-500">
                                            {balance.used} used / {balance.entitled} entitled
                                        </p>
                                    </div>
                                </div>
                            ))}
                            {(!detailDialog.data.balances || detailDialog.data.balances.length === 0) && (
                                <p className="py-4 text-center text-sm text-zinc-400">No balance records found.</p>
                            )}
                        </div>
                    ) : (
                        <p className="py-4 text-center text-sm text-zinc-400">Unable to load balance details.</p>
                    )}
                </DialogContent>
            </Dialog>

            <Dialog open={initDialog} onOpenChange={setInitDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Initialize Year Balances</DialogTitle>
                        <DialogDescription>
                            This will initialize leave balances for all employees based on entitlement rules.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-3">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-zinc-700">Year</label>
                            <Select value={initYear} onValueChange={setInitYear}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {yearOptions.map((y) => (
                                        <SelectItem key={y} value={y}>{y}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setInitDialog(false)}>Cancel</Button>
                        <Button onClick={handleInitialize} disabled={initMutation.isPending}>
                            {initMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                            Initialize
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={adjustDialog.open} onOpenChange={() => setAdjustDialog({ open: false, employee: null })}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Adjust Leave Balance</DialogTitle>
                        <DialogDescription>
                            Manually adjust balance for {adjustDialog.employee?.full_name}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-3">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-zinc-700">Leave Type</label>
                            <Select
                                value={adjustForm.leave_type_id}
                                onValueChange={(v) => setAdjustForm({ ...adjustForm, leave_type_id: v })}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select leave type" />
                                </SelectTrigger>
                                <SelectContent>
                                    {leaveTypes.map((lt) => (
                                        <SelectItem key={lt.id} value={String(lt.id)}>{lt.name}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="flex gap-3">
                            <div className="flex-1">
                                <label className="mb-1 block text-sm font-medium text-zinc-700">Type</label>
                                <Select
                                    value={adjustForm.type}
                                    onValueChange={(v) => setAdjustForm({ ...adjustForm, type: v })}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="add">Add Days</SelectItem>
                                        <SelectItem value="deduct">Deduct Days</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="flex-1">
                                <label className="mb-1 block text-sm font-medium text-zinc-700">Days</label>
                                <Input
                                    type="number"
                                    min="0.5"
                                    step="0.5"
                                    value={adjustForm.days}
                                    onChange={(e) => setAdjustForm({ ...adjustForm, days: e.target.value })}
                                    placeholder="0"
                                />
                            </div>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-zinc-700">Reason</label>
                            <textarea
                                value={adjustForm.reason}
                                onChange={(e) => setAdjustForm({ ...adjustForm, reason: e.target.value })}
                                placeholder="Reason for adjustment..."
                                className="w-full rounded-lg border border-zinc-300 p-3 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                rows={2}
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setAdjustDialog({ open: false, employee: null })}>Cancel</Button>
                        <Button
                            onClick={handleAdjust}
                            disabled={!adjustForm.leave_type_id || !adjustForm.days || adjustMutation.isPending}
                        >
                            {adjustMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                            {adjustForm.type === 'add' ? 'Add' : 'Deduct'} Days
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
