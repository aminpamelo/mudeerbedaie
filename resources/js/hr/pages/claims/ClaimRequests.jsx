import { useState, useCallback } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Download,
    Eye,
    ThumbsUp,
    ThumbsDown,
    ChevronLeft,
    ChevronRight,
    Loader2,
    FileText,
    Filter,
    CreditCard,
} from 'lucide-react';
import {
    fetchClaimRequests,
    fetchClaimRequest,
    approveClaimRequest,
    rejectClaimRequest,
    markClaimPaid,
    exportClaimRequests,
    fetchClaimTypes,
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
    { value: 'draft', label: 'Draft' },
    { value: 'pending', label: 'Pending' },
    { value: 'approved', label: 'Approved' },
    { value: 'rejected', label: 'Rejected' },
    { value: 'paid', label: 'Paid' },
];

const STATUS_BADGE = {
    draft: { className: 'bg-zinc-100 text-zinc-600', label: 'Draft' },
    pending: { className: 'bg-amber-100 text-amber-700', label: 'Pending' },
    approved: { className: 'bg-emerald-100 text-emerald-700', label: 'Approved' },
    rejected: { className: 'bg-red-100 text-red-700', label: 'Rejected' },
    paid: { className: 'bg-blue-100 text-blue-700', label: 'Paid' },
};

