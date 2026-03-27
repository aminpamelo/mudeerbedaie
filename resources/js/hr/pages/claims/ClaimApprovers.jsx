import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Plus, Trash2, UserCheck, Loader2 } from 'lucide-react';
import {
    fetchClaimApprovers,
    createClaimApprover,
    deleteClaimApprover,
    fetchEmployees,
} from '../../lib/api';
import PageHeader from '../../components/PageHeader';
import SearchInput from '../../components/SearchInput';
import { Button } from '../../components/ui/button';
import { Card, CardContent } from '../../components/ui/card';
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

export default function ClaimApprovers() {
    const queryClient = useQueryClient();
    const [search, setSearch] = useState('');
    const [assignDialog, setAssignDialog] = useState(false);
    const [form, setForm] = useState({ employee_id: '', approver_id: '' });
    const [deleteDialog, setDeleteDialog] = useState({ open: false, approver: null });
    const [errors, setErrors] = useState({});

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'claims', 'approvers', { search }],
        queryFn: () => fetchClaimApprovers({ search: search || undefined, per_page: 50 }),
    });

    const { data: employeesData } = useQuery({
        queryKey: ['hr', 'employees', 'list'],
        queryFn: () => fetchEmployees({ per_page: 200 }),
    });

    const createMutation = useMutation({
        mutationFn: (data) => createClaimApprover(data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'claims', 'approvers'] });
            setAssignDialog(false);
            setForm({ employee_id: '', approver_id: '' });
            setErrors({});
        },
        onError: (err) => {
            if (err.response?.data?.errors) {
                setErrors(err.response.data.errors);
            }
        },
    });

    const deleteMutation = useMutation({
        mutationFn: (id) => deleteClaimApprover(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'claims', 'approvers'] });
            setDeleteDialog({ open: false, approver: null });
        },
    });

    const approvers = data?.data || [];
    const employees = employeesData?.data || [];

    function handleSubmit(e) {
        e.preventDefault();
        createMutation.mutate({
            employee_id: parseInt(form.employee_id),
            approver_id: parseInt(form.approver_id),
        });
    }

    return (
        <div>
            <PageHeader
                title="Claim Approvers"
                description="Assign approvers for employee expense claims."
                action={
                    <Button onClick={() => { setAssignDialog(true); setForm({ employee_id: '', approver_id: '' }); setErrors({}); }}>
                        <Plus className="mr-1.5 h-4 w-4" />
                        Assign Approver
                    </Button>
                }
            />

            <Card>
                <CardContent className="p-6">
                    <div className="mb-4">
                        <SearchInput
                            value={search}
                            onChange={setSearch}
                            placeholder="Search by employee name..."
                        />
                    </div>

                    {isLoading ? (
                        <div className="flex justify-center py-16">
                            <Loader2 className="h-6 w-6 animate-spin text-zinc-400" />
                        </div>
                    ) : approvers.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-16 text-center">
                            <UserCheck className="mb-3 h-10 w-10 text-zinc-300" />
                            <p className="text-sm font-medium text-zinc-600">No claim approvers assigned</p>
                            <p className="mt-1 text-xs text-zinc-400">Assign approvers to manage claim approvals.</p>
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Employee</TableHead>
                                    <TableHead>Department</TableHead>
                                    <TableHead>Approver</TableHead>
                                    <TableHead>Approver Department</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {approvers.map((item) => (
                                    <TableRow key={item.id}>
                                        <TableCell className="font-medium">
                                            {item.employee?.full_name || '-'}
                                        </TableCell>
                                        <TableCell className="text-sm text-zinc-500">
                                            {item.employee?.department?.name || '-'}
                                        </TableCell>
                                        <TableCell className="font-medium">
                                            {item.approver?.full_name || '-'}
                                        </TableCell>
                                        <TableCell className="text-sm text-zinc-500">
                                            {item.approver?.department?.name || '-'}
                                        </TableCell>
                                        <TableCell>
                                            {item.is_active ? (
                                                <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">
                                                    Active
                                                </span>
                                            ) : (
                                                <span className="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-500">
                                                    Inactive
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="text-red-600 hover:text-red-700"
                                                onClick={() => setDeleteDialog({ open: true, approver: item })}
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>

            {/* Assign Dialog */}
            <Dialog open={assignDialog} onOpenChange={setAssignDialog}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Assign Claim Approver</DialogTitle>
                        <DialogDescription>
                            Select an employee and their designated claim approver.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Employee *</label>
                            <Select value={form.employee_id} onValueChange={(v) => setForm((f) => ({ ...f, employee_id: v }))}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select employee..." />
                                </SelectTrigger>
                                <SelectContent>
                                    {employees.map((emp) => (
                                        <SelectItem key={emp.id} value={String(emp.id)}>
                                            {emp.full_name} ({emp.employee_id})
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.employee_id && <p className="mt-1 text-xs text-red-600">{errors.employee_id[0]}</p>}
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Approver *</label>
                            <Select value={form.approver_id} onValueChange={(v) => setForm((f) => ({ ...f, approver_id: v }))}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select approver..." />
                                </SelectTrigger>
                                <SelectContent>
                                    {employees.map((emp) => (
                                        <SelectItem key={emp.id} value={String(emp.id)}>
                                            {emp.full_name} ({emp.employee_id})
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.approver_id && <p className="mt-1 text-xs text-red-600">{errors.approver_id[0]}</p>}
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setAssignDialog(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={createMutation.isPending || !form.employee_id || !form.approver_id}>
                                {createMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                                Assign
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Delete Dialog */}
            <Dialog open={deleteDialog.open} onOpenChange={() => setDeleteDialog({ open: false, approver: null })}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Remove Approver Assignment</DialogTitle>
                        <DialogDescription>
                            Remove <strong>{deleteDialog.approver?.approver?.full_name}</strong> as approver for{' '}
                            <strong>{deleteDialog.approver?.employee?.full_name}</strong>?
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDeleteDialog({ open: false, approver: null })}>
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() => deleteMutation.mutate(deleteDialog.approver.id)}
                            disabled={deleteMutation.isPending}
                        >
                            {deleteMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                            Remove
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
