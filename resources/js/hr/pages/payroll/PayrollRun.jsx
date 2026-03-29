import { useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    ChevronLeft,
    Play,
    Send,
    CheckCircle,
    RotateCcw,
    Lock,
    Plus,
    Trash2,
    Loader2,
    RefreshCw,
    DollarSign,
    TrendingDown,
    Wallet,
    Users,
    Download,
    FileDown,
} from 'lucide-react';
import {
    Card,
    CardHeader,
    CardContent,
    CardTitle,
    CardDescription,
} from '../../components/ui/card';
import {
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableHead,
    TableCell,
} from '../../components/ui/table';
import { Button } from '../../components/ui/button';
import { Badge } from '../../components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '../../components/ui/dialog';
import { cn } from '../../lib/utils';
import {
    fetchPayrollRun,
    calculatePayroll,
    calculatePayrollEmployee,
    submitPayrollReview,
    approvePayroll,
    returnPayrollDraft,
    finalizePayroll,
    fetchPayslips,
    addPayrollItem,
    deletePayrollItem,
    fetchSalaryComponents,
    downloadPayslipPdf,
} from '../../lib/api';

const MONTHS = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December',
];

const STATUS_STEPS = ['draft', 'review', 'approved', 'finalized'];

const STATUS_CONFIG = {
    draft: { label: 'Draft', bg: 'bg-zinc-100', text: 'text-zinc-700' },
    review: { label: 'Pending Review', bg: 'bg-amber-100', text: 'text-amber-700' },
    approved: { label: 'Approved', bg: 'bg-emerald-100', text: 'text-emerald-700' },
    finalized: { label: 'Finalized', bg: 'bg-purple-100', text: 'text-purple-700' },
};

