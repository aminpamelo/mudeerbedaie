import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import {
    Search,
    Eye,
    Send,
    XCircle,
    Download,
    Loader2,
    AlertTriangle,
    ChevronLeft,
    ChevronRight,
} from 'lucide-react';
import {
    fetchDisciplinaryActions,
    issueDisciplinaryAction,
    closeDisciplinaryAction,
    downloadDisciplinaryPdf,
} from '../../lib/api';
import PageHeader from '../../components/PageHeader';
import { Button } from '../../components/ui/button';
import { Card, CardContent } from '../../components/ui/card';
import { Input } from '../../components/ui/input';
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
import { cn } from '../../lib/utils';

const STATUS_CONFIG = {
    draft: { label: 'Draft', bg: 'bg-zinc-100', text: 'text-zinc-700' },
    issued: { label: 'Issued', bg: 'bg-blue-100', text: 'text-blue-700' },
    pending_response: { label: 'Pending Response', bg: 'bg-amber-100', text: 'text-amber-700' },
    responded: { label: 'Responded', bg: 'bg-purple-100', text: 'text-purple-700' },
    closed: { label: 'Closed', bg: 'bg-zinc-100', text: 'text-zinc-700' },
};

const TYPE_OPTIONS = [
    { value: 'all', label: 'All Types' },
    { value: 'verbal_warning', label: 'Verbal Warning' },
    { value: 'first_written', label: '1st Written Warning' },
    { value: 'second_written', label: '2nd Written Warning' },
    { value: 'show_cause', label: 'Show Cause' },
    { value: 'termination', label: 'Termination' },
];

const STATUS_OPTIONS = [
    { value: 'all', label: 'All Statuses' },
    { value: 'draft', label: 'Draft' },
    { value: 'issued', label: 'Issued' },
    { value: 'pending_response', label: 'Pending Response' },
    { value: 'responded', label: 'Responded' },
    { value: 'closed', label: 'Closed' },
];

const TYPE_LABELS = {
    verbal_warning: 'Verbal Warning',
    first_written: '1st Written Warning',
    second_written: '2nd Written Warning',
    show_cause: 'Show Cause',
    termination: 'Termination',
};

