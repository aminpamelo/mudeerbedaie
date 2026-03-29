import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Loader2,
    LogOut,
    FileText,
    CheckCircle2,
    Clock,
    XCircle,
    CalendarDays,
    UserCheck,
    AlertTriangle,
} from 'lucide-react';
import { fetchMyResignation, submitMyResignation } from '../../lib/api';
import { cn } from '../../lib/utils';
import PageHeader from '../../components/PageHeader';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '../../components/ui/card';
import { Button } from '../../components/ui/button';
import { Badge } from '../../components/ui/badge';

const STATUS_CONFIG = {
    pending: { label: 'Pending', icon: Clock, color: 'text-amber-600', bg: 'bg-amber-50', badgeClass: 'border-amber-300 bg-amber-50 text-amber-700' },
    approved: { label: 'Approved', icon: CheckCircle2, color: 'text-green-600', bg: 'bg-green-50', badgeClass: 'border-green-300 bg-green-50 text-green-700' },
    rejected: { label: 'Rejected', icon: XCircle, color: 'text-red-600', bg: 'bg-red-50', badgeClass: 'border-red-300 bg-red-50 text-red-700' },
    completed: { label: 'Completed', icon: CheckCircle2, color: 'text-zinc-600', bg: 'bg-zinc-50', badgeClass: 'border-zinc-300 bg-zinc-50 text-zinc-600' },
};

const PROGRESSION_STEPS = ['pending', 'approved', 'completed'];

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-MY', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

function StatusProgressBar({ status }) {
    const isRejected = status === 'rejected';
    const currentIndex = isRejected ? -1 : PROGRESSION_STEPS.indexOf(status);

    return (
        <div className="flex items-center gap-2">
            {PROGRESSION_STEPS.map((step, index) => {
                const isActive = !isRejected && index <= currentIndex;
                const isCurrent = !isRejected && index === currentIndex;

                return (
                    <div key={step} className="flex items-center gap-2">
                        <div className="flex flex-col items-center">
                            <div
                                className={cn(
                                    'flex h-8 w-8 items-center justify-center rounded-full border-2 text-xs font-semibold transition-colors',
                                    isActive
                                        ? 'border-green-500 bg-green-500 text-white'
                                        : 'border-zinc-300 bg-white text-zinc-400'
                                )}
                            >
                                {isActive ? (
                                    <CheckCircle2 className="h-4 w-4" />
                                ) : (
                                    index + 1
                                )}
                            </div>
                            <span
                                className={cn(
                                    'mt-1 text-[10px] font-medium capitalize',
                                    isCurrent ? 'text-green-700' : 'text-zinc-400'
                                )}
                            >
                                {step}
                            </span>
                        </div>
                        {index < PROGRESSION_STEPS.length - 1 && (
                            <div
                                className={cn(
                                    'mb-4 h-0.5 w-12 sm:w-16',
                                    !isRejected && index < currentIndex
                                        ? 'bg-green-500'
                                        : 'bg-zinc-200'
                                )}
                            />
                        )}
                    </div>
                );
            })}
        </div>
    );
}

