import { useState, useCallback, useMemo } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Download,
    Eye,
    CheckCircle2,
    XCircle,
    AlertCircle,
    ChevronLeft,
    ChevronRight,
    Loader2,
    FileText,
    Filter,
    CreditCard,
    Paperclip,
    Car,
    MapPin,
    Route,
    Plus,
    Upload,
    Users,
    Wallet,
} from 'lucide-react';
import {
    fetchClaimRequests,
    fetchClaimRequest,
    approveClaimRequest,
    rejectClaimRequest,
    markClaimPaid,
    payAllClaimsForEmployee,
    exportClaimRequests,
    fetchClaimTypes,
    fetchDepartments,
    fetchEmployees,
    createAdminClaimRequest,
} from '../../lib/api';
import { cn } from '../../lib/utils';
import PageHeader from '../../components/PageHeader';
import SearchInput from '../../components/SearchInput';
import { Button } from '../../components/ui/button';
import { Card, CardContent } from '../../components/ui/card';
import { Badge } from '../../components/ui/badge';
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

const STATUS_OPTIONS = [
    { value: 'all', label: 'All Status' },
    { value: 'draft', label: 'Draft' },
    { value: 'pending', label: 'Pending' },
    { value: 'approved', label: 'Approved' },
    { value: 'rejected', label: 'Rejected' },
    { value: 'paid', label: 'Paid' },
];

const STATUS_BADGE = {
    draft: { className: 'bg-zinc-100 text-zinc-600', label: 'Draft' },
    pending: { className: 'bg-amber-100 text-amber-700', label: 'Pending' },
    approved: { className: 'bg-emerald-100 text-emerald-700', label: 'Approved' },
    rejected: { className: 'bg-red-100 text-red-700', label: 'Rejected' },
    paid: { className: 'bg-blue-100 text-blue-700', label: 'Paid' },
};

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

