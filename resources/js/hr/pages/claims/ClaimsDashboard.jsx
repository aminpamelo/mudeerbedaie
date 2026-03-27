import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Clock,
    CheckCircle,
    DollarSign,
    FileText,
    ThumbsUp,
    ThumbsDown,
    Eye,
    Loader2,
} from 'lucide-react';
import {
    fetchClaimsDashboardStats,
    fetchPendingClaimRequests,
    fetchClaimsDistribution,
    approveClaimRequest,
    rejectClaimRequest,
} from '../../lib/api';
import { cn } from '../../lib/utils';
import PageHeader from '../../components/PageHeader';
import { Button } from '../../components/ui/button';
import { Card, CardContent } from '../../components/ui/card';
import { Badge } from '../../components/ui/badge';
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

const STAT_CARDS = [
    { key: 'pending', label: 'Pending Requests', icon: Clock, color: 'text-amber-600', bg: 'bg-amber-50' },
    { key: 'approved_this_month', label: 'Approved This Month', icon: CheckCircle, color: 'text-emerald-600', bg: 'bg-emerald-50' },
    { key: 'total_amount_this_month', label: 'Total Amount (Month)', icon: DollarSign, color: 'text-blue-600', bg: 'bg-blue-50', isCurrency: true },
    { key: 'pending_amount', label: 'Pending Amount', icon: FileText, color: 'text-purple-600', bg: 'bg-purple-50', isCurrency: true },
];

const PIE_COLORS = ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316'];

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

const STATUS_BADGE_CLASS = {
    draft: 'bg-zinc-100 text-zinc-600',
    pending: 'bg-amber-100 text-amber-700',
    approved: 'bg-emerald-100 text-emerald-700',
    rejected: 'bg-red-100 text-red-700',
    paid: 'bg-blue-100 text-blue-700',
};