export default function MyResignation() {
    const queryClient = useQueryClient();
    const [reason, setReason] = useState('');
    const [confirmDialog, setConfirmDialog] = useState(false);

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'me', 'resignation'],
        queryFn: fetchMyResignation,
    });

    const resignation = data?.data ?? null;

    const submitMutation = useMutation({
        mutationFn: (data) => submitMyResignation(data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'me', 'resignation'] });
            setReason('');
            setConfirmDialog(false);
        },
    });

    function handleSubmit() {
        if (!reason.trim()) return;
        submitMutation.mutate({ reason });
    }

    if (isLoading) {
        return (
            <div className="space-y-6">
                <PageHeader
                    title="My Resignation"
                    description="Submit and track your resignation"
                />
                <Card>
                    <CardContent className="p-6">
                        <div className="space-y-4">
                            {Array.from({ length: 4 }).map((_, i) => (
                                <div key={i} className="h-5 w-full animate-pulse rounded bg-zinc-200" />
                            ))}
                        </div>
                    </CardContent>
                </Card>
            </div>
        );
    }

    // Resignation exists - show status card
    if (resignation) {
        const statusConfig = STATUS_CONFIG[resignation.status] || STATUS_CONFIG.pending;
        const StatusIcon = statusConfig.icon;

        return (
            <div className="space-y-6">
                <PageHeader
                    title="My Resignation"
                    description="Track the status of your resignation"
                />

                {/* Status Card */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Resignation Status</CardTitle>
                                <CardDescription>
                                    Submitted on {formatDate(resignation.submitted_at || resignation.created_at)}
                                </CardDescription>
                            </div>
                            <Badge variant="outline" className={cn('text-xs', statusConfig.badgeClass)}>
                                <StatusIcon className="mr-1 h-3 w-3" />
                                {statusConfig.label}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        {/* Progress Bar */}
                        <div className="flex justify-center py-4">
                            <StatusProgressBar status={resignation.status} />
                        </div>

                        {/* Rejected Notice */}
                        {resignation.status === 'rejected' && (
                            <div className="rounded-lg bg-red-50 p-4">
                                <div className="flex items-start gap-2">
                                    <XCircle className="mt-0.5 h-4 w-4 text-red-600 shrink-0" />
                                    <div>
                                        <p className="text-sm font-medium text-red-800">Resignation Rejected</p>
                                        {resignation.rejection_reason && (
                                            <p className="mt-1 text-sm text-red-700">{resignation.rejection_reason}</p>
                                        )}
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Details Grid */}
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div className="rounded-lg border border-zinc-200 p-4">
                                <div className="flex items-center gap-2 mb-2">
                                    <CalendarDays className="h-4 w-4 text-zinc-400" />
                                    <p className="text-xs font-medium text-zinc-500">Submitted Date</p>
                                </div>
                                <p className="text-sm font-semibold text-zinc-900">
                                    {formatDate(resignation.submitted_at || resignation.created_at)}
                                </p>
                            </div>

                            <div className="rounded-lg border border-zinc-200 p-4">
                                <div className="flex items-center gap-2 mb-2">
                                    <Clock className="h-4 w-4 text-zinc-400" />
                                    <p className="text-xs font-medium text-zinc-500">Notice Period</p>
                                </div>
                                <p className="text-sm font-semibold text-zinc-900">
                                    {resignation.notice_period || '-'}
                                </p>
                            </div>

                            <div className="rounded-lg border border-zinc-200 p-4">
                                <div className="flex items-center gap-2 mb-2">
                                    <CalendarDays className="h-4 w-4 text-zinc-400" />
                                    <p className="text-xs font-medium text-zinc-500">Last Working Date</p>
                                </div>
                                <p className="text-sm font-semibold text-zinc-900">
                                    {formatDate(resignation.last_working_date)}
                                </p>
                            </div>

                            <div className="rounded-lg border border-zinc-200 p-4">
                                <div className="flex items-center gap-2 mb-2">
                                    <UserCheck className="h-4 w-4 text-zinc-400" />
                                    <p className="text-xs font-medium text-zinc-500">Approved By</p>
                                </div>
                                <p className="text-sm font-semibold text-zinc-900">
                                    {resignation.approved_by_name || '-'}
                                </p>
                            </div>
                        </div>

                        {/* Reason */}
                        <div className="rounded-lg bg-zinc-50 p-4">
                            <p className="mb-1 text-xs font-semibold uppercase tracking-wide text-zinc-500">Reason for Resignation</p>
                            <p className="text-sm text-zinc-700">{resignation.reason}</p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        );
    }

    // No resignation - show submission form
    return (
        <div className="space-y-6">
            <PageHeader
                title="My Resignation"
                description="Submit your resignation notice"
            />

            {/* Warning Notice */}
            <div className="rounded-lg border border-amber-200 bg-amber-50 p-4">
                <div className="flex items-start gap-3">
                    <AlertTriangle className="mt-0.5 h-5 w-5 text-amber-600 shrink-0" />
                    <div>
                        <p className="text-sm font-medium text-amber-800">Important Notice</p>
                        <p className="mt-1 text-sm text-amber-700">
                            Submitting a resignation is a formal process. Once submitted, it will be reviewed by HR
                            and your manager. Please ensure you have considered this decision carefully before proceeding.
                        </p>
                    </div>
                </div>
            </div>

            {/* Resignation Form */}
            <Card>
                <CardHeader>
                    <CardTitle>Submit Resignation</CardTitle>
                    <CardDescription>
                        Please provide a reason for your resignation. This will be reviewed by HR.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div>
                        <label className="mb-1.5 block text-sm font-medium text-zinc-700">
                            Reason for Resignation
                        </label>
                        <textarea
                            value={reason}
                            onChange={(e) => setReason(e.target.value)}
                            placeholder="Please provide the reason for your resignation..."
                            rows={6}
                            className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm text-zinc-900 placeholder:text-zinc-400 focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                        />
                    </div>

                    {!confirmDialog ? (
                        <Button
                            onClick={() => setConfirmDialog(true)}
                            disabled={!reason.trim()}
                            variant="outline"
                            className="border-red-300 text-red-600 hover:bg-red-50"
                        >
                            <LogOut className="mr-2 h-4 w-4" />
                            Submit Resignation
                        </Button>
                    ) : (
                        <div className="rounded-lg border border-red-200 bg-red-50 p-4">
                            <p className="text-sm font-medium text-red-800">
                                Are you sure you want to submit your resignation?
                            </p>
                            <p className="mt-1 text-xs text-red-600">
                                This action cannot be undone after HR approval.
                            </p>
                            <div className="mt-3 flex gap-2">
                                <Button
                                    onClick={handleSubmit}
                                    disabled={submitMutation.isPending}
                                    size="sm"
                                    className="bg-red-600 hover:bg-red-700 text-white"
                                >
                                    {submitMutation.isPending ? (
                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    ) : (
                                        <LogOut className="mr-2 h-4 w-4" />
                                    )}
                                    Confirm Resignation
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setConfirmDialog(false)}
                                >
                                    Cancel
                                </Button>
                            </div>
                        </div>
                    )}

                    {submitMutation.isError && (
                        <div className="rounded-lg bg-red-50 p-3">
                            <p className="text-sm text-red-700">
                                {submitMutation.error?.response?.data?.message || 'Failed to submit resignation. Please try again.'}
                            </p>
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
