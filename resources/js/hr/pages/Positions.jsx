import { useState, useMemo } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Plus,
    Pencil,
    Trash2,
    Briefcase,
    Users,
    Loader2,
    ChevronDown,
    ChevronRight,
    UserPlus,
    X,
    Search,
} from 'lucide-react';
import {
    fetchPositions,
    fetchDepartments,
    fetchEmployees,
    createPosition,
    updatePosition,
    deletePosition,
    fetchPositionEmployees,
    assignPositionEmployees,
    removePositionEmployee,
} from '../lib/api';
import PageHeader from '../components/PageHeader';
import SearchInput from '../components/SearchInput';
import ConfirmDialog from '../components/ConfirmDialog';
import { Button } from '../components/ui/button';
import { Input } from '../components/ui/input';
import { Label } from '../components/ui/label';
import { Textarea } from '../components/ui/textarea';
import { Badge } from '../components/ui/badge';
import {
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableHead,
    TableCell,
} from '../components/ui/table';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '../components/ui/dialog';
import {
    Select,
    SelectTrigger,
    SelectContent,
    SelectItem,
    SelectValue,
} from '../components/ui/select';

function ExpandedEmployees({ position }) {
    const queryClient = useQueryClient();
    const [showAssignDialog, setShowAssignDialog] = useState(false);
    const [employeeSearch, setEmployeeSearch] = useState('');
    const [selectedEmployeeIds, setSelectedEmployeeIds] = useState([]);
    const [removeTarget, setRemoveTarget] = useState(null);

    const { data: positionEmployees, isLoading } = useQuery({
        queryKey: ['hr', 'positions', position.id, 'employees'],
        queryFn: () => fetchPositionEmployees(position.id),
    });

    const { data: allEmployees } = useQuery({
        queryKey: ['hr', 'employees', 'all'],
        queryFn: () => fetchEmployees({ per_page: 999 }),
        enabled: showAssignDialog,
    });

    const assignMutation = useMutation({
        mutationFn: (employeeIds) => assignPositionEmployees(position.id, employeeIds),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'positions'] });
            queryClient.invalidateQueries({ queryKey: ['hr', 'positions', position.id, 'employees'] });
            setShowAssignDialog(false);
            setSelectedEmployeeIds([]);
            setEmployeeSearch('');
        },
    });

    const removeMutation = useMutation({
        mutationFn: (employeeId) => removePositionEmployee(position.id, employeeId),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'positions'] });
            queryClient.invalidateQueries({ queryKey: ['hr', 'positions', position.id, 'employees'] });
            setRemoveTarget(null);
        },
    });

    const assignedIds = useMemo(() => {
        return new Set((positionEmployees?.data || []).map((e) => e.id));
    }, [positionEmployees]);

    const filteredAvailableEmployees = useMemo(() => {
        if (!allEmployees?.data) return [];
        let list = allEmployees.data.filter((e) => !assignedIds.has(e.id));
        if (employeeSearch) {
            const q = employeeSearch.toLowerCase();
            list = list.filter(
                (e) =>
                    e.full_name?.toLowerCase().includes(q) ||
                    e.employee_id?.toLowerCase().includes(q)
            );
        }
        return list;
    }, [allEmployees, assignedIds, employeeSearch]);

    function toggleEmployee(id) {
        setSelectedEmployeeIds((prev) =>
            prev.includes(id) ? prev.filter((eid) => eid !== id) : [...prev, id]
        );
    }

    const employees = positionEmployees?.data || [];

    return (
        <div className="px-6 py-4 bg-zinc-50/50">
            <div className="flex items-center justify-between mb-3">
                <h4 className="text-sm font-medium text-zinc-700">
                    Assigned Employees ({employees.length})
                </h4>
                <Button
                    size="sm"
                    variant="outline"
                    onClick={() => setShowAssignDialog(true)}
                >
                    <UserPlus className="mr-1.5 h-3.5 w-3.5" />
                    Assign Employee
                </Button>
            </div>

            {isLoading ? (
                <div className="flex items-center justify-center py-6">
                    <Loader2 className="h-4 w-4 animate-spin text-zinc-400" />
                </div>
            ) : employees.length === 0 ? (
                <p className="text-sm text-zinc-400 py-3">
                    No employees assigned to this position yet.
                </p>
            ) : (
                <div className="space-y-2">
                    {employees.map((emp) => (
                        <div
                            key={emp.id}
                            className="flex items-center justify-between rounded-lg border border-zinc-200 bg-white px-3 py-2"
                        >
                            <div className="flex items-center gap-3">
                                {emp.profile_photo_url || emp.profile_photo ? (
                                    <img
                                        src={emp.profile_photo_url || `/storage/${emp.profile_photo}`}
                                        alt={emp.full_name}
                                        className="h-8 w-8 rounded-full object-cover"
                                    />
                                ) : (
                                    <div className="flex h-8 w-8 items-center justify-center rounded-full bg-zinc-200 text-xs font-medium text-zinc-600">
                                        {emp.full_name?.charAt(0)?.toUpperCase() || '?'}
                                    </div>
                                )}
                                <div>
                                    <p className="text-sm font-medium text-zinc-900">
                                        {emp.full_name}
                                    </p>
                                    <div className="flex items-center gap-2">
                                        <span className="text-xs text-zinc-500">
                                            {emp.employee_id}
                                        </span>
                                        {emp.department?.name && (
                                            <span className="text-xs text-zinc-400">
                                                {emp.department.name}
                                            </span>
                                        )}
                                    </div>
                                </div>
                                {emp.pivot?.is_primary ? (
                                    <Badge variant="default" className="text-[10px]">
                                        Primary
                                    </Badge>
                                ) : null}
                            </div>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="h-7 w-7 text-zinc-400 hover:text-red-600 hover:bg-red-50"
                                onClick={() => setRemoveTarget(emp)}
                                title="Remove from position"
                            >
                                <X className="h-3.5 w-3.5" />
                            </Button>
                        </div>
                    ))}
                </div>
            )}

            {/* Assign Employee Dialog */}
            <Dialog open={showAssignDialog} onOpenChange={setShowAssignDialog}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Assign Employees</DialogTitle>
                        <DialogDescription>
                            Select employees to assign to <strong>{position.title}</strong>.
                            Employees can hold multiple positions.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-3">
                        <div className="relative">
                            <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-zinc-400" />
                            <Input
                                value={employeeSearch}
                                onChange={(e) => setEmployeeSearch(e.target.value)}
                                placeholder="Search employees..."
                                className="pl-9"
                            />
                        </div>

                        <div className="max-h-64 overflow-y-auto rounded-lg border border-zinc-200">
                            {filteredAvailableEmployees.length === 0 ? (
                                <p className="px-3 py-6 text-center text-sm text-zinc-400">
                                    {employeeSearch
                                        ? 'No matching employees found.'
                                        : 'All employees are already assigned.'}
                                </p>
                            ) : (
                                filteredAvailableEmployees.map((emp) => (
                                    <label
                                        key={emp.id}
                                        className="flex cursor-pointer items-center gap-3 px-3 py-2 hover:bg-zinc-50 border-b border-zinc-100 last:border-b-0"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={selectedEmployeeIds.includes(emp.id)}
                                            onChange={() => toggleEmployee(emp.id)}
                                            className="h-4 w-4 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-500"
                                        />
                                        <div className="flex items-center gap-2 min-w-0">
                                            {emp.profile_photo_url || emp.profile_photo ? (
                                                <img
                                                    src={emp.profile_photo_url || `/storage/${emp.profile_photo}`}
                                                    alt={emp.full_name}
                                                    className="h-7 w-7 rounded-full object-cover shrink-0"
                                                />
                                            ) : (
                                                <div className="flex h-7 w-7 items-center justify-center rounded-full bg-zinc-200 text-[10px] font-medium text-zinc-600 shrink-0">
                                                    {emp.full_name?.charAt(0)?.toUpperCase() || '?'}
                                                </div>
                                            )}
                                            <div className="min-w-0">
                                                <p className="text-sm font-medium text-zinc-900 truncate">
                                                    {emp.full_name}
                                                </p>
                                                <p className="text-xs text-zinc-500 truncate">
                                                    {emp.employee_id}
                                                    {emp.department?.name && ` - ${emp.department.name}`}
                                                </p>
                                            </div>
                                        </div>
                                    </label>
                                ))
                            )}
                        </div>

                        {selectedEmployeeIds.length > 0 && (
                            <p className="text-xs text-zinc-500">
                                {selectedEmployeeIds.length} employee(s) selected
                            </p>
                        )}
                    </div>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => {
                                setShowAssignDialog(false);
                                setSelectedEmployeeIds([]);
                                setEmployeeSearch('');
                            }}
                            disabled={assignMutation.isPending}
                        >
                            Cancel
                        </Button>
                        <Button
                            onClick={() => assignMutation.mutate(selectedEmployeeIds)}
                            disabled={selectedEmployeeIds.length === 0 || assignMutation.isPending}
                        >
                            {assignMutation.isPending && (
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                            )}
                            Assign ({selectedEmployeeIds.length})
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Remove Employee Confirm */}
            <ConfirmDialog
                open={!!removeTarget}
                onOpenChange={(open) => {
                    if (!open) setRemoveTarget(null);
                }}
                title="Remove Employee"
                description={`Remove "${removeTarget?.full_name}" from the "${position.title}" position?`}
                confirmLabel="Remove"
                loading={removeMutation.isPending}
                onConfirm={() => {
                    if (removeTarget) {
                        removeMutation.mutate(removeTarget.id);
                    }
                }}
            />
        </div>
    );
}

