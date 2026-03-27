import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Plus, RotateCcw, Package2, ChevronLeft, ChevronRight, Loader2 } from 'lucide-react';
import {
    fetchAssetAssignments,
    createAssetAssignment,
    returnAsset,
    fetchAssets,
    fetchEmployees,
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

const RETURN_CONDITIONS = [
    { value: 'good', label: 'Good' },
    { value: 'fair', label: 'Fair' },
    { value: 'poor', label: 'Poor' },
    { value: 'damaged', label: 'Damaged' },
];

const STATUS_BADGE = {
    active: 'bg-blue-100 text-blue-700',
    returned: 'bg-emerald-100 text-emerald-700',
    lost: 'bg-red-100 text-red-700',
    damaged: 'bg-orange-100 text-orange-700',
};

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' });
}

export default function AssetAssignments() {
    const queryClient = useQueryClient();
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [statusFilter, setStatusFilter] = useState('active');
    const [assignDialog, setAssignDialog] = useState(false);
    const [returnDialog, setReturnDialog] = useState({ open: false, assignment: null });
    const [assignForm, setAssignForm] = useState({
        asset_id: '',
        employee_id: '',
        assigned_by: '',
        assigned_date: new Date().toISOString().split('T')[0],
        expected_return_date: '',
        notes: '',
    });
    const [returnForm, setReturnForm] = useState({
        returned_date: new Date().toISOString().split('T')[0],
        returned_condition: 'good',
        return_notes: '',
    });
    const [errors, setErrors] = useState({});

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'assets', 'assignments', { search, page, statusFilter }],
        queryFn: () => fetchAssetAssignments({
            search: search || undefined,
            status: statusFilter !== 'all' ? statusFilter : undefined,
            page,
            per_page: 15,
        }),
    });

    const { data: assetsData } = useQuery({
        queryKey: ['hr', 'assets', 'available'],
        queryFn: () => fetchAssets({ status: 'available', per_page: 200 }),
    });

    const { data: employeesData } = useQuery({
        queryKey: ['hr', 'employees', 'list'],
        queryFn: () => fetchEmployees({ per_page: 200 }),
    });

    const assignMutation = useMutation({
        mutationFn: (data) => createAssetAssignment(data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'assets', 'assignments'] });
            queryClient.invalidateQueries({ queryKey: ['hr', 'assets', 'list'] });
            setAssignDialog(false);
            setAssignForm({
                asset_id: '',
                employee_id: '',
                assigned_by: '',
                assigned_date: new Date().toISOString().split('T')[0],
                expected_return_date: '',
                notes: '',
            });
            setErrors({});
        },
        onError: (err) => {
            if (err.response?.data?.errors) {
                setErrors(err.response.data.errors);
            }
        },
    });

    const returnMutation = useMutation({
        mutationFn: ({ id, data }) => returnAsset(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'assets', 'assignments'] });
            queryClient.invalidateQueries({ queryKey: ['hr', 'assets', 'list'] });
            setReturnDialog({ open: false, assignment: null });
        },
    });

    const assignments = data?.data || [];
    const meta = data?.meta || {};
    const availableAssets = assetsData?.data || [];
    const employees = employeesData?.data || [];

    function handleAssign(e) {
        e.preventDefault();
        assignMutation.mutate({
            asset_id: parseInt(assignForm.asset_id),
            employee_id: parseInt(assignForm.employee_id),
            assigned_by: parseInt(assignForm.assigned_by),
            assigned_date: assignForm.assigned_date,
            expected_return_date: assignForm.expected_return_date || null,
            notes: assignForm.notes || null,
        });
    }

    function handleReturn(e) {
        e.preventDefault();
        returnMutation.mutate({
            id: returnDialog.assignment.id,
            data: returnForm,
        });
    }

    return (
        <div>
            <PageHeader
                title="Asset Assignments"
                description="Manage asset assignments and returns."
                action={
                    <Button onClick={() => { setAssignDialog(true); setErrors({}); }}>
                        <Plus className="mr-1.5 h-4 w-4" />
                        Assign Asset
                    </Button>
                }
            />

            <Card>
                <CardContent className="p-6">
                    <div className="mb-4 flex flex-wrap items-center gap-3">
                        <div className="flex-1">
                            <SearchInput
                                value={search}
                                onChange={(v) => { setSearch(v); setPage(1); }}
                                placeholder="Search by employee or asset tag..."
                            />
                        </div>
                        <Select value={statusFilter} onValueChange={(v) => { setStatusFilter(v); setPage(1); }}>
                            <SelectTrigger className="w-36">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Status</SelectItem>
                                <SelectItem value="active">Active</SelectItem>
                                <SelectItem value="returned">Returned</SelectItem>
                                <SelectItem value="lost">Lost</SelectItem>
                                <SelectItem value="damaged">Damaged</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    {isLoading ? (
                        <div className="flex justify-center py-16">
                            <Loader2 className="h-6 w-6 animate-spin text-zinc-400" />
                        </div>
                    ) : assignments.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-16 text-center">
                            <Package2 className="mb-3 h-10 w-10 text-zinc-300" />
                            <p className="text-sm font-medium text-zinc-600">No assignments found</p>
                        </div>
                    ) : (
                        <>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Asset</TableHead>
                                        <TableHead>Employee</TableHead>
                                        <TableHead>Assigned Date</TableHead>
                                        <TableHead>Expected Return</TableHead>
                                        <TableHead>Returned Date</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {assignments.map((assignment) => (
                                        <TableRow key={assignment.id}>
                                            <TableCell>
                                                <p className="font-mono text-sm font-medium">{assignment.asset?.asset_tag}</p>
                                                <p className="text-xs text-zinc-400">{assignment.asset?.name}</p>
                                            </TableCell>
                                            <TableCell className="font-medium">
                                                {assignment.employee?.full_name || '-'}
                                            </TableCell>
                                            <TableCell className="text-sm">{formatDate(assignment.assigned_date)}</TableCell>
                                            <TableCell className="text-sm">{formatDate(assignment.expected_return_date)}</TableCell>
                                            <TableCell className="text-sm">{formatDate(assignment.returned_date)}</TableCell>
                                            <TableCell>
                                                <span className={cn('rounded-full px-2 py-0.5 text-xs font-medium', STATUS_BADGE[assignment.status] || 'bg-zinc-100 text-zinc-600')}>
                                                    {assignment.status}
                                                </span>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {assignment.status === 'active' && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => {
                                                            setReturnDialog({ open: true, assignment });
                                                            setReturnForm({
                                                                returned_date: new Date().toISOString().split('T')[0],
                                                                returned_condition: 'good',
                                                                return_notes: '',
                                                            });
                                                        }}
                                                    >
                                                        <RotateCcw className="h-4 w-4" />
                                                    </Button>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>

                            {meta.last_page > 1 && (
                                <div className="mt-4 flex items-center justify-between text-sm text-zinc-500">
                                    <span>Showing {meta.from}–{meta.to} of {meta.total}</span>
                                    <div className="flex items-center gap-2">
                                        <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                                            <ChevronLeft className="h-4 w-4" />
                                        </Button>
                                        <span>Page {meta.current_page} of {meta.last_page}</span>
                                        <Button variant="outline" size="sm" disabled={page >= meta.last_page} onClick={() => setPage((p) => p + 1)}>
                                            <ChevronRight className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </>
                    )}
                </CardContent>
            </Card>

            {/* Assign Dialog */}
            <Dialog open={assignDialog} onOpenChange={setAssignDialog}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Assign Asset</DialogTitle>
                        <DialogDescription>Assign an available asset to an employee.</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleAssign} className="space-y-4">
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Asset *</label>
                            <Select value={assignForm.asset_id} onValueChange={(v) => setAssignForm((f) => ({ ...f, asset_id: v }))}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select available asset..." />
                                </SelectTrigger>
                                <SelectContent>
                                    {availableAssets.map((a) => (
                                        <SelectItem key={a.id} value={String(a.id)}>
                                            {a.asset_tag} — {a.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.asset_id && <p className="mt-1 text-xs text-red-600">{errors.asset_id[0]}</p>}
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Employee *</label>
                            <Select value={assignForm.employee_id} onValueChange={(v) => setAssignForm((f) => ({ ...f, employee_id: v }))}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select employee..." />
                                </SelectTrigger>
                                <SelectContent>
                                    {employees.map((emp) => (
                                        <SelectItem key={emp.id} value={String(emp.id)}>
                                            {emp.full_name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.employee_id && <p className="mt-1 text-xs text-red-600">{errors.employee_id[0]}</p>}
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Assigned By *</label>
                            <Select value={assignForm.assigned_by} onValueChange={(v) => setAssignForm((f) => ({ ...f, assigned_by: v }))}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select assigner..." />
                                </SelectTrigger>
                                <SelectContent>
                                    {employees.map((emp) => (
                                        <SelectItem key={emp.id} value={String(emp.id)}>
                                            {emp.full_name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-zinc-700">Assigned Date *</label>
                                <input
                                    type="date"
                                    value={assignForm.assigned_date}
                                    onChange={(e) => setAssignForm((f) => ({ ...f, assigned_date: e.target.value }))}
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                                    required
                                />
                            </div>
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-zinc-700">Expected Return</label>
                                <input
                                    type="date"
                                    value={assignForm.expected_return_date}
                                    onChange={(e) => setAssignForm((f) => ({ ...f, expected_return_date: e.target.value }))}
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                                />
                            </div>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setAssignDialog(false)}>Cancel</Button>
                            <Button type="submit" disabled={assignMutation.isPending || !assignForm.asset_id || !assignForm.employee_id}>
                                {assignMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                                Assign
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Return Dialog */}
            <Dialog open={returnDialog.open} onOpenChange={() => setReturnDialog({ open: false, assignment: null })}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Return Asset</DialogTitle>
                        <DialogDescription>
                            Process return of <strong>{returnDialog.assignment?.asset?.asset_tag}</strong> from{' '}
                            <strong>{returnDialog.assignment?.employee?.full_name}</strong>.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleReturn} className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-zinc-700">Return Date *</label>
                                <input
                                    type="date"
                                    value={returnForm.returned_date}
                                    onChange={(e) => setReturnForm((f) => ({ ...f, returned_date: e.target.value }))}
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                                    required
                                />
                            </div>
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-zinc-700">Condition *</label>
                                <Select value={returnForm.returned_condition} onValueChange={(v) => setReturnForm((f) => ({ ...f, returned_condition: v }))}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {RETURN_CONDITIONS.map((c) => (
                                            <SelectItem key={c.value} value={c.value}>{c.label}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Return Notes</label>
                            <textarea
                                value={returnForm.return_notes}
                                onChange={(e) => setReturnForm((f) => ({ ...f, return_notes: e.target.value }))}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                                rows={2}
                            />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setReturnDialog({ open: false, assignment: null })}>Cancel</Button>
                            <Button type="submit" disabled={returnMutation.isPending}>
                                {returnMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                                Process Return
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}
