import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import {
    Plus,
    Eye,
    CheckCircle,
    XCircle,
    Loader2,
    FileText,
} from 'lucide-react';
import {
    fetchResignations,
    createResignation,
    approveResignation,
    rejectResignation,
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
    { value: 'pending', label: 'Pending' },
    { value: 'approved', label: 'Approved' },
    { value: 'rejected', label: 'Rejected' },
    { value: 'completed', label: 'Completed' },
];

const STATUS_CONFIG = {
    pending: { label: 'Pending', variant: 'warning' },
    approved: { label: 'Approved', variant: 'success' },
    rejected: { label: 'Rejected', variant: 'destructive' },
    completed: { label: 'Completed', variant: 'secondary' },
};

function formatDate(dateString) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('en-MY', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

export default function ResignationRequests() {
    const queryClient = useQueryClient();
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [createDialog, setCreateDialog] = useState(false);
    const [approveDialog, setApproveDialog] = useState({ open: false, id: null });
    const [rejectDialog, setRejectDialog] = useState({ open: false, id: null });
    const [approveNotes, setApproveNotes] = useState('');
    const [rejectReason, setRejectReason] = useState('');
    const [form, setForm] = useState({
        employee_id: '',
        submitted_date: new Date().toISOString().split('T')[0],
        reason: '',
    });

    const params = {
        search: search || undefined,
        status: statusFilter !== 'all' ? statusFilter : undefined,
    };

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'offboarding', 'resignations', params],
        queryFn: () => fetchResignations(params),
    });

    const { data: employeesData } = useQuery({
        queryKey: ['hr', 'employees', 'list'],
        queryFn: () => fetchEmployees({ per_page: 200 }),
    });

    const createMutation = useMutation({
        mutationFn: (data) => createResignation(data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'offboarding', 'resignations'] });
            setCreateDialog(false);
            setForm({ employee_id: '', submitted_date: new Date().toISOString().split('T')[0], reason: '' });
        },
    });

    const approveMutation = useMutation({
        mutationFn: ({ id, data }) => approveResignation(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'offboarding', 'resignations'] });
            setApproveDialog({ open: false, id: null });
            setApproveNotes('');
        },
    });

    const rejectMutation = useMutation({
        mutationFn: ({ id, data }) => rejectResignation(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'offboarding', 'resignations'] });
            setRejectDialog({ open: false, id: null });
            setRejectReason('');
        },
    });

    const resignations = data?.data || [];
    const employees = employeesData?.data || [];

    function handleCreate() {
        createMutation.mutate({
            employee_id: form.employee_id,
            submitted_date: form.submitted_date,
            reason: form.reason,
        });
    }

    function handleApprove() {
        approveMutation.mutate({
            id: approveDialog.id,
            data: { notes: approveNotes },
        });
    }

    function handleReject() {
        rejectMutation.mutate({
            id: rejectDialog.id,
            data: { reason: rejectReason },
        });
    }

    function getStatusBadge(status) {
        const config = STATUS_CONFIG[status] || { label: status, variant: 'secondary' };
        return <Badge variant={config.variant}>{config.label}</Badge>;
    }

    return (
        <div>
            <PageHeader
                title="Resignation Requests"
                description="Manage employee resignation submissions and approvals."
                action={
                    <Button onClick={() => setCreateDialog(true)}>
                        <Plus className="mr-2 h-4 w-4" />
                        New Resignation
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
            ) : resignations.length === 0 ? (
                <Card>
                    <CardContent className="flex flex-col items-center justify-center py-16">
                        <FileText className="mb-3 h-10 w-10 text-zinc-300" />
                        <p className="text-sm font-medium text-zinc-500">No resignation requests found</p>
                        <p className="text-xs text-zinc-400">Create a new resignation request to get started.</p>
                    </CardContent>
                </Card>
            ) : (
                <Card>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Employee</TableHead>
                                    <TableHead>Submitted Date</TableHead>
                                    <TableHead>Reason</TableHead>
                                    <TableHead>Notice Period</TableHead>
                                    <TableHead>Last Working Date</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {resignations.map((resignation) => (
                                    <TableRow key={resignation.id}>
                                        <TableCell>
                                            <div>
                                                <p className="text-sm font-medium text-zinc-900">
                                                    {resignation.employee?.full_name || '-'}
                                                </p>
                                                <p className="text-xs text-zinc-500">
                                                    {resignation.employee?.employee_id || ''}
                                                </p>
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {formatDate(resignation.submitted_date)}
                                        </TableCell>
                                        <TableCell className="max-w-[200px] text-sm text-zinc-600">
                                            <p className="truncate">{resignation.reason || '-'}</p>
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {resignation.notice_period ? `${resignation.notice_period} days` : '-'}
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {formatDate(resignation.last_working_date)}
                                        </TableCell>
                                        <TableCell>{getStatusBadge(resignation.status)}</TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                <Link to={`/offboarding/resignations/${resignation.id}`}>
                                                    <Button variant="ghost" size="sm">
                                                        <Eye className="h-4 w-4" />
                                                    </Button>
                                                </Link>
                                                {resignation.status === 'pending' && (
                                                    <>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => {
                                                                setApproveNotes('');
                                                                setApproveDialog({ open: true, id: resignation.id });
                                                            }}
                                                            className="text-emerald-600 hover:text-emerald-700"
                                                        >
                                                            <CheckCircle className="h-4 w-4" />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => {
                                                                setRejectReason('');
                                                                setRejectDialog({ open: true, id: resignation.id });
                                                            }}
                                                            className="text-red-600 hover:text-red-700"
                                                        >
                                                            <XCircle className="h-4 w-4" />
                                                        </Button>
                                                    </>
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

            {/* Create Resignation Dialog */}
            <Dialog open={createDialog} onOpenChange={setCreateDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>New Resignation Request</DialogTitle>
                        <DialogDescription>Submit a resignation request for an employee.</DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Employee</label>
                            <select
                                value={form.employee_id}
                                onChange={(e) => setForm((p) => ({ ...p, employee_id: e.target.value }))}
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
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Submitted Date</label>
                            <input
                                type="date"
                                value={form.submitted_date}
                                onChange={(e) => setForm((p) => ({ ...p, submitted_date: e.target.value }))}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                            />
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Reason</label>
                            <textarea
                                value={form.reason}
                                onChange={(e) => setForm((p) => ({ ...p, reason: e.target.value }))}
                                rows={3}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                                placeholder="Reason for resignation..."
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setCreateDialog(false)}>
                            Cancel
                        </Button>
                        <Button
                            onClick={handleCreate}
                            disabled={createMutation.isPending || !form.employee_id || !form.reason}
                        >
                            {createMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                            Submit
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Approve Dialog */}
            <Dialog open={approveDialog.open} onOpenChange={() => setApproveDialog({ open: false, id: null })}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Approve Resignation</DialogTitle>
                        <DialogDescription>Approve this resignation request. You may add optional notes.</DialogDescription>
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
                        <Button variant="outline" onClick={() => setApproveDialog({ open: false, id: null })}>
                            Cancel
                        </Button>
                        <Button onClick={handleApprove} disabled={approveMutation.isPending}>
                            {approveMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                            Approve
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Reject Dialog */}
            <Dialog open={rejectDialog.open} onOpenChange={() => setRejectDialog({ open: false, id: null })}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Reject Resignation</DialogTitle>
                        <DialogDescription>Provide a reason for rejecting this resignation request.</DialogDescription>
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
                        <Button variant="outline" onClick={() => setRejectDialog({ open: false, id: null })}>
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={handleReject}
                            disabled={rejectMutation.isPending || !rejectReason.trim()}
                        >
                            {rejectMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                            Reject
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
