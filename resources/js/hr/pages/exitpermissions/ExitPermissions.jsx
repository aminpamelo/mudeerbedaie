import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Check, X, Download, DoorOpen, AlertCircle, Loader2 } from 'lucide-react';
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
    DialogFooter,
} from '../../components/ui/dialog';
import {
    Select,
    SelectTrigger,
    SelectContent,
    SelectItem,
    SelectValue,
} from '../../components/ui/select';
import PageHeader from '../../components/PageHeader';
import {
    getExitPermissions,
    adminApproveExitPermission,
    adminRejectExitPermission,
    downloadExitPermissionPdf,
} from '../../lib/api';

const TABS = ['all', 'pending', 'approved', 'rejected', 'cancelled'];

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' });
}

const STATUS_COLORS = {
    pending: 'bg-amber-100 text-amber-800',
    approved: 'bg-emerald-100 text-emerald-800',
    rejected: 'bg-red-100 text-red-800',
    cancelled: 'bg-gray-100 text-gray-600',
};

const ERRAND_TYPE_COLORS = {
    company: 'bg-blue-100 text-blue-800',
    personal: 'bg-purple-100 text-purple-800',
};

const ERRAND_TYPE_LABELS = {
    company: 'Company Business',
    personal: 'Personal Business',
};

function SkeletonTable() {
    return (
        <div className="space-y-3 p-4">
            {Array.from({ length: 5 }).map((_, i) => (
                <div key={i} className="flex items-center gap-4 py-3">
                    <div className="h-4 w-24 animate-pulse rounded bg-zinc-200" />
                    <div className="flex-1 space-y-2">
                        <div className="h-4 w-40 animate-pulse rounded bg-zinc-200" />
                        <div className="h-3 w-28 animate-pulse rounded bg-zinc-200" />
                    </div>
                    <div className="h-4 w-20 animate-pulse rounded bg-zinc-200" />
                    <div className="h-4 w-20 animate-pulse rounded bg-zinc-200" />
                </div>
            ))}
        </div>
    );
}

