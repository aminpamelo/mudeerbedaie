import { useState, useCallback } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Download,
    Eye,
    ThumbsUp,
    ThumbsDown,
    Paperclip,
    ChevronLeft,
    ChevronRight,
    Loader2,
    FileText,
    Filter,
} from 'lucide-react';
import {
    fetchLeaveRequests,
    fetchLeaveRequest,
    approveLeaveRequest,
    rejectLeaveRequest,
    exportLeaveRequests,
    fetchLeaveTypes,
    fetchDepartments,
} from '../../lib/api';
import { cn } from '../../lib/utils';
import PageHeader from '../../components/PageHeader';
import SearchInput from '../../components/SearchInput';
import { Button } from '../../components/ui/button';
import { Card, CardContent } from '../../components/ui/card';
import { Badge } from '../../components/ui/badge';
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

const STATUS_OPTIONS = [
    { value: 'all', label: 'All Status' },
    { value: 'pending', label: 'Pending' },
    { value: 'approved', label: 'Approved' },
    { value: 'rejected', label: 'Rejected' },
    { value: 'cancelled', label: 'Cancelled' },
];

const STATUS_BADGE = {
    pending: { variant: 'warning', label: 'Pending' },
    approved: { variant: 'success', label: 'Approved' },
    rejected: { variant: 'destructive', label: 'Rejected' },
    cancelled: { variant: 'secondary', label: 'Cancelled' },
};

