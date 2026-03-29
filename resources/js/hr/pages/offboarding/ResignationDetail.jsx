import { useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    ChevronLeft,
    CheckCircle,
    XCircle,
    Loader2,
    User,
    Calendar,
    Clock,
    FileText,
    ClipboardList,
    Flag,
} from 'lucide-react';
import {
    fetchResignation,
    approveResignation,
    rejectResignation,
    completeResignation,
} from '../../lib/api';
import { cn } from '../../lib/utils';
import { Button } from '../../components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '../../components/ui/card';
import { Badge } from '../../components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '../../components/ui/dialog';

const STATUS_CONFIG = {
    pending: { label: 'Pending', bg: 'bg-amber-100', text: 'text-amber-700' },
    approved: { label: 'Approved', bg: 'bg-emerald-100', text: 'text-emerald-700' },
    rejected: { label: 'Rejected', bg: 'bg-red-100', text: 'text-red-700' },
    completed: { label: 'Completed', bg: 'bg-zinc-100', text: 'text-zinc-700' },
};

function formatDate(dateString) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('en-MY', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function formatDateTime(dateString) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('en-MY', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function StatusBadge({ status }) {
    const config = STATUS_CONFIG[status] || { label: status, bg: 'bg-zinc-100', text: 'text-zinc-700' };
    return (
        <span className={cn('inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold', config.bg, config.text)}>
            {config.label}
        </span>
    );
}

export default function ResignationDetail() {
    const { id } = useParams();
    const queryClient = useQueryClient();
    const [approveDialog, setApproveDialog] = useState(false);
    const [rejectDialog, setRejectDialog] = useState(false);
    const [confirmCompleteDialog, setConfirmCompleteDialog] = useState(false);
    const [approveNotes, setApproveNotes] = useState('');
    const [rejectReason, setRejectReason] = useState('');

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'offboarding', 'resignation', id],
        queryFn: () => fetchResignation(id),
    });

    const approveMutation = useMutation({
        mutationFn: (data) => approveResignation(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'offboarding', 'resignation', id] });
            queryClient.invalidateQueries({ queryKey: ['hr', 'offboarding', 'resignations'] });
            setApproveDialog(false);
            setApproveNotes('');
        },
    });

    const rejectMutation = useMutation({
        mutationFn: (data) => rejectResignation(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'offboarding', 'resignation', id] });
            queryClient.invalidateQueries({ queryKey: ['hr', 'offboarding', 'resignations'] });
            setRejectDialog(false);
            setRejectReason('');
        },
    });

    const completeMutation = useMutation({
        mutationFn: () => completeResignation(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'offboarding', 'resignation', id] });
            queryClient.invalidateQueries({ queryKey: ['hr', 'offboarding', 'resignations'] });
            setConfirmCompleteDialog(false);
        },
    });

    const resignation = data?.data;

    if (isLoading) {
        return (
            <div className="flex items-center justify-center py-24">
                <Loader2 className="h-8 w-8 animate-spin text-zinc-400" />
            </div>
        );
    }

    if (!resignation) {
        return (
            <div className="flex flex-col items-center justify-center py-24">
                <p className="text-sm text-zinc-500">Resignation request not found.</p>
                <Link to="/offboarding/resignations" className="mt-3">
                    <Button variant="outline" size="sm">Back to Resignations</Button>
                </Link>
            </div>
        );
    }

    const isAnyPending = approveMutation.isPending || rejectMutation.isPending || completeMutation.isPending;

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <Link to="/offboarding/resignations">
                        <Button variant="ghost" size="sm">
                            <ChevronLeft className="mr-1 h-4 w-4" />
                            Back
                        </Button>
                    </Link>
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-xl font-semibold text-zinc-900">
                                Resignation - {resignation.employee?.full_name || 'Unknown'}
                            </h1>
                            <StatusBadge status={resignation.status} />
                        </div>
                        <p className="text-sm text-zinc-500">
                            Submitted on {formatDate(resignation.submitted_date)}
                        </p>
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    {resignation.status === 'pending' && (
                        <>
                            <Button
                                variant="outline"
                                onClick={() => {
                                    setRejectReason('');
                                    setRejectDialog(true);
                                }}
                                disabled={isAnyPending}
                            >
                                <XCircle className="mr-2 h-4 w-4" />
                                Reject
                            </Button>
                            <Button
                                onClick={() => {
                                    setApproveNotes('');
                                    setApproveDialog(true);
                                }}
                                disabled={isAnyPending}
                            >
                                <CheckCircle className="mr-2 h-4 w-4" />
                                Approve
                            </Button>
                        </>
                    )}
                    {resignation.status === 'approved' && (
                        <Button
                            onClick={() => setConfirmCompleteDialog(true)}
                            disabled={isAnyPending}
                        >
                            <Flag className="mr-2 h-4 w-4" />
                            Mark Complete
                        </Button>
                    )}
                </div>
            </div>

            {/* Details Grid */}
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                {/* Employee Info */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <User className="h-4 w-4 text-zinc-500" />
                            Employee Information
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl className="space-y-3">
                            <div className="flex justify-between">
                                <dt className="text-sm text-zinc-500">Name</dt>
                                <dd className="text-sm font-medium text-zinc-900">
                                    {resignation.employee?.full_name || '-'}
                                </dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-sm text-zinc-500">Employee ID</dt>
                                <dd className="text-sm font-medium text-zinc-900">
                                    {resignation.employee?.employee_id || '-'}
                                </dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-sm text-zinc-500">Department</dt>
                                <dd className="text-sm font-medium text-zinc-900">
                                    {resignation.employee?.department?.name || '-'}
                                </dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-sm text-zinc-500">Position</dt>
                                <dd className="text-sm font-medium text-zinc-900">
                                    {resignation.employee?.position?.name || '-'}
                                </dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                {/* Resignation Details */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <FileText className="h-4 w-4 text-zinc-500" />
                            Resignation Details
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl className="space-y-3">
                            <div className="flex justify-between">
                                <dt className="text-sm text-zinc-500">Submitted Date</dt>
                                <dd className="text-sm font-medium text-zinc-900">
                                    {formatDate(resignation.submitted_date)}
                                </dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-sm text-zinc-500">Notice Period</dt>
                                <dd className="text-sm font-medium text-zinc-900">
                                    {resignation.notice_period ? `${resignation.notice_period} days` : '-'}
                                </dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-sm text-zinc-500">Last Working Date</dt>
                                <dd className="text-sm font-medium text-zinc-900">
                                    {formatDate(resignation.last_working_date)}
                                </dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-sm text-zinc-500">Status</dt>
                                <dd><StatusBadge status={resignation.status} /></dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>
            </div>

            {/* Reason */}
            <Card>
                <CardHeader>
                    <CardTitle className="text-base">Reason for Resignation</CardTitle>
                </CardHeader>
                <CardContent>
                    <p className="text-sm text-zinc-700 whitespace-pre-wrap">
                        {resignation.reason || 'No reason provided.'}
                    </p>
                </CardContent>
            </Card>

            {/* Approval / Rejection Info */}
            {(resignation.approved_by || resignation.rejected_by) && (
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            {resignation.status === 'rejected' ? 'Rejection Details' : 'Approval Details'}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl className="space-y-3">
                            {resignation.approved_by_name && (
                                <div className="flex justify-between">
                                    <dt className="text-sm text-zinc-500">Approved By</dt>
                                    <dd className="text-sm font-medium text-zinc-900">
                                        {resignation.approved_by_name}
                                    </dd>
                                </div>
                            )}
                            {resignation.approved_at && (
                                <div className="flex justify-between">
                                    <dt className="text-sm text-zinc-500">Approved At</dt>
                                    <dd className="text-sm font-medium text-zinc-900">
                                        {formatDateTime(resignation.approved_at)}
                                    </dd>
                                </div>
                            )}
                            {resignation.approval_notes && (
                                <div>
                                    <dt className="mb-1 text-sm text-zinc-500">Notes</dt>
                                    <dd className="text-sm text-zinc-700 whitespace-pre-wrap">
                                        {resignation.approval_notes}
                                    </dd>
                                </div>
                            )}
                            {resignation.rejected_by_name && (
                                <div className="flex justify-between">
                                    <dt className="text-sm text-zinc-500">Rejected By</dt>
                                    <dd className="text-sm font-medium text-zinc-900">
                                        {resignation.rejected_by_name}
                                    </dd>
                                </div>
                            )}
                            {resignation.rejected_at && (
                                <div className="flex justify-between">
                                    <dt className="text-sm text-zinc-500">Rejected At</dt>
                                    <dd className="text-sm font-medium text-zinc-900">
                                        {formatDateTime(resignation.rejected_at)}
                                    </dd>
                                </div>
                            )}
                            {resignation.rejection_reason && (
                                <div>
                                    <dt className="mb-1 text-sm text-zinc-500">Rejection Reason</dt>
                                    <dd className="text-sm text-zinc-700 whitespace-pre-wrap">
                                        {resignation.rejection_reason}
                                    </dd>
                                </div>
                            )}
                        </dl>
                    </CardContent>
                </Card>
            )}

            {/* Linked Exit Checklist */}
            {resignation.exit_checklist && (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <ClipboardList className="h-4 w-4 text-zinc-500" />
                            Exit Checklist
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm font-medium text-zinc-900">
                                    Status: {resignation.exit_checklist.status || '-'}
                                </p>
                                <p className="text-xs text-zinc-500">
                                    {resignation.exit_checklist.completed_items ?? 0} / {resignation.exit_checklist.total_items ?? 0} items completed
                                </p>
                            </div>
                            <div className="flex items-center gap-3">
                                <div className="h-2 w-32 overflow-hidden rounded-full bg-zinc-100">
                                    <div
                                        className="h-full rounded-full bg-emerald-500 transition-all"
                                        style={{
                                            width: resignation.exit_checklist.total_items
                                                ? `${(resignation.exit_checklist.completed_items / resignation.exit_checklist.total_items) * 100}%`
                                                : '0%',
                                        }}
                                    />
                                </div>
                                <Link to={`/offboarding/checklists`}>
                                    <Button variant="outline" size="sm">View Checklist</Button>
                                </Link>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Approve Dialog */}
            <Dialog open={approveDialog} onOpenChange={setApproveDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Approve Resignation</DialogTitle>
                        <DialogDescription>
                            Approve the resignation request for {resignation.employee?.full_name}. You may add optional notes.
                        </DialogDescription>
                    </DialogHeader>
                    <div>
                        <label className="mb-1.5 block text-sm font-medium text-zinc-700">Notes (Optional)</label>
                        <textarea
                            value={approveNotes}
                            onChange={(e) => setApproveNotes(e.target.value)}
                            rows={3}
                            className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                            placeholder="Add approval notes..."
                        />
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setApproveDialog(false)}>
                            Cancel
                        </Button>
                        <Button
                            onClick={() => approveMutation.mutate({ notes: approveNotes })}
                            disabled={approveMutation.isPending}
                        >
                            {approveMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                            Approve
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Reject Dialog */}
            <Dialog open={rejectDialog} onOpenChange={setRejectDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Reject Resignation</DialogTitle>
                        <DialogDescription>
                            Provide a reason for rejecting the resignation request for {resignation.employee?.full_name}.
                        </DialogDescription>
                    </DialogHeader>
                    <div>
                        <label className="mb-1.5 block text-sm font-medium text-zinc-700">Rejection Reason</label>
                        <textarea
                            value={rejectReason}
                            onChange={(e) => setRejectReason(e.target.value)}
                            rows={3}
                            className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                            placeholder="Reason for rejection..."
                        />
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setRejectDialog(false)}>
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() => rejectMutation.mutate({ reason: rejectReason })}
                            disabled={rejectMutation.isPending || !rejectReason.trim()}
                        >
                            {rejectMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                            Reject
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Complete Confirmation Dialog */}
            <Dialog open={confirmCompleteDialog} onOpenChange={setConfirmCompleteDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Complete Resignation</DialogTitle>
                        <DialogDescription>
                            Mark this resignation as completed for {resignation.employee?.full_name}. This indicates the offboarding process is finished.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setConfirmCompleteDialog(false)}>
                            Cancel
                        </Button>
                        <Button
                            onClick={() => completeMutation.mutate()}
                            disabled={completeMutation.isPending}
                        >
                            {completeMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                            Complete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
