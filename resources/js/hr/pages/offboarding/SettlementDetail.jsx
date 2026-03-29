import { useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    ChevronLeft,
    CheckCircle,
    Banknote,
    Download,
    Loader2,
    DollarSign,
    TrendingDown,
    Wallet,
    Calculator,
} from 'lucide-react';
import {
    fetchFinalSettlement,
    approveFinalSettlement,
    markSettlementPaid,
    downloadSettlementPdf,
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

const STATUS_STEPS = ['draft', 'calculated', 'approved', 'paid'];

const STATUS_CONFIG = {
    draft: { label: 'Draft', bg: 'bg-zinc-100', text: 'text-zinc-700' },
    calculated: { label: 'Calculated', bg: 'bg-amber-100', text: 'text-amber-700' },
    approved: { label: 'Approved', bg: 'bg-emerald-100', text: 'text-emerald-700' },
    paid: { label: 'Paid', bg: 'bg-blue-100', text: 'text-blue-700' },
};

function formatCurrency(amount) {
    if (amount == null) return 'MYR 0.00';
    return new Intl.NumberFormat('en-MY', { style: 'currency', currency: 'MYR' }).format(amount);
}

function formatDate(dateString) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('en-MY', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
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

function StatusWorkflow({ currentStatus }) {
    const currentIndex = STATUS_STEPS.indexOf(currentStatus);
    return (
        <div className="flex items-center gap-2">
            {STATUS_STEPS.map((step, index) => {
                const config = STATUS_CONFIG[step];
                const isActive = index <= currentIndex;
                const isCurrent = step === currentStatus;
                return (
                    <div key={step} className="flex items-center gap-2">
                        {index > 0 && (
                            <div className={cn('h-0.5 w-8', isActive ? 'bg-emerald-400' : 'bg-zinc-200')} />
                        )}
                        <div
                            className={cn(
                                'flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium transition-colors',
                                isCurrent ? cn(config.bg, config.text) : isActive ? 'bg-emerald-50 text-emerald-700' : 'bg-zinc-50 text-zinc-400'
                            )}
                        >
                            {isActive && index < currentIndex && (
                                <CheckCircle className="h-3.5 w-3.5" />
                            )}
                            {config.label}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

function SummaryCard({ title, value, icon: Icon, iconColor, iconBg }) {
    return (
        <Card>
            <CardContent className="p-4">
                <div className="flex items-center gap-3">
                    <div className={cn('flex h-10 w-10 items-center justify-center rounded-lg', iconBg)}>
                        <Icon className={cn('h-5 w-5', iconColor)} />
                    </div>
                    <div>
                        <p className="text-xs font-medium text-zinc-500">{title}</p>
                        <p className="text-lg font-bold text-zinc-900">{value}</p>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

export default function SettlementDetail() {
    const { id } = useParams();
    const queryClient = useQueryClient();
    const [confirmDialog, setConfirmDialog] = useState({ open: false, action: null });
    const [downloading, setDownloading] = useState(false);

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'offboarding', 'settlement', id],
        queryFn: () => fetchFinalSettlement(id),
    });

    const approveMutation = useMutation({
        mutationFn: () => approveFinalSettlement(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'offboarding', 'settlement', id] });
            queryClient.invalidateQueries({ queryKey: ['hr', 'offboarding', 'settlements'] });
            setConfirmDialog({ open: false, action: null });
        },
    });

    const markPaidMutation = useMutation({
        mutationFn: () => markSettlementPaid(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'offboarding', 'settlement', id] });
            queryClient.invalidateQueries({ queryKey: ['hr', 'offboarding', 'settlements'] });
            setConfirmDialog({ open: false, action: null });
        },
    });

    const settlement = data?.data;

    async function handleDownload() {
        setDownloading(true);
        try {
            const blob = await downloadSettlementPdf(id);
            const url = window.URL.createObjectURL(new Blob([blob]));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', `Settlement_${settlement?.employee?.full_name?.replace(/\s+/g, '_') || id}.pdf`);
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);
        } catch (err) {
            console.error('Download failed:', err);
            alert('Failed to download PDF: ' + (err?.response?.data?.message || err.message));
        } finally {
            setDownloading(false);
        }
    }

    function handleConfirmAction() {
        if (confirmDialog.action === 'approve') {
            approveMutation.mutate();
        } else if (confirmDialog.action === 'mark_paid') {
            markPaidMutation.mutate();
        }
    }

    const isAnyPending = approveMutation.isPending || markPaidMutation.isPending;

    if (isLoading) {
        return (
            <div className="flex items-center justify-center py-24">
                <Loader2 className="h-8 w-8 animate-spin text-zinc-400" />
            </div>
        );
    }

    if (!settlement) {
        return (
            <div className="flex flex-col items-center justify-center py-24">
                <p className="text-sm text-zinc-500">Settlement not found.</p>
                <Link to="/offboarding/settlements" className="mt-3">
                    <Button variant="outline" size="sm">Back to Settlements</Button>
                </Link>
            </div>
        );
    }

    const earnings = [
        { label: 'Prorated Salary', amount: settlement.prorated_salary },
        { label: 'Leave Encashment', amount: settlement.leave_encashment },
        { label: 'Other Earnings', amount: settlement.other_earnings },
    ];

    const deductions = [
        { label: 'EPF (Employee)', amount: settlement.epf_deduction },
        { label: 'SOCSO (Employee)', amount: settlement.socso_deduction },
        { label: 'EIS (Employee)', amount: settlement.eis_deduction },
        { label: 'PCB (Tax)', amount: settlement.pcb_deduction },
        { label: 'Other Deductions', amount: settlement.other_deductions },
    ];

    const grossAmount = settlement.gross_amount ?? 0;
    const totalDeductions = settlement.total_deductions ?? 0;
    const netAmount = settlement.net_amount ?? 0;

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <Link to="/offboarding/settlements">
                        <Button variant="ghost" size="sm">
                            <ChevronLeft className="mr-1 h-4 w-4" />
                            Back
                        </Button>
                    </Link>
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-xl font-semibold text-zinc-900">
                                Final Settlement - {settlement.employee?.full_name || 'Unknown'}
                            </h1>
                            <StatusBadge status={settlement.status} />
                        </div>
                        <p className="text-sm text-zinc-500">
                            {settlement.employee?.employee_id || ''} &middot; Created {formatDate(settlement.created_at)}
                        </p>
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    {settlement.status === 'calculated' && (
                        <Button
                            onClick={() => setConfirmDialog({ open: true, action: 'approve' })}
                            disabled={isAnyPending}
                        >
                            <CheckCircle className="mr-2 h-4 w-4" />
                            Approve
                        </Button>
                    )}
                    {settlement.status === 'approved' && (
                        <>
                            <Button
                                variant="outline"
                                onClick={handleDownload}
                                disabled={downloading}
                            >
                                {downloading ? (
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                ) : (
                                    <Download className="mr-2 h-4 w-4" />
                                )}
                                Download PDF
                            </Button>
                            <Button
                                onClick={() => setConfirmDialog({ open: true, action: 'mark_paid' })}
                                disabled={isAnyPending}
                            >
                                <Banknote className="mr-2 h-4 w-4" />
                                Mark as Paid
                            </Button>
                        </>
                    )}
                    {settlement.status === 'paid' && (
                        <Button
                            variant="outline"
                            onClick={handleDownload}
                            disabled={downloading}
                        >
                            {downloading ? (
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                            ) : (
                                <Download className="mr-2 h-4 w-4" />
                            )}
                            Download PDF
                        </Button>
                    )}
                </div>
            </div>

            {/* Status Workflow */}
            <Card>
                <CardContent className="p-4">
                    <StatusWorkflow currentStatus={settlement.status} />
                </CardContent>
            </Card>

            {/* Summary Cards */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <SummaryCard
                    title="Gross Amount"
                    value={formatCurrency(grossAmount)}
                    icon={DollarSign}
                    iconColor="text-blue-600"
                    iconBg="bg-blue-50"
                />
                <SummaryCard
                    title="Total Deductions"
                    value={formatCurrency(totalDeductions)}
                    icon={TrendingDown}
                    iconColor="text-red-600"
                    iconBg="bg-red-50"
                />
                <SummaryCard
                    title="Net Amount"
                    value={formatCurrency(netAmount)}
                    icon={Wallet}
                    iconColor="text-emerald-600"
                    iconBg="bg-emerald-50"
                />
            </div>

            {/* Breakdown */}
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                {/* Earnings */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <DollarSign className="h-4 w-4 text-emerald-600" />
                            Earnings
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3">
                            {earnings.map((item, index) => (
                                <div key={index} className="flex items-center justify-between">
                                    <span className="text-sm text-zinc-600">{item.label}</span>
                                    <span className="text-sm font-medium text-zinc-900">
                                        {formatCurrency(item.amount)}
                                    </span>
                                </div>
                            ))}
                            <div className="border-t border-zinc-200 pt-3">
                                <div className="flex items-center justify-between">
                                    <span className="text-sm font-semibold text-zinc-900">Total Gross</span>
                                    <span className="text-sm font-bold text-emerald-600">
                                        {formatCurrency(grossAmount)}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Deductions */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <TrendingDown className="h-4 w-4 text-red-600" />
                            Deductions
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3">
                            {deductions.map((item, index) => (
                                <div key={index} className="flex items-center justify-between">
                                    <span className="text-sm text-zinc-600">{item.label}</span>
                                    <span className="text-sm font-medium text-red-600">
                                        {formatCurrency(item.amount)}
                                    </span>
                                </div>
                            ))}
                            <div className="border-t border-zinc-200 pt-3">
                                <div className="flex items-center justify-between">
                                    <span className="text-sm font-semibold text-zinc-900">Total Deductions</span>
                                    <span className="text-sm font-bold text-red-600">
                                        {formatCurrency(totalDeductions)}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Net Amount Highlight */}
            <Card className="border-emerald-200 bg-emerald-50/50">
                <CardContent className="p-6">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-emerald-100">
                                <Wallet className="h-6 w-6 text-emerald-600" />
                            </div>
                            <div>
                                <p className="text-sm font-medium text-emerald-800">Net Settlement Amount</p>
                                <p className="text-xs text-emerald-600">
                                    Final amount payable to the employee
                                </p>
                            </div>
                        </div>
                        <p className="text-3xl font-bold text-emerald-700">
                            {formatCurrency(netAmount)}
                        </p>
                    </div>
                </CardContent>
            </Card>

            {/* Employee Details */}
            <Card>
                <CardHeader>
                    <CardTitle className="text-base">Employee Details</CardTitle>
                </CardHeader>
                <CardContent>
                    <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <dt className="text-sm text-zinc-500">Full Name</dt>
                            <dd className="text-sm font-medium text-zinc-900">
                                {settlement.employee?.full_name || '-'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-sm text-zinc-500">Employee ID</dt>
                            <dd className="text-sm font-medium text-zinc-900">
                                {settlement.employee?.employee_id || '-'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-sm text-zinc-500">Department</dt>
                            <dd className="text-sm font-medium text-zinc-900">
                                {settlement.employee?.department?.name || '-'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-sm text-zinc-500">Position</dt>
                            <dd className="text-sm font-medium text-zinc-900">
                                {settlement.employee?.position?.name || '-'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-sm text-zinc-500">Last Working Date</dt>
                            <dd className="text-sm font-medium text-zinc-900">
                                {formatDate(settlement.last_working_date)}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-sm text-zinc-500">Settlement Date</dt>
                            <dd className="text-sm font-medium text-zinc-900">
                                {formatDate(settlement.settlement_date)}
                            </dd>
                        </div>
                    </dl>
                </CardContent>
            </Card>

            {/* Notes */}
            {settlement.notes && (
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Notes</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="text-sm text-zinc-700 whitespace-pre-wrap">{settlement.notes}</p>
                    </CardContent>
                </Card>
            )}

            {/* Confirm Action Dialog */}
            <Dialog open={confirmDialog.open} onOpenChange={() => setConfirmDialog({ open: false, action: null })}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Confirm Action</DialogTitle>
                        <DialogDescription>
                            {confirmDialog.action === 'approve' &&
                                `Are you sure you want to approve the final settlement of ${formatCurrency(netAmount)} for ${settlement.employee?.full_name}?`}
                            {confirmDialog.action === 'mark_paid' &&
                                `Are you sure you want to mark this settlement as paid? This confirms that ${formatCurrency(netAmount)} has been disbursed to ${settlement.employee?.full_name}. This action cannot be undone.`}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setConfirmDialog({ open: false, action: null })}>
                            Cancel
                        </Button>
                        <Button onClick={handleConfirmAction} disabled={isAnyPending}>
                            {isAnyPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                            {confirmDialog.action === 'approve' ? 'Approve' : 'Mark as Paid'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
