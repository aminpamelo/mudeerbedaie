import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Clock,
    CheckCircle,
    XCircle,
    AlertCircle,
    Timer,
    Eye,
} from 'lucide-react';
import {
    Card,
    CardContent,
} from '../../components/ui/card';
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
import { Tabs, TabsList, TabsTrigger, TabsContent } from '../../components/ui/tabs';
import { Button } from '../../components/ui/button';
import { Input } from '../../components/ui/input';
import { Label } from '../../components/ui/label';
import { Badge } from '../../components/ui/badge';
import { Textarea } from '../../components/ui/textarea';
import PageHeader from '../../components/PageHeader';
import ConfirmDialog from '../../components/ConfirmDialog';
import { cn } from '../../lib/utils';
import {
    fetchOvertimeRequests,
    fetchOvertimeClaims,
    approveOvertime,
    rejectOvertime,
    completeOvertime,
    approveOvertimeClaim,
    rejectOvertimeClaim,
} from '../../lib/api';

const STATUS_CONFIG = {
    pending: { label: 'Pending', variant: 'warning', icon: AlertCircle },
    approved: { label: 'Approved', variant: 'success', icon: CheckCircle },
    rejected: { label: 'Rejected', variant: 'destructive', icon: XCircle },
    completed: { label: 'Completed', variant: 'secondary', icon: Timer },
};

function OTStatusBadge({ status }) {
    const config = STATUS_CONFIG[status] || { label: status, variant: 'secondary' };
    return <Badge variant={config.variant}>{config.label}</Badge>;
}