function formatCurrency(amount) {
    if (amount == null) return 'RM 0.00';
    return `RM ${parseFloat(amount).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function PayrollStatusBadge({ status }) {
    const config = STATUS_CONFIG[status] || { label: status, bg: 'bg-zinc-100', text: 'text-zinc-700' };
    return (
        <span className={cn('inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold', config.bg, config.text)}>
            {config.label}
        </span>
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

export default function PayrollRun() {
    const { id } = useParams();
    const queryClient = useQueryClient();
    const [adHocDialog, setAdHocDialog] = useState(false);
    const [adHocEmployeeId, setAdHocEmployeeId] = useState(null);
    const [adHocForm, setAdHocForm] = useState({ component_id: '', amount: '', type: 'earning' });
    const [confirmDialog, setConfirmDialog] = useState({ open: false, action: null });
    const [downloadingId, setDownloadingId] = useState(null);
    const [downloadingBulk, setDownloadingBulk] = useState(false);

    const { data: runData, isLoading } = useQuery({
        queryKey: ['hr', 'payroll', 'run', id],
        queryFn: () => fetchPayrollRun(id),
    });

    const { data: payslipsData } = useQuery({
        queryKey: ['hr', 'payroll', 'payslips', id],
        queryFn: () => fetchPayslips({ payroll_run_id: id }),
        enabled: !!id,
    });

    const { data: componentsData } = useQuery({
        queryKey: ['hr', 'payroll', 'components'],
        queryFn: () => fetchSalaryComponents({ is_active: 1 }),
    });

    const run = runData?.data;
    const payslips = payslipsData?.data || [];
    const components = componentsData?.data || [];

    // Group payroll items by employee for the table (available after calculation)
    const employeeSummaries = (() => {
        if (!run?.items?.length) return [];
        const grouped = {};
        for (const item of run.items) {
            const empId = item.employee_id;
            if (!grouped[empId]) {
                grouped[empId] = {
                    employee_id: empId,
                    employee: item.employee,
                    gross: 0,
                    deductions: 0,
                    epf_employee: 0,
                    socso_employee: 0,
                    eis_employee: 0,
                    pcb: 0,
                    items: [],
                };
            }
            grouped[empId].items.push(item);
            const amt = parseFloat(item.amount || 0);
            if (item.type === 'earning') {
                grouped[empId].gross += amt;
            } else if (item.type === 'deduction') {
                grouped[empId].deductions += amt;
                const code = (item.component_code || '').toUpperCase();
                if (code === 'EPF_EE') grouped[empId].epf_employee += amt;
                else if (code === 'SOCSO_EE') grouped[empId].socso_employee += amt;
                else if (code === 'EIS_EE') grouped[empId].eis_employee += amt;
                else if (code === 'PCB') grouped[empId].pcb += amt;
            }
        }
        return Object.values(grouped).map((emp) => ({
            ...emp,
            net: emp.gross - emp.deductions,
        }));
    })();

    const calculateMutation = useMutation({
        mutationFn: () => calculatePayroll(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'payroll', 'run', id] });
            queryClient.invalidateQueries({ queryKey: ['hr', 'payroll', 'payslips', id] });
            setConfirmDialog({ open: false, action: null });
        },
        onError: (err) => {
            console.error('Calculate failed:', err?.response?.status, err?.response?.data || err.message);
            setConfirmDialog({ open: false, action: null });
            alert('Calculate failed: ' + (err?.response?.data?.message || err.message));
        },
    });

    const calcEmployeeMutation = useMutation({
        mutationFn: (empId) => calculatePayrollEmployee(id, empId),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'payroll', 'run', id] });
            queryClient.invalidateQueries({ queryKey: ['hr', 'payroll', 'payslips', id] });
        },
    });

    const submitReviewMutation = useMutation({
        mutationFn: () => submitPayrollReview(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'payroll', 'run', id] });
            setConfirmDialog({ open: false, action: null });
        },
    });

    const approveMutation = useMutation({
        mutationFn: () => approvePayroll(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'payroll', 'run', id] });
            setConfirmDialog({ open: false, action: null });
        },
    });

    const returnDraftMutation = useMutation({
        mutationFn: () => returnPayrollDraft(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'payroll', 'run', id] });
            setConfirmDialog({ open: false, action: null });
        },
    });

    const finalizeMutation = useMutation({
        mutationFn: () => finalizePayroll(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'payroll', 'run', id] });
            setConfirmDialog({ open: false, action: null });
        },
    });

    const addItemMutation = useMutation({
        mutationFn: (data) => addPayrollItem(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'payroll', 'run', id] });
            queryClient.invalidateQueries({ queryKey: ['hr', 'payroll', 'payslips', id] });
            setAdHocDialog(false);
            setAdHocForm({ component_id: '', amount: '', type: 'earning' });
        },
    });

    const deleteItemMutation = useMutation({
        mutationFn: ({ itemId }) => deletePayrollItem(id, itemId),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'payroll', 'run', id] });
            queryClient.invalidateQueries({ queryKey: ['hr', 'payroll', 'payslips', id] });
        },
    });

    async function handleDownloadPayslip(payslipId, employeeName) {
        setDownloadingId(payslipId);
        try {
            const blob = await downloadPayslipPdf(payslipId);
            const url = window.URL.createObjectURL(new Blob([blob]));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', `Payslip_${employeeName?.replace(/\s+/g, '_') || payslipId}.pdf`);
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);
        } catch (err) {
            console.error('Download failed:', err);
            alert('Failed to download payslip: ' + (err?.response?.data?.message || err.message));
        } finally {
            setDownloadingId(null);
        }
    }

    async function handleDownloadAll() {
        setDownloadingBulk(true);
        try {
            const { downloadBulkPayslipsPdf } = await import('../../lib/api');
            const blob = await downloadBulkPayslipsPdf(id);
            const url = window.URL.createObjectURL(new Blob([blob]));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', `Payslips_${run?.year}_${String(run?.month).padStart(2, '0')}.zip`);
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);
        } catch (err) {
            console.error('Bulk download failed:', err);
            alert('Failed to download: ' + (err?.response?.data?.message || err.message));
        } finally {
            setDownloadingBulk(false);
        }
    }

    function openAdHoc(employeeId) {
        setAdHocEmployeeId(employeeId);
        setAdHocForm({ component_id: '', amount: '', type: 'earning' });
        setAdHocDialog(true);
    }

    function handleAction(action) {
        setConfirmDialog({ open: true, action });
    }

    function confirmAction() {
        const { action } = confirmDialog;
        if (action === 'calculate') calculateMutation.mutate();
        else if (action === 'submit_review') submitReviewMutation.mutate();
        else if (action === 'approve') approveMutation.mutate();
        else if (action === 'return_draft') returnDraftMutation.mutate();
        else if (action === 'finalize') finalizeMutation.mutate();
    }

    const isAnyPending = [
        calculateMutation, submitReviewMutation, approveMutation, returnDraftMutation, finalizeMutation,
    ].some((m) => m.isPending);

    if (isLoading) {
        return (
            <div className="flex items-center justify-center py-24">
                <Loader2 className="h-8 w-8 animate-spin text-zinc-400" />
            </div>
        );
    }

    if (!run) {
        return (
            <div className="flex flex-col items-center justify-center py-24">
                <p className="text-sm text-zinc-500">Payroll run not found.</p>
                <Link to="/payroll" className="mt-3">
                    <Button variant="outline" size="sm">Back to Dashboard</Button>
                </Link>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <Link to="/payroll">
                        <Button variant="ghost" size="sm">
                            <ChevronLeft className="mr-1 h-4 w-4" />
                            Back
                        </Button>
                    </Link>
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-xl font-semibold text-zinc-900">
                                {MONTHS[run.month - 1]} {run.year} Payroll
                            </h1>
                            <PayrollStatusBadge status={run.status} />
                        </div>
                        <p className="text-sm text-zinc-500">
                            {run.employee_count ?? 0} employees &middot; Created by {run.prepared_by_name || 'System'}
                        </p>
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    {run.status === 'draft' && (run.employee_count ?? 0) === 0 && (
                        <Button onClick={() => handleAction('calculate')} disabled={isAnyPending}>
                            <Play className="mr-2 h-4 w-4" />
                            Calculate
                        </Button>
                    )}
                    {run.status === 'draft' && (run.employee_count ?? 0) > 0 && (
                        <>
                            <Button variant="outline" onClick={() => handleAction('calculate')} disabled={isAnyPending}>
                                <RefreshCw className="mr-2 h-4 w-4" />
                                Recalculate
                            </Button>
                            <Button onClick={() => handleAction('submit_review')} disabled={isAnyPending}>
                                <Send className="mr-2 h-4 w-4" />
                                Submit for Review
                            </Button>
                        </>
                    )}
                    {run.status === 'review' && (
                        <>
                            <Button variant="outline" onClick={() => handleAction('return_draft')} disabled={isAnyPending}>
                                <RotateCcw className="mr-2 h-4 w-4" />
                                Return to Draft
                            </Button>
                            <Button onClick={() => handleAction('approve')} disabled={isAnyPending}>
                                <CheckCircle className="mr-2 h-4 w-4" />
                                Approve
                            </Button>
                        </>
                    )}
                    {run.status === 'approved' && (
                        <Button onClick={() => handleAction('finalize')} disabled={isAnyPending}>
                            <Lock className="mr-2 h-4 w-4" />
                            Finalize
                        </Button>
                    )}
                </div>
            </div>

            {/* Summary Cards */}
            <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <SummaryCard
                    title="Total Gross"
                    value={formatCurrency(run.total_gross)}
                    icon={DollarSign}
                    iconColor="text-blue-600"
                    iconBg="bg-blue-50"
                />
                <SummaryCard
                    title="Total Deductions"
                    value={formatCurrency(run.total_deductions)}
                    icon={TrendingDown}
                    iconColor="text-red-600"
                    iconBg="bg-red-50"
                />
                <SummaryCard
                    title="Total Net Pay"
                    value={formatCurrency(run.total_net)}
                    icon={Wallet}
                    iconColor="text-emerald-600"
                    iconBg="bg-emerald-50"
                />
                <SummaryCard
                    title="Employees"
                    value={run.employee_count ?? 0}
                    icon={Users}
                    iconColor="text-purple-600"
                    iconBg="bg-purple-50"
                />
            </div>

            {/* Employee Payroll Table */}
            <Card>
                <CardHeader>
                    <div className="flex items-center justify-between">
                        <div>
                            <CardTitle>Employee Payroll</CardTitle>
                            <CardDescription>
                                {run.status === 'finalized' ? 'Finalized payslip details' : 'Individual payroll details for this run'}
                            </CardDescription>
                        </div>
                        {run.status === 'finalized' && payslips.length > 0 && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={handleDownloadAll}
                                disabled={downloadingBulk}
                            >
                                {downloadingBulk ? (
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                ) : (
                                    <FileDown className="mr-2 h-4 w-4" />
                                )}
                                Download All
                            </Button>
                        )}
                    </div>
                </CardHeader>
                <CardContent>
                    {payslips.length === 0 && employeeSummaries.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-12 text-center">
                            <Users className="mb-3 h-10 w-10 text-zinc-300" />
                            <p className="text-sm font-medium text-zinc-500">No payroll data yet</p>
                            <p className="text-xs text-zinc-400">Click "Calculate" to generate payroll</p>
                        </div>
                    ) : payslips.length > 0 ? (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Employee</TableHead>
                                    <TableHead>Gross</TableHead>
                                    <TableHead>EPF (EE)</TableHead>
                                    <TableHead>SOCSO (EE)</TableHead>
                                    <TableHead>PCB</TableHead>
                                    <TableHead>Net Pay</TableHead>
                                    <TableHead className="text-right">Payslip</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {payslips.map((payslip) => (
                                    <TableRow key={payslip.id}>
                                        <TableCell>
                                            <div>
                                                <p className="text-sm font-medium text-zinc-900">
                                                    {payslip.employee?.full_name || '-'}
                                                </p>
                                                <p className="text-xs text-zinc-500">{payslip.employee?.employee_id || ''}</p>
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-sm font-medium">{formatCurrency(payslip.gross_salary)}</TableCell>
                                        <TableCell className="text-sm text-zinc-600">{formatCurrency(payslip.epf_employee)}</TableCell>
                                        <TableCell className="text-sm text-zinc-600">{formatCurrency(payslip.socso_employee)}</TableCell>
                                        <TableCell className="text-sm text-zinc-600">{formatCurrency(payslip.pcb_amount)}</TableCell>
                                        <TableCell className="text-sm font-semibold text-emerald-600">{formatCurrency(payslip.net_salary)}</TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => handleDownloadPayslip(payslip.id, payslip.employee?.full_name)}
                                                disabled={downloadingId === payslip.id}
                                            >
                                                {downloadingId === payslip.id ? (
                                                    <Loader2 className="h-4 w-4 animate-spin" />
                                                ) : (
                                                    <Download className="h-4 w-4" />
                                                )}
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Employee</TableHead>
                                    <TableHead>Gross</TableHead>
                                    <TableHead>EPF (EE)</TableHead>
                                    <TableHead>SOCSO (EE)</TableHead>
                                    <TableHead>PCB</TableHead>
                                    <TableHead>Net Pay</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {employeeSummaries.map((emp) => (
                                    <TableRow key={emp.employee_id}>
                                        <TableCell>
                                            <div>
                                                <p className="text-sm font-medium text-zinc-900">
                                                    {emp.employee?.full_name || '-'}
                                                </p>
                                                <p className="text-xs text-zinc-500">{emp.employee?.employee_id || ''}</p>
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-sm font-medium">{formatCurrency(emp.gross)}</TableCell>
                                        <TableCell className="text-sm text-zinc-600">{formatCurrency(emp.epf_employee)}</TableCell>
                                        <TableCell className="text-sm text-zinc-600">{formatCurrency(emp.socso_employee)}</TableCell>
                                        <TableCell className="text-sm text-zinc-600">{formatCurrency(emp.pcb)}</TableCell>
                                        <TableCell className="text-sm font-semibold text-emerald-600">{formatCurrency(emp.net)}</TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                {run.status !== 'finalized' && (
                                                    <>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => openAdHoc(emp.employee_id)}
                                                        >
                                                            <Plus className="h-4 w-4" />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => calcEmployeeMutation.mutate(emp.employee_id)}
                                                            disabled={calcEmployeeMutation.isPending}
                                                        >
                                                            <RefreshCw className="h-4 w-4" />
                                                        </Button>
                                                    </>
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

            {/* Add Ad-Hoc Item Dialog */}
            <Dialog open={adHocDialog} onOpenChange={setAdHocDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Add Ad-Hoc Item</DialogTitle>
                        <DialogDescription>Add a one-time earning or deduction for this employee.</DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Type</label>
                            <select
                                value={adHocForm.type}
                                onChange={(e) => setAdHocForm((p) => ({ ...p, type: e.target.value }))}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                            >
                                <option value="earning">Earning</option>
                                <option value="deduction">Deduction</option>
                            </select>
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Salary Component</label>
                            <select
                                value={adHocForm.component_id}
                                onChange={(e) => setAdHocForm((p) => ({ ...p, component_id: e.target.value }))}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                            >
                                <option value="">Select component...</option>
                                {components
                                    .filter((c) => c.type === adHocForm.type)
                                    .map((c) => (
                                        <option key={c.id} value={c.id}>{c.name}</option>
                                    ))}
                            </select>
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Amount (RM)</label>
                            <input
                                type="number"
                                min="0"
                                step="0.01"
                                value={adHocForm.amount}
                                onChange={(e) => setAdHocForm((p) => ({ ...p, amount: e.target.value }))}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                                placeholder="0.00"
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setAdHocDialog(false)}>Cancel</Button>
                        <Button
                            onClick={() => addItemMutation.mutate({
                                employee_id: adHocEmployeeId,
                                salary_component_id: adHocForm.component_id,
                                amount: adHocForm.amount,
                                type: adHocForm.type,
                            })}
                            disabled={addItemMutation.isPending || !adHocForm.component_id || !adHocForm.amount}
                        >
                            {addItemMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                            Add Item
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Confirm Action Dialog */}
            <Dialog open={confirmDialog.open} onOpenChange={() => setConfirmDialog({ open: false, action: null })}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Confirm Action</DialogTitle>
                        <DialogDescription>
                            {confirmDialog.action === 'calculate' && 'Calculate payroll for all active employees?'}
                            {confirmDialog.action === 'submit_review' && 'Submit this payroll run for review? You will not be able to make changes after this.'}
                            {confirmDialog.action === 'approve' && 'Approve this payroll run?'}
                            {confirmDialog.action === 'return_draft' && 'Return this payroll run to draft status?'}
                            {confirmDialog.action === 'finalize' && 'Finalize this payroll run? This action cannot be undone and payslips will be locked.'}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setConfirmDialog({ open: false, action: null })}>
                            Cancel
                        </Button>
                        <Button
                            variant={confirmDialog.action === 'finalize' ? 'default' : 'default'}
                            onClick={confirmAction}
                            disabled={isAnyPending}
                        >
                            {isAnyPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                            Confirm
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