function formatDate(dateString) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('en-MY', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function formatCurrency(amount) {
    if (amount === null || amount === undefined) return '-';
    return new Intl.NumberFormat('en-MY', { style: 'currency', currency: 'MYR' }).format(amount);
}

function SkeletonTable() {
    return (
        <div className="space-y-3">
            {Array.from({ length: 5 }).map((_, i) => (
                <div key={i} className="flex items-center gap-4 px-4 py-3">
                    <div className="h-4 w-32 animate-pulse rounded bg-zinc-200" />
                    <div className="h-4 w-24 animate-pulse rounded bg-zinc-200" />
                    <div className="h-4 w-20 animate-pulse rounded bg-zinc-200" />
                    <div className="flex-1" />
                    <div className="h-8 w-16 animate-pulse rounded bg-zinc-200" />
                </div>
            ))}
        </div>
    );
}

export default function ClaimRequests() {
    const queryClient = useQueryClient();
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('all');
    const [claimTypeId, setClaimTypeId] = useState('all');
    const [page, setPage] = useState(1);
    const [selectedRequest, setSelectedRequest] = useState(null);
    const [detailLoading, setDetailLoading] = useState(false);
    const [rejectReason, setRejectReason] = useState('');
    const [payReference, setPayReference] = useState('');
    const [actionDialog, setActionDialog] = useState({ open: false, type: null, request: null });
    const [filtersOpen, setFiltersOpen] = useState(false);

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'claims', 'requests', { search, status, claimTypeId, page }],
        queryFn: () => fetchClaimRequests({
            search: search || undefined,
            status: status !== 'all' ? status : undefined,
            claim_type_id: claimTypeId !== 'all' ? claimTypeId : undefined,
            page,
            per_page: 15,
        }),
    });

    const { data: claimTypesData } = useQuery({
        queryKey: ['hr', 'claims', 'types', 'list'],
        queryFn: () => fetchClaimTypes({ per_page: 100 }),
    });

    const approveMutation = useMutation({
        mutationFn: ({ id }) => approveClaimRequest(id, {}),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'claims', 'requests'] });
            setActionDialog({ open: false, type: null, request: null });
        },
    });

    const rejectMutation = useMutation({
        mutationFn: ({ id, reason }) => rejectClaimRequest(id, { reason }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'claims', 'requests'] });
            setActionDialog({ open: false, type: null, request: null });
            setRejectReason('');
        },
    });

    const payMutation = useMutation({
        mutationFn: ({ id, reference }) => markClaimPaid(id, { reference }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'claims', 'requests'] });
            setActionDialog({ open: false, type: null, request: null });
            setPayReference('');
        },
    });

    const exportMutation = useMutation({
        mutationFn: () => exportClaimRequests({
            search: search || undefined,
            status: status !== 'all' ? status : undefined,
        }),
        onSuccess: (blob) => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `claim-requests-${new Date().toISOString().split('T')[0]}.csv`;
            a.click();
            window.URL.revokeObjectURL(url);
        },
    });

    const requests = data?.data || [];
    const meta = data?.meta || {};
    const claimTypes = claimTypesData?.data || [];

    const handleSearch = useCallback((val) => {
        setSearch(val);
        setPage(1);
    }, []);

    async function viewDetail(request) {
        setDetailLoading(true);
        try {
            const res = await fetchClaimRequest(request.id);
            setSelectedRequest(res.data || res);
        } catch {
            setSelectedRequest(request);
        } finally {
            setDetailLoading(false);
        }
    }

    function handleAction(type, request) {
        setActionDialog({ open: true, type, request });
        setRejectReason('');
        setPayReference('');
    }

    function confirmAction() {
        const { type, request } = actionDialog;
        if (type === 'approve') {
            approveMutation.mutate({ id: request.id });
        } else if (type === 'reject') {
            rejectMutation.mutate({ id: request.id, reason: rejectReason });
        } else if (type === 'pay') {
            payMutation.mutate({ id: request.id, reference: payReference });
        }
    }

    const isActionLoading = approveMutation.isPending || rejectMutation.isPending || payMutation.isPending;

    return (
        <div>
            <PageHeader
                title="Claim Requests"
                description="Review and manage employee expense claims."
            />

            <Card>
                <CardContent className="p-6">
                    {/* Toolbar */}
                    <div className="mb-4 flex flex-wrap items-center gap-3">
                        <div className="flex-1">
                            <SearchInput
                                value={search}
                                onChange={handleSearch}
                                placeholder="Search by employee, claim number..."
                            />
                        </div>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setFiltersOpen((p) => !p)}
                        >
                            <Filter className="mr-1.5 h-4 w-4" />
                            Filters
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => exportMutation.mutate()}
                            disabled={exportMutation.isPending}
                        >
                            {exportMutation.isPending ? (
                                <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />
                            ) : (
                                <Download className="mr-1.5 h-4 w-4" />
                            )}
                            Export
                        </Button>
                    </div>

                    {filtersOpen && (
                        <div className="mb-4 flex flex-wrap gap-3 rounded-lg border border-zinc-200 bg-zinc-50 p-4">
                            <div className="w-40">
                                <label className="mb-1 block text-xs font-medium text-zinc-600">Status</label>
                                <Select value={status} onValueChange={(v) => { setStatus(v); setPage(1); }}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {STATUS_OPTIONS.map((opt) => (
                                            <SelectItem key={opt.value} value={opt.value}>
                                                {opt.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="w-48">
                                <label className="mb-1 block text-xs font-medium text-zinc-600">Claim Type</label>
                                <Select value={claimTypeId} onValueChange={(v) => { setClaimTypeId(v); setPage(1); }}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="All Types" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Types</SelectItem>
                                        {claimTypes.map((t) => (
                                            <SelectItem key={t.id} value={String(t.id)}>
                                                {t.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    )}

                    {isLoading ? (
                        <SkeletonTable />
                    ) : requests.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-16 text-center">
                            <FileText className="mb-3 h-10 w-10 text-zinc-300" />
                            <p className="text-sm font-medium text-zinc-600">No claim requests found</p>
                            <p className="mt-1 text-xs text-zinc-400">Try adjusting your search or filters.</p>
                        </div>
                    ) : (
                        <>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Claim No.</TableHead>
                                        <TableHead>Employee</TableHead>
                                        <TableHead>Type</TableHead>
                                        <TableHead>Amount</TableHead>
                                        <TableHead>Date</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {requests.map((request) => {
                                        const badge = STATUS_BADGE[request.status] || { className: 'bg-zinc-100 text-zinc-600', label: request.status };
                                        return (
                                            <TableRow key={request.id}>
                                                <TableCell className="font-mono text-sm">
                                                    {request.claim_number}
                                                </TableCell>
                                                <TableCell className="font-medium">
                                                    {request.employee?.full_name || '-'}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant="outline">
                                                        {request.claim_type?.name || '-'}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="font-medium">
                                                    {formatCurrency(request.amount)}
                                                </TableCell>
                                                <TableCell className="text-sm text-zinc-500">
                                                    {formatDate(request.claim_date)}
                                                </TableCell>
                                                <TableCell>
                                                    <span className={cn('rounded-full px-2.5 py-0.5 text-xs font-medium', badge.className)}>
                                                        {badge.label}
                                                    </span>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <div className="flex items-center justify-end gap-1">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => viewDetail(request)}
                                                        >
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
                                                        {request.status === 'approved' && (
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                className="text-blue-600 hover:text-blue-700"
                                                                onClick={() => handleAction('pay', request)}
                                                            >
                                                                <CreditCard className="h-4 w-4" />
                                                            </Button>
                                                        )}
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>

                            {/* Pagination */}
                            {meta.last_page > 1 && (
                                <div className="mt-4 flex items-center justify-between text-sm text-zinc-500">
                                    <span>
                                        Showing {meta.from}–{meta.to} of {meta.total} results
                                    </span>
                                    <div className="flex items-center gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            disabled={page <= 1}
                                            onClick={() => setPage((p) => p - 1)}
                                        >
                                            <ChevronLeft className="h-4 w-4" />
                                        </Button>
                                        <span className="px-2">
                                            Page {meta.current_page} of {meta.last_page}
                                        </span>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            disabled={page >= meta.last_page}
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

            {/* Detail Dialog */}
            <Dialog open={!!selectedRequest} onOpenChange={() => setSelectedRequest(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Claim Request Detail</DialogTitle>
                        <DialogDescription>
                            Full details for claim {selectedRequest?.claim_number}.
                        </DialogDescription>
                    </DialogHeader>
                    {detailLoading ? (
                        <div className="flex justify-center py-8">
                            <Loader2 className="h-6 w-6 animate-spin text-zinc-400" />
                        </div>
                    ) : selectedRequest && (
                        <div className="space-y-3 text-sm">
                            <div className="flex justify-between">
                                <span className="text-zinc-500">Claim No.</span>
                                <span className="font-mono font-medium">{selectedRequest.claim_number}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-zinc-500">Employee</span>
                                <span className="font-medium">{selectedRequest.employee?.full_name}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-zinc-500">Type</span>
                                <span className="font-medium">{selectedRequest.claim_type?.name}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-zinc-500">Amount</span>
                                <span className="font-medium">{formatCurrency(selectedRequest.amount)}</span>
                            </div>
                            {selectedRequest.approved_amount && (
                                <div className="flex justify-between">
                                    <span className="text-zinc-500">Approved Amount</span>
                                    <span className="font-medium">{formatCurrency(selectedRequest.approved_amount)}</span>
                                </div>
                            )}
                            <div className="flex justify-between">
                                <span className="text-zinc-500">Date</span>
                                <span className="font-medium">{formatDate(selectedRequest.claim_date)}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-zinc-500">Status</span>
                                <span className={cn(
                                    'rounded-full px-2 py-0.5 text-xs font-medium',
                                    STATUS_BADGE[selectedRequest.status]?.className || 'bg-zinc-100 text-zinc-600'
                                )}>
                                    {STATUS_BADGE[selectedRequest.status]?.label || selectedRequest.status}
                                </span>
                            </div>
                            {selectedRequest.description && (
                                <div>
                                    <span className="text-zinc-500">Description</span>
                                    <p className="mt-1 rounded-lg bg-zinc-50 p-3 text-zinc-700">
                                        {selectedRequest.description}
                                    </p>
                                </div>
                            )}
                            {selectedRequest.rejected_reason && (
                                <div>
                                    <span className="text-zinc-500">Rejection Reason</span>
                                    <p className="mt-1 rounded-lg bg-red-50 p-3 text-red-700">
                                        {selectedRequest.rejected_reason}
                                    </p>
                                </div>
                            )}
                        </div>
                    )}
                </DialogContent>
            </Dialog>

            {/* Action Dialog */}
            <Dialog open={actionDialog.open} onOpenChange={() => setActionDialog({ open: false, type: null, request: null })}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {actionDialog.type === 'approve' && 'Approve Claim Request'}
                            {actionDialog.type === 'reject' && 'Reject Claim Request'}
                            {actionDialog.type === 'pay' && 'Mark as Paid'}
                        </DialogTitle>
                        <DialogDescription>
                            {actionDialog.type === 'approve' && 'Are you sure you want to approve this claim?'}
                            {actionDialog.type === 'reject' && 'Please provide a reason for rejection.'}
                            {actionDialog.type === 'pay' && 'Enter the payment reference number.'}
                        </DialogDescription>
                    </DialogHeader>
                    {actionDialog.request && (
                        <div className="space-y-3">
                            <div className="rounded-lg bg-zinc-50 p-3 text-sm">
                                <p className="font-medium">{actionDialog.request.employee?.full_name}</p>
                                <p className="text-zinc-500">
                                    {actionDialog.request.claim_type?.name} &middot;{' '}
                                    {formatCurrency(actionDialog.request.amount)} &middot;{' '}
                                    {actionDialog.request.claim_number}
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
                            {actionDialog.type === 'pay' && (
                                <input
                                    type="text"
                                    value={payReference}
                                    onChange={(e) => setPayReference(e.target.value)}
                                    placeholder="Payment reference number..."
                                    className="w-full rounded-lg border border-zinc-300 p-3 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                />
                            )}
                        </div>
                    )}
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setActionDialog({ open: false, type: null, request: null })}>
                            Cancel
                        </Button>
                        <Button
                            variant={actionDialog.type === 'reject' ? 'destructive' : 'default'}
                            onClick={confirmAction}
                            disabled={isActionLoading}
                        >
                            {isActionLoading && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                            {actionDialog.type === 'approve' && 'Approve'}
                            {actionDialog.type === 'reject' && 'Reject'}
                            {actionDialog.type === 'pay' && 'Mark as Paid'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
