import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Pencil,
    Trash2,
    Loader2,
    Users,
    X,
    ShieldCheck,
} from 'lucide-react';
import {
    fetchDepartmentApprovers,
    createDepartmentApprover,
    updateDepartmentApprover,
    deleteDepartmentApprover,
    fetchDepartments,
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

function SkeletonTable() {
    return (
        <div className="space-y-3">
            {Array.from({ length: 6 }).map((_, i) => (
                <div key={i} className="flex items-center gap-4 px-4 py-3">
                    <div className="h-4 w-32 animate-pulse rounded bg-zinc-200" />
                    <div className="flex flex-1 gap-2">
                        <div className="h-6 w-20 animate-pulse rounded-full bg-zinc-200" />
                        <div className="h-6 w-24 animate-pulse rounded-full bg-zinc-200" />
                    </div>
                    <div className="h-8 w-20 animate-pulse rounded bg-zinc-200" />
                </div>
            ))}
        </div>
    );
}

function EmptyState() {
    return (
        <div className="flex flex-col items-center justify-center py-16 text-center">
            <ShieldCheck className="mb-4 h-12 w-12 text-zinc-300" />
            <h3 className="text-lg font-semibold text-zinc-900">No leave approvers configured</h3>
            <p className="mt-1 text-sm text-zinc-500">
                Set up leave approvers per department to enable the approval workflow.
            </p>
        </div>
    );
}

export default function LeaveApprovers() {
    const queryClient = useQueryClient();
    const [departmentFilter, setDepartmentFilter] = useState('all');
    const [editDialog, setEditDialog] = useState({ open: false, department: null, approvers: [] });
    const [selectedEmployees, setSelectedEmployees] = useState([]);
    const [employeeSearch, setEmployeeSearch] = useState('');
    const [deleteDialog, setDeleteDialog] = useState({ open: false, approver: null });

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'leave', 'approvers', { department: departmentFilter }],
        queryFn: () =>
            fetchDepartmentApprovers({
                approval_type: 'leave',
                department_id: departmentFilter !== 'all' ? departmentFilter : undefined,
            }),
    });

    const { data: departmentsData } = useQuery({
        queryKey: ['hr', 'departments', 'list'],
        queryFn: () => fetchDepartments({ per_page: 100 }),
    });

    const { data: employeesData } = useQuery({
        queryKey: ['hr', 'employees', 'list', { search: employeeSearch }],
        queryFn: () => fetchEmployees({ per_page: 50, search: employeeSearch, status: 'active' }),
        enabled: editDialog.open,
    });

    const updateMutation = useMutation({
        mutationFn: ({ id, data }) => updateDepartmentApprover(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'leave', 'approvers'] });
            closeEditDialog();
        },
    });

    const createMutation = useMutation({
        mutationFn: (data) => createDepartmentApprover(data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'leave', 'approvers'] });
            closeEditDialog();
        },
    });

    const deleteMutation = useMutation({
        mutationFn: (id) => deleteDepartmentApprover(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'leave', 'approvers'] });
            setDeleteDialog({ open: false, approver: null });
        },
    });

    const approvers = data?.data || [];
    const departments = departmentsData?.data || [];
    const employees = employeesData?.data || [];

    const approversByDepartment = {};
    approvers.forEach((approver) => {
        const deptId = approver.department_id;
        if (!approversByDepartment[deptId]) {
            approversByDepartment[deptId] = {
                department: approver.department || departments.find((d) => d.id === deptId) || { name: 'Unknown', id: deptId },
                approvers: [],
            };
        }
        approversByDepartment[deptId].approvers.push(approver);
    });

    function openEditDialog(deptId) {
        const group = approversByDepartment[deptId];
        const dept = group?.department || departments.find((d) => d.id === Number(deptId));
        const currentApprovers = group?.approvers || [];
        setSelectedEmployees(currentApprovers.map((a) => ({
            id: a.employee_id || a.approver?.id,
            full_name: a.employee?.full_name || a.approver?.full_name || 'Unknown',
            approver_record_id: a.id,
        })));
        setEditDialog({ open: true, department: dept, approvers: currentApprovers });
        setEmployeeSearch('');
    }

    function openNewDepartmentDialog() {
        setSelectedEmployees([]);
        setEditDialog({ open: true, department: null, approvers: [] });
        setEmployeeSearch('');
    }

    function closeEditDialog() {
        setEditDialog({ open: false, department: null, approvers: [] });
        setSelectedEmployees([]);
        setEmployeeSearch('');
    }

    function addEmployee(employee) {
        if (!selectedEmployees.find((e) => e.id === employee.id)) {
            setSelectedEmployees([...selectedEmployees, { id: employee.id, full_name: employee.full_name }]);
        }
        setEmployeeSearch('');
    }

    function removeEmployee(employeeId) {
        setSelectedEmployees(selectedEmployees.filter((e) => e.id !== employeeId));
    }

    function handleSave() {
        const employeeIds = selectedEmployees.map((e) => e.id);

        if (editDialog.department) {
            if (editDialog.approvers.length > 0) {
                updateMutation.mutate({
                    id: editDialog.approvers[0].id,
                    data: {
                        department_id: editDialog.department.id,
                        employee_ids: employeeIds,
                        approval_type: 'leave',
                    },
                });
            } else {
                createMutation.mutate({
                    department_id: editDialog.department.id,
                    employee_ids: employeeIds,
                    approval_type: 'leave',
                });
            }
        }
    }

    function handleDelete() {
        deleteMutation.mutate(deleteDialog.approver.id);
    }

    const [newDeptId, setNewDeptId] = useState('');
    const isSaving = updateMutation.isPending || createMutation.isPending;

    const filteredEmployees = employees.filter(
        (emp) => !selectedEmployees.find((se) => se.id === emp.id)
    );

    return (
        <div>
            <PageHeader
                title="Leave Approvers"
                description="Configure who can approve leave requests per department."
                action={
                    <Button onClick={openNewDepartmentDialog}>
                        <Users className="mr-1.5 h-4 w-4" />
                        Configure Department
                    </Button>
                }
            />

            <Card>
                <CardContent className="p-6">
                    <div className="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center">
                        <Select value={departmentFilter} onValueChange={setDepartmentFilter}>
                            <SelectTrigger className="w-full lg:w-48">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Departments</SelectItem>
                                {departments.map((dept) => (
                                    <SelectItem key={dept.id} value={String(dept.id)}>{dept.name}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {isLoading ? (
                        <SkeletonTable />
                    ) : Object.keys(approversByDepartment).length === 0 ? (
                        <EmptyState />
                    ) : (
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Department</TableHead>
                                        <TableHead>Leave Approvers</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {Object.entries(approversByDepartment).map(([deptId, group]) => (
                                        <TableRow key={deptId}>
                                            <TableCell className="font-medium">{group.department.name}</TableCell>
                                            <TableCell>
                                                <div className="flex flex-wrap gap-1.5">
                                                    {group.approvers.map((approver) => (
                                                        <Badge key={approver.id} variant="secondary" className="text-xs">
                                                            {approver.employee?.full_name || approver.approver?.full_name || 'Unknown'}
                                                        </Badge>
                                                    ))}
                                                </div>
                                                <p className="mt-1 text-xs text-zinc-400">(Any one can approve)</p>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex items-center justify-end gap-1">
                                                    <Button variant="ghost" size="sm" onClick={() => openEditDialog(deptId)}>
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="text-red-600 hover:text-red-700"
                                                        onClick={() => setDeleteDialog({
                                                            open: true,
                                                            approver: group.approvers[0],
                                                        })}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    )}
                </CardContent>
            </Card>

            <Dialog open={editDialog.open} onOpenChange={closeEditDialog}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {editDialog.department ? `Edit Approvers - ${editDialog.department.name}` : 'Configure Leave Approvers'}
                        </DialogTitle>
                        <DialogDescription>
                            Select employees who can approve leave requests for this department. Any one of the selected approvers can approve a request.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        {!editDialog.department && (
                            <div>
                                <label className="mb-1 block text-sm font-medium text-zinc-700">Department</label>
                                <Select value={newDeptId} onValueChange={setNewDeptId}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select department" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {departments.map((dept) => (
                                            <SelectItem key={dept.id} value={String(dept.id)}>{dept.name}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        )}

                        <div>
                            <label className="mb-1 block text-sm font-medium text-zinc-700">Selected Approvers</label>
                            {selectedEmployees.length === 0 ? (
                                <p className="rounded-lg border border-dashed border-zinc-200 p-4 text-center text-sm text-zinc-400">
                                    No approvers selected. Search and add employees below.
                                </p>
                            ) : (
                                <div className="flex flex-wrap gap-2 rounded-lg border border-zinc-200 p-3">
                                    {selectedEmployees.map((emp) => (
                                        <Badge key={emp.id} variant="secondary" className="gap-1 pr-1">
                                            {emp.full_name}
                                            <button
                                                onClick={() => removeEmployee(emp.id)}
                                                className="ml-0.5 rounded-full p-0.5 hover:bg-zinc-300"
                                            >
                                                <X className="h-3 w-3" />
                                            </button>
                                        </Badge>
                                    ))}
                                </div>
                            )}
                        </div>

                        <div>
                            <label className="mb-1 block text-sm font-medium text-zinc-700">Search Employees</label>
                            <SearchInput
                                value={employeeSearch}
                                onChange={setEmployeeSearch}
                                placeholder="Search by name..."
                            />
                            {employeeSearch && filteredEmployees.length > 0 && (
                                <div className="mt-2 max-h-40 overflow-y-auto rounded-lg border border-zinc-200">
                                    {filteredEmployees.slice(0, 10).map((emp) => (
                                        <button
                                            key={emp.id}
                                            onClick={() => addEmployee(emp)}
                                            className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-zinc-50"
                                        >
                                            <span className="font-medium text-zinc-900">{emp.full_name}</span>
                                            <span className="text-xs text-zinc-400">{emp.department?.name || ''}</span>
                                        </button>
                                    ))}
                                </div>
                            )}
                            {employeeSearch && filteredEmployees.length === 0 && (
                                <p className="mt-2 text-center text-sm text-zinc-400">No employees found.</p>
                            )}
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={closeEditDialog}>Cancel</Button>
                        <Button
                            onClick={() => {
                                if (!editDialog.department && newDeptId) {
                                    const dept = departments.find((d) => d.id === Number(newDeptId));
                                    setEditDialog({ ...editDialog, department: dept });
                                    createMutation.mutate({
                                        department_id: Number(newDeptId),
                                        employee_ids: selectedEmployees.map((e) => e.id),
                                        approval_type: 'leave',
                                    });
                                } else {
                                    handleSave();
                                }
                            }}
                            disabled={selectedEmployees.length === 0 || isSaving || (!editDialog.department && !newDeptId)}
                        >
                            {isSaving && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                            Save
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={deleteDialog.open} onOpenChange={() => setDeleteDialog({ open: false, approver: null })}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Remove Department Approvers</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to remove all leave approvers for this department? Leave requests will not be able to be approved until new approvers are assigned.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDeleteDialog({ open: false, approver: null })}>
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={handleDelete} disabled={deleteMutation.isPending}>
                            {deleteMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                            Remove
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