export default function ExitPermissions() {
    const queryClient = useQueryClient();
    const [tab, setTab] = useState('all');
    const [errandFilter, setErrandFilter] = useState('all');
    const [rejectDialog, setRejectDialog] = useState(null);
    const [rejectReason, setRejectReason] = useState('');
    const [actionError, setActionError] = useState('');
    const [downloadingId, setDownloadingId] = useState(null);

    const params = {};
    if (tab !== 'all') {
        params.status = tab;
    }
    if (errandFilter !== 'all') {
        params.errand_type = errandFilter;
    }

    const { data, isLoading } = useQuery({
        queryKey: ['exit-permissions', tab, errandFilter],
        queryFn: () => getExitPermissions(params),
    });

    const approveMut = useMutation({
        mutationFn: (id) => adminApproveExitPermission(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['exit-permissions'] });
        },
        onError: (err) => setActionError(err.response?.data?.message ?? 'Failed to approve.'),
    });

    const rejectMut = useMutation({
        mutationFn: ({ id, reason }) =>
            adminRejectExitPermission(id, { rejection_reason: reason }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['exit-permissions'] });
            setRejectDialog(null);
            setRejectReason('');
            setActionError('');
        },
        onError: (err) => setActionError(err.response?.data?.message ?? 'Failed to reject.'),
    });

    const records = data?.data?.data ?? [];

    async function handleDownloadPdf(record) {
        setDownloadingId(record.id);
        try {
            const blob = await downloadExitPermissionPdf(record.id);
            const url = window.URL.createObjectURL(new Blob([blob], { type: 'application/pdf' }));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', `${record.permission_number ?? `EP-${record.id}`}.pdf`);
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);
        } catch (e) {
            console.error('PDF download failed', e);
        } finally {
            setDownloadingId(null);
        }
    }

    function openRejectDialog(record) {
        setRejectDialog({ id: record.id, name: record.employee?.name });
        setRejectReason('');
        setActionError('');
    }

    return (
        <div className="space-y-6">
            <PageHeader
                title="Exit Permissions"
                description="Review and manage employee office exit permission requests"
            />

            {/* Filters */}
            <div className="flex flex-wrap items-center gap-3">
                {/* Status tabs */}
                <div className="flex gap-1 overflow-x-auto rounded-lg bg-slate-100 p-1">
                    {TABS.map((t) => (
                        <button
                            key={t}
                            onClick={() => setTab(t)}
                            className={`flex-shrink-0 rounded-md px-3 py-1.5 text-sm font-medium capitalize transition-colors ${
                                tab === t
                                    ? 'bg-white text-slate-800 shadow-sm'
                                    : 'text-slate-500 hover:text-slate-700'
                            }`}
                        >
                            {t}
                        </button>
                    ))}
                </div>

                {/* Errand type filter */}
                <Select value={errandFilter} onValueChange={setErrandFilter}>
                    <SelectTrigger className="w-48">
                        <SelectValue placeholder="Errand Type" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Errand Types</SelectItem>
                        <SelectItem value="company">Company Business</SelectItem>
                        <SelectItem value="personal">Personal Business</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            {/* Table */}
            <Card>
                <CardContent className="p-0">
                    {isLoading ? (
                        <SkeletonTable />
                    ) : records.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-16 text-center">
                            <DoorOpen className="mb-3 h-10 w-10 text-zinc-300" />
                            <p className="text-sm font-medium text-zinc-500">No exit permission requests</p>
                            <p className="text-xs text-zinc-400">
                                {tab === 'all'
                                    ? 'No requests have been submitted yet'
                                    : `No ${tab} requests found`}
                            </p>
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Permission No</TableHead>
                                    <TableHead>Employee</TableHead>
                                    <TableHead>Date</TableHead>
                                    <TableHead>Time</TableHead>
                                    <TableHead>Errand Type</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="w-32">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {records.map((record) => (
                                    <TableRow key={record.id}>
                                        <TableCell>
                                            <span className="font-mono text-sm text-zinc-600">
                                                {record.permission_number ?? `EP-${record.id}`}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            <p className="text-sm font-medium text-zinc-900">
                                                {record.employee?.full_name}
                                            </p>
                                            <p className="text-xs text-zinc-400">
                                                {record.employee?.department?.name}
                                            </p>
                                        </TableCell>
                                        <TableCell>
                                            <span className="text-sm text-zinc-700">{formatDate(record.exit_date)}</span>
                                        </TableCell>
                                        <TableCell>
                                            <span className="text-sm text-zinc-700">
                                                {record.exit_time} &rarr; {record.return_time}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            <span
                                                className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                                    ERRAND_TYPE_COLORS[record.errand_type] ?? 'bg-zinc-100 text-zinc-600'
                                                }`}
                                            >
                                                {ERRAND_TYPE_LABELS[record.errand_type] ?? record.errand_type}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            <span
                                                className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize ${
                                                    STATUS_COLORS[record.status] ?? 'bg-zinc-100 text-zinc-600'
                                                }`}
                                            >
                                                {record.status}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-1">
                                                {record.status === 'pending' && (
                                                    <>
                                                        <button
                                                            onClick={() => approveMut.mutate(record.id)}
                                                            disabled={approveMut.isPending}
                                                            title="Approve"
                                                            className="flex items-center gap-1 rounded-lg bg-emerald-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
                                                        >
                                                            <Check className="h-3.5 w-3.5" />
                                                            Approve
                                                        </button>
                                                        <button
                                                            onClick={() => openRejectDialog(record)}
                                                            title="Reject"
                                                            className="flex items-center gap-1 rounded-lg border border-red-200 px-2.5 py-1 text-xs font-medium text-red-600 hover:bg-red-50"
                                                        >
                                                            <X className="h-3.5 w-3.5" />
                                                            Reject
                                                        </button>
                                                    </>
                                                )}
                                                {record.status === 'approved' && (
                                                    <button
                                                        onClick={() => handleDownloadPdf(record)}
                                                        disabled={downloadingId === record.id}
                                                        title="Download PDF"
                                                        className="flex items-center gap-1 rounded-lg border border-zinc-200 px-2.5 py-1 text-xs font-medium text-zinc-600 hover:bg-zinc-50 disabled:opacity-50"
                                                    >
                                                        {downloadingId === record.id
                                                            ? <Loader2 className="h-3.5 w-3.5 animate-spin" />
                                                            : <Download className="h-3.5 w-3.5" />
                                                        }
                                                        PDF
                                                    </button>
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

            {/* Reject Dialog */}
            <Dialog open={!!rejectDialog} onOpenChange={() => setRejectDialog(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Reject Exit Permission</DialogTitle>
                    </DialogHeader>
                    <p className="text-sm text-slate-500">
                        Rejecting request from <strong>{rejectDialog?.name}</strong>. Please provide a reason.
                    </p>
                    <textarea
                        className="mt-2 w-full rounded-lg border border-slate-200 p-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                        rows={3}
                        placeholder="Reason for rejection (min 5 characters)"
                        value={rejectReason}
                        onChange={(e) => setRejectReason(e.target.value)}
                    />
                    {actionError && (
                        <p className="flex items-center gap-1 text-sm text-red-500">
                            <AlertCircle className="h-4 w-4" /> {actionError}
                        </p>
                    )}
                    <DialogFooter>
                        <button
                            onClick={() => setRejectDialog(null)}
                            className="rounded-lg border border-slate-200 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50"
                        >
                            Cancel
                        </button>
                        <button
                            onClick={() =>
                                rejectMut.mutate({ id: rejectDialog.id, reason: rejectReason })
                            }
                            disabled={rejectReason.length < 5 || rejectMut.isPending}
                            className="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
                        >
                            {rejectMut.isPending ? 'Rejecting...' : 'Confirm Reject'}
                        </button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