export default function Positions() {
    const queryClient = useQueryClient();

    const [search, setSearch] = useState('');
    const [departmentFilter, setDepartmentFilter] = useState('');
    const [showDialog, setShowDialog] = useState(false);
    const [editingPosition, setEditingPosition] = useState(null);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [formErrors, setFormErrors] = useState({});
    const [expandedPositionId, setExpandedPositionId] = useState(null);

    const [formData, setFormData] = useState({
        title: '',
        department_id: '',
        level: '1',
        description: '',
    });

    const { data: positions, isLoading } = useQuery({
        queryKey: [
            'hr',
            'positions',
            { department_id: departmentFilter || undefined },
        ],
        queryFn: () =>
            fetchPositions({
                department_id: departmentFilter || undefined,
            }),
    });

    const { data: departments } = useQuery({
        queryKey: ['hr', 'departments'],
        queryFn: () => fetchDepartments(),
    });

    const filteredPositions = useMemo(() => {
        if (!positions?.data) {
            return [];
        }
        if (!search) {
            return positions.data;
        }
        const query = search.toLowerCase();
        return positions.data.filter((pos) =>
            pos.title.toLowerCase().includes(query)
        );
    }, [positions, search]);

    const groupedPositions = useMemo(() => {
        const groups = {};
        filteredPositions.forEach((pos) => {
            const deptName = pos.department?.name || 'Unassigned';
            const deptId = pos.department_id || 'none';
            if (!groups[deptId]) {
                groups[deptId] = {
                    name: deptName,
                    positions: [],
                };
            }
            groups[deptId].positions.push(pos);
        });
        return Object.values(groups);
    }, [filteredPositions]);

    const createMutation = useMutation({
        mutationFn: createPosition,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'positions'] });
            closeDialog();
        },
        onError: (error) => {
            if (error.response?.data?.errors) {
                setFormErrors(error.response.data.errors);
            }
        },
    });

    const updateMutation = useMutation({
        mutationFn: ({ id, data }) => updatePosition(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'positions'] });
            closeDialog();
        },
        onError: (error) => {
            if (error.response?.data?.errors) {
                setFormErrors(error.response.data.errors);
            }
        },
    });

    const deleteMutation = useMutation({
        mutationFn: deletePosition,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'positions'] });
            setDeleteTarget(null);
        },
        onError: (error) => {
            const message =
                error.response?.data?.message ||
                'Cannot delete this position. It may have employees assigned.';
            alert(message);
            setDeleteTarget(null);
        },
    });

    function openCreateDialog() {
        setEditingPosition(null);
        setFormData({ title: '', department_id: '', level: '1', description: '' });
        setFormErrors({});
        setShowDialog(true);
    }

    function openEditDialog(position) {
        setEditingPosition(position);
        setFormData({
            title: position.title || '',
            department_id: position.department_id
                ? String(position.department_id)
                : '',
            level: position.level ? String(position.level) : '1',
            description: position.description || '',
        });
        setFormErrors({});
        setShowDialog(true);
    }

    function closeDialog() {
        setShowDialog(false);
        setEditingPosition(null);
        setFormData({ title: '', department_id: '', level: '1', description: '' });
        setFormErrors({});
    }

    function handleSubmit(e) {
        e.preventDefault();
        setFormErrors({});

        const payload = {
            ...formData,
            level: parseInt(formData.level, 10),
        };

        if (editingPosition) {
            updateMutation.mutate({ id: editingPosition.id, data: payload });
        } else {
            createMutation.mutate(payload);
        }
    }

    function handleDelete(position) {
        if (position.employees_count > 0) {
            alert(
                `Cannot delete this position. It has ${position.employees_count} employee(s) assigned.`
            );
            return;
        }
        setDeleteTarget(position);
    }

    function toggleExpand(positionId) {
        setExpandedPositionId((prev) => (prev === positionId ? null : positionId));
    }

    const isSaving = createMutation.isPending || updateMutation.isPending;

    return (
        <div>
            <PageHeader
                title="Positions"
                description="Manage job positions across departments."
                action={
                    <Button onClick={openCreateDialog}>
                        <Plus className="mr-2 h-4 w-4" />
                        Add Position
                    </Button>
                }
            />

            <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                <SearchInput
                    value={search}
                    onChange={setSearch}
                    placeholder="Search by title..."
                    className="w-full sm:max-w-xs"
                />

                <Select
                    value={departmentFilter}
                    onValueChange={(value) =>
                        setDepartmentFilter(value === 'all' ? '' : value)
                    }
                >
                    <SelectTrigger className="w-full sm:w-[200px]">
                        <SelectValue placeholder="All Departments" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Departments</SelectItem>
                        {departments?.data?.map((dept) => (
                            <SelectItem key={dept.id} value={String(dept.id)}>
                                {dept.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            <div className="rounded-xl border border-zinc-200 bg-white">
                {isLoading ? (
                    <div className="flex items-center justify-center py-12">
                        <Loader2 className="h-6 w-6 animate-spin text-zinc-400" />
                    </div>
                ) : filteredPositions.length === 0 ? (
                    <div className="py-12 text-center">
                        <Briefcase className="mx-auto h-10 w-10 text-zinc-300" />
                        <p className="mt-2 text-sm text-zinc-500">
                            {search || departmentFilter
                                ? 'No positions match your filters.'
                                : 'No positions yet. Create your first position.'}
                        </p>
                    </div>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-8"></TableHead>
                                <TableHead>Title</TableHead>
                                <TableHead>Department</TableHead>
                                <TableHead>Level</TableHead>
                                <TableHead>Employees</TableHead>
                                <TableHead className="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {groupedPositions.map((group) => (
                                <>
                                    <TableRow
                                        key={`group-${group.name}`}
                                        className="bg-zinc-50/80 hover:bg-zinc-50/80"
                                    >
                                        <TableCell
                                            colSpan={6}
                                            className="py-2"
                                        >
                                            <div className="flex items-center gap-2">
                                                <span className="text-xs font-semibold uppercase tracking-wider text-zinc-500">
                                                    {group.name}
                                                </span>
                                                <Badge variant="secondary" className="text-[10px]">
                                                    {group.positions.length}{' '}
                                                    {group.positions.length === 1
                                                        ? 'position'
                                                        : 'positions'}
                                                </Badge>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                    {group.positions.map((pos) => (
                                        <>
                                            <TableRow
                                                key={pos.id}
                                                className={`cursor-pointer ${expandedPositionId === pos.id ? 'bg-zinc-50' : ''}`}
                                                onClick={() => toggleExpand(pos.id)}
                                            >
                                                <TableCell className="w-8 pr-0">
                                                    {expandedPositionId === pos.id ? (
                                                        <ChevronDown className="h-4 w-4 text-zinc-400" />
                                                    ) : (
                                                        <ChevronRight className="h-4 w-4 text-zinc-400" />
                                                    )}
                                                </TableCell>
                                                <TableCell className="font-medium">
                                                    {pos.title}
                                                </TableCell>
                                                <TableCell>
                                                    {pos.department?.name || (
                                                        <span className="text-zinc-400">--</span>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant="outline">
                                                        Level {pos.level}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center gap-1.5">
                                                        <Users className="h-3.5 w-3.5 text-zinc-400" />
                                                        {pos.employees_count ?? 0}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <div
                                                        className="flex items-center justify-end gap-1"
                                                        onClick={(e) => e.stopPropagation()}
                                                    >
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() =>
                                                                openEditDialog(pos)
                                                            }
                                                            title="Edit"
                                                        >
                                                            <Pencil className="h-4 w-4" />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() =>
                                                                handleDelete(pos)
                                                            }
                                                            title="Delete"
                                                            className="text-red-600 hover:text-red-700 hover:bg-red-50"
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                            {expandedPositionId === pos.id && (
                                                <TableRow key={`expanded-${pos.id}`}>
                                                    <TableCell colSpan={6} className="p-0">
                                                        <ExpandedEmployees position={pos} />
                                                    </TableCell>
                                                </TableRow>
                                            )}
                                        </>
                                    ))}
                                </>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </div>

            <Dialog open={showDialog} onOpenChange={setShowDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {editingPosition ? 'Edit Position' : 'Add Position'}
                        </DialogTitle>
                        <DialogDescription>
                            {editingPosition
                                ? 'Update the position details below.'
                                : 'Fill in the details to create a new position.'}
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="pos-title">
                                Title <span className="text-red-500">*</span>
                            </Label>
                            <Input
                                id="pos-title"
                                value={formData.title}
                                onChange={(e) =>
                                    setFormData({ ...formData, title: e.target.value })
                                }
                                placeholder="e.g. Senior Software Engineer"
                                required
                            />
                            {formErrors.title && (
                                <p className="text-xs text-red-600">
                                    {formErrors.title[0]}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label>
                                Department <span className="text-red-500">*</span>
                            </Label>
                            <Select
                                value={formData.department_id}
                                onValueChange={(value) =>
                                    setFormData({ ...formData, department_id: value })
                                }
                                required
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select department" />
                                </SelectTrigger>
                                <SelectContent>
                                    {departments?.data?.map((dept) => (
                                        <SelectItem
                                            key={dept.id}
                                            value={String(dept.id)}
                                        >
                                            {dept.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {formErrors.department_id && (
                                <p className="text-xs text-red-600">
                                    {formErrors.department_id[0]}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="pos-level">
                                Level <span className="text-red-500">*</span>
                            </Label>
                            <Input
                                id="pos-level"
                                type="number"
                                min={1}
                                value={formData.level}
                                onChange={(e) =>
                                    setFormData({ ...formData, level: e.target.value })
                                }
                                placeholder="e.g. 3"
                                required
                            />
                            {formErrors.level && (
                                <p className="text-xs text-red-600">
                                    {formErrors.level[0]}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="pos-description">Description</Label>
                            <Textarea
                                id="pos-description"
                                value={formData.description}
                                onChange={(e) =>
                                    setFormData({
                                        ...formData,
                                        description: e.target.value,
                                    })
                                }
                                placeholder="Optional position description..."
                                rows={3}
                            />
                            {formErrors.description && (
                                <p className="text-xs text-red-600">
                                    {formErrors.description[0]}
                                </p>
                            )}
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={closeDialog}
                                disabled={isSaving}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={isSaving}>
                                {isSaving && (
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                )}
                                {editingPosition ? 'Update' : 'Create'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={!!deleteTarget}
                onOpenChange={(open) => {
                    if (!open) {
                        setDeleteTarget(null);
                    }
                }}
                title="Delete Position"
                description={`Are you sure you want to delete "${deleteTarget?.title}"? This action cannot be undone.`}
                confirmLabel="Delete"
                loading={deleteMutation.isPending}
                onConfirm={() => {
                    if (deleteTarget) {
                        deleteMutation.mutate(deleteTarget.id);
                    }
                }}
            />
        </div>
    );
}
