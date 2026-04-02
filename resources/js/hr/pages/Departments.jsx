import { useState, useMemo } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Plus,
    Pencil,
    Trash2,
    LayoutList,
    Network,
    ChevronRight,
    ChevronDown,
    Building2,
    Users,
    Loader2,
} from 'lucide-react';
import {
    fetchDepartments,
    fetchDepartmentTree,
    createDepartment,
    updateDepartment,
    deleteDepartment,
} from '../lib/api';
import { cn } from '../lib/utils';
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

export default function Departments() {
    const queryClient = useQueryClient();

    const [viewMode, setViewMode] = useState('table');
    const [search, setSearch] = useState('');
    const [showDialog, setShowDialog] = useState(false);
    const [editingDepartment, setEditingDepartment] = useState(null);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [formErrors, setFormErrors] = useState({});

    const [formData, setFormData] = useState({
        name: '',
        code: '',
        description: '',
        parent_id: '',
    });

    const { data: departments, isLoading } = useQuery({
        queryKey: ['hr', 'departments'],
        queryFn: () => fetchDepartments(),
    });

    const { data: tree, isLoading: isTreeLoading } = useQuery({
        queryKey: ['hr', 'departments', 'tree'],
        queryFn: fetchDepartmentTree,
        enabled: viewMode === 'tree',
    });

    const filteredDepartments = useMemo(() => {
        if (!departments?.data) {
            return [];
        }
        if (!search) {
            return departments.data;
        }
        const query = search.toLowerCase();
        return departments.data.filter(
            (dept) =>
                dept.name.toLowerCase().includes(query) ||
                dept.code?.toLowerCase().includes(query)
        );
    }, [departments, search]);

    const createMutation = useMutation({
        mutationFn: createDepartment,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'departments'] });
            closeDialog();
        },
        onError: (error) => {
            if (error.response?.data?.errors) {
                setFormErrors(error.response.data.errors);
            }
        },
    });

    const updateMutation = useMutation({
        mutationFn: ({ id, data }) => updateDepartment(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'departments'] });
            closeDialog();
        },
        onError: (error) => {
            if (error.response?.data?.errors) {
                setFormErrors(error.response.data.errors);
            }
        },
    });

    const deleteMutation = useMutation({
        mutationFn: deleteDepartment,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'departments'] });
            setDeleteTarget(null);
        },
        onError: (error) => {
            const message =
                error.response?.data?.message ||
                'Cannot delete this department. It may have employees or child departments.';
            alert(message);
            setDeleteTarget(null);
        },
    });

    function openCreateDialog() {
        setEditingDepartment(null);
        setFormData({ name: '', code: '', description: '', parent_id: '' });
        setFormErrors({});
        setShowDialog(true);
    }

    function openEditDialog(department) {
        setEditingDepartment(department);
        setFormData({
            name: department.name || '',
            code: department.code || '',
            description: department.description || '',
            parent_id: department.parent_id ? String(department.parent_id) : '',
        });
        setFormErrors({});
        setShowDialog(true);
    }

    function closeDialog() {
        setShowDialog(false);
        setEditingDepartment(null);
        setFormData({ name: '', code: '', description: '', parent_id: '' });
        setFormErrors({});
    }

    function handleSubmit(e) {
        e.preventDefault();
        setFormErrors({});

        const payload = {
            ...formData,
            parent_id: formData.parent_id || null,
        };

        if (editingDepartment) {
            updateMutation.mutate({ id: editingDepartment.id, data: payload });
        } else {
            createMutation.mutate(payload);
        }
    }

    function handleDelete(department) {
        if (department.employees_count > 0 || department.children_count > 0) {
            alert(
                'Cannot delete this department. It has ' +
                    (department.employees_count > 0
                        ? `${department.employees_count} employee(s)`
                        : '') +
                    (department.employees_count > 0 && department.children_count > 0
                        ? ' and '
                        : '') +
                    (department.children_count > 0
                        ? `${department.children_count} child department(s)`
                        : '') +
                    '.'
            );
            return;
        }
        setDeleteTarget(department);
    }

    const isSaving = createMutation.isPending || updateMutation.isPending;

    return (
        <div>
            <PageHeader
                title="Departments"
                description="Manage your organization's department structure."
                action={
                    <Button onClick={openCreateDialog}>
                        <Plus className="mr-2 h-4 w-4" />
                        Add Department
                    </Button>
                }
            />

            <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <SearchInput
                    value={search}
                    onChange={setSearch}
                    placeholder="Search by name or code..."
                    className="w-full sm:max-w-xs"
                />

                <div className="flex items-center gap-1 rounded-lg border border-zinc-200 bg-white p-1">
                    <Button
                        variant={viewMode === 'table' ? 'secondary' : 'ghost'}
                        size="sm"
                        onClick={() => setViewMode('table')}
                    >
                        <LayoutList className="mr-1.5 h-4 w-4" />
                        Table
                    </Button>
                    <Button
                        variant={viewMode === 'tree' ? 'secondary' : 'ghost'}
                        size="sm"
                        onClick={() => setViewMode('tree')}
                    >
                        <Network className="mr-1.5 h-4 w-4" />
                        Tree
                    </Button>
                </div>
            </div>

            {viewMode === 'table' ? (
                <div className="rounded-xl border border-zinc-200 bg-white">
                    {isLoading ? (
                        <div className="flex items-center justify-center py-12">
                            <Loader2 className="h-6 w-6 animate-spin text-zinc-400" />
                        </div>
                    ) : filteredDepartments.length === 0 ? (
                        <div className="py-12 text-center">
                            <Building2 className="mx-auto h-10 w-10 text-zinc-300" />
                            <p className="mt-2 text-sm text-zinc-500">
                                {search ? 'No departments match your search.' : 'No departments yet. Create your first department.'}
                            </p>
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Code</TableHead>
                                    <TableHead>Employees</TableHead>
                                    <TableHead>Parent Department</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {filteredDepartments.map((dept) => (
                                    <TableRow key={dept.id}>
                                        <TableCell className="font-medium">
                                            {dept.name}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="secondary">{dept.code}</Badge>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-1.5">
                                                <Users className="h-3.5 w-3.5 text-zinc-400" />
                                                {dept.employees_count ?? 0}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {dept.parent?.name || (
                                                <span className="text-zinc-400">--</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() => openEditDialog(dept)}
                                                    title="Edit"
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() => handleDelete(dept)}
                                                    title="Delete"
                                                    className="text-red-600 hover:text-red-700 hover:bg-red-50"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </div>
            ) : (
                <div className="rounded-xl border border-zinc-200 bg-white p-4">
                    {isTreeLoading ? (
                        <div className="flex items-center justify-center py-12">
                            <Loader2 className="h-6 w-6 animate-spin text-zinc-400" />
                        </div>
                    ) : !tree?.data || tree.data.length === 0 ? (
                        <div className="py-12 text-center">
                            <Network className="mx-auto h-10 w-10 text-zinc-300" />
                            <p className="mt-2 text-sm text-zinc-500">
                                No department hierarchy found.
                            </p>
                        </div>
                    ) : (
                        <div className="space-y-1">
                            {tree.data.map((node) => (
                                <TreeNode
                                    key={node.id}
                                    node={node}
                                    depth={0}
                                    onEdit={openEditDialog}
                                    onDelete={handleDelete}
                                />
                            ))}
                        </div>
                    )}
                </div>
            )}

            <Dialog open={showDialog} onOpenChange={setShowDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {editingDepartment ? 'Edit Department' : 'Add Department'}
                        </DialogTitle>
                        <DialogDescription>
                            {editingDepartment
                                ? 'Update the department details below.'
                                : 'Fill in the details to create a new department.'}
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="dept-name">
                                Name <span className="text-red-500">*</span>
                            </Label>
                            <Input
                                id="dept-name"
                                value={formData.name}
                                onChange={(e) =>
                                    setFormData({ ...formData, name: e.target.value })
                                }
                                placeholder="e.g. Engineering"
                                required
                            />
                            {formErrors.name && (
                                <p className="text-xs text-red-600">{formErrors.name[0]}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="dept-code">
                                Code <span className="text-red-500">*</span>
                            </Label>
                            <Input
                                id="dept-code"
                                value={formData.code}
                                onChange={(e) =>
                                    setFormData({
                                        ...formData,
                                        code: e.target.value.toUpperCase(),
                                    })
                                }
                                placeholder="e.g. ENG"
                                required
                            />
                            {formErrors.code && (
                                <p className="text-xs text-red-600">{formErrors.code[0]}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="dept-description">Description</Label>
                            <Textarea
                                id="dept-description"
                                value={formData.description}
                                onChange={(e) =>
                                    setFormData({ ...formData, description: e.target.value })
                                }
                                placeholder="Optional department description..."
                                rows={3}
                            />
                            {formErrors.description && (
                                <p className="text-xs text-red-600">
                                    {formErrors.description[0]}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label>Parent Department</Label>
                            <Select
                                value={formData.parent_id}
                                onValueChange={(value) =>
                                    setFormData({
                                        ...formData,
                                        parent_id: value === 'none' ? '' : value,
                                    })
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="None (top-level)" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">None (top-level)</SelectItem>
                                    {departments?.data
                                        ?.filter(
                                            (d) => d.id !== editingDepartment?.id
                                        )
                                        .map((dept) => (
                                            <SelectItem
                                                key={dept.id}
                                                value={String(dept.id)}
                                            >
                                                {dept.name}
                                            </SelectItem>
                                        ))}
                                </SelectContent>
                            </Select>
                            {formErrors.parent_id && (
                                <p className="text-xs text-red-600">
                                    {formErrors.parent_id[0]}
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
                            <Button type="submit" disabled={!formData.name || !formData.code || isSaving}>
                                {isSaving && (
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                )}
                                {editingDepartment ? 'Update' : 'Create'}
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
                title="Delete Department"
                description={`Are you sure you want to delete "${deleteTarget?.name}"? This action cannot be undone.`}
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

function TreeNode({ node, depth, onEdit, onDelete }) {
    const [expanded, setExpanded] = useState(true);
    const hasChildren = node.children && node.children.length > 0;

    return (
        <div>
            <div
                className={cn(
                    'group flex items-center gap-2 rounded-lg px-3 py-2 hover:bg-zinc-50',
                    depth > 0 && 'ml-6 border-l-2 border-zinc-200'
                )}
            >
                <button
                    type="button"
                    onClick={() => setExpanded(!expanded)}
                    className={cn(
                        'flex h-5 w-5 shrink-0 items-center justify-center rounded text-zinc-400',
                        hasChildren
                            ? 'hover:bg-zinc-200 hover:text-zinc-600'
                            : 'invisible'
                    )}
                >
                    {expanded ? (
                        <ChevronDown className="h-3.5 w-3.5" />
                    ) : (
                        <ChevronRight className="h-3.5 w-3.5" />
                    )}
                </button>

                <Building2 className="h-4 w-4 shrink-0 text-zinc-400" />

                <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2">
                        <span className="text-sm font-medium text-zinc-900">
                            {node.name}
                        </span>
                        <Badge variant="secondary" className="text-[10px]">
                            {node.code}
                        </Badge>
                    </div>
                </div>

                <div className="flex items-center gap-2 text-xs text-zinc-500">
                    <Users className="h-3.5 w-3.5" />
                    {node.employees_count ?? 0}
                </div>

                <div className="flex items-center gap-0.5 opacity-0 transition-opacity group-hover:opacity-100">
                    <Button
                        variant="ghost"
                        size="icon"
                        className="h-7 w-7"
                        onClick={() => onEdit(node)}
                        title="Edit"
                    >
                        <Pencil className="h-3.5 w-3.5" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="h-7 w-7 text-red-600 hover:text-red-700 hover:bg-red-50"
                        onClick={() => onDelete(node)}
                        title="Delete"
                    >
                        <Trash2 className="h-3.5 w-3.5" />
                    </Button>
                </div>
            </div>

            {hasChildren && expanded && (
                <div className="space-y-0.5">
                    {node.children.map((child) => (
                        <TreeNode
                            key={child.id}
                            node={child}
                            depth={depth + 1}
                            onEdit={onEdit}
                            onDelete={onDelete}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}