function SkeletonCards() {
    return (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {Array.from({ length: 4 }).map((_, i) => (
                <Card key={i}>
                    <CardContent className="p-6">
                        <div className="flex items-center gap-4">
                            <div className="h-12 w-12 animate-pulse rounded-lg bg-zinc-200" />
                            <div className="flex-1 space-y-2">
                                <div className="h-3 w-24 animate-pulse rounded bg-zinc-200" />
                                <div className="h-6 w-12 animate-pulse rounded bg-zinc-200" />
                            </div>
                        </div>
                    </CardContent>
                </Card>
            ))}
        </div>
    );
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

function SimplePieChart({ data }) {
    if (!data || data.length === 0) {
        return (
            <div className="flex h-48 items-center justify-center text-sm text-zinc-400">
                No claim data available
            </div>
        );
    }

    const total = data.reduce((sum, d) => sum + (d.value || 0), 0);
    let cumulativeAngle = 0;

    return (
        <div className="flex items-center gap-6">
            <svg viewBox="0 0 100 100" className="h-48 w-48 shrink-0">
                {data.map((item, i) => {
                    const angle = total > 0 ? (item.value / total) * 360 : 0;
                    const startAngle = cumulativeAngle;
                    cumulativeAngle += angle;

                    const startRad = ((startAngle - 90) * Math.PI) / 180;
                    const endRad = ((startAngle + angle - 90) * Math.PI) / 180;

                    const x1 = 50 + 40 * Math.cos(startRad);
                    const y1 = 50 + 40 * Math.sin(startRad);
                    const x2 = 50 + 40 * Math.cos(endRad);
                    const y2 = 50 + 40 * Math.sin(endRad);

                    const largeArc = angle > 180 ? 1 : 0;

                    if (angle === 0) return null;

                    if (angle >= 359.99) {
                        return (
                            <circle
                                key={i}
                                cx="50"
                                cy="50"
                                r="40"
                                fill={PIE_COLORS[i % PIE_COLORS.length]}
                            />
                        );
                    }

                    return (
                        <path
                            key={i}
                            d={`M 50 50 L ${x1} ${y1} A 40 40 0 ${largeArc} 1 ${x2} ${y2} Z`}
                            fill={PIE_COLORS[i % PIE_COLORS.length]}
                        />
                    );
                })}
            </svg>
            <div className="flex flex-col gap-2">
                {data.map((item, i) => (
                    <div key={i} className="flex items-center gap-2 text-sm">
                        <div
                            className="h-3 w-3 rounded-full"
                            style={{ backgroundColor: PIE_COLORS[i % PIE_COLORS.length] }}
                        />
                        <span className="text-zinc-600">{item.name}</span>
                        <span className="font-medium text-zinc-900">{item.value}</span>
                    </div>
                ))}
            </div>
        </div>
    );
}

export default function ClaimsDashboard() {
    const queryClient = useQueryClient();
    const [selectedRequest, setSelectedRequest] = useState(null);
    const [rejectReason, setRejectReason] = useState('');
    const [actionDialog, setActionDialog] = useState({ open: false, type: null, request: null });

    const { data: stats, isLoading: statsLoading } = useQuery({
        queryKey: ['hr', 'claims', 'dashboard', 'stats'],
        queryFn: fetchClaimsDashboardStats,
    });

    const { data: pendingData, isLoading: pendingLoading } = useQuery({
        queryKey: ['hr', 'claims', 'dashboard', 'pending'],
        queryFn: () => fetchPendingClaimRequests({ per_page: 10 }),
    });

    const { data: distributionData } = useQuery({
        queryKey: ['hr', 'claims', 'dashboard', 'distribution'],
        queryFn: fetchClaimsDistribution,
    });

    const approveMutation = useMutation({
        mutationFn: ({ id }) => approveClaimRequest(id, {}),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'claims'] });
            setActionDialog({ open: false, type: null, request: null });
        },
    });

    const rejectMutation = useMutation({
        mutationFn: ({ id, reason }) => rejectClaimRequest(id, { reason }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'claims'] });
            setActionDialog({ open: false, type: null, request: null });
            setRejectReason('');
        },
    });

    const pendingRequests = pendingData?.data || [];
    const distribution = distributionData?.data || [];

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

    return (
        <div>
            <PageHeader
                title="Claims Dashboard"
                description="Overview of expense claims, pending approvals, and spending trends."
            />

            {statsLoading ? (
                <SkeletonCards />
            ) : (
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {STAT_CARDS.map((card) => {
                        const Icon = card.icon;
                        const rawValue = stats?.[card.key] ?? 0;
                        const displayValue = card.isCurrency ? formatCurrency(rawValue) : rawValue;
                        return (
                            <Card key={card.key}>
                                <CardContent className="p-6">
                                    <div className="flex items-center gap-4">
                                        <div className={cn('flex h-12 w-12 items-center justify-center rounded-lg', card.bg)}>
                                            <Icon className={cn('h-6 w-6', card.color)} />
                                        </div>
                                        <div>
                                            <p className="text-sm text-zinc-500">{card.label}</p>
                                            <p className="text-2xl font-bold text-zinc-900">{displayValue}</p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>
            )}

            <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardContent className="p-6">
                        <h3 className="mb-4 text-lg font-semibold text-zinc-900">Pending Approvals</h3>
                        {pendingLoading ? (
                            <SkeletonTable />
                        ) : pendingRequests.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-12 text-center">
                                <CheckCircle className="mb-3 h-10 w-10 text-emerald-300" />
                                <p className="text-sm font-medium text-zinc-600">All caught up!</p>
                                <p className="mt-1 text-xs text-zinc-400">No pending claim requests.</p>
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Employee</TableHead>
                                        <TableHead>Type</TableHead>
                                        <TableHead>Amount</TableHead>
                                        <TableHead>Date</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {pendingRequests.map((request) => (
                                        <TableRow key={request.id}>
                                            <TableCell className="font-medium">
                                                {request.employee?.full_name || '-'}
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="outline">
                                                    {request.claim_type?.name || '-'}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="font-medium text-zinc-900">
                                                {formatCurrency(request.amount)}
                                            </TableCell>
                                            <TableCell className="text-sm text-zinc-500">
                                                {formatDate(request.claim_date)}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex items-center justify-end gap-1">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => setSelectedRequest(request)}
                                                    >
                                                        <Eye className="h-4 w-4" />
                                                    </Button>
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
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-6">
                        <h3 className="mb-4 text-lg font-semibold text-zinc-900">Claims by Type</h3>
                        <SimplePieChart data={distribution} />
                    </CardContent>
                </Card>
            </div>

            {/* Detail Dialog */}
            <Dialog open={!!selectedRequest} onOpenChange={() => setSelectedRequest(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Claim Request Detail</DialogTitle>
                        <DialogDescription>
                            Review the claim request details below.
                        </DialogDescription>
                    </DialogHeader>
                    {selectedRequest && (
                        <div className="space-y-3 text-sm">
                            <div className="flex justify-between">
                                <span className="text-zinc-500">Claim No.</span>
                                <span className="font-medium">{selectedRequest.claim_number}</span>
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
                            <div className="flex justify-between">
                                <span className="text-zinc-500">Date</span>
                                <span className="font-medium">{formatDate(selectedRequest.claim_date)}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-zinc-500">Status</span>
                                <span className={cn('rounded-full px-2 py-0.5 text-xs font-medium', STATUS_BADGE_CLASS[selectedRequest.status] || 'bg-zinc-100 text-zinc-600')}>
                                    {selectedRequest.status}
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
                        </div>
                    )}
                </DialogContent>
            </Dialog>

            {/* Action Dialog */}
            <Dialog open={actionDialog.open} onOpenChange={() => setActionDialog({ open: false, type: null, request: null })}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {actionDialog.type === 'approve' ? 'Approve' : 'Reject'} Claim Request
                        </DialogTitle>
                        <DialogDescription>
                            {actionDialog.type === 'approve'
                                ? 'Are you sure you want to approve this claim request?'
                                : 'Please provide a reason for rejecting this claim request.'}
                        </DialogDescription>
                    </DialogHeader>
                    {actionDialog.request && (
                        <div className="space-y-3">
                            <div className="rounded-lg bg-zinc-50 p-3 text-sm">
                                <p className="font-medium">{actionDialog.request.employee?.full_name}</p>
                                <p className="text-zinc-500">
                                    {actionDialog.request.claim_type?.name} &middot;{' '}
                                    {formatCurrency(actionDialog.request.amount)} &middot;{' '}
                                    {formatDate(actionDialog.request.claim_date)}
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
                        <Button
                            variant="outline"
                            onClick={() => setActionDialog({ open: false, type: null, request: null })}
                        >
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