function formatDate(dateString) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('en-MY', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function SkeletonTable() {
    return (
        <div className="space-y-3">
            {Array.from({ length: 8 }).map((_, i) => (
                <div key={i} className="flex items-center gap-4 px-4 py-3">
                    <div className="h-4 w-32 animate-pulse rounded bg-zinc-200" />
                    <div className="h-4 w-20 animate-pulse rounded bg-zinc-200" />
                    <div className="h-4 w-36 animate-pulse rounded bg-zinc-200" />
                    <div className="h-4 w-12 animate-pulse rounded bg-zinc-200" />
                    <div className="flex-1" />
                    <div className="h-6 w-16 animate-pulse rounded-full bg-zinc-200" />
                    <div className="h-8 w-20 animate-pulse rounded bg-zinc-200" />
                </div>
            ))}
        </div>
    );
}

function EmptyState({ hasFilters, onClearFilters }) {
    return (
        <div className="flex flex-col items-center justify-center py-16 text-center">
            <FileText className="mb-4 h-12 w-12 text-zinc-300" />
            <h3 className="text-lg font-semibold text-zinc-900">
                {hasFilters ? 'No leave requests found' : 'No leave requests yet'}
            </h3>
            <p className="mt-1 text-sm text-zinc-500">
                {hasFilters
                    ? 'Try adjusting your filters to find what you are looking for.'
                    : 'Leave requests from employees will appear here.'}
            </p>
            {hasFilters && (
                <Button variant="outline" className="mt-4" onClick={onClearFilters}>
                    Clear Filters
                </Button>
            )}
        </div>
    );
}

export default function LeaveRequests() {
    const queryClient = useQueryClient();
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [typeFilter, setTypeFilter] = useState('all');
    const [departmentFilter, setDepartmentFilter] = useState('all');
    const [startDate, setStartDate] = useState('');
    const [endDate, setEndDate] = useState('');
    const [page, setPage] = useState(1);
    const [selectedRequest, setSelectedRequest] = useState(null);
    const [detailLoading, setDetailLoading] = useState(false);
    const [actionDialog, setActionDialog] = useState({ open: false, type: null, request: null });
    const [rejectReason, setRejectReason] = useState('');
    const [exporting, setExporting] = useState(false);

    const hasFilters =
        search !== '' ||
        statusFilter !== 'all' ||
        typeFilter !== 'all' ||
        departmentFilter !== 'all' ||
        startDate !== '' ||
        endDate !== '';

    const { data, isLoading } = useQuery({
        queryKey: [
            'hr', 'leave', 'requests',
            { search, status: statusFilter, type: typeFilter, department: departmentFilter, startDate, endDate, page },
        ],
        queryFn: () =>
            fetchLeaveRequests({
                search,
                status: statusFilter !== 'all' ? statusFilter : undefined,
                leave_type_id: typeFilter !== 'all' ? typeFilter : undefined,
                department_id: departmentFilter !== 'all' ? departmentFilter : undefined,
                start_date: startDate || undefined,
                end_date: endDate || undefined,
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

    const approveMutation = useMutation({
        mutationFn: ({ id }) => approveLeaveRequest(id, {}),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'leave'] });
            setActionDialog({ open: false, type: null, request: null });
        },
    });

    const rejectMutation = useMutation({
        mutationFn: ({ id, reason }) => rejectLeaveRequest(id, { reason }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'leave'] });
            setActionDialog({ open: false, type: null, request: null });
            setRejectReason('');
        },
    });

    const requests = data?.data || [];
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
        setStatusFilter('all');
        setTypeFilter('all');
        setDepartmentFilter('all');
        setStartDate('');
        setEndDate('');
        resetPage();
    }

    async function handleViewRequest(request) {
        setDetailLoading(true);
        try {
            const detail = await fetchLeaveRequest(request.id);
            setSelectedRequest(detail.data || detail);
        } catch {
            setSelectedRequest(request);
        } finally {
            setDetailLoading(false);
        }
    }

    function handleAction(type, request) {
        setActionDialog({ open: true, type, request });
        setRejectReason('');
    }

    function confirmAction() {
        const { type, request } = actionDialog;
        if (type === 'approve') {
            approveMutation.mutate({ id: request.id });
        } else {
            rejectMutation.mutate({ id: request.id, reason: rejectReason });
        }
    }

    async function handleExport() {
        setExporting(true);
        try {
            const blob = await exportLeaveRequests({
                status: statusFilter !== 'all' ? statusFilter : undefined,
                leave_type_id: typeFilter !== 'all' ? typeFilter : undefined,
                department_id: departmentFilter !== 'all' ? departmentFilter : undefined,
                start_date: startDate || undefined,
                end_date: endDate || undefined,
            });
            const url = window.URL.createObjectURL(new Blob([blob]));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', 'leave-requests.csv');
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);
        } finally {
            setExporting(false);
        }
    }

    return (
        <div>
            <PageHeader
                title="Leave Requests"
                description="Manage and review all employee leave requests."
                action={
                    <Button variant="outline" onClick={handleExport} disabled={exporting}>
                        {exporting ? (
                            <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />
                        ) : (
                            <Download className="mr-1.5 h-4 w-4" />
                        )}
                        Export CSV
                    </Button>
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
                        <Select value={statusFilter} onValueChange={(v) => { setStatusFilter(v); resetPage(); }}>
                            <SelectTrigger className="w-full lg:w-40">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {STATUS_OPTIONS.map((opt) => (
                                    <SelectItem key={opt.value} value={opt.value}>{opt.label}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select value={typeFilter} onValueChange={(v) => { setTypeFilter(v); resetPage(); }}>
                            <SelectTrigger className="w-full lg:w-40">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Types</SelectItem>
                                {leaveTypes.map((lt) => (
                                    <SelectItem key={lt.id} value={String(lt.id)}>{lt.name}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
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
                        <div className="flex items-center gap-2">
                            <input
                                type="date"
                                value={startDate}
                                onChange={(e) => { setStartDate(e.target.value); resetPage(); }}
                                className="rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                            />
                            <span className="text-zinc-400">to</span>
                            <input
                                type="date"
                                value={endDate}
                                onChange={(e) => { setEndDate(e.target.value); resetPage(); }}
                                className="rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                            />
                        </div>
                        {hasFilters && (
                            <Button variant="ghost" size="sm" onClick={clearFilters}>
                                <Filter className="mr-1 h-4 w-4" />
                                Clear
                            </Button>
                        )}
                    </div>

                    {isLoading ? (
                        <SkeletonTable />
                    ) : requests.length === 0 ? (
                        <EmptyState hasFilters={hasFilters} onClearFilters={clearFilters} />
                    ) : (
                        <>
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Employee</TableHead>
                                            <TableHead>Type</TableHead>
                                            <TableHead>Start - End</TableHead>
                                            <TableHead>Days</TableHead>
                                            <TableHead>Half Day</TableHead>
                                            <TableHead>Attachment</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead>Approved By</TableHead>
                                            <TableHead className="text-right">Actions</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {requests.map((request) => {
                                            const statusCfg = STATUS_BADGE[request.status] || { variant: 'secondary', label: request.status };
                                            return (
                                                <TableRow
                                                    key={request.id}
                                                    className="cursor-pointer hover:bg-zinc-50"
                                                    onClick={() => handleViewRequest(request)}
                                                >
                                                    <TableCell className="font-medium">
                                                        {request.employee?.full_name || '-'}
                                                    </TableCell>
                                                    <TableCell>
                                                        <Badge
                                                            variant="outline"
                                                            className="border-transparent"
                                                            style={{
                                                                backgroundColor: request.leave_type?.color
                                                                    ? `${request.leave_type.color}20`
                                                                    : undefined,
                                                                color: request.leave_type?.color || undefined,
                                                            }}
                                                        >
                                                            {request.leave_type?.name || '-'}
                                                        </Badge>
                                                    </TableCell>
                                                    <TableCell className="text-sm text-zinc-500">
                                                        {formatDate(request.start_date)} - {formatDate(request.end_date)}
                                                    </TableCell>
                                                    <TableCell>{request.total_days ?? '-'}</TableCell>
                                                    <TableCell>{request.half_day ? request.half_day_period || 'Yes' : '-'}</TableCell>
                                                    <TableCell>
                                                        {request.attachment_url ? (
                                                            <Paperclip className="h-4 w-4 text-zinc-400" />
                                                        ) : (
                                                            '-'
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        <Badge variant={statusCfg.variant}>{statusCfg.label}</Badge>
                                                    </TableCell>
                                                    <TableCell className="text-sm text-zinc-500">
                                                        {request.approved_by?.full_name || '-'}
                                                    </TableCell>
                                                    <TableCell className="text-right" onClick={(e) => e.stopPropagation()}>
                                                        <div className="flex items-center justify-end gap-1">
                                                            <Button variant="ghost" size="sm" onClick={() => handleViewRequest(request)}>
                                                                <Eye className="h-4 w-4" />
                                                            </Button>
                                                            {request.status === 'pending' && (
                                                                <>
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="sm"
                                                                        className="text-emerald-600 hover:text-emerald-700"
                                                                        onClick={() => handleAction('approve', request)}
                                                                    >
                                                                        <ThumbsUp className="h-4 w-4" />
                                                                    </Button>
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="sm"
                                                                        className="text-red-600 hover:text-red-700"
                                                                        onClick={() => handleAction('reject', request)}
                                                                    >
                                                                        <ThumbsDown className="h-4 w-4" />
                                                                    </Button>
                                                                </>
                                                            )}
                                                        </div>
                                                    </TableCell>
                                                </TableRow>
                                            );
                                        })}
                                    </TableBody>
                                </Table>
                            </div>

                            {lastPage > 1 && (
                                <div className="mt-4 flex items-center justify-between">
                                    <p className="text-sm text-zinc-500">
                                        Page {page} of {lastPage} ({pagination.total || 0} total)
                                    </p>
                                    <div className="flex gap-1">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            disabled={page <= 1}
                                            onClick={() => setPage((p) => Math.max(1, p - 1))}
                                        >
                                            <ChevronLeft className="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            disabled={page >= lastPage}
                                            onClick={() => setPage((p) => p + 1)}
                                        >
                                            <ChevronRight className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </>
                    )}
                </CardContent>
            </Card>

            <Dialog open={!!selectedRequest} onOpenChange={() => setSelectedRequest(null)}>
                <DialogContent className="max-w-xl">
                    <DialogHeader>
                        <DialogTitle>Leave Request Detail</DialogTitle>
                        <DialogDescription>Full details for this leave request.</DialogDescription>
                    </DialogHeader>
                    {detailLoading ? (
                        <div className="flex items-center justify-center py-8">
                            <Loader2 className="h-6 w-6 animate-spin text-zinc-400" />
                        </div>
                    ) : selectedRequest ? (
                        <div className="space-y-3 text-sm">
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <span className="text-zinc-500">Employee</span>
                                    <p className="font-medium">{selectedRequest.employee?.full_name}</p>
                                </div>
                                <div>
                                    <span className="text-zinc-500">Department</span>
                                    <p className="font-medium">{selectedRequest.employee?.department?.name || '-'}</p>
                                </div>
                                <div>
                                    <span className="text-zinc-500">Leave Type</span>
                                    <p className="font-medium">{selectedRequest.leave_type?.name}</p>
                                </div>
                                <div>
                                    <span className="text-zinc-500">Status</span>
                                    <div className="mt-0.5">
                                        <Badge variant={STATUS_BADGE[selectedRequest.status]?.variant || 'secondary'}>
                                            {STATUS_BADGE[selectedRequest.status]?.label || selectedRequest.status}
                                        </Badge>
                                    </div>
                                </div>
                                <div>
                                    <span className="text-zinc-500">Period</span>
                                    <p className="font-medium">
                                        {formatDate(selectedRequest.start_date)} - {formatDate(selectedRequest.end_date)}
                                    </p>
                                </div>
                                <div>
                                    <span className="text-zinc-500">Total Days</span>
                                    <p className="font-medium">{selectedRequest.total_days}</p>
                                </div>
                                {selectedRequest.half_day && (
                                    <div>
                                        <span className="text-zinc-500">Half Day</span>
                                        <p className="font-medium">{selectedRequest.half_day_period || 'Yes'}</p>
                                    </div>
                                )}
                                {selectedRequest.approved_by && (
                                    <div>
                                        <span className="text-zinc-500">Approved By</span>
                                        <p className="font-medium">{selectedRequest.approved_by.full_name}</p>
                                    </div>
                                )}
                            </div>
                            {selectedRequest.reason && (
                                <div>
                                    <span className="text-zinc-500">Reason</span>
                                    <p className="mt-1 rounded-lg bg-zinc-50 p-3 text-zinc-700">{selectedRequest.reason}</p>
                                </div>
                            )}
                            {selectedRequest.rejection_reason && (
                                <div>
                                    <span className="text-zinc-500">Rejection Reason</span>
                                    <p className="mt-1 rounded-lg bg-red-50 p-3 text-red-700">{selectedRequest.rejection_reason}</p>
                                </div>
                            )}
                            {selectedRequest.attachment_url && (
                                <div>
                                    <span className="text-zinc-500">Attachment</span>
                                    <a
                                        href={selectedRequest.attachment_url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="mt-1 flex items-center gap-1 text-blue-600 hover:underline"
                                    >
                                        <Paperclip className="h-4 w-4" />
                                        View Attachment
                                    </a>
                                </div>
                            )}
                        </div>
                    ) : null}
                </DialogContent>
            </Dialog>

            <Dialog open={actionDialog.open} onOpenChange={() => setActionDialog({ open: false, type: null, request: null })}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {actionDialog.type === 'approve' ? 'Approve' : 'Reject'} Leave Request
                        </DialogTitle>
                        <DialogDescription>
                            {actionDialog.type === 'approve'
                                ? 'Confirm approval of this leave request.'
                                : 'Provide a reason for rejection.'}
                        </DialogDescription>
                    </DialogHeader>
                    {actionDialog.request && (
                        <div className="space-y-3">
                            <div className="rounded-lg bg-zinc-50 p-3 text-sm">
                                <p className="font-medium">{actionDialog.request.employee?.full_name}</p>
                                <p className="text-zinc-500">
                                    {actionDialog.request.leave_type?.name} &middot;{' '}
                                    {formatDate(actionDialog.request.start_date)} - {formatDate(actionDialog.request.end_date)}
                                    {' '}&middot; {actionDialog.request.total_days} day(s)
                                </p>
                            </div>
                            {actionDialog.type === 'reject' && (
                                <textarea
                                    value={rejectReason}
                                    onChange={(e) => setRejectReason(e.target.value)}
                                    placeholder="Reason for rejection..."
                                    className="w-full rounded-lg border border-zinc-300 p-3 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                    rows={3}
                                />
                            )}
                        </div>
                    )}
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setActionDialog({ open: false, type: null, request: null })}>
                            Cancel
                        </Button>
                        <Button
                            variant={actionDialog.type === 'approve' ? 'default' : 'destructive'}
                            onClick={confirmAction}
                            disabled={approveMutation.isPending || rejectMutation.isPending}
                        >
                            {(approveMutation.isPending || rejectMutation.isPending) && (
                                <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />
                            )}
                            {actionDialog.type === 'approve' ? 'Approve' : 'Reject'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