export default function ClaimRequests() {
    const queryClient = useQueryClient();
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('all');
    const [claimTypeId, setClaimTypeId] = useState('all');
    const [page, setPage] = useState(1);
    const [selectedRequest, setSelectedRequest] = useState(null);
    const [detailLoading, setDetailLoading] = useState(false);
    const [rejectReason, setRejectReason] = useState('');
    const [payReference, setPayReference] = useState('');
    const [approvedAmount, setApprovedAmount] = useState('');
    const [actionDialog, setActionDialog] = useState({ open: false, type: null, request: null });
    const [actionError, setActionError] = useState('');
    const [filtersOpen, setFiltersOpen] = useState(false);
    const [createOpen, setCreateOpen] = useState(false);
    const [createForm, setCreateForm] = useState({
        employee_id: '',
        claim_type_id: '',
        amount: '',
        claim_date: new Date().toISOString().split('T')[0],
        description: '',
        receipt: null,
        vehicle_rate_id: '',
        distance_km: '',
        origin: '',
        destination: '',
        trip_purpose: '',
    });
    const [createErrors, setCreateErrors] = useState({});
    const [createWarning, setCreateWarning] = useState('');
    const [groupByEmployee, setGroupByEmployee] = useState(true);
    const [payAllDialog, setPayAllDialog] = useState(null);
    const [payAllReference, setPayAllReference] = useState('');
    const [payAllError, setPayAllError] = useState('');

    const perPage = groupByEmployee ? 200 : 15;
    const effectivePage = groupByEmployee ? 1 : page;

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'claims', 'requests', { search, status, claimTypeId, page: effectivePage, perPage }],
        queryFn: () => fetchClaimRequests({
            search: search || undefined,
            status: status !== 'all' ? status : undefined,
            claim_type_id: claimTypeId !== 'all' ? claimTypeId : undefined,
            page: effectivePage,
            per_page: perPage,
        }),
    });

    const { data: claimTypesData } = useQuery({
        queryKey: ['hr', 'claims', 'types', 'list'],
        queryFn: () => fetchClaimTypes({ per_page: 100 }),
    });

    const approveMutation = useMutation({
        mutationFn: ({ id, approved_amount }) => approveClaimRequest(id, { approved_amount: parseFloat(approved_amount) }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'claims', 'requests'] });
            setActionDialog({ open: false, type: null, request: null });
            setApprovedAmount('');
            setActionError('');
        },
        onError: (err) => setActionError(err?.response?.data?.message || 'Failed to approve claim.'),
    });

    const rejectMutation = useMutation({
        mutationFn: ({ id, reason }) => rejectClaimRequest(id, { rejected_reason: reason }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'claims', 'requests'] });
            setActionDialog({ open: false, type: null, request: null });
            setRejectReason('');
            setActionError('');
        },
        onError: (err) => setActionError(err?.response?.data?.message || 'Failed to reject claim.'),
    });

    const payMutation = useMutation({
        mutationFn: ({ id, reference }) => markClaimPaid(id, { reference }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'claims', 'requests'] });
            setActionDialog({ open: false, type: null, request: null });
            setPayReference('');
        },
    });

    const payAllMutation = useMutation({
        mutationFn: ({ employeeId, reference }) =>
            payAllClaimsForEmployee(employeeId, { paid_reference: reference || null }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'claims', 'requests'] });
            setPayAllDialog(null);
            setPayAllReference('');
            setPayAllError('');
        },
        onError: (err) => setPayAllError(err?.response?.data?.message || 'Failed to mark claims as paid.'),
    });

    const { data: employeesData } = useQuery({
        queryKey: ['hr', 'employees', 'list-for-claims'],
        queryFn: () => fetchEmployees({ per_page: 200, sort: 'full_name' }),
        enabled: createOpen,
    });

    const createMutation = useMutation({
        mutationFn: (formData) => createAdminClaimRequest(formData),
        onSuccess: (res) => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'claims', 'requests'] });
            if (res?.warning) {
                setCreateWarning(res.warning);
            } else {
                closeCreateDialog();
            }
        },
        onError: (err) => {
            if (err?.response?.data?.errors) {
                setCreateErrors(err.response.data.errors);
            } else {
                setCreateErrors({ _: [err?.response?.data?.message || 'Failed to create claim.'] });
            }
        },
    });

    const exportMutation = useMutation({
        mutationFn: () => exportClaimRequests({
            search: search || undefined,
            status: status !== 'all' ? status : undefined,
        }),
        onSuccess: (blob) => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `claim-requests-${new Date().toISOString().split('T')[0]}.csv`;
            a.click();
            window.URL.revokeObjectURL(url);
        },
    });

    const requests = data?.data || [];
    const meta = data?.meta || {};
    const claimTypes = claimTypesData?.data || [];
    const employees = employeesData?.data || [];

    const groupedRequests = useMemo(() => {
        if (!groupByEmployee) return [];
        const map = new Map();
        for (const req of requests) {
            const empId = req.employee?.id ?? req.employee_id ?? 'unknown';
            if (!map.has(empId)) {
                map.set(empId, {
                    employee_id: empId,
                    employee: req.employee,
                    claims: [],
                    approvedTotal: 0,
                    approvedCount: 0,
                });
            }
            const group = map.get(empId);
            group.claims.push(req);
            if (req.status === 'approved') {
                group.approvedCount += 1;
                group.approvedTotal += parseFloat(req.approved_amount ?? req.amount ?? 0);
            }
        }
        return Array.from(map.values()).sort((a, b) => {
            if (b.approvedTotal !== a.approvedTotal) return b.approvedTotal - a.approvedTotal;
            const aName = a.employee?.full_name || '';
            const bName = b.employee?.full_name || '';
            return aName.localeCompare(bName);
        });
    }, [groupByEmployee, requests]);

    const selectedClaimType = useMemo(
        () => claimTypes.find((t) => String(t.id) === String(createForm.claim_type_id)) || null,
        [claimTypes, createForm.claim_type_id],
    );
    const isMileageType = selectedClaimType?.is_mileage_type === true;
    const vehicleRates = selectedClaimType?.vehicle_rates?.filter((r) => r.is_active) || [];
    const selectedVehicleRate = vehicleRates.find((r) => String(r.id) === String(createForm.vehicle_rate_id));
    const calculatedAmount = isMileageType && selectedVehicleRate && createForm.distance_km
        ? (parseFloat(createForm.distance_km) * parseFloat(selectedVehicleRate.rate_per_km)).toFixed(2)
        : null;

    function openCreateDialog() {
        setCreateForm({
            employee_id: '',
            claim_type_id: '',
            amount: '',
            claim_date: new Date().toISOString().split('T')[0],
            description: '',
            receipt: null,
            vehicle_rate_id: '',
            distance_km: '',
            origin: '',
            destination: '',
            trip_purpose: '',
        });
        setCreateErrors({});
        setCreateWarning('');
        setCreateOpen(true);
    }

    function closeCreateDialog() {
        setCreateOpen(false);
        setCreateErrors({});
        setCreateWarning('');
    }

    function updateCreateForm(field, value) {
        setCreateForm((prev) => {
            const next = { ...prev, [field]: value };
            if (field === 'claim_type_id') {
                next.vehicle_rate_id = '';
                next.distance_km = '';
                next.origin = '';
                next.destination = '';
                next.trip_purpose = '';
                next.amount = '';
            }
            return next;
        });
        setCreateErrors((prev) => {
            const { [field]: _omit, ...rest } = prev;
            return rest;
        });
    }

    function submitCreateForm(e) {
        e?.preventDefault?.();
        const fd = new FormData();
        fd.append('employee_id', createForm.employee_id);
        fd.append('claim_type_id', createForm.claim_type_id);
        fd.append('claim_date', createForm.claim_date);
        fd.append('description', createForm.description);
        if (isMileageType) {
            fd.append('vehicle_rate_id', createForm.vehicle_rate_id);
            fd.append('distance_km', createForm.distance_km);
            fd.append('origin', createForm.origin);
            fd.append('destination', createForm.destination);
            fd.append('trip_purpose', createForm.trip_purpose);
        } else {
            fd.append('amount', createForm.amount);
        }
        if (createForm.receipt) {
            fd.append('receipt', createForm.receipt);
        }
        createMutation.mutate(fd);
    }

    const handleSearch = useCallback((val) => {
        setSearch(val);
        setPage(1);
    }, []);

    async function viewDetail(request) {
        setDetailLoading(true);
        try {
            const res = await fetchClaimRequest(request.id);
            setSelectedRequest(res.data || res);
        } catch {
            setSelectedRequest(request);
        } finally {
            setDetailLoading(false);
        }
    }

    function handleAction(type, request) {
        setActionDialog({ open: true, type, request });
        setRejectReason('');
        setPayReference('');
        setApprovedAmount(request?.amount ? String(request.amount) : '');
        setActionError('');
    }

    function confirmAction() {
        const { type, request } = actionDialog;
        if (type === 'approve') {
            approveMutation.mutate({ id: request.id, approved_amount: approvedAmount });
        } else if (type === 'reject') {
            rejectMutation.mutate({ id: request.id, reason: rejectReason });
        } else if (type === 'pay') {
            payMutation.mutate({ id: request.id, reference: payReference });
        }
    }

    const isActionLoading = approveMutation.isPending || rejectMutation.isPending || payMutation.isPending;

    return (
        <div>
            <PageHeader
                title="Claim Requests"
                description="Review and manage employee expense claims."
            />

            <Card>
                <CardContent className="p-6">
                    {/* Toolbar */}
                    <div className="mb-4 flex flex-wrap items-center gap-3">
                        <div className="flex-1">
                            <SearchInput
                                value={search}
                                onChange={handleSearch}
                                placeholder="Search by employee, claim number..."
                            />
                        </div>
                        <Button
                            variant={groupByEmployee ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => { setGroupByEmployee((p) => !p); setPage(1); }}
                        >
                            <Users className="mr-1.5 h-4 w-4" />
                            {groupByEmployee ? 'Grouped by Staff' : 'Group by Staff'}
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setFiltersOpen((p) => !p)}
                        >
                            <Filter className="mr-1.5 h-4 w-4" />
                            Filters
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => exportMutation.mutate()}
                            disabled={exportMutation.isPending}
                        >
                            {exportMutation.isPending ? (
                                <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />
                            ) : (
                                <Download className="mr-1.5 h-4 w-4" />
                            )}
                            Export
                        </Button>
                        <Button
                            size="sm"
                            onClick={openCreateDialog}
                        >
                            <Plus className="mr-1.5 h-4 w-4" />
                            New Claim
                        </Button>
                    </div>

                    {filtersOpen && (
                        <div className="mb-4 flex flex-wrap gap-3 rounded-lg border border-zinc-200 bg-zinc-50 p-4">
                            <div className="w-40">
                                <label className="mb-1 block text-xs font-medium text-zinc-600">Status</label>
                                <Select value={status} onValueChange={(v) => { setStatus(v); setPage(1); }}>
                                    <SelectTrigger>
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
                            <div className="w-48">
                                <label className="mb-1 block text-xs font-medium text-zinc-600">Claim Type</label>
                                <Select value={claimTypeId} onValueChange={(v) => { setClaimTypeId(v); setPage(1); }}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="All Types" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Types</SelectItem>
                                        {claimTypes.map((t) => (
                                            <SelectItem key={t.id} value={String(t.id)}>
                                                {t.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    )}

                    {isLoading ? (
                        <SkeletonTable />
                    ) : requests.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-16 text-center">
                            <FileText className="mb-3 h-10 w-10 text-zinc-300" />
                            <p className="text-sm font-medium text-zinc-600">No claim requests found</p>
                            <p className="mt-1 text-xs text-zinc-400">Try adjusting your search or filters.</p>
                        </div>
                    ) : groupByEmployee ? (
                        <div className="space-y-4">
                            {groupedRequests.map((group) => {
                                const empName = group.employee?.full_name || 'Unknown';
                                const deptName = group.employee?.department?.name;
                                const initials = empName.split(' ').filter(Boolean).slice(0, 2).map((w) => w[0]).join('').toUpperCase() || '?';
                                return (
                                    <div key={group.employee_id} className="overflow-hidden rounded-xl border border-zinc-200 bg-white">
                                        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-100 bg-zinc-50/70 px-4 py-3">
                                            <div className="flex items-center gap-3">
                                                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-700">
                                                    {initials}
                                                </div>
                                                <div className="min-w-0">
                                                    <p className="text-sm font-semibold text-zinc-900">{empName}</p>
                                                    <p className="text-xs text-zinc-500">
                                                        {deptName ? `${deptName} · ` : ''}{group.claims.length} claim{group.claims.length !== 1 ? 's' : ''}
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-3">
                                                <div className="text-right">
                                                    <p className="text-[10px] uppercase tracking-wide text-zinc-400">Pay-out</p>
                                                    <p className="text-base font-bold text-emerald-700">
                                                        {formatCurrency(group.approvedTotal)}
                                                    </p>
                                                    <p className="text-[10px] text-zinc-400">
                                                        {group.approvedCount} approved
                                                    </p>
                                                </div>
                                                <Button
                                                    size="sm"
                                                    disabled={group.approvedCount === 0}
                                                    onClick={() => {
                                                        setPayAllDialog({
                                                            employee_id: group.employee_id,
                                                            name: empName,
                                                            count: group.approvedCount,
                                                            total: group.approvedTotal,
                                                        });
                                                        setPayAllReference('');
                                                        setPayAllError('');
                                                    }}
                                                >
                                                    <Wallet className="mr-1.5 h-4 w-4" />
                                                    Pay All
                                                </Button>
                                            </div>
                                        </div>
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead>Claim No.</TableHead>
                                                    <TableHead>Type</TableHead>
                                                    <TableHead>Amount</TableHead>
                                                    <TableHead>Date</TableHead>
                                                    <TableHead>Receipt</TableHead>
                                                    <TableHead>Status</TableHead>
                                                    <TableHead className="text-right">Actions</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {group.claims.map((request) => {
                                                    const badge = STATUS_BADGE[request.status] || { className: 'bg-zinc-100 text-zinc-600', label: request.status };
                                                    return (
                                                        <TableRow key={request.id}>
                                                            <TableCell className="font-mono text-sm">{request.claim_number}</TableCell>
                                                            <TableCell>
                                                                <Badge variant="outline" className="gap-1">
                                                                    {request.claim_type?.is_mileage_type && (
                                                                        <Car className="h-3 w-3 text-blue-500" />
                                                                    )}
                                                                    {request.claim_type?.name || '-'}
                                                                </Badge>
                                                            </TableCell>
                                                            <TableCell className="font-medium">
                                                                {formatCurrency(request.status === 'approved' && request.approved_amount ? request.approved_amount : request.amount)}
                                                            </TableCell>
                                                            <TableCell className="text-sm text-zinc-500">{formatDate(request.claim_date)}</TableCell>
                                                            <TableCell>
                                                                {request.receipt_url ? (
                                                                    <a
                                                                        href={request.receipt_url}
                                                                        target="_blank"
                                                                        rel="noopener noreferrer"
                                                                        onClick={(e) => e.stopPropagation()}
                                                                        className="inline-flex items-center gap-1 text-xs font-medium text-blue-600 hover:text-blue-700"
                                                                    >
                                                                        <Paperclip className="h-3.5 w-3.5" />
                                                                        View
                                                                    </a>
                                                                ) : (
                                                                    <span className="text-xs text-zinc-400">-</span>
                                                                )}
                                                            </TableCell>
                                                            <TableCell>
                                                                <span className={cn('rounded-full px-2.5 py-0.5 text-xs font-medium', badge.className)}>
                                                                    {badge.label}
                                                                </span>
                                                            </TableCell>
                                                            <TableCell className="text-right">
                                                                <div className="flex items-center justify-end gap-1">
                                                                    <Button variant="ghost" size="sm" onClick={() => viewDetail(request)}>
                                                                        <Eye className="h-4 w-4" />
                                                                    </Button>
                                                                    {request.status === 'pending' && (
                                                                        <>
                                                                            <Button variant="ghost" size="sm" className="text-emerald-600 hover:text-emerald-700" onClick={() => handleAction('approve', request)}>
                                                                                <CheckCircle2 className="h-4 w-4" />
                                                                            </Button>
                                                                            <Button variant="ghost" size="sm" className="text-red-600 hover:text-red-700" onClick={() => handleAction('reject', request)}>
                                                                                <XCircle className="h-4 w-4" />
                                                                            </Button>
                                                                        </>
                                                                    )}
                                                                    {request.status === 'approved' && (
                                                                        <Button variant="ghost" size="sm" className="text-blue-600 hover:text-blue-700" onClick={() => handleAction('pay', request)}>
                                                                            <CreditCard className="h-4 w-4" />
                                                                        </Button>
                                                                    )}
                                                                </div>
                                                            </TableCell>
                                                        </TableRow>
                                                    );
                                                })}
                                            </TableBody>
                                        </Table>
                                    </div>
                                );
                            })}
                            {meta.total > requests.length && (
                                <p className="text-center text-xs text-zinc-400">
                                    Showing {requests.length} of {meta.total} claims. Narrow filters to group all matching claims.
                                </p>
                            )}
                        </div>
                    ) : (
                        <>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Claim No.</TableHead>
                                        <TableHead>Employee</TableHead>
                                        <TableHead>Type</TableHead>
                                        <TableHead>Amount</TableHead>
                                        <TableHead>Date</TableHead>
                                        <TableHead>Receipt</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {requests.map((request) => {
                                        const badge = STATUS_BADGE[request.status] || { className: 'bg-zinc-100 text-zinc-600', label: request.status };
                                        return (
                                            <TableRow key={request.id}>
                                                <TableCell className="font-mono text-sm">
                                                    {request.claim_number}
                                                </TableCell>
                                                <TableCell className="font-medium">
                                                    {request.employee?.full_name || '-'}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant="outline" className="gap-1">
                                                        {request.claim_type?.is_mileage_type && (
                                                            <Car className="h-3 w-3 text-blue-500" />
                                                        )}
                                                        {request.claim_type?.name || '-'}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="font-medium">
                                                    {formatCurrency(request.amount)}
                                                </TableCell>
                                                <TableCell className="text-sm text-zinc-500">
                                                    {formatDate(request.claim_date)}
                                                </TableCell>
                                                <TableCell>
                                                    {request.receipt_url ? (
                                                        <a
                                                            href={request.receipt_url}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            onClick={(e) => e.stopPropagation()}
                                                            className="inline-flex items-center gap-1 text-xs font-medium text-blue-600 hover:text-blue-700"
                                                        >
                                                            <Paperclip className="h-3.5 w-3.5" />
                                                            View
                                                        </a>
                                                    ) : (
                                                        <span className="text-xs text-zinc-400">-</span>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <span className={cn('rounded-full px-2.5 py-0.5 text-xs font-medium', badge.className)}>
                                                        {badge.label}
                                                    </span>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <div className="flex items-center justify-end gap-1">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => viewDetail(request)}
                                                        >
                                                            <Eye className="h-4 w-4" />
                                                        </Button>
                                                        {request.status === 'pending' && (
                                                            <>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    className="text-emerald-600 hover:text-emerald-700"
                                                                    onClick={() => handleAction('approve', request)}
                                                                >
                                                                    <CheckCircle2 className="h-4 w-4" />
                                                                </Button>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    className="text-red-600 hover:text-red-700"
                                                                    onClick={() => handleAction('reject', request)}
                                                                >
                                                                    <XCircle className="h-4 w-4" />
                                                                </Button>
                                                            </>
                                                        )}
                                                        {request.status === 'approved' && (
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                className="text-blue-600 hover:text-blue-700"
                                                                onClick={() => handleAction('pay', request)}
                                                            >
                                                                <CreditCard className="h-4 w-4" />
                                                            </Button>
                                                        )}
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>

                            {/* Pagination */}
                            {meta.last_page > 1 && (
                                <div className="mt-4 flex items-center justify-between text-sm text-zinc-500">
                                    <span>
                                        Showing {meta.from}–{meta.to} of {meta.total} results
                                    </span>
                                    <div className="flex items-center gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            disabled={page <= 1}
                                            onClick={() => setPage((p) => p - 1)}
                                        >
                                            <ChevronLeft className="h-4 w-4" />
                                        </Button>
                                        <span className="px-2">
                                            Page {meta.current_page} of {meta.last_page}
                                        </span>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            disabled={page >= meta.last_page}
                                            onClick={() => setPage((p) => p + 1)}
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

            {/* Detail Dialog */}
            <Dialog open={!!selectedRequest} onOpenChange={() => setSelectedRequest(null)}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Claim Request Detail</DialogTitle>
                        <DialogDescription>
                            Full details for claim {selectedRequest?.claim_number}.
                        </DialogDescription>
                    </DialogHeader>
                    {detailLoading ? (
                        <div className="flex justify-center py-8">
                            <Loader2 className="h-6 w-6 animate-spin text-zinc-400" />
                        </div>
                    ) : selectedRequest && (
                        <div className="space-y-4 text-sm">
                            {/* Header info */}
                            <div className="grid grid-cols-2 gap-3 rounded-lg bg-zinc-50 p-3">
                                <div>
                                    <p className="text-xs text-zinc-500">Claim No.</p>
                                    <p className="font-mono font-medium text-zinc-900">{selectedRequest.claim_number}</p>
                                </div>
                                <div>
                                    <p className="text-xs text-zinc-500">Status</p>
                                    <span className={cn(
                                        'inline-block rounded-full px-2 py-0.5 text-xs font-medium',
                                        STATUS_BADGE[selectedRequest.status]?.className || 'bg-zinc-100 text-zinc-600'
                                    )}>
                                        {STATUS_BADGE[selectedRequest.status]?.label || selectedRequest.status}
                                    </span>
                                </div>
                                <div>
                                    <p className="text-xs text-zinc-500">Employee</p>
                                    <p className="font-medium text-zinc-900">{selectedRequest.employee?.full_name || '-'}</p>
                                    {selectedRequest.employee?.department?.name && (
                                        <p className="text-xs text-zinc-400">{selectedRequest.employee.department.name}</p>
                                    )}
                                </div>
                                <div>
                                    <p className="text-xs text-zinc-500">Claim Date</p>
                                    <p className="font-medium text-zinc-900">{formatDate(selectedRequest.claim_date)}</p>
                                </div>
                            </div>

                            {/* Claim type + amounts */}
                            <div className="space-y-2">
                                <div className="flex items-center justify-between">
                                    <span className="text-zinc-500">Claim Type</span>
                                    <span className="inline-flex items-center gap-1.5 font-medium">
                                        {selectedRequest.claim_type?.is_mileage_type && (
                                            <Car className="h-3.5 w-3.5 text-blue-500" />
                                        )}
                                        {selectedRequest.claim_type?.name || '-'}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-zinc-500">Claimed Amount</span>
                                    <span className="font-semibold text-zinc-900">{formatCurrency(selectedRequest.amount)}</span>
                                </div>
                                {selectedRequest.approved_amount && (
                                    <div className="flex items-center justify-between">
                                        <span className="text-zinc-500">Approved Amount</span>
                                        <span className="font-semibold text-emerald-700">{formatCurrency(selectedRequest.approved_amount)}</span>
                                    </div>
                                )}
                            </div>

                            {/* Mileage details */}
                            {selectedRequest.distance_km && (
                                <div className="rounded-lg border border-blue-100 bg-blue-50/50 p-3 space-y-2">
                                    <div className="flex items-center gap-1.5 text-xs font-medium text-blue-700">
                                        <Route className="h-3.5 w-3.5" />
                                        Mileage Details
                                    </div>
                                    {selectedRequest.vehicle_rate && (
                                        <div className="flex items-center justify-between">
                                            <span className="text-zinc-500">Vehicle Type</span>
                                            <span className="font-medium">
                                                {selectedRequest.vehicle_rate.name}
                                                <span className="ml-1.5 text-xs text-zinc-400">
                                                    RM {parseFloat(selectedRequest.vehicle_rate.rate_per_km).toFixed(2)}/km
                                                </span>
                                            </span>
                                        </div>
                                    )}
                                    <div className="flex items-center justify-between">
                                        <span className="text-zinc-500">Distance</span>
                                        <span className="font-medium">{selectedRequest.distance_km} km</span>
                                    </div>
                                    {selectedRequest.origin && (
                                        <div className="flex items-start justify-between gap-4">
                                            <span className="text-zinc-500 shrink-0">Route</span>
                                            <span className="font-medium text-right">
                                                <span className="inline-flex items-center gap-1">
                                                    <MapPin className="h-3 w-3 text-green-500" />
                                                    {selectedRequest.origin}
                                                </span>
                                                <span className="mx-1.5 text-zinc-400">→</span>
                                                <span className="inline-flex items-center gap-1">
                                                    <MapPin className="h-3 w-3 text-red-500" />
                                                    {selectedRequest.destination}
                                                </span>
                                            </span>
                                        </div>
                                    )}
                                    {selectedRequest.trip_purpose && (
                                        <div className="flex items-start justify-between gap-4">
                                            <span className="text-zinc-500 shrink-0">Purpose</span>
                                            <span className="font-medium text-right">{selectedRequest.trip_purpose}</span>
                                        </div>
                                    )}
                                </div>
                            )}

                            {/* Description */}
                            {selectedRequest.description && (
                                <div>
                                    <p className="mb-1 text-zinc-500">Description</p>
                                    <p className="rounded-lg bg-zinc-50 p-3 text-zinc-700">{selectedRequest.description}</p>
                                </div>
                            )}

                            {/* Receipt */}
                            <div className="flex items-center justify-between">
                                <span className="text-zinc-500">Receipt / Attachment</span>
                                {selectedRequest.receipt_url ? (
                                    <a
                                        href={selectedRequest.receipt_url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="inline-flex items-center gap-1 font-medium text-blue-600 hover:text-blue-700"
                                    >
                                        <Paperclip className="h-3.5 w-3.5" />
                                        View Document
                                    </a>
                                ) : (
                                    <span className="text-zinc-400">No attachment</span>
                                )}
                            </div>

                            {/* Paid reference */}
                            {selectedRequest.paid_reference && (
                                <div className="flex items-center justify-between">
                                    <span className="text-zinc-500">Payment Reference</span>
                                    <span className="font-mono font-medium text-zinc-900">{selectedRequest.paid_reference}</span>
                                </div>
                            )}
                            {selectedRequest.paid_at && (
                                <div className="flex items-center justify-between">
                                    <span className="text-zinc-500">Paid At</span>
                                    <span className="font-medium text-zinc-900">{formatDate(selectedRequest.paid_at)}</span>
                                </div>
                            )}

                            {/* Rejection reason */}
                            {selectedRequest.rejected_reason && (
                                <div>
                                    <p className="mb-1 text-zinc-500">Rejection Reason</p>
                                    <p className="rounded-lg bg-red-50 p-3 text-red-700">{selectedRequest.rejected_reason}</p>
                                </div>
                            )}
                        </div>
                    )}
                </DialogContent>
            </Dialog>

            {/* Create Dialog */}
            <Dialog open={createOpen} onOpenChange={(open) => { if (!open) { closeCreateDialog(); } }}>
                <DialogContent className="max-w-xl">
                    <DialogHeader>
                        <DialogTitle>New Claim Request</DialogTitle>
                        <DialogDescription>
                            File a claim on behalf of an employee. The claim will be submitted for approval.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitCreateForm} className="space-y-4">
                        {/* Employee */}
                        <div>
                            <label className="mb-1 block text-sm font-medium text-zinc-700">
                                Employee <span className="text-red-500">*</span>
                            </label>
                            <Select
                                value={createForm.employee_id ? String(createForm.employee_id) : ''}
                                onValueChange={(v) => updateCreateForm('employee_id', v)}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select an employee..." />
                                </SelectTrigger>
                                <SelectContent>
                                    {employees.map((emp) => (
                                        <SelectItem key={emp.id} value={String(emp.id)}>
                                            {emp.full_name}
                                            {emp.department?.name && (
                                                <span className="ml-2 text-xs text-zinc-400">
                                                    · {emp.department.name}
                                                </span>
                                            )}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {createErrors.employee_id && (
                                <p className="mt-1 text-xs text-red-600">{createErrors.employee_id[0]}</p>
                            )}
                        </div>

                        {/* Claim Type */}
                        <div>
                            <label className="mb-1 block text-sm font-medium text-zinc-700">
                                Claim Type <span className="text-red-500">*</span>
                            </label>
                            <Select
                                value={createForm.claim_type_id ? String(createForm.claim_type_id) : ''}
                                onValueChange={(v) => updateCreateForm('claim_type_id', v)}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select a claim type..." />
                                </SelectTrigger>
                                <SelectContent>
                                    {claimTypes.map((t) => (
                                        <SelectItem key={t.id} value={String(t.id)}>
                                            {t.name}
                                            {t.is_mileage_type && (
                                                <span className="ml-1.5 text-xs text-blue-500">(mileage)</span>
                                            )}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {createErrors.claim_type_id && (
                                <p className="mt-1 text-xs text-red-600">{createErrors.claim_type_id[0]}</p>
                            )}
                        </div>

                        {/* Mileage fields */}
                        {isMileageType && (
                            <div className="space-y-3 rounded-lg border border-blue-100 bg-blue-50/40 p-3">
                                <div className="flex items-center gap-1.5 text-xs font-medium text-blue-700">
                                    <Route className="h-3.5 w-3.5" />
                                    Mileage Details
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs font-medium text-zinc-600">
                                        Vehicle Type <span className="text-red-500">*</span>
                                    </label>
                                    <Select
                                        value={createForm.vehicle_rate_id ? String(createForm.vehicle_rate_id) : ''}
                                        onValueChange={(v) => updateCreateForm('vehicle_rate_id', v)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select vehicle type..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {vehicleRates.map((r) => (
                                                <SelectItem key={r.id} value={String(r.id)}>
                                                    {r.name} · RM {parseFloat(r.rate_per_km).toFixed(2)}/km
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {createErrors.vehicle_rate_id && (
                                        <p className="mt-1 text-xs text-red-600">{createErrors.vehicle_rate_id[0]}</p>
                                    )}
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs font-medium text-zinc-600">
                                        Distance (km) <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        value={createForm.distance_km}
                                        onChange={(e) => updateCreateForm('distance_km', e.target.value)}
                                        placeholder="e.g. 12.5"
                                        className="w-full rounded-lg border border-zinc-300 p-2.5 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                    />
                                    {createErrors.distance_km && (
                                        <p className="mt-1 text-xs text-red-600">{createErrors.distance_km[0]}</p>
                                    )}
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="mb-1 block text-xs font-medium text-zinc-600">
                                            Origin <span className="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="text"
                                            value={createForm.origin}
                                            onChange={(e) => updateCreateForm('origin', e.target.value)}
                                            placeholder="From"
                                            className="w-full rounded-lg border border-zinc-300 p-2.5 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                        />
                                        {createErrors.origin && (
                                            <p className="mt-1 text-xs text-red-600">{createErrors.origin[0]}</p>
                                        )}
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-xs font-medium text-zinc-600">
                                            Destination <span className="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="text"
                                            value={createForm.destination}
                                            onChange={(e) => updateCreateForm('destination', e.target.value)}
                                            placeholder="To"
                                            className="w-full rounded-lg border border-zinc-300 p-2.5 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                        />
                                        {createErrors.destination && (
                                            <p className="mt-1 text-xs text-red-600">{createErrors.destination[0]}</p>
                                        )}
                                    </div>
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs font-medium text-zinc-600">
                                        Trip Purpose <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        value={createForm.trip_purpose}
                                        onChange={(e) => updateCreateForm('trip_purpose', e.target.value)}
                                        placeholder="e.g. Client meeting"
                                        className="w-full rounded-lg border border-zinc-300 p-2.5 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                    />
                                    {createErrors.trip_purpose && (
                                        <p className="mt-1 text-xs text-red-600">{createErrors.trip_purpose[0]}</p>
                                    )}
                                </div>
                                {calculatedAmount && (
                                    <div className="flex items-center justify-between rounded bg-white px-3 py-2 text-sm">
                                        <span className="text-zinc-500">Calculated amount</span>
                                        <span className="font-semibold text-emerald-700">
                                            {formatCurrency(calculatedAmount)}
                                        </span>
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Amount (non-mileage) */}
                        {selectedClaimType && !isMileageType && (
                            <div>
                                <label className="mb-1 block text-sm font-medium text-zinc-700">
                                    Amount (MYR) <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    value={createForm.amount}
                                    onChange={(e) => updateCreateForm('amount', e.target.value)}
                                    placeholder="e.g. 50.00"
                                    className="w-full rounded-lg border border-zinc-300 p-2.5 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                />
                                {createErrors.amount && (
                                    <p className="mt-1 text-xs text-red-600">{createErrors.amount[0]}</p>
                                )}
                            </div>
                        )}

                        {/* Date */}
                        <div>
                            <label className="mb-1 block text-sm font-medium text-zinc-700">
                                Claim Date <span className="text-red-500">*</span>
                            </label>
                            <input
                                type="date"
                                value={createForm.claim_date}
                                onChange={(e) => updateCreateForm('claim_date', e.target.value)}
                                className="w-full rounded-lg border border-zinc-300 p-2.5 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                            />
                            {createErrors.claim_date && (
                                <p className="mt-1 text-xs text-red-600">{createErrors.claim_date[0]}</p>
                            )}
                        </div>

                        {/* Description */}
                        <div>
                            <label className="mb-1 block text-sm font-medium text-zinc-700">
                                Description <span className="text-red-500">*</span>
                            </label>
                            <textarea
                                value={createForm.description}
                                onChange={(e) => updateCreateForm('description', e.target.value)}
                                placeholder="Describe the claim..."
                                rows={3}
                                className="w-full rounded-lg border border-zinc-300 p-2.5 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                            />
                            {createErrors.description && (
                                <p className="mt-1 text-xs text-red-600">{createErrors.description[0]}</p>
                            )}
                        </div>

                        {/* Receipt */}
                        <div>
                            <label className="mb-1 block text-sm font-medium text-zinc-700">
                                Receipt (optional)
                            </label>
                            <label className="flex cursor-pointer items-center gap-2 rounded-lg border border-dashed border-zinc-300 px-3 py-3 text-sm text-zinc-600 hover:border-zinc-400">
                                <Upload className="h-4 w-4" />
                                {createForm.receipt ? createForm.receipt.name : 'Click to upload PDF / JPG / PNG (max 5 MB)'}
                                <input
                                    type="file"
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    onChange={(e) => updateCreateForm('receipt', e.target.files?.[0] || null)}
                                    className="hidden"
                                />
                            </label>
                            {createErrors.receipt && (
                                <p className="mt-1 text-xs text-red-600">{createErrors.receipt[0]}</p>
                            )}
                        </div>

                        {createWarning && (
                            <div className="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2">
                                <AlertCircle className="mt-0.5 h-4 w-4 shrink-0 text-amber-600" />
                                <div className="flex-1 text-sm text-amber-800">
                                    <p className="font-medium">Limit warning</p>
                                    <p>{createWarning}</p>
                                    <p className="mt-1 text-xs">Claim was created. You can close this dialog.</p>
                                </div>
                            </div>
                        )}

                        {createErrors._ && (
                            <div className="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2">
                                <AlertCircle className="h-4 w-4 shrink-0 text-red-500" />
                                <p className="text-sm text-red-700">{createErrors._[0]}</p>
                            </div>
                        )}

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={closeCreateDialog}>
                                {createWarning ? 'Close' : 'Cancel'}
                            </Button>
                            {!createWarning && (
                                <Button type="submit" disabled={createMutation.isPending}>
                                    {createMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                                    Create Claim
                                </Button>
                            )}
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Action Dialog */}
            <Dialog open={actionDialog.open} onOpenChange={() => { setActionDialog({ open: false, type: null, request: null }); setActionError(''); }}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {actionDialog.type === 'approve' && 'Approve Claim Request'}
                            {actionDialog.type === 'reject' && 'Reject Claim Request'}
                            {actionDialog.type === 'pay' && 'Mark as Paid'}
                        </DialogTitle>
                        <DialogDescription>
                            {actionDialog.type === 'approve' && 'Are you sure you want to approve this claim?'}
                            {actionDialog.type === 'reject' && 'Please provide a reason for rejection.'}
                            {actionDialog.type === 'pay' && 'Enter the payment reference number.'}
                        </DialogDescription>
                    </DialogHeader>
                    {actionDialog.request && (
                        <div className="space-y-3">
                            <div className="rounded-lg bg-zinc-50 p-3 text-sm">
                                <p className="font-medium">{actionDialog.request.employee?.full_name}</p>
                                <p className="text-zinc-500">
                                    {actionDialog.request.claim_type?.name} &middot;{' '}
                                    {formatCurrency(actionDialog.request.amount)} &middot;{' '}
                                    {actionDialog.request.claim_number}
                                </p>
                            </div>
                            {actionDialog.type === 'approve' && (
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-zinc-700">
                                        Approved Amount (MYR) *
                                    </label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        value={approvedAmount}
                                        onChange={(e) => setApprovedAmount(e.target.value)}
                                        placeholder="Enter approved amount..."
                                        className="w-full rounded-lg border border-zinc-300 p-3 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                    />
                                </div>
                            )}
                            {actionDialog.type === 'reject' && (
                                <textarea
                                    value={rejectReason}
                                    onChange={(e) => setRejectReason(e.target.value)}
                                    placeholder="Reason for rejection..."
                                    className="w-full rounded-lg border border-zinc-300 p-3 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                    rows={3}
                                />
                            )}
                            {actionDialog.type === 'pay' && (
                                <input
                                    type="text"
                                    value={payReference}
                                    onChange={(e) => setPayReference(e.target.value)}
                                    placeholder="Payment reference number..."
                                    className="w-full rounded-lg border border-zinc-300 p-3 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                />
                            )}
                        </div>
                    )}
                    {actionError && (
                        <div className="flex items-center gap-2 rounded-lg bg-red-50 border border-red-200 px-3 py-2">
                            <AlertCircle className="h-4 w-4 text-red-500 shrink-0" />
                            <p className="text-sm text-red-700">{actionError}</p>
                        </div>
                    )}
                    <DialogFooter>
                        <Button variant="outline" onClick={() => { setActionDialog({ open: false, type: null, request: null }); setActionError(''); }}>
                            Cancel
                        </Button>
                        <Button
                            variant={actionDialog.type === 'reject' ? 'destructive' : 'default'}
                            onClick={confirmAction}
                            disabled={isActionLoading || (actionDialog.type === 'approve' && !approvedAmount)}
                        >
                            {isActionLoading && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                            {actionDialog.type === 'approve' && 'Approve'}
                            {actionDialog.type === 'reject' && 'Reject'}
                            {actionDialog.type === 'pay' && 'Mark as Paid'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Pay All Dialog */}
            <Dialog open={!!payAllDialog} onOpenChange={(open) => { if (!open) { setPayAllDialog(null); setPayAllError(''); } }}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Pay All Approved Claims</DialogTitle>
                        <DialogDescription>
                            All approved claims for this staff will be marked as paid in one transaction.
                        </DialogDescription>
                    </DialogHeader>
                    {payAllDialog && (
                        <div className="space-y-3">
                            <div className="rounded-lg bg-emerald-50/60 p-3 text-sm">
                                <p className="font-medium text-zinc-900">{payAllDialog.name}</p>
                                <p className="text-zinc-600">
                                    <span className="font-semibold text-emerald-700">{payAllDialog.count}</span>{' '}
                                    approved claim{payAllDialog.count !== 1 ? 's' : ''} &middot;{' '}
                                    Total{' '}
                                    <span className="font-semibold text-emerald-700">{formatCurrency(payAllDialog.total)}</span>
                                </p>
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-zinc-700">
                                    Payment Reference (optional)
                                </label>
                                <input
                                    type="text"
                                    value={payAllReference}
                                    onChange={(e) => setPayAllReference(e.target.value)}
                                    placeholder="e.g. BNK-20260514-001"
                                    className="w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm focus:border-zinc-900 focus:outline-none focus:ring-1 focus:ring-zinc-900"
                                />
                                <p className="mt-1 text-xs text-zinc-500">
                                    The same reference will be saved on every claim in this transfer.
                                </p>
                            </div>
                            {payAllError && (
                                <p className="flex items-center gap-1.5 text-sm text-red-600">
                                    <AlertCircle className="h-4 w-4 shrink-0" /> {payAllError}
                                </p>
                            )}
                        </div>
                    )}
                    <DialogFooter>
                        <Button variant="outline" onClick={() => { setPayAllDialog(null); setPayAllError(''); }}>
                            Cancel
                        </Button>
                        <Button
                            onClick={() => payAllMutation.mutate({ employeeId: payAllDialog.employee_id, reference: payAllReference })}
                            disabled={payAllMutation.isPending || !payAllDialog?.count}
                        >
                            {payAllMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                            <Wallet className="mr-1.5 h-4 w-4" />
                            Confirm Pay All
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