function StatusBadge({ status }) {
    const config = STATUS_CONFIG[status] || { label: status, bg: 'bg-zinc-100', text: 'text-zinc-700' };
    return (
        <span className={cn('inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold', config.bg, config.text)}>
            {config.label}
        </span>
    );
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-MY', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

export default function DisciplinaryRecords() {
    const queryClient = useQueryClient();
    const [search, setSearch] = useState('');
    const [typeFilter, setTypeFilter] = useState('all');
    const [statusFilter, setStatusFilter] = useState('all');
    const [page, setPage] = useState(1);
    const [confirmDialog, setConfirmDialog] = useState({ open: false, action: null, id: null });
    const [downloadingId, setDownloadingId] = useState(null);

    const params = {
        page,
        per_page: 15,
        search: search || undefined,
        type: typeFilter !== 'all' ? typeFilter : undefined,
        status: statusFilter !== 'all' ? statusFilter : undefined,
        sort: '-created_at',
    };

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'disciplinary', 'actions', params],
        queryFn: () => fetchDisciplinaryActions(params),
    });

    const actions = data?.data || [];
    const meta = data?.meta || {};

    const issueMutation = useMutation({
        mutationFn: (id) => issueDisciplinaryAction(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'disciplinary'] });
            setConfirmDialog({ open: false, action: null, id: null });
        },
        onError: (err) => {
            alert('Failed to issue action: ' + (err?.response?.data?.message || err.message));
            setConfirmDialog({ open: false, action: null, id: null });
        },
    });

    const closeMutation = useMutation({
        mutationFn: (id) => closeDisciplinaryAction(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'disciplinary'] });
            setConfirmDialog({ open: false, action: null, id: null });
        },
        onError: (err) => {
            alert('Failed to close action: ' + (err?.response?.data?.message || err.message));
            setConfirmDialog({ open: false, action: null, id: null });
        },
    });

    async function handleDownloadPdf(id, refNumber) {
        setDownloadingId(id);
        try {
            const blob = await downloadDisciplinaryPdf(id);
            const url = window.URL.createObjectURL(new Blob([blob]));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', `Disciplinary_${refNumber || id}.pdf`);
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);
        } catch (err) {
            alert('Failed to download PDF: ' + (err?.response?.data?.message || err.message));
        } finally {
            setDownloadingId(null);
        }
    }

    function handleConfirm() {
        if (confirmDialog.action === 'issue') {
            issueMutation.mutate(confirmDialog.id);
        } else if (confirmDialog.action === 'close') {
            closeMutation.mutate(confirmDialog.id);
        }
    }

    const isAnyPending = issueMutation.isPending || closeMutation.isPending;

    return (
        <div>
            <PageHeader
                title="Disciplinary Records"
                description="View and manage all disciplinary actions."
                action={
                    <Link to="/disciplinary/actions/create">
                        <Button>
                            New Action
                        </Button>
                    </Link>
                }
            />

            {/* Filters */}
            <Card className="mb-6">
                <CardContent className="p-4">
                    <div className="flex flex-wrap items-end gap-4">
                        <div className="flex-1">
                            <label className="mb-1 block text-xs font-medium text-zinc-600">Search</label>
                            <div className="relative">
                                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                                <Input
                                    placeholder="Search by employee name..."
                                    value={search}
                                    onChange={(e) => {
                                        setSearch(e.target.value);
                                        setPage(1);
                                    }}
                                    className="pl-9"
                                />
                            </div>
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-zinc-600">Type</label>
                            <Select value={typeFilter} onValueChange={(v) => { setTypeFilter(v); setPage(1); }}>
                                <SelectTrigger className="w-44">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {TYPE_OPTIONS.map((opt) => (
                                        <SelectItem key={opt.value} value={opt.value}>{opt.label}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-zinc-600">Status</label>
                            <Select value={statusFilter} onValueChange={(v) => { setStatusFilter(v); setPage(1); }}>
                                <SelectTrigger className="w-44">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {STATUS_OPTIONS.map((opt) => (
                                        <SelectItem key={opt.value} value={opt.value}>{opt.label}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Table */}
            <Card>
                <CardContent className="p-0">
                    {isLoading ? (
                        <div className="flex justify-center py-16">
                            <Loader2 className="h-6 w-6 animate-spin text-zinc-400" />
                        </div>
                    ) : actions.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-16 text-center">
                            <AlertTriangle className="mb-3 h-10 w-10 text-zinc-300" />
                            <p className="text-sm font-medium text-zinc-500">No disciplinary records found</p>
                            <p className="text-xs text-zinc-400">Try adjusting your filters or create a new action</p>
                        </div>
                    ) : (
                        <>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Reference #</TableHead>
                                        <TableHead>Employee</TableHead>
                                        <TableHead>Type</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Incident Date</TableHead>
                                        <TableHead>Issued By</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {actions.map((action) => (
                                        <TableRow key={action.id}>
                                            <TableCell className="font-medium text-zinc-900">
                                                {action.reference_number || '-'}
                                            </TableCell>
                                            <TableCell>
                                                <div>
                                                    <p className="text-sm font-medium text-zinc-900">
                                                        {action.employee?.full_name || '-'}
                                                    </p>
                                                    <p className="text-xs text-zinc-500">
                                                        {action.employee?.department?.name || ''}
                                                    </p>
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                {TYPE_LABELS[action.type] || action.type}
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge status={action.status} />
                                            </TableCell>
                                            <TableCell className="text-sm text-zinc-600">
                                                {formatDate(action.incident_date)}
                                            </TableCell>
                                            <TableCell className="text-sm text-zinc-600">
                                                {action.issued_by?.full_name || '-'}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex items-center justify-end gap-1">
                                                    <Link to={`/disciplinary/actions/${action.id}`}>
                                                        <Button variant="ghost" size="sm" title="View">
                                                            <Eye className="h-4 w-4" />
                                                        </Button>
                                                    </Link>
                                                    {action.status === 'draft' && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            title="Issue"
                                                            onClick={() => setConfirmDialog({ open: true, action: 'issue', id: action.id })}
                                                        >
                                                            <Send className="h-4 w-4 text-blue-600" />
                                                        </Button>
                                                    )}
                                                    {['issued', 'pending_response', 'responded'].includes(action.status) && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            title="Close"
                                                            onClick={() => setConfirmDialog({ open: true, action: 'close', id: action.id })}
                                                        >
                                                            <XCircle className="h-4 w-4 text-zinc-500" />
                                                        </Button>
                                                    )}
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        title="Download PDF"
                                                        onClick={() => handleDownloadPdf(action.id, action.reference_number)}
                                                        disabled={downloadingId === action.id}
                                                    >
                                                        {downloadingId === action.id ? (
                                                            <Loader2 className="h-4 w-4 animate-spin" />
                                                        ) : (
                                                            <Download className="h-4 w-4" />
                                                        )}
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>

                            {/* Pagination */}
                            {meta.last_page > 1 && (
                                <div className="flex items-center justify-between border-t border-zinc-200 px-4 py-3">
                                    <p className="text-sm text-zinc-500">
                                        Showing {meta.from || 0} to {meta.to || 0} of {meta.total || 0} records
                                    </p>
                                    <div className="flex items-center gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => setPage((p) => Math.max(1, p - 1))}
                                            disabled={page <= 1}
                                        >
                                            <ChevronLeft className="h-4 w-4" />
                                        </Button>
                                        <span className="text-sm text-zinc-600">
                                            Page {meta.current_page} of {meta.last_page}
                                        </span>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => setPage((p) => p + 1)}
                                            disabled={page >= meta.last_page}
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

            {/* Confirm Dialog */}
            <Dialog open={confirmDialog.open} onOpenChange={() => setConfirmDialog({ open: false, action: null, id: null })}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {confirmDialog.action === 'issue' ? 'Issue Disciplinary Action' : 'Close Disciplinary Action'}
                        </DialogTitle>
                        <DialogDescription>
                            {confirmDialog.action === 'issue'
                                ? 'Are you sure you want to issue this disciplinary action? The employee will be notified.'
                                : 'Are you sure you want to close this disciplinary action? This cannot be undone.'}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setConfirmDialog({ open: false, action: null, id: null })}
                        >
                            Cancel
                        </Button>
                        <Button
                            onClick={handleConfirm}
                            disabled={isAnyPending}
                        >
                            {isAnyPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                            {confirmDialog.action === 'issue' ? 'Issue' : 'Close'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
