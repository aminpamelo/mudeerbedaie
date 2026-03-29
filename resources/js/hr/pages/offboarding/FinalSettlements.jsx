import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import {
    Calculator,
    CheckCircle,
    Banknote,
    Download,
    Eye,
    Loader2,
    Wallet,
    Plus,
} from 'lucide-react';
import {
    fetchFinalSettlements,
    calculateFinalSettlement,
    approveFinalSettlement,
    markSettlementPaid,
    downloadSettlementPdf,
    fetchEmployees,
} from '../../lib/api';
import { cn } from '../../lib/utils';
import PageHeader from '../../components/PageHeader';
import SearchInput from '../../components/SearchInput';
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
import {
    Select,
    SelectTrigger,
    SelectContent,
    SelectItem,
    SelectValue,
} from '../../components/ui/select';

const STATUS_OPTIONS = [
    { value: 'all', label: 'All Status' },
    { value: 'draft', label: 'Draft' },
    { value: 'calculated', label: 'Calculated' },
    { value: 'approved', label: 'Approved' },
    { value: 'paid', label: 'Paid' },
];

const STATUS_CONFIG = {
    draft: { label: 'Draft', variant: 'secondary' },
    calculated: { label: 'Calculated', variant: 'warning' },
    approved: { label: 'Approved', variant: 'success' },
    paid: { label: 'Paid', variant: 'default' },
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

export default function FinalSettlements() {
    const queryClient = useQueryClient();
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [calculateDialog, setCalculateDialog] = useState(false);
    const [selectedEmployeeId, setSelectedEmployeeId] = useState('');
    const [finalLastDate, setFinalLastDate] = useState('');
    const [confirmDialog, setConfirmDialog] = useState({ open: false, action: null, id: null });
    const [downloadingId, setDownloadingId] = useState(null);

    const params = {
        search: search || undefined,
        status: statusFilter !== 'all' ? statusFilter : undefined,
    };

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'offboarding', 'settlements', params],
        queryFn: () => fetchFinalSettlements(params),
    });

    const { data: employeesData } = useQuery({
        queryKey: ['hr', 'employees', 'list'],
        queryFn: () => fetchEmployees({ per_page: 200 }),
    });

    const calculateMutation = useMutation({
        mutationFn: ({ employeeId, data }) => calculateFinalSettlement(employeeId, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'offboarding', 'settlements'] });
            setCalculateDialog(false);
            setSelectedEmployeeId('');
            setFinalLastDate('');
        },
    });

    const approveMutation = useMutation({
        mutationFn: (id) => approveFinalSettlement(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'offboarding', 'settlements'] });
            setConfirmDialog({ open: false, action: null, id: null });
        },
    });

    const markPaidMutation = useMutation({
        mutationFn: (id) => markSettlementPaid(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'offboarding', 'settlements'] });
            setConfirmDialog({ open: false, action: null, id: null });
        },
    });

    const settlements = data?.data || [];
    const employees = employeesData?.data || [];

    function getStatusBadge(status) {
        const config = STATUS_CONFIG[status] || { label: status, variant: 'secondary' };
        return <Badge variant={config.variant}>{config.label}</Badge>;
    }

    function handleConfirmAction() {
        if (confirmDialog.action === 'approve') {
            approveMutation.mutate(confirmDialog.id);
        } else if (confirmDialog.action === 'mark_paid') {
            markPaidMutation.mutate(confirmDialog.id);
        }
    }

    async function handleDownload(settlementId, employeeName) {
        setDownloadingId(settlementId);
        try {
            const blob = await downloadSettlementPdf(settlementId);
            const url = window.URL.createObjectURL(new Blob([blob]));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', `Settlement_${employeeName?.replace(/\s+/g, '_') || settlementId}.pdf`);
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);
        } catch (err) {
            console.error('Download failed:', err);
            alert('Failed to download settlement PDF: ' + (err?.response?.data?.message || err.message));
        } finally {
            setDownloadingId(null);
        }
    }

    const isAnyPending = approveMutation.isPending || markPaidMutation.isPending;

    return (
        <div>
            <PageHeader
                title="Final Settlements"
                description="Calculate and manage final pay settlements for departing employees."
                action={
                    <Button onClick={() => setCalculateDialog(true)}>
                        <Calculator className="mr-2 h-4 w-4" />
                        Calculate Settlement
                    </Button>
                }
            />

            {/* Filters */}
            <Card className="mb-6">
                <CardContent className="p-4">
                    <div className="flex flex-wrap items-end gap-4">
                        <SearchInput
                            value={search}
                            onChange={setSearch}
                            placeholder="Search employee..."
                            className="w-64"
                        />
                        <div>
                            <label className="mb-1 block text-xs font-medium text-zinc-600">Status</label>
                            <Select value={statusFilter} onValueChange={setStatusFilter}>
                                <SelectTrigger className="w-36">
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
                    </div>
                </CardContent>
            </Card>

            {/* Table */}
            {isLoading ? (
                <div className="flex justify-center py-16">
                    <Loader2 className="h-6 w-6 animate-spin text-zinc-400" />
                </div>
            ) : settlements.length === 0 ? (
                <Card>
                    <CardContent className="flex flex-col items-center justify-center py-16">
                        <Wallet className="mb-3 h-10 w-10 text-zinc-300" />
                        <p className="text-sm font-medium text-zinc-500">No final settlements found</p>
                        <p className="text-xs text-zinc-400">Calculate a settlement for a departing employee.</p>
                    </CardContent>
                </Card>
            ) : (
                <Card>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Employee</TableHead>
                                    <TableHead>Gross</TableHead>
                                    <TableHead>Deductions</TableHead>
                                    <TableHead>Net Amount</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {settlements.map((settlement) => (
                                    <TableRow key={settlement.id}>
                                        <TableCell>
                                            <div>
                                                <p className="text-sm font-medium text-zinc-900">
                                                    {settlement.employee?.full_name || '-'}
                                                </p>
                                                <p className="text-xs text-zinc-500">
                                                    {settlement.employee?.employee_id || ''}
                                                </p>
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-sm font-medium">
                                            {formatCurrency(settlement.gross_amount)}
                                        </TableCell>
                                        <TableCell className="text-sm text-red-600">
                                            {formatCurrency(settlement.total_deductions)}
                                        </TableCell>
                                        <TableCell className="text-sm font-semibold text-emerald-600">
                                            {formatCurrency(settlement.net_amount)}
                                        </TableCell>
                                        <TableCell>{getStatusBadge(settlement.status)}</TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                <Link to={`/offboarding/settlements/${settlement.id}`}>
                                                    <Button variant="ghost" size="sm">
                                                        <Eye className="h-4 w-4" />
                                                    </Button>
                                                </Link>
                                                {settlement.status === 'calculated' && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => setConfirmDialog({
                                                            open: true,
                                                            action: 'approve',
                                                            id: settlement.id,
                                                        })}
                                                        className="text-emerald-600 hover:text-emerald-700"
                                                    >
                                                        <CheckCircle className="h-4 w-4" />
                                                    </Button>
                                                )}
                                                {settlement.status === 'approved' && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => setConfirmDialog({
                                                            open: true,
                                                            action: 'mark_paid',
                                                            id: settlement.id,
                                                        })}
                                                        className="text-blue-600 hover:text-blue-700"
                                                    >
                                                        <Banknote className="h-4 w-4" />
                                                    </Button>
                                                )}
                                                {(settlement.status === 'approved' || settlement.status === 'paid') && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => handleDownload(settlement.id, settlement.employee?.full_name)}
                                                        disabled={downloadingId === settlement.id}
                                                    >
                                                        {downloadingId === settlement.id ? (
                                                            <Loader2 className="h-4 w-4 animate-spin" />
                                                        ) : (
                                                            <Download className="h-4 w-4" />
                                                        )}
                                                    </Button>
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            )}

            {/* Calculate Settlement Dialog */}
            <Dialog open={calculateDialog} onOpenChange={setCalculateDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Calculate Final Settlement</DialogTitle>
                        <DialogDescription>
                            Select an employee and their final working date to calculate settlement.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Employee</label>
                            <select
                                value={selectedEmployeeId}
                                onChange={(e) => setSelectedEmployeeId(e.target.value)}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                            >
                                <option value="">Select employee...</option>
                                {employees.map((emp) => (
                                    <option key={emp.id} value={emp.id}>
                                        {emp.full_name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Final Last Working Date</label>
                            <input
                                type="date"
                                value={finalLastDate}
                                onChange={(e) => setFinalLastDate(e.target.value)}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                            />
                        </div>
                        {calculateMutation.isError && (
                            <p className="text-sm text-red-600">
                                {calculateMutation.error?.response?.data?.message || 'Failed to calculate settlement.'}
                            </p>
                        )}
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setCalculateDialog(false)}>
                            Cancel
                        </Button>
                        <Button
                            onClick={() => calculateMutation.mutate({
                                employeeId: selectedEmployeeId,
                                data: { final_last_date: finalLastDate },
                            })}
                            disabled={calculateMutation.isPending || !selectedEmployeeId || !finalLastDate}
                        >
                            {calculateMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                            Calculate
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Confirm Action Dialog */}
            <Dialog open={confirmDialog.open} onOpenChange={() => setConfirmDialog({ open: false, action: null, id: null })}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Confirm Action</DialogTitle>
                        <DialogDescription>
                            {confirmDialog.action === 'approve' && 'Are you sure you want to approve this final settlement?'}
                            {confirmDialog.action === 'mark_paid' && 'Are you sure you want to mark this settlement as paid? This action cannot be undone.'}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setConfirmDialog({ open: false, action: null, id: null })}
                        >
                            Cancel
                        </Button>
                        <Button onClick={handleConfirmAction} disabled={isAnyPending}>
                            {isAnyPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                            Confirm
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