function formatDate(dateString) {
    if (!dateString) {
        return '-';
    }
    return new Date(dateString).toLocaleDateString('en-MY', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function formatHours(hours) {
    if (!hours && hours !== 0) {
        return '-';
    }
    return `${Number(hours).toFixed(1)}h`;
}

function formatDuration(minutes) {
    if (!minutes && minutes !== 0) {
        return '-';
    }
    const mins = Number(minutes);
    if (mins < 60) {
        return `${mins}min`;
    }
    const h = Math.floor(mins / 60);
    const m = mins % 60;
    return m > 0 ? `${h}h ${m}min` : `${h}h`;
}

function SkeletonTable() {
    return (
        <div className="space-y-3">
            {Array.from({ length: 6 }).map((_, i) => (
                <div key={i} className="flex items-center gap-4 px-4 py-3">
                    <div className="flex-1 space-y-2">
                        <div className="h-4 w-40 animate-pulse rounded bg-zinc-200" />
                        <div className="h-3 w-28 animate-pulse rounded bg-zinc-200" />
                    </div>
                    <div className="h-6 w-16 animate-pulse rounded-full bg-zinc-200" />
                </div>
            ))}
        </div>
    );
}

export default function OvertimeManagement() {
    const queryClient = useQueryClient();
    const [mainView, setMainView] = useState('overtime'); // 'overtime' | 'claims'
    const [activeTab, setActiveTab] = useState('all');
    const [claimsTab, setClaimsTab] = useState('all'); // 'all' | 'pending' | 'approved' | 'rejected' | 'cancelled'
    const [viewTarget, setViewTarget] = useState(null);
    const [rejectTarget, setRejectTarget] = useState(null);
    const [rejectReason, setRejectReason] = useState('');
    const [completeTarget, setCompleteTarget] = useState(null);
    const [actualHours, setActualHours] = useState('');
    const [claimRejectTarget, setClaimRejectTarget] = useState(null);
    const [claimRejectReason, setClaimRejectReason] = useState('');

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'attendance', 'overtime', activeTab],
        queryFn: () => fetchOvertimeRequests({ status: activeTab !== 'all' ? activeTab : undefined }),
    });

    const { data: claimsData, isLoading: claimsLoading } = useQuery({
        queryKey: ['hr', 'attendance', 'overtime-claims', claimsTab],
        queryFn: () => fetchOvertimeClaims({ status: claimsTab !== 'all' ? claimsTab : undefined }),
        enabled: mainView === 'claims',
    });

    const approveMutation = useMutation({
        mutationFn: approveOvertime,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'attendance', 'overtime'] });
        },
        onError: (error) => alert(error?.response?.data?.message || 'Failed to approve overtime request'),
    });

    const rejectMutation = useMutation({
        mutationFn: ({ id, reason }) => rejectOvertime(id, { reason }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'attendance', 'overtime'] });
            setRejectTarget(null);
            setRejectReason('');
        },
    });

    const completeMutation = useMutation({
        mutationFn: ({ id, actual_hours }) => completeOvertime(id, { actual_hours }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'attendance', 'overtime'] });
            setCompleteTarget(null);
            setActualHours('');
        },
    });

    const claimApproveMutation = useMutation({
        mutationFn: approveOvertimeClaim,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'attendance', 'overtime-claims'] });
        },
        onError: (error) => alert(error?.response?.data?.message || 'Failed to approve claim'),
    });

    const claimRejectMutation = useMutation({
        mutationFn: ({ id, rejection_reason }) => rejectOvertimeClaim(id, { rejection_reason }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'attendance', 'overtime-claims'] });
            setClaimRejectTarget(null);
            setClaimRejectReason('');
        },
        onError: (error) => alert(error?.response?.data?.message || 'Failed to reject claim'),
    });

    const requests = data?.data || [];

    function handleApprove(id) {
        approveMutation.mutate(id);
    }

    function openReject(request) {
        setRejectTarget(request);
        setRejectReason('');
    }

    function handleReject() {
        rejectMutation.mutate({ id: rejectTarget.id, reason: rejectReason });
    }

    function openComplete(request) {
        setCompleteTarget(request);
        setActualHours(String(request.planned_hours || ''));
    }

    function handleComplete() {
        completeMutation.mutate({ id: completeTarget.id, actual_hours: parseFloat(actualHours) });
    }

    const claims = claimsData?.data || [];

    return (
        <div className="space-y-6">
            <PageHeader
                title="Overtime Management"
                description="Review and manage overtime requests"
            />

            <div className="flex rounded-lg border border-zinc-200 p-0.5 bg-zinc-50 w-fit">
                <button
                    onClick={() => setMainView('overtime')}
                    className={cn(
                        'px-4 py-1.5 text-sm font-medium rounded-md transition-colors',
                        mainView === 'overtime'
                            ? 'bg-white text-zinc-900 shadow-sm'
                            : 'text-zinc-500 hover:text-zinc-700'
                    )}
                >
                    OT Requests
                </button>
                <button
                    onClick={() => setMainView('claims')}
                    className={cn(
                        'px-4 py-1.5 text-sm font-medium rounded-md transition-colors',
                        mainView === 'claims'
                            ? 'bg-white text-zinc-900 shadow-sm'
                            : 'text-zinc-500 hover:text-zinc-700'
                    )}
                >
                    OT Claims
                </button>
            </div>

            {mainView === 'claims' ? (
                <>
                <Tabs value={claimsTab} onValueChange={setClaimsTab}>
                    <TabsList>
                        <TabsTrigger value="all">All</TabsTrigger>
                        <TabsTrigger value="pending">Pending</TabsTrigger>
                        <TabsTrigger value="approved">Approved</TabsTrigger>
                        <TabsTrigger value="rejected">Rejected</TabsTrigger>
                        <TabsTrigger value="cancelled">Cancelled</TabsTrigger>
                    </TabsList>
                </Tabs>
                <Card>
                    <CardContent className="p-0">
                        {claimsLoading ? (
                            <SkeletonTable />
                        ) : claims.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-16 text-center">
                                <Clock className="mb-3 h-10 w-10 text-zinc-300" />
                                <p className="text-sm font-medium text-zinc-500">No claims found</p>
                                <p className="text-xs text-zinc-400">No overtime claims have been submitted</p>
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Employee</TableHead>
                                        <TableHead>Date</TableHead>
                                        <TableHead>Start Time</TableHead>
                                        <TableHead>Duration</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="w-28">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {claims.map((claim) => (
                                        <TableRow key={claim.id}>
                                            <TableCell>
                                                <div>
                                                    <p className="text-sm font-medium text-zinc-900">
                                                        {claim.employee?.full_name || 'Unknown'}
                                                    </p>
                                                    <p className="text-xs text-zinc-500">
                                                        {claim.employee?.department?.name || ''}
                                                    </p>
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-sm text-zinc-900">
                                                {formatDate(claim.claim_date)}
                                            </TableCell>
                                            <TableCell className="text-sm text-zinc-600">
                                                {claim.start_time ? claim.start_time.slice(0, 5) : '-'}
                                            </TableCell>
                                            <TableCell className="text-sm text-zinc-600">
                                                {formatDuration(claim.duration_minutes)}
                                            </TableCell>
                                            <TableCell>
                                                <OTStatusBadge status={claim.status} />
                                            </TableCell>
                                            <TableCell>
                                                {claim.status === 'pending' && (
                                                    <div className="flex items-center gap-1">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => claimApproveMutation.mutate(claim.id)}
                                                            disabled={claimApproveMutation.isPending}
                                                            className="text-emerald-600 hover:text-emerald-700"
                                                        >
                                                            <CheckCircle className="h-4 w-4" />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => { setClaimRejectTarget(claim); setClaimRejectReason(''); }}
                                                            className="text-red-500 hover:text-red-700"
                                                        >
                                                            <XCircle className="h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
                </>
            ) : null}

            {mainView === 'overtime' ? (
            <Tabs value={activeTab} onValueChange={setActiveTab}>
                <TabsList>
                    <TabsTrigger value="all">All</TabsTrigger>
                    <TabsTrigger value="pending">
                        Pending
                    </TabsTrigger>
                    <TabsTrigger value="approved">Approved</TabsTrigger>
                    <TabsTrigger value="completed">Completed</TabsTrigger>
                    <TabsTrigger value="rejected">Rejected</TabsTrigger>
                </TabsList>

                <TabsContent value={activeTab}>
                    <Card>
                        <CardContent className="p-0">
                            {isLoading ? (
                                <SkeletonTable />
                            ) : requests.length === 0 ? (
                                <div className="flex flex-col items-center justify-center py-16 text-center">
                                    <Clock className="mb-3 h-10 w-10 text-zinc-300" />
                                    <p className="text-sm font-medium text-zinc-500">No overtime requests</p>
                                    <p className="text-xs text-zinc-400">
                                        {activeTab === 'all'
                                            ? 'No overtime requests have been submitted'
                                            : `No ${activeTab} overtime requests`}
                                    </p>
                                </div>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Employee</TableHead>
                                            <TableHead>Date</TableHead>
                                            <TableHead>Planned Hours</TableHead>
                                            <TableHead>Actual Hours</TableHead>
                                            <TableHead>Reason</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead className="w-32">Actions</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {requests.map((request) => (
                                            <TableRow key={request.id}>
                                                <TableCell>
                                                    <div>
                                                        <p className="text-sm font-medium text-zinc-900">
                                                            {request.employee?.full_name || 'Unknown'}
                                                        </p>
                                                        <p className="text-xs text-zinc-500">
                                                            {request.employee?.department?.name || ''}
                                                        </p>
                                                    </div>
                                                </TableCell>
                                                <TableCell className="text-sm text-zinc-900">
                                                    {formatDate(request.requested_date)}
                                                </TableCell>
                                                <TableCell className="text-sm text-zinc-600">
                                                    {formatHours(request.planned_hours)}
                                                </TableCell>
                                                <TableCell className="text-sm text-zinc-600">
                                                    {formatHours(request.actual_hours)}
                                                </TableCell>
                                                <TableCell>
                                                    <p className="max-w-[200px] truncate text-sm text-zinc-600">
                                                        {request.reason || '-'}
                                                    </p>
                                                </TableCell>
                                                <TableCell>
                                                    <OTStatusBadge status={request.status} />
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center gap-1">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => setViewTarget(request)}
                                                            className="text-zinc-500 hover:text-zinc-700"
                                                        >
                                                            <Eye className="h-4 w-4" />
                                                        </Button>
                                                        {request.status === 'pending' && (
                                                            <>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() => handleApprove(request.id)}
                                                                    disabled={approveMutation.isPending}
                                                                    className="text-emerald-600 hover:text-emerald-700"
                                                                >
                                                                    <CheckCircle className="h-4 w-4" />
                                                                </Button>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() => openReject(request)}
                                                                    className="text-red-500 hover:text-red-700"
                                                                >
                                                                    <XCircle className="h-4 w-4" />
                                                                </Button>
                                                            </>
                                                        )}
                                                        {request.status === 'approved' && (
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                onClick={() => openComplete(request)}
                                                            >
                                                                Complete
                                                            </Button>
                                                        )}
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
            ) : null}

            {/* View Dialog */}
            <Dialog open={!!viewTarget} onOpenChange={() => setViewTarget(null)}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Overtime Request Details</DialogTitle>
                        <DialogDescription>
                            {viewTarget?.employee?.full_name} — {formatDate(viewTarget?.requested_date)}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <p className="text-xs font-medium uppercase tracking-wide text-zinc-400">Employee</p>
                                <p className="mt-0.5 text-sm font-medium text-zinc-900">{viewTarget?.employee?.full_name || '-'}</p>
                                <p className="text-xs text-zinc-500">{viewTarget?.employee?.department?.name || ''}</p>
                            </div>
                            <div>
                                <p className="text-xs font-medium uppercase tracking-wide text-zinc-400">Status</p>
                                <div className="mt-0.5">
                                    <OTStatusBadge status={viewTarget?.status} />
                                </div>
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <p className="text-xs font-medium uppercase tracking-wide text-zinc-400">Date</p>
                                <p className="mt-0.5 text-sm text-zinc-900">{formatDate(viewTarget?.requested_date)}</p>
                            </div>
                            <div>
                                <p className="text-xs font-medium uppercase tracking-wide text-zinc-400">Start Time</p>
                                <p className="mt-0.5 text-sm text-zinc-900">{viewTarget?.start_time ? viewTarget.start_time.slice(0, 5) : '-'}</p>
                            </div>
                        </div>
                        <div className="grid grid-cols-3 gap-4">
                            <div>
                                <p className="text-xs font-medium uppercase tracking-wide text-zinc-400">Planned</p>
                                <p className="mt-0.5 text-sm text-zinc-900">{formatHours(viewTarget?.planned_hours)}</p>
                            </div>
                            <div>
                                <p className="text-xs font-medium uppercase tracking-wide text-zinc-400">Actual</p>
                                <p className="mt-0.5 text-sm text-zinc-900">{formatHours(viewTarget?.actual_hours)}</p>
                            </div>
                            <div>
                                <p className="text-xs font-medium uppercase tracking-wide text-zinc-400">Replacement</p>
                                <p className="mt-0.5 text-sm text-zinc-900">{viewTarget?.replacement_hours ? formatHours(viewTarget.replacement_hours) : '-'}</p>
                            </div>
                        </div>
                        <div>
                            <p className="text-xs font-medium uppercase tracking-wide text-zinc-400">Reason</p>
                            <p className="mt-0.5 text-sm text-zinc-700">{viewTarget?.reason || '-'}</p>
                        </div>
                        {viewTarget?.rejection_reason && (
                            <div className="rounded-md border border-red-100 bg-red-50 p-3">
                                <p className="text-xs font-medium uppercase tracking-wide text-red-400">Rejection Reason</p>
                                <p className="mt-0.5 text-sm text-red-700">{viewTarget.rejection_reason}</p>
                            </div>
                        )}
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setViewTarget(null)}>Close</Button>
                        {viewTarget?.status === 'pending' && (
                            <>
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    onClick={() => { setViewTarget(null); openReject(viewTarget); }}
                                >
                                    Reject
                                </Button>
                                <Button
                                    size="sm"
                                    onClick={() => { handleApprove(viewTarget.id); setViewTarget(null); }}
                                    disabled={approveMutation.isPending}
                                    className="bg-emerald-600 hover:bg-emerald-700"
                                >
                                    Approve
                                </Button>
                            </>
                        )}
                        {viewTarget?.status === 'approved' && (
                            <Button
                                size="sm"
                                onClick={() => { setViewTarget(null); openComplete(viewTarget); }}
                            >
                                Complete
                            </Button>
                        )}
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Reject Dialog */}
            <Dialog open={!!rejectTarget} onOpenChange={() => setRejectTarget(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Reject Overtime Request</DialogTitle>
                        <DialogDescription>
                            Reject overtime request for {rejectTarget?.employee?.full_name} on {formatDate(rejectTarget?.requested_date)}
                        </DialogDescription>
                    </DialogHeader>
                    <div>
                        <Label>Reason for Rejection</Label>
                        <Textarea
                            value={rejectReason}
                            onChange={(e) => setRejectReason(e.target.value)}
                            placeholder="Provide a reason for rejection..."
                            rows={3}
                        />
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setRejectTarget(null)}>
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={handleReject}
                            disabled={rejectMutation.isPending || !rejectReason}
                        >
                            {rejectMutation.isPending ? 'Rejecting...' : 'Reject'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Claim Reject Dialog */}
            <Dialog open={!!claimRejectTarget} onOpenChange={() => setClaimRejectTarget(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Reject OT Claim</DialogTitle>
                        <DialogDescription>
                            Reject OT claim for {claimRejectTarget?.employee?.full_name} on {formatDate(claimRejectTarget?.claim_date)}
                        </DialogDescription>
                    </DialogHeader>
                    <div>
                        <Label>Reason for Rejection</Label>
                        <Textarea
                            value={claimRejectReason}
                            onChange={(e) => setClaimRejectReason(e.target.value)}
                            placeholder="Provide a reason for rejection..."
                            rows={3}
                        />
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setClaimRejectTarget(null)}>
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() => claimRejectMutation.mutate({ id: claimRejectTarget.id, rejection_reason: claimRejectReason })}
                            disabled={claimRejectMutation.isPending || !claimRejectReason}
                        >
                            {claimRejectMutation.isPending ? 'Rejecting...' : 'Reject'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Complete Dialog */}
            <Dialog open={!!completeTarget} onOpenChange={() => setCompleteTarget(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Complete Overtime</DialogTitle>
                        <DialogDescription>
                            Record actual hours for {completeTarget?.employee?.full_name} on {formatDate(completeTarget?.requested_date)}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div>
                            <Label>Planned Hours</Label>
                            <p className="text-sm text-zinc-600">{formatHours(completeTarget?.planned_hours)}</p>
                        </div>
                        <div>
                            <Label>Actual Hours Worked</Label>
                            <Input
                                type="number"
                                step="0.5"
                                min="0"
                                value={actualHours}
                                onChange={(e) => setActualHours(e.target.value)}
                                placeholder="e.g. 2.5"
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setCompleteTarget(null)}>
                            Cancel
                        </Button>
                        <Button
                            onClick={handleComplete}
                            disabled={completeMutation.isPending || !actualHours}
                        >
                            {completeMutation.isPending ? 'Saving...' : 'Mark Complete'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
